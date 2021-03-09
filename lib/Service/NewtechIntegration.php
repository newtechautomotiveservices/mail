<?php

namespace OCA\Mail\Service;

use OCP\IConfig;
use GuzzleHttp\Client;
use OCA\Mail\Exception\ImproperNTConfiguration;

class NewtechIntegration
{

	/** @var storeNumber */
	private $storeNumber;

	/** @var entityKey */
	private $entityKey;

	/** @var String */
	private $newtechApiEndpoint;

	/** @var String */
	private $newtechApiKey;

	/** @var String */
	private $redisHost;
	private $redisPort;


	function __construct(String $sn, String $ek, IConfig $config)
	{
		$this->storeNumber = $sn;
		$this->entityKey = $ek;
		$this->newtechApiEndpoint = $config->getSystemValue('newtech_api_endpoint', '');
		$this->newtechApiKey = $config->getSystemValue('newtech_api_key', '');
		$this->redisHost = $config->getSystemValue('redis', '')['host'] ?? '';
		$this->redisPort = $config->getSystemValue('redis', '')['port'] ?? '';

		if (empty($this->newtechApiEndpoint) || empty($this->newtechApiKey)) {
			throw new ImproperNTConfiguration();
		}
	}

	/**
	 * Search for customers with a name or email close to matching
	 * the given query string. Uses the NtAPI customers API.
	 *
	 * @param  String $query
	 * @return Array
	 */
	function searchCustomersByNameOrEmail(String $query): array
	{
		$apiEndpoint = $this->newtechApiEndpoint;
		$apiKey = $this->newtechApiKey;
		$client = new \GuzzleHttp\Client([
			'base_uri' => $apiEndpoint
		]);
		try {
			$res = $client->get('v2/customers/customer/' . $this->storeNumber, [
				'query' => [
					'desired' => [
						'id',
						'name.full',
						'name.first',
						'name.middle',
						'name.last',
						'communication.email.primary.address'
					],
					'relation' => 'or',
					'filter' => [
						[
							'key' => 'communication.email.primary.address',
							'value' => "{$query}"
						],
						[
							'key' => 'name.full',
							'value' => "{$query}"
						],
						[
							'key' => 'name.first',
							'value' => "{$query}"
						],
						[
							'key' => 'name.middle',
							'value' => "{$query}"
						],
						[
							'key' => 'name.last',
							'value' => "{$query}"
						]
					]
				],
				'headers' => [
					'Authorization' => 'Bearer ' . $apiKey
				]
			]);
		} catch (\GuzzleHttp\Exception\ServerException $ex) {
			return [];
		} catch (\GuzzleHttp\Exception\ClientException $ex) {
			return [];
		}

		$customers = (array)json_decode($res->getBody())->data;

		$customers = array_filter($customers, function ($customer) {
			return $customer->communication->email->primary->address != '';
		});

		return array_map(function ($customer) {
			return [
				'id' => $customer->id,
				'label'	=> $customer->name->full,
				'email' => $customer->communication->email->primary->address
			];
		}, $customers);
	}

	/**
	 * Search for a contact picture with a given email.
	 *
	 * @param String $email
	 * @return Array|NULL
	 */
	function findCustomerContactPictureByEmail(String $email): ?array
	{
		$redis = false;
		try {
			$redis = new \Redis();
			$redis->connect($this->redisHost, $this->redisPort);
		} catch (\Exception $ex) {
			$logger = \OC::$server->getLogger();
			$this->logger->error('redis connection failed: ' . $ex);
		}


		if ($redis) {
			$redisRes = $redis->get('avatar::nc::' . $email);
			if (!empty($redisRes)) {
				return (array)json_decode($redisRes);
			}
		}

		$apiEndpoint = $this->newtechApiEndpoint;
		$apiKey = $this->newtechApiKey;

		$client = new \GuzzleHttp\Client([
			'base_uri' => $apiEndpoint
		]);

		try {
			$res = $client->get('v2/customers/customer/' . $this->storeNumber, [
				'query' => [
					'desired' => [
						'communication.email.primary.address',
						'driversLicense.image.face'
					],
					'filter' => [
						[
							'key' => 'communication.email.primary.address',
							'value' => "{$email}"
						],
						[
							'key' => 'communication.email.primary.address',
							'value' => "''",
							'comparison' => '!='
						],
						[
							'key' => 'driversLicense.image.face',
							'value' => "''",
							'comparison' => '!='
						]
					]
				],
				'headers' => [
					'Authorization' => 'Bearer ' . $apiKey
				]
			]);
		} catch (\GuzzleHttp\Exception\ServerException $ex) {
			return null;
		} catch (\GuzzleHttp\Exception\ClientException $ex) {
			return null;
		}

		if (isset(json_decode($res->getBody())->data[0])) {
			$customer =  (array)json_decode($res->getBody())->data[0];
		} else {
			$customer = [];
		}

		if ($customer == []) {
			return null;
		}

		if ($redis) {
			$redis->set('avatar::nc::' . $email, json_encode([
				'isExternal' => true,
				'mime' => 'image/jpeg',
				'url' => $customer['driversLicense']->image->face
			]), 60 * 10); // Store returned image for 10 minutes.
		}

		return [
			'isExternal' => true,
			'mime' => 'image/jpeg',
			'url' => $customer['driversLicense']->image->face
		];
	}

	function sendNewContactHistory($customerId, $customerName, $notes, bool $incoming = false): bool
	{
		$dateTime = new \DateTime();
		$apiEndpoint = $this->newtechApiEndpoint;
		$apiKey = $this->newtechApiKey;
		$client = new \GuzzleHttp\Client([
			'base_uri' => $apiEndpoint
		]);
		try {
			$res = $client->post('v2/customers/contacthistory/', [
				'json' => [
					'customer' => [
						'id' => $customerId,
						'name' => $customerName
					],
					'employee' => [
						'id' => $this->entityKey
					],
					'store' => [
						'number' => $this->storeNumber
					],
					'method' => 'E-mail',
					'purpose' => 'Miscellaneous',
					'dateOfContact' => $dateTime->format('Y-m-d H:i:s'),
					'notes' => $notes,
					'incoming' => $incoming,
					'source' => [
						'type' => 'Process Pro Email',
						'description' => 'Process Pro Online Email'
					]
				],
				'headers' => [
					'Authorization' => 'Bearer ' . $apiKey
				]
			]);
		} catch (\GuzzleHttp\Exception\ServerException $ex) {
			return false;
			die;
		} catch (\GuzzleHttp\Exception\ClientException $ex) {
			return false;
			die;
		}
		return true;
	}
}
