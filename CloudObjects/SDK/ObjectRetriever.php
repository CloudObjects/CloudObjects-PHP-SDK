<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\SDK;

use ML\IRI\IRI, ML\JsonLD\JsonLD;
use Doctrine\Common\Cache\RedisCache;
use Psr\Log\LoggerInterface, Psr\Log\LoggerAwareTrait;
use GuzzleHttp\ClientInterface, GuzzleHttp\Client, GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware, Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use CloudObjects\SDK\Exceptions\CoreAPIException;

/**
 * The ObjectRetriever provides access to objects on CloudObjects.
 */
class ObjectRetriever {

	use LoggerAwareTrait;

	private $client;
	private $prefix;
	private $options;
	private $cache;
	private $objects;

	const CO_API_URL = 'https://api.cloudobjects.net/';
	
	const REVISION_PROPERTY = 'coid://cloudobjects.io/isAtRevision';

	public function __construct($options = []) {
		// Merge options with defaults
		$this->options = array_merge([
			'cache_provider' => 'none',
			'cache_prefix' => 'clobj:',
			'cache_ttl' => 60,
			'static_config_path' => null,
			'auth_ns' => null,
			'auth_secret' => null,
			'api_base_url' => null,
			'logger' => null
		], $options);

		// Set up object cache
		switch ($this->options['cache_provider']) {
			case 'none':
				// no caching
				$this->cache = null;
				break;
			case 'redis':
				// caching with Redis
				$redis = new \Redis();
				$redis->pconnect(
						isset($this->options['cache_provider.redis.host']) ? $this->options['cache_provider.redis.host'] : '127.0.0.1',
						isset($this->options['cache_provider.redis.port']) ? $this->options['cache_provider.redis.port'] : 6379);

				$this->cache = new RedisCache();
				$this->cache->setRedis($redis);
				break;
			case 'file':
				// caching on the filesystem
				$this->cache = new \Doctrine\Common\Cache\FilesystemCache(
					isset($this->options['cache_provider.file.directory']) ? $this->options['cache_provider.file.directory'] : sys_get_temp_dir()
				);
				break;
			default:
				throw new \Exception('Valid values for cache_provider are: none, redis, file');
		}

		// Set up logger
		if (is_a($this->options['logger'], LoggerInterface::class))
			$this->setLogger($this->options['logger']);

		// Set up handler stack
		$stack = HandlerStack::create();
		
		// Add HTTP cache if specified
		if (isset($this->cache)) {
			$stack->push(
				new CacheMiddleware(
					new PrivateCacheStrategy(
						new DoctrineCacheStorage($this->cache)
					)
				)
			);
		}		

		// Initialize client
		$options = [
			'base_uri' => isset($options['api_base_url']) ? $options['api_base_url'] : self::CO_API_URL,
			'handler' => $stack
		];
		
		if (isset($this->options['auth_ns']) && isset($this->options['auth_secret']))
			$options['auth'] = [$this->options['auth_ns'], $this->options['auth_secret']];

		$this->client = new Client($options);
	}

	private function logInfoWithTime($message, $ts) {
		if (isset($this->logger))
			$this->logger->info($message, [ 'elapsed_ms' => round((microtime(true) - $ts) * 1000) ]);
	}

	private function getFromCache($id) {
		return (isset($this->cache) && $this->cache->contains($this->options['cache_prefix'].$id))
			? $this->cache->fetch($this->options['cache_prefix'].$id) : null;
	}

	private function putIntoCache($id, $data, $ttl) {
		if (isset($this->cache))
			$this->cache->save($this->options['cache_prefix'].$id, $data, $ttl);
	}

	/**
	 * Get the HTTP client that is used to access the API.
	 */
	public function getClient() {
		return $this->client;
	}

	/**
	 * Set the HTTP client that is used to access the API.
	 *
	 * @param ClientInterface $client The HTTP client.
	 * @param string $prefix An optional prefix (e.g. an AccountGateway mountpoint)
	 */
	public function setClient(ClientInterface $client, $prefix = null) {
		$this->client = $client;
		$this->prefix = $prefix;
	}

