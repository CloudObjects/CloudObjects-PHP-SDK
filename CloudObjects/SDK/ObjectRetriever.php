<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\SDK;

use ML\IRI\IRI, ML\JsonLD\JsonLD;
use Doctrine\Common\Cache\RedisCache;
use GuzzleHttp\ClientInterface, GuzzleHttp\Client;

/**
 * The ObjectRetriever provides access to objects on CloudObjects.
 */
class ObjectRetriever {

	private $client;
	private $prefix;
	private $options;
	private $cache;
	private $objects;

	const CO_API_URL = 'http://api.cloudobjects.io/';
	
	const REVISION_PROPERTY = 'coid://cloudobjects.io/isAtRevision';

	public function __construct($options = array()) {
		// Merge options with defaults
		$this->options = array_merge(array(
			'cache_prefix' => 'clobj:',
			'cache_provider' => 'none',
			'cache_ttl' => 60,
			'cache_ttl_attachments' => 0,
			'static_config_path' => null,
			'auth_ns' => null,
			'auth_secret' => null,
			'api_base_url' => null
		), $options);

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

		// Initialize client
		$options = [
			'base_uri' => isset($options['api_base_url']) ? $options['api_base_url'] : self::CO_API_URL
		];
		
		if (isset($this->options['auth_ns']) && isset($this->options['auth_secret']))
			$options['auth'] = [$this->options['auth_ns'], $this->options['auth_secret']];

		$this->client = new Client($options);
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
	 * Get an object description from CloudObjects.
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

		if (isset($this->options['static_config_path'])) {
			$location = realpath($this->options['static_config_path'].DIRECTORY_SEPARATOR.
				$coid->getHost().str_replace('/', DIRECTORY_SEPARATOR, $coid->getPath())
				.DIRECTORY_SEPARATOR.'object.jsonld');

			if ($location && file_exists($location)) {
				$object = $location;
			}
		}

		if (!isset($object)) $object = $this->getFromCache($uriString);

		if (!isset($object)) {
			try {
				$response = $this->client
					->get((isset($this->prefix) ? $this->prefix : '').$coid->getHost().$coid->getPath().'/object',
						['headers' => ['Accept' => 'application/ld+json']]);

				$object = (string)$response->getBody();
				$this->putIntoCache($uriString, $object, $this->options['cache_ttl']);
			} catch (\Exception $e) {
				return null;
			}
		}

		$document = JsonLD::getDocument($object);
		$this->objects[$uriString] = $document->getGraph()->getNode($uriString);
		return $this->objects[$uriString];
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

		$cacheId = $object->getId().'#'.$filename;
		$fileData =  $this->getFromCache($cacheId);

		// Parse cached data into revision and content
		if (isset($fileData)) list($fileRevision, $fileContent) = explode('#', $fileData, 2);

		if (!isset($fileData)
				|| $fileRevision!=$object->getProperty(self::REVISION_PROPERTY)->getValue()) {

			// Does not exist in cache or is outdated, fetch from CloudObjects
			try {
				$response = $this->client->get((isset($this->prefix) ? $this->prefix : '').$coid->getHost().$coid->getPath()
					.'/'.basename($filename));

				$fileContent = $response->getBody()->getContents();
				$fileData = $object->getProperty(self::REVISION_PROPERTY)->getValue().'#'.$fileContent;
				$this->putIntoCache($cacheId, $fileData, $this->options['cache_ttl_attachments']);
			} catch (\Exception $e) {
				// ignore exception - treat as non-existing file
			}

		}

		return $fileContent;
	}

}
