<?php

declare(strict_types=1);

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * Mail
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Mail\AppInfo;

use Exception;
use Horde_Translation;
use OCA\Mail\Contracts\IAttachmentService;
use OCA\Mail\Contracts\IAvatarService;
use OCA\Mail\Contracts\IMailManager;
use OCA\Mail\Contracts\IMailSearch;
use OCA\Mail\Contracts\IMailTransmission;
use OCA\Mail\Contracts\IUserPreferences;
use OCA\Mail\Dashboard\MailWidget;
use OCA\Mail\Events\DraftSavedEvent;
use OCA\Mail\Events\MailboxesSynchronizedEvent;
use OCA\Mail\Events\SynchronizationEvent;
use OCA\Mail\Events\MessageDeletedEvent;
use OCA\Mail\Events\MessageFlaggedEvent;
use OCA\Mail\Events\MessageSentEvent;
use OCA\Mail\Events\NewMessagesSynchronized;
use OCA\Mail\HordeTranslationHandler;
use OCA\Mail\Http\Middleware\ErrorMiddleware;
use OCA\Mail\Http\Middleware\ProvisioningMiddleware;
use OCA\Mail\Listener\AddressCollectionListener;
use OCA\Mail\Listener\DeleteDraftListener;
use OCA\Mail\Listener\FlagRepliedMessageListener;
use OCA\Mail\Listener\InteractionListener;
use OCA\Mail\Listener\NewtechSentMessageListener;
use OCA\Mail\Listener\NewtechReceivedMessageListener;
use OCA\Mail\Listener\AccountSynchronizedThreadUpdaterListener;
use OCA\Mail\Listener\MailboxesSynchronizedSpecialMailboxesUpdater;
use OCA\Mail\Listener\MessageCacheUpdaterListener;
use OCA\Mail\Listener\NewMessageClassificationListener;
use OCA\Mail\Listener\SaveSentMessageListener;
use OCA\Mail\Listener\UserDeletedListener;
use OCA\Mail\Search\Provider;
use OCA\Mail\Service\Attachment\AttachmentService;
use OCA\Mail\Service\AvatarService;
use OCA\Mail\Service\MailManager;
use OCA\Mail\Service\MailTransmission;
use OCA\Mail\Service\Search\MailSearch;
use OCA\Mail\Service\UserPreferenceSevice;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IServerContainer;
use OCP\User\Events\UserDeletedEvent;
use OCP\Util;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use OCA\NTSSO\Controller\NTUser;
use OCA\Mail\SMTP\SmtpClientFactory;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Grant\RefreshToken;
use League\OAuth2\Client\Provider\Google;
use OCA\Mail\Db\MailAccount;
use OCA\Mail\Account;
use OCA\Mail\Service\AccountService;
use OCP\Security\ICrypto;
use OCA\Mail\Service\AliasesService;

class Application extends App implements IBootstrap {
	public const APP_ID = 'mail';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		if ((@include_once __DIR__ . '/../../vendor/autoload.php') === false) {
			throw new Exception('Cannot include autoload. Did you run install dependencies using composer?');
		}

		$context->registerParameter('hostname', Util::getServerHostName());

		$context->registerService('userFolder', function (ContainerInterface $c) {
			$userContainer = $c->get(IServerContainer::class);
			$uid = $c->get('UserId');

			return $userContainer->getUserFolder($uid);
		});

		$context->registerServiceAlias(IAvatarService::class, AvatarService::class);
		$context->registerServiceAlias(IAttachmentService::class, AttachmentService::class);
		$context->registerServiceAlias(IMailManager::class, MailManager::class);
		$context->registerServiceAlias(IMailSearch::class, MailSearch::class);
		$context->registerServiceAlias(IMailTransmission::class, MailTransmission::class);
		$context->registerServiceAlias(IUserPreferences::class, UserPreferenceSevice::class);

