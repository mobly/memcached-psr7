<?php

namespace Mobly\Cache\Adapter\Memcached;

use Mobly\Cache\AbstractCacheAdapter;
use Mobly\Cache\Interfaces\ConfigurationInterface;
use Mobly\Cache\CacheItem;
use Mobly\Cache\Exception\CacheException;
use Psr\Cache\CacheItemInterface;

class MemcachedAdapter extends AbstractCacheAdapter
{

    /**
     * @var \Memcached
     */
    private $cache;

    /**
     * @var CacheAdapterConfiguration
     */
    private $configuration;

    /**
     * @var self The reference to *Singleton* instance of this class
     */
    private static $instance;

    /**
     * @param ConfigurationInterface $configuration
     */
    protected function __construct(ConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;

        $this->cache = new \Memcached();
        $serverList = $this->cache->getServerList();
        if(empty($serverList)) {
            $this->cache->addServer($this->configuration->getHost(), $this->configuration->getPort());
        }

        $this->cache->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);

        if ($this->configuration->shouldCheckConnection()) {
            $this->checkConnection();
        }
    }

    /**
     * Private clone method to prevent cloning of the instance of the
     * *Singleton* instance.
     *
     * @return void
     */
    private function __clone()
    {
    }

    /**
     * @param ConfigurationInterface $configuration
     * @return MemcachedAdapter
     */
    public static function getInstance(ConfigurationInterface $configuration)
    {
        if (null === static::$instance) {
            static::$instance = new static($configuration);
        }

        return static::$instance;
    }

    /**
     * @return bool
     */
    protected function checkConnection()
    {
        $stats = $this->cache->getStats();
        $result = (isset($stats[$this->configuration->getHost().":".$this->configuration->getPort()]));
        if (!$result) {
            throw new CacheException('Connection error!');
        }

        return true;
    }

    /**
     * @param ConfigurationInterface $configuration
     */
    public function setConfiguration(ConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @param string $key
     * @return CacheItemInterface
     */
    protected function fetchObjectFromCache($key)
    {
        $cacheItem = new CacheItem($key);
        if (false === $result = unserialize($this->cache->get($key))) {
            return $cacheItem;
        }

        $cacheItem->set($result);

        return $cacheItem;
    }

    /**
     * @param array $keys
     * @return array
     */
    protected function fetchMultiObjectsFromCache(array $keys)
    {
        $items = [];
        $result = $this->cache->getMulti($keys, $null, \Memcached::GET_PRESERVE_ORDER);

        foreach ($result as $key => $value) {
            $cacheItem = new CacheItem($key);
            if (false !== $result = unserialize($this->cache->get($key))) {
                $cacheItem->set($result);
            }

            $items[$key] = $cacheItem;
        }
        return $items;
    }

    /**
     * @return bool
     */
    protected function clearAllObjectsFromCache()
    {
        return $this->cache->flush();
    }

    /**
     * @param string $key
     * @return bool
     */
    protected function clearOneObjectFromCache($key)
    {
        $this->commit();

        if ($this->cache->delete($key)) {
            return true;
        }

        // Return true if key not found
        return $this->cache->getResultCode() === \Memcached::RES_NOTFOUND;
    }

    /**
     * @param string $key
     * @param CacheItemInterface $item
     * @param int|null $ttl
     * @return bool
     */
    protected function storeItemInCache($key, CacheItemInterface $item, $ttl)
    {
        if ($ttl === null) {
            $ttl = $this->configuration->getTimeToLive();
        }

        return $this->cache->set($key, serialize($item->get()), $ttl);
    }

}