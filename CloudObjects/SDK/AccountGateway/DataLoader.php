<?php

namespace CloudObjects\SDK\AccountGateway;

use Doctrine\Common\Cache\Cache;
use ML\JsonLD\JsonLD;
use GuzzleHttp\Psr7\Request;

class DataLoader {

  const CACHE_TTL = 172800; // cache at most 48 hours

  private $cache;
  private $cachePrefix = 'accdata:';
  private $mountPointName = '~';

  public function getCache() {
    return $this->cache;
  }

  public function setCache(Cache $cache) {
    $this->cache = $cache;
    return $this;
  }

  public function getCachePrefix() {
    return $this->cachePrefix;
  }

  public function setCachePrefix($cachePrefix) {
    $this->cachePrefix = $cachePrefix;
    return $this;
  }

  public function getMountPointName() {
    return $this->mountPointName;
  }

  public function setMountPointName($mountPointName) {
    $this->mountPointName = $mountPointName;
    return $this;
  }

  public function fetchAccountGraphDataDocument(AccountContext $accountContext) {
    $dataRequest = new Request('GET', '/'.$this->mountPointName.'/',
      ['Accept' => 'application/ld+json']);

    if (!$this->cache || !$accountContext->getRequest()
        || !$accountContext->getRequest()->headers->has('C-Data-Updated')) {
      // No cache or no timestamp available, so always fetch from Account Gateway
      $dataString = (string)$accountContext->getClient()->send($dataRequest)->getBody();
    } else {
      $key = $this->cachePrefix.$accountContext->getAAUID();
      $remoteTimestamp = $accountContext->getRequest()->headers->get('C-Data-Updated');
      if ($this->cache->contains($key)) {
        // Check timestamp
        $cacheEntry = $this->cache->fetch($key);
        $timestamp = substr($cacheEntry, 0, strpos($cacheEntry, '|'));
        if ($timestamp==$remoteTimestamp) {
          // Cache data is up to date, can be returned
          $dataString = substr($cacheEntry, strpos($cacheEntry, '|')+1);
        } else {
          // Fetch from Account Gateway and update cache entry
          $dataString = (string)$accountContext->getClient()->send($dataRequest)->getBody();
          $this->cache->save($key, $remoteTimestamp.'|'.$dataString, self::CACHE_TTL);
        }
      } else {
        // Fetch from Account Gateway and store in cache
        $dataString = (string)$accountContext->getClient()->send($dataRequest)->getBody();
        $this->cache->save($key, $remoteTimestamp.'|'.$dataString, self::CACHE_TTL);
      }
    }

    return JsonLD::getDocument($dataString);
  }

}
