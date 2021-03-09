<?php

declare(strict_types=1);

namespace OCA\Mail\Listener;

use OCA\Mail\Db\MailboxMapper;
use OCA\Mail\Events\MessageSentEvent;
use OCA\Mail\IMAP\IMAPClientFactory;
use OCA\Mail\IMAP\MessageMapper;
use OCP\EventDispatcher\Event;
use OCA\Mail\Service\NewtechIntegration;
use OCA\NTSSO\Controller\NTUser;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class NewtechSentMessageListener implements IEventListener {

	/** @var MailboxMapper */
	private $mailboxMapper;

	/** @var IMAPClientFactory */
	private $imapClientFactory;

	/** @var MessageMapper */
	private $messageMapper;

	/** @var LoggerInterface */
	private $logger;

	/** @var NewtechIntegration */
	private $ntIntegration;

	/** @var NTUser */
	private $user;

	public function __construct(MailboxMapper $mailboxMapper,
								IMAPClientFactory $imapClientFactory,
								MessageMapper $messageMapper,
								LoggerInterface $logger,
								IConfig $config,
								NTUser $user) {
		$this->mailboxMapper = $mailboxMapper;
		$this->imapClientFactory = $imapClientFactory;
		$this->messageMapper = $messageMapper;
		$this->logger = $logger;
		$this->user = $user;
		$this->ntIntegration = new NewtechIntegration($user->getStore()->store_number, (string)$user->getEntityKey(), $config);
	}

	public function handle(Event $event): void {
		if (!($event instanceof MessageSentEvent)) {
			return;
		}
		foreach ($event->getMessage()->getTo()->jsonSerialize() as $account) {
			$customers = $this->ntIntegration->searchCustomersByNameOrEmail($account['email']);
			if (empty($customers)) continue;
			$this->ntIntegration->sendNewContactHistory(
				$customers[0]['id'],
				$customers[0]['label'],
				'Subject: ' . $event->getMessage()->getSubject() . ' Body: ' . $event->getMessage()->getContent()
			);
		}
	}
}