	/**
	 * Get an object description from CloudObjects. Attempts to get object
	 * from in-memory cache first, stored static configurations next,
	 * configured external cache third, and finally calls the Object API
	 * on CloudObjects Core. Returns null if the object was not found.
	 *
	 * @param IRI $coid COID of the object
	 * @return Node|null
	 */
	public function getObject(IRI $coid) {
		if (!COIDParser::isValidCOID($coid))
			throw new \Exception("Not a valid COID.");

		$uriString = (string)$coid;

		if (isset($this->objects[$uriString]))
			// Return from in-memory cache if it exists
			return $this->objects[$uriString];

		$ts = microtime(true);

		if (isset($this->options['static_config_path'])) {
			$location = realpath($this->options['static_config_path'].DIRECTORY_SEPARATOR.
				$coid->getHost().str_replace('/', DIRECTORY_SEPARATOR, $coid->getPath())
				.DIRECTORY_SEPARATOR.'object.jsonld');

			if ($location && file_exists($location)) {
				$object = $location;
				$this->logInfoWithTime('Fetched <'.$uriString.'> from static configuration.', $ts);
			}
		}

		if (!isset($object)) {
			$object = $this->getFromCache($uriString);
			if (isset($object))
				$this->logInfoWithTime('Fetched <'.$uriString.'> from object cache.', $ts);
		}

		if (!isset($object)) {
			try {
				$response = $this->client
					->get((isset($this->prefix) ? $this->prefix : '').$coid->getHost().$coid->getPath().'/object',
						['headers' => ['Accept' => 'application/ld+json']]);

				$object = (string)$response->getBody();
				$this->putIntoCache($uriString, $object, $this->options['cache_ttl']);
				$this->logInfoWithTime('Fetched <'.$uriString.'> from Core API ['.$response->getStatusCode().'].', $ts);
			} catch (\Exception $e) {
				$this->logInfoWithTime('Object <'.$uriString.'> not found in Core API ['.$e->getResponse()->getStatusCode().'].', $ts);
				return null;
			}
		}

		$document = JsonLD::getDocument($object);
		$this->objects[$uriString] = $document->getGraph()->getNode($uriString);
		return $this->objects[$uriString];
	}

	/**
	 * Fetch all object descriptions for objects in a specific namespace
	 * and with a certain type from CloudObjects. Adds individual objects
	 * to cache and returns a list of COIDs (as IRI) for them. The list
	 * itself is not cached, which means that every call of this function
	 * goes to the Object API.
	 *
	 * @param IRI $namespaceCoid COID of the namespace
	 * @param $type RDF type that objects should have
	 * @return array<IRI>
	 */
	public function fetchObjectsInNamespaceWithType(IRI $namespaceCoid, $type) {
		if (COIDParser::getType($namespaceCoid) != COIDParser::COID_ROOT)
			throw new \Exception("Not a valid namespace COID.");

		$ts = microtime(true);

		try {
			$response = $this->client
				->get((isset($this->prefix) ? $this->prefix : '').$namespaceCoid->getHost().'/all',
					[
						'headers' => [ 'Accept' => 'application/ld+json' ],
						'query' => [ 'type' => $type ]
					]);

			$document = JsonLD::getDocument((string)$response->getBody());
			$allObjects = $document->getGraph()->getNodesByType($type);
			$allIris = [];
			foreach ($allObjects as $object) {
				$iri = new IRI($object->getId());
				if (!COIDParser::isValidCOID($iri)) continue;
				if ($iri->getHost() != $namespaceCoid->getHost()) continue;

				$this->objects[$object->getId()] = $object;
				$this->putIntoCache($object->getId(), $object, $this->options['cache_ttl']);
				$allIris[] = $iri;
			}

			$this->logInfoWithTime('Fetched all objects with <'.$type.'> for <'.$namespaceCoid->getHost().'> from Core API ['.$response->getStatusCode().'].', $ts);
		} catch (\Exception $e) {
			throw new CoreAPIException;
		}
	
		return $allIris;
	}

