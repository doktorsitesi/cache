<?php

namespace Doctrine\Common\Cache;

/**
 * Base class for cache provider implementations.
 *
 * @since  2.2
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author Jonathan Wage <jonwage@gmail.com>
 * @author Roman Borschel <roman@code-factory.org>
 * @author Fabio B. Silva <fabio.bat.silva@gmail.com>
 * @author Benoit Burnichon <bburnichon@gmail.com>
 */
abstract class CacheProvider implements Cache, FlushableCache, ClearableCache, MultiOperationCache
{
    const DOCTRINE_NAMESPACE_CACHEKEY = 'DoctrineNamespaceCacheKey[%s]';

    /**
     * The namespace to prefix all cache ids with.
     *
     * @var string
     */
    private $namespace = '';

    /**
     * The namespace version.
     *
     * @var integer|null
     */
    private $namespaceVersion;

    /**
     * Sets the namespace to prefix all cache ids with.
     *
     * @param string $namespace
     *
     * @return void
     */
    public function setNamespace($namespace)
    {
        $this->namespace        = (string) $namespace;
        $this->namespaceVersion = null;
    }

    /**
     * Retrieves the namespace that prefixes all cache ids.
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($id)
    {
        return $this->doFetch($this->getNamespacedId($id));
    }

    /**
     * {@inheritdoc}
     */
    public function fetchMultiple(array $keys)
    {
        if (empty($keys)) {
            return [];
        }

        // note: the array_combine() is in place to keep an association between our $keys and the $namespacedKeys
        $namespacedKeys = array_combine($keys, array_map([$this, 'getNamespacedId'], $keys));
        $items          = $this->doFetchMultiple($namespacedKeys);
        $foundItems     = [];

        // no internal array function supports this sort of mapping: needs to be iterative
        // this filters and combines keys in one pass
        foreach ($namespacedKeys as $requestedKey => $namespacedKey) {
            if (isset($items[$namespacedKey]) || array_key_exists($namespacedKey, $items)) {
                $foundItems[$requestedKey] = $items[$namespacedKey];
            }
        }

        return $foundItems;
    }

    /**
     * {@inheritdoc}
     */
    public function saveMultiple(array $keysAndValues, $lifetime = 0)
    {
        $namespacedKeysAndValues = [];
        foreach ($keysAndValues as $key => $value) {
            $namespacedKeysAndValues[$this->getNamespacedId($key)] = $value;
        }

        return $this->doSaveMultiple($namespacedKeysAndValues, $lifetime);
    }

    /**
     * {@inheritdoc}
     */
    public function contains($id)
    {
        return $this->doContains($this->getNamespacedId($id));
    }

    /**
     * {@inheritdoc}
     */
    public function save($id, $data, $lifeTime = 0)
    {
        return $this->doSave($this->getNamespacedId($id), $data, $lifeTime);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $keys)
    {
        return $this->doDeleteMultiple(array_map(array($this, 'getNamespacedId'), $keys));
    }

    /**
     * {@inheritdoc}
     */
    public function delete($id)
    {
        return $this->doDelete($this->getNamespacedId($id));
    }

    /**
     * {@inheritdoc}
     */
    public function getStats()
    {
        return $this->doGetStats();
    }