		$context->registerEventListener(DraftSavedEvent::class, DeleteDraftListener::class);
		$context->registerEventListener(MailboxesSynchronizedEvent::class, MailboxesSynchronizedSpecialMailboxesUpdater::class);
		$context->registerEventListener(MessageFlaggedEvent::class, MessageCacheUpdaterListener::class);
		$context->registerEventListener(MessageDeletedEvent::class, MessageCacheUpdaterListener::class);
		$context->registerEventListener(MessageSentEvent::class, AddressCollectionListener::class);
		$context->registerEventListener(MessageSentEvent::class, DeleteDraftListener::class);
		$context->registerEventListener(MessageSentEvent::class, FlagRepliedMessageListener::class);
		$context->registerEventListener(MessageSentEvent::class, InteractionListener::class);
		$context->registerEventListener(MessageSentEvent::class, SaveSentMessageListener::class);
		$context->registerEventListener(MessageSentEvent::class, NewtechSentMessageListener::class);
		$context->registerEventListener(NewMessagesSynchronized::class, NewMessageClassificationListener::class);
		$context->registerEventListener(NewMessagesSynchronized::class, NewtechReceivedMessageListener::class);
		$context->registerEventListener(SynchronizationEvent::class, AccountSynchronizedThreadUpdaterListener::class);
		$context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);

		$context->registerMiddleWare(ErrorMiddleware::class);
		$context->registerMiddleWare(ProvisioningMiddleware::class);

		$context->registerDashboardWidget(MailWidget::class);
		$context->registerSearchProvider(Provider::class);

		// bypass Horde Translation system
		Horde_Translation::setHandler('Horde_Imap_Client', new HordeTranslationHandler());
		Horde_Translation::setHandler('Horde_Mime', new HordeTranslationHandler());
		Horde_Translation::setHandler('Horde_Smtp', new HordeTranslationHandler());

		
	}

	public function boot(IBootContext $context): void {
		$dispatcher = $this->query(IEventDispatcher::class);
		$dispatcher->addListener("refreshPermissions", [$this, 'registerAuthProviders']);
		$dispatcher->addListener("refreshAuthProviders", [$this, 'registerAuthProviders']);
	}

	public function registerAuthProviders() {
		$logger = $this->query(LoggerInterface::class);
		$userSession = $this->query(IUserSession::class);
		$crypto = $this->query(ICrypto::class);
		$ntuser = $this->query(NTUser::class);
		$userProfile = $ntuser->getProfile();
		$accountService = $this->query(AccountService::class);
		$aliasesService = $this->query(AliasesService::class);
		$smtpClientFactory = $this->query(SmtpClientFactory::class);
		$ncUser = $userSession->getUser();
		$mailManager = $this->query(IMailManager::class);
		$name = ucfirst($userProfile->account->first_name) . " " . ucfirst($userProfile->account->last_name);
		if(!is_null($ncUser)) {
			if(count($userProfile->authProviders) > 0) {
				$mailAccounts = $accountService->findByUserId($ncUser->getUID());
				// Get all registered auth providers
				$registered = collect([]);
				foreach($mailAccounts as $mailAccount) {
					$authProvidersCollection = collect($userProfile->authProviders);
					$possibleProviders = $authProvidersCollection->where('email', '=', $mailAccount->getEmail());
					if($possibleProviders->count() > 0) {
						$currentProvider = $possibleProviders->first();
						$registered->push(["email" => $currentProvider->email, "account" => $mailAccount, "disabled" => $mailAccount->getMailAccount()->getDeleted()]);
					}
				}
				// Does this user have a Auth provider?
				foreach($userProfile->authProviders as $index => $provider) {
					$emails = $registered->where('email', '=', $provider->email);
					if($emails->count() == 0) {
						$newAccount = new MailAccount();
						$newAccount->setUserId($ncUser->getUID());
						$newAccount->setName($name);
						$newAccount->setEmail($provider->email);
						if($provider->provider == "google") {
							$supportedProvider = true;
							$password = $crypto->encrypt("XOAUTH2");
							$newAccount->setInboundHost("imap.gmail.com");
							$newAccount->setInboundPort(993);
							$newAccount->setInboundSslMode("ssl");
							$newAccount->setInboundUser($newAccount->getEmail());
							$newAccount->setInboundPassword($password);
					
							$newAccount->setOutboundHost("smtp.gmail.com");
							$newAccount->setOutboundPort(587);
							$newAccount->setOutboundSslMode("tls");
							$newAccount->setOutboundUser($newAccount->getEmail());
							$newAccount->setOutboundPassword($password);
						}
						
						if($supportedProvider) {
							$prov = new Google([
								'clientId'     => getenv('GOOGLE_OAUTH_ID'),
								'clientSecret' => getenv('GOOGLE_OAUTH_SECRET'),
								'redirectUri'  => "/auth/authenticate/google"
							]);
							try {
								$credentials = (object) $provider->credentials;
								$grant = new RefreshToken();
								
								$refresh_token = $credentials->refresh_token;
								$token = $prov->getAccessToken($grant, ['refresh_token' => $refresh_token]);
								$xoauth_token = new \Horde_Imap_Client_Password_Xoauth2($newAccount->getEmail(), $token->getToken());
								$newAccount->setUsesExternalAuth(true);
								$newAccount->setExternalAuth($xoauth_token->getPassword());
								
								$account = new Account($newAccount, $ntuser);
								$logger->debug('Connecting to account {account}', ['account' => $newAccount->getEmail()]);
								$transport = $smtpClientFactory->create($account);
							
								$account->testConnectivity($transport);
								$newAccount->setDeleted(false);
								$accountService->save($newAccount);
								$logger->debug("account created from auth provider - " . $newAccount->getId());
							} catch (\Throwable $th) {
								$newAccount->setDeleted(true);
								$logger->debug("creation from auth provider failed - " . $th->getMessage());
							}
						}
					} else {
						$email = $emails->first();
						$mailAccount = $email['account']->getMailAccount();
						
						$mailAccount->setName($name);
						if($provider->provider == "google") {
							$supportedProvider = true;
							
							$password = $crypto->encrypt("XOAUTH2");
							$mailAccount->setInboundHost("imap.gmail.com");
							$mailAccount->setInboundPort(993);
							$mailAccount->setInboundSslMode("ssl");
							$mailAccount->setInboundUser($mailAccount->getEmail());
							$mailAccount->setInboundPassword($password);
					
							$mailAccount->setOutboundHost("smtp.gmail.com");
							$mailAccount->setOutboundPort(587);
							$mailAccount->setOutboundSslMode("tls");
							$mailAccount->setOutboundUser($mailAccount->getEmail());
							$mailAccount->setOutboundPassword($password);
						}
						if($supportedProvider) {
							
							$prov = new Google([
								'clientId'     => getenv('GOOGLE_OAUTH_ID'),
								'clientSecret' => getenv('GOOGLE_OAUTH_SECRET'),
								'redirectUri'  => "/auth/authenticate/google"
							]);
							try {
								$credentials = (object) $provider->credentials;
								$grant = new RefreshToken();
								
								$refresh_token = $credentials->refresh_token;
								$token = $prov->getAccessToken($grant, ['refresh_token' => $refresh_token]);
								$xoauth_token = new \Horde_Imap_Client_Password_Xoauth2($mailAccount->getEmail(), $token->getToken());
								$mailAccount->setUsesExternalAuth(true);
								$mailAccount->setExternalAuth($xoauth_token->getPassword());

								$account = new Account($mailAccount, $ntuser);
								$logger->debug('Connecting to account {account}', ['account' => $mailAccount->getEmail()]);
								$transport = $smtpClientFactory->create($account);
								$account->testConnectivity($transport);
								$mailAccount->setDeleted(false);
								$accountService->save($mailAccount);
								$logger->debug("account created from auth provider - " . $mailAccount->getId());
							} catch (\Throwable $th) {
								$mailAccount->setDeleted(true);
								$logger->debug("creation from auth provider failed - " . $th->getMessage());
							}
						}
					}
				}
			} else {
				$mailAccounts = $accountService->findByUserId($ncUser->getUID());
				foreach($mailAccounts as $mailAccount) {
					$xoauth = $mailAccount->getUsesExternalAuth();
					if($xoauth) {
						$mailAccount->getMailAccount()->setDeleted(true);
						$accountService->save($mailAccount->getMailAccount());
					}
				}
			}
		} else {
			$logger->debug("no auth providers checked, ncuser was undefined");
		}
	}

	public function refreshAuthProviders() {
		$userSession = $this->query(IUserSession::class);
		$accountService = $this->query(AccountService::class);
		$ncUser = $userSession->getUser();
		$mailAccounts = $accountService->findByUserId($ncUser->getUID());
		foreach($mailAccounts as $mailAccount) {
			$authProvidersCollection = collect($userProfile->authProviders);
			$possibleProviders = $authProvidersCollection->where('email', '=', $mailAccount->getEmail());
			if($possibleProviders->count() > 0) {
				$currentProvider = $possibleProviders->first();
				# Refresh Auth
				$credentials = (object) $currentProvider->credentials;
				$grant = new RefreshToken();
				$refresh_token = $credentials->refresh_token;
				$token = $prov->getAccessToken($grant, ['refresh_token' => $refresh_token]);
				$xoauth_token = new \Horde_Imap_Client_Password_Xoauth2($mailAccount->getEmail(), $token->getToken());
				# ----
				$mailAccount->setExternalAuth($xoauth_token);
				$accountService->save($mailAccount);
			}
		}
	}
	private function query($className)
    {
        return $this->getContainer()->query($className);
    }
}