	/**
	 * Fetch all object descriptions for objects in a specific namespace
	 * from CloudObjects. Adds individual objects to cache and returns a
	 * list of COIDs (as IRI) for them. The list itself is not cached,
	 * which means that every call of this function goes to the Object API.
	 *
	 * @param IRI $namespaceCoid COID of the namespace
	 * @return array<IRI>
	 */
	public function fetchAllObjectsInNamespace(IRI $namespaceCoid) {
		if (COIDParser::getType($namespaceCoid) != COIDParser::COID_ROOT)
			throw new \Exception("Not a valid namespace COID.");

		$ts = microtime(true);

		try {
			$response = $this->client
				->get((isset($this->prefix) ? $this->prefix : '').$namespaceCoid->getHost().'/all',
					[ 'headers' => [ 'Accept' => 'application/ld+json' ] ]);

			$document = JsonLD::getDocument((string)$response->getBody());
			$allObjects = $document->getGraph()->getNodes();
			$allIris = [];
			foreach ($allObjects as $object) {
				$iri = new IRI($object->getId());
				if (!COIDParser::isValidCOID($iri)) continue;
				if ($iri->getHost() != $namespaceCoid->getHost()) continue;

				$this->objects[$object->getId()] = $object;
				$this->putIntoCache($object->getId(), $object, $this->options['cache_ttl']);
				$allIris[] = $iri;
			}

			$this->logInfoWithTime('Fetched all objects for <'.$namespaceCoid->getHost().'> from Core API ['.$response->getStatusCode().'].', $ts);

		} catch (\Exception $e) {
			throw new CoreAPIException;
		}
	
		return $allIris;
	}

	/**
	 * Get an object description from CloudObjects. Shorthand method for
	 * "getObject" which allows passing the COID as string instead of IRI.
	 *
	 * @param any $coid
	 * @return Node|null
	 */
	public function get($coid) {
		if (is_string($coid))
			return $this->getObject(new IRI($coid));

		if (is_object($coid) && get_class($coid)=='ML\IRI\IRI')
			return $this->getObject($coid);

		throw new \Exception('COID must be passed as a string or an IRI object.');
	}

	/**
	 * Get a object's attachment.
	 *
	 * @param IRI $coid
	 * @param string $filename
	 */
	public function getAttachment(IRI $coid, $filename) {
		$object = $this->getObject($coid);

		if (!$object)
			// Cannot get attachment for non-existing object
			return null;

		$ts = microtime(true);

		$cacheId = $object->getId().'#'.$filename;
		$fileData =  $this->getFromCache($cacheId);

		// Parse cached data into revision and content
		if (isset($fileData)) {
			$this->logInfoWithTime('Fetched attachment <'.$filename.'> for <'.$object->getId().'> from object cache.', $ts);
			list($fileRevision, $fileContent) = explode('#', $fileData, 2);
		}

		if (!isset($fileData)
				|| $fileRevision!=$object->getProperty(self::REVISION_PROPERTY)->getValue()) {

			// Does not exist in cache or is outdated, fetch from CloudObjects
			try {
				$response = $this->client->get((isset($this->prefix) ? $this->prefix : '').$coid->getHost().$coid->getPath()
					.'/'.basename($filename));

				$fileContent = $response->getBody()->getContents();
				$fileData = $object->getProperty(self::REVISION_PROPERTY)->getValue().'#'.$fileContent;
				$this->putIntoCache($cacheId, $fileData, 0);

				$this->logInfoWithTime('Fetched attachment <'.$filename.'> for <'.$object->getId().'> from Core API ['.$response->getStatusCode().'].', $ts);
			} catch (\Exception $e) {
				$this->logInfoWithTime('Attachment <'.$filename.'> for <'.$object->getId().'> not found in Core API ['.$e->getResponse()->getStatusCode().'].', $ts);
				// ignore exception - treat as non-existing file
			}

		}

		return $fileContent;
	}

	/**
	 * Retrieve the object that describes the namespace provided with the "auth_ns"
	 * configuration option.
	 * 
	 * @return Node
	 */
	public function getAuthenticatingNamespaceObject() {
		if (!isset($this->options['auth_ns']))
			throw new \Exception("Missing 'auth_ns' configuration option.");

		$namespaceCoid = COIDParser::fromString($this->options['auth_ns']);
		if (COIDParser::getType($namespaceCoid) != COIDParser::COID_ROOT)
			throw new \Exception("The 'auth_ns' configuration option is not a valid namespace/root COID.");
		
		return $this->getObject($namespaceCoid);
	}

}