<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\SDK\AccountGateway;

use ML\IRI\IRI;
use ML\JsonLD\Document, ML\JsonLD\JsonLD, ML\JsonLD\Node;
use Symfony\Component\HttpFoundation\Request, Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client, GuzzleHttp\HandlerStack, GuzzleHttp\Middleware;
use Psr\Http\Message\ResponseInterface;

/**
 * The context of an request for an account.
 */
class AccountContext {

	const AGW_URL_PREFIX = "https://";
	const AGW_URL_SUFFIX = ".aauid.net";

	private $aauid;
	private $accessToken;
	private $dataLoader;

	private $accountDomain = null;
	private $connectionQualifier = null;
	private $installQualifier = null;
	private $accessor = null;
	private $latestAccessorVersionCOID = null;

	private $request; // optional

	private $document;
	private $client;

	private $logCode = null;

	/**
	 * Create a new context using an AAUID and an OAuth 2.0 bearer access token.
	 */
	public function __construct(IRI $aauid, $accessToken, DataLoader $dataLoader = null) {
		if (AAUIDParser::getType($aauid) != AAUIDParser::AAUID_ACCOUNT)
			throw new \Exception("Not a valid AAUID");

		$this->aauid = $aauid;
		$this->accessToken = $accessToken;
		if ($dataLoader) {
			$this->dataLoader = $dataLoader;
		} else {
			$this->dataLoader = new DataLoader;
		}
	}

	private function parseHeaderIntoNode($headerName, Node $node) {
		$keyValuePairs = explode(',', $this->request->headers->get($headerName));
		foreach ($keyValuePairs as $pair) {
			$keyValue = explode('=', $pair);
			$node->addPropertyValue($keyValue[0], urldecode($keyValue[1]));
		}
	}

	private function parseSymfonyRequest(Request $request) {
		$this->request = $request;

		if ($request->headers->has('C-Accessor')) {
			// Store COID of Accessor
			$this->accessor = new IRI($request->headers->get('C-Accessor'));
		}

		if ($request->headers->has('C-Account-Domain')) {
			// Store account domain
			$this->accountDomain = $request->headers->get('C-Account-Domain');
		}

		if ($request->headers->has('C-Accessor-Latest-Version')) {
			// A new version of thie accessor is available, store its COID
			$this->latestAccessorVersionCOID = new IRI($request->headers
				->get('C-Accessor-Latest-Version'));
		}

		if ($request->headers->has('C-Account-Connection')) {
			// For access from connected accounts, store qualifier
			$this->connectionQualifier = $request->headers->get('C-Account-Connection');
		}

		if ($request->headers->has('C-Install-Connection')) {
			// For access from applications, store qualifier
			$this->installQualifier = $request->headers->get('C-Install-Connection');
		}

		if ($request->headers->has('C-Connection-Data')) {
			// Copy Data into document
			if (!$this->document) $this->document = new Document();
			$this->parseHeaderIntoNode('C-Connection-Data',
					$this->document->getGraph()->createNode('aauid:'.$this->getAAUID().':connection:'.$this->connectionQualifier));
		}
	}

	/**
	 * Create a new context from the current request.
	 *
	 * @param Request $request
	 */
	public static function fromSymfonyRequest(Request $request) {
		if (!$request->headers->has('C-AAUID') || !$request->headers->has('C-Access-Token')) return null;

		$context = new AccountContext(
			new IRI('aauid:'.$request->headers->get('C-AAUID')),
			$request->headers->get('C-Access-Token'));

		$context->parseSymfonyRequest($request);

		return $context;
	}

	public function getAAUID() {
		return $this->aauid;
	}

	public function getAccessToken() {
		return $this->accessToken;
	}

	public function getRequest() {
		return $this->request;
	}

	public function getDataLoader() {
		return $this->dataLoader;
	}

	private function getDocument() {
		if (!$this->document) {
			$this->document = $this->dataLoader->fetchAccountGraphDataDocument($this);
		}

		return $this->document;
	}

	public function getAccount() {
		return $this->getDocument()->getGraph()->getNode($this->getAAUID());
	}

	public function getPerson() {
		return $this->getDocument()->getGraph()->getNode($this->getAAUID().':person');
	}

	/**
	 * Checks whether the context uses an account connection, which is the case when an API
	 * is requested by a connected account on another service.
	 */
	public function usesAccountConnection() {
		return ($this->connectionQualifier !== null);
	}

	/**
	 * Get the qualifier of the account connection used for accessing the API.
	 */
	public function getConnectionQualifier() {
		return $this->connectionQualifier;
	}

	/**
	 * Get the qualifier for the connection to the platform service.
	 * Only available when the accessor is an application.
	 */
	public function getInstallQualifier() {
		return $this->installQualifier;
	}

	/**
	 * Get the accessor.
	 */
	public function getAccessorCOID() {
		return $this->accessor;
	}

	/**
	 * Get the account's domain.
	 * Only set from external API requests, null otherwise.
	 *
	 * @return string|null
	 */
	public function getAccountDomain() {
		return $this->accountDomain;
	}