    /**
     * {@inheritDoc}
     */
    public function flushAll()
    {
        return $this->doFlush();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteAll()
    {
        $namespaceCacheKey = $this->getNamespaceCacheKey();
        $namespaceVersion  = $this->getNamespaceVersion() + 1;

        if ($this->doSave($namespaceCacheKey, $namespaceVersion)) {
            $this->namespaceVersion = $namespaceVersion;

            return true;
        }

        return false;
    }

    /**
     * Prefixes the passed id with the configured namespace value.
     *
     * @param string $id The id to namespace.
     *
     * @return string The namespaced id.
     */
    private function getNamespacedId(string $id) : string
    {
        $namespaceVersion = $this->getNamespaceVersion();

        return sprintf('%s[%s][%s]', $this->namespace, $id, $namespaceVersion);
    }

    /**
     * Returns the namespace cache key.
     *
     * @return string
     */
    private function getNamespaceCacheKey() : string
    {
        return sprintf(self::DOCTRINE_NAMESPACE_CACHEKEY, $this->namespace);
    }

    /**
     * Returns the namespace version.
     *
     * @return integer
     */
    private function getNamespaceVersion() : int
    {
        if (null !== $this->namespaceVersion) {
            return $this->namespaceVersion;
        }

        $namespaceCacheKey      = $this->getNamespaceCacheKey();
        $this->namespaceVersion = (int) $this->doFetch($namespaceCacheKey) ?: 1;

        return $this->namespaceVersion;
    }

    /**
     * Default implementation of doFetchMultiple. Each driver that supports multi-get should owerwrite it.
     *
     * @param array $keys Array of keys to retrieve from cache
     * @return array Array of values retrieved for the given keys.
     */
    protected function doFetchMultiple(array $keys)
    {
        $returnValues = [];

        foreach ($keys as $key) {
            if (false !== ($item = $this->doFetch($key)) || $this->doContains($key)) {
                $returnValues[$key] = $item;
            }
        }

        return $returnValues;
    }

    
        /**
     * @param $prefix
     * @return array
     */
    public function deleteByPrefix($prefix)
    {
        $prefix = $this->getNamespacedIdDelete($prefix);
        $ids = $this->getIds();
        $deleted = array();

        if ($ids)
        {
            foreach ($ids as $id) {
    
                if (strpos($id, $prefix) !== false) {
                    $this->doDelete($id);
    
    
                    $deleted[] = $id;
                }
            }
        }
        return $deleted;
    }

    /**
     * @param $id
     * @return string
     */
    private function getNamespacedIdDelete($id)
    {
        $namespaceVersion = $this->getNamespaceVersion();
        return sprintf('%s[%s', $this->namespace, $id, $namespaceVersion);
    }
    
    /**
     * Fetches an entry from the cache.
     *
     * @param string $id The id of the cache entry to fetch.
     *
     * @return mixed|false The cached data or FALSE, if no cache entry exists for the given id.
     */
    abstract protected function doFetch($id);

    /**
     * Tests if an entry exists in the cache.
     *
     * @param string $id The cache id of the entry to check for.
     *
     * @return bool TRUE if a cache entry exists for the given cache id, FALSE otherwise.
     */
    abstract protected function doContains($id);

    /**
     * Default implementation of doSaveMultiple. Each driver that supports multi-put should override it.
     *
     * @param array $keysAndValues  Array of keys and values to save in cache
     * @param int   $lifetime       The lifetime. If != 0, sets a specific lifetime for these
     *                              cache entries (0 => infinite lifeTime).
     *
     * @return bool TRUE if the operation was successful, FALSE if it wasn't.
     */
    protected function doSaveMultiple(array $keysAndValues, $lifetime = 0)
    {
        $success = true;

        foreach ($keysAndValues as $key => $value) {
            if ( ! $this->doSave($key, $value, $lifetime)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Puts data into the cache.
     *
     * @param string $id       The cache id.
     * @param string $data     The cache entry/data.
     * @param int    $lifeTime The lifetime. If != 0, sets a specific lifetime for this
     *                           cache entry (0 => infinite lifeTime).
     *
     * @return bool TRUE if the entry was successfully stored in the cache, FALSE otherwise.
     */
    abstract protected function doSave($id, $data, $lifeTime = 0);

    /**
     * Default implementation of doDeleteMultiple. Each driver that supports multi-delete should override it.
     *
     * @param array $keys Array of keys to delete from cache
     *
     * @return bool TRUE if the operation was successful, FALSE if it wasn't
     */
    protected function doDeleteMultiple(array $keys)
    {
        $success = true;

        foreach ($keys as $key) {
            if ( ! $this->doDelete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Deletes a cache entry.
     *
     * @param string $id The cache id.
     *
     * @return bool TRUE if the cache entry was successfully deleted, FALSE otherwise.
     */
    abstract protected function doDelete($id);

    /**
     * Flushes all cache entries.
     *
     * @return bool TRUE if the cache entries were successfully flushed, FALSE otherwise.
     */
    abstract protected function doFlush();

    /**
     * Retrieves cached information from the data store.
     *
     * @since 2.2
     *
     * @return array|null An associative array with server's statistics if available, NULL otherwise.
     */
    abstract protected function doGetStats();
}