	/**
	 * Get a connected account.
	 * @param $qualifier The qualifier for the account connection. If not specified, uses the connection qualifier.
	 */
	public function getConnectedAccount($qualifier = null) {
		if (!$qualifier) $qualifier = $this->getConnectionQualifier();
		if (!$qualifier) return null;
		return $this->getDocument()->getGraph()->getNode($this->getAAUID().':account:'.$qualifier);
	}

	/**
	 * Get an account connection.
	 * @param $qualifier The qualifier for the account connection. If not specified, uses the connection qualifier.
	 */
	public function getAccountConnection($qualifier = null) {
		if (!$qualifier) $qualifier = $this->getConnectionQualifier();
		if (!$qualifier) return null;
		return $this->getDocument()->getGraph()->getNode($this->getAAUID().':connection:'.$qualifier);
	}

	/**
	 * Get the connected account for a service.
	 * @param $service COID of the service
	 */
	public function getConnectedAccountForService($service) {
		$accounts = $this->getDocument()->getGraph()->getNodesByType('coid://accountgateways.cloudobjects.io/Account');
		foreach ($accounts as $a) {
			if ($a->getProperty('coid://accountgateways.cloudobjects.io/isForService')
				&& $a->getProperty('coid://accountgateways.cloudobjects.io/isForService')->getId()==$service) return $a;
		}
		return null;
	}

	/**
	 * Get all account connections.
	 */
	public function getAllAccountConnections() {
		$connections = $this->getAccount()->getProperty('coid://accountgateways.cloudobjects.io/hasConnection');
		if (!is_array($connections)) $connections = array($connections);
		return $connections;
	}

	/**
	 * Get all connected accounts.
	 */
	public function getAllConnectedAccounts() {
		$accounts = array();
		foreach ($this->getAllAccountConnections() as $ac) {
			$accounts[] = $aC->getProperty('coid://accountgateways.cloudobjects.io/connectsTo');
		}
		return $accounts;
	}

	/**
	 * Pushes changes on the Account Graph into the Account Graph.
	 */
	public function pushGraphUpdates() {
		$this->getClient()->post('/~/', [
			'headers' => ['Content-Type' => 'application/ld+json'],
			'body' => JsonLD::toString($this->getDocument()->toJsonLd())
		]);
	}

	/**
	 * Get a preconfigured Guzzle client to access the Account Gateway.
	 * @return Client
	 */
	public function getClient() {
		if (!$this->client) {
			// Create custom handler stack with middlewares
			$stack = HandlerStack::create();

			$context = $this;
			$stack->push(Middleware::mapResponse(function (ResponseInterface $response) use ($context) {
   				// If a new version of this accessor is available, store its COID
				if ($response->hasHeader('C-Accessor-Latest-Version'))
      				$context->setLatestAccessorVersionCOID(
						new IRI((string)$response->getHeader('C-Accessor-Latest-Version')));
    			return $response;
			}));

			// Prepare client options
			$options = [
				'base_uri' => self::AGW_URL_PREFIX
					.AAUIDParser::getAAUID($this->getAAUID())
					.self::AGW_URL_SUFFIX,
				'headers' => [
					'Authorization' => 'Bearer '.$this->getAccessToken()
				],
				'handler' => $stack
			];
			if (isset($this->request)) {
				$options['headers']['X-Forwarded-For'] = $this->request->headers->has('X-Forwarded-For')
					? $this->request->headers->get('X-Forwarded-For') : $this->request->getClientIp();
			}

			// Create client
			$this->client = new Client($options);
		}
		return $this->client;
	}

	/**
	 * Set a custom code for the current request in the Account Gateway logs.
	 */
	public function setLogCode($logCode) {
		if (!$this->request) {
			throw new \Exception('Not in a request context.');
		}
		$this->logCode = $logCode;
	}

	/**
	 * Process a response and add headers if applicable.
	 */
	public function processResponse(Response $response) {
		if ($this->logCode) {
			$response->headers->set('C-Code-For-Logger', $this->logCode);
		}
	}

	/**
	 * Check whether a new version of the accessor is available. This information
	 * is updated from incoming and outgoing requests. If no request was executed,
	 * returns false.
	 *
	 * @return boolean
	 */
	public function isNewAccessorVersionAvailable() {
		return isset($this->latestAccessorVersionCOID);
	}

	/**
	 * Get the COID of the latest accessor version, if one is available, or
	 * null otherwise. This information is updated from incoming and outgoing
	 * requests. If no request was executed, returns null.
	 *
	 * @return IRI|null
	 */
	public function getLatestAccessorVersionCOID() {
		return $this->latestAccessorVersionCOID;
	}

	/**
	 * Set the COID of the latest accessor version. This method should only
	 * called from request processing codes. Most developers should not use it.
	 *
	 * @param IRI $latestAccessorVersionCOID
	 */
	public function setLatestAccessorVersionCOID(IRI $latestAccessorVersionCOID) {
		$this->latestAccessorVersionCOID = $latestAccessorVersionCOID;
	}

}
