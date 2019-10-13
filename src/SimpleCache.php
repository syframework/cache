<?php
namespace Sy\Cache;

class SimpleCache implements \Psr\SimpleCache\CacheInterface {

	private $pool;

	private $directory;

	public function __construct($directory = null) {
		$this->pool = [];
		$this->directory = (is_null($directory) ? sys_get_temp_dir() : $directory) . '/cache';
	}

	/**
	 * Fetches a value from the cache.
	 *
	 * @param string $key     The unique key of this item in the cache.
	 * @param mixed  $default Default value to return if the key does not exist.
	 *
	 * @return mixed The value of the item from the cache, or $default in case of cache miss.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException if the $key string is not a legal value.
	 */
	public function get($key, $default = null) {
		$this->validateKey($key);
		$res = $default;
		if (isset($this->pool[$key])) {
			$res = $this->pool[$key];
		} elseif (is_file($this->directory . "/$key")) {
			@include $this->directory . "/$key";
			if (isset($val)) {
				$res = $val;
				$this->pool[$key] = $val;
			}
		}
		return $res;
	}

	/**
	 * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
	 *
	 * @param string                 $key   The key of the item to store.
	 * @param mixed                  $value The value of the item to store. Must be serializable.
	 * @param null|int|\DateInterval $ttl   Optional. The TTL value of this item. If no value is sent and
	 *                                      the driver supports TTL then the library may set a default value
	 *                                      for it or let the driver take care of that.
	 *
	 * @return bool True on success and false on failure.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException if the $key string is not a legal value.
	 */
	public function set($key, $value, $ttl = null) {
		$this->validateKey($key);
		// Do not cache empty value
		if (empty($value)) return false;
		$this->pool[$key] = $value;
		$val = var_export($value, true);
		$val = str_replace('stdClass::__set_state', '(object)', $val);
		$tmp = $this->directory . '/' . $key . uniqid('', true) . '.tmp';
		if (!file_exists(dirname($tmp))) {
			mkdir(dirname($tmp), 0777, true);
		}
		if (!file_put_contents($tmp, '<?php $val = ' . $val . ';', LOCK_EX)) {
			return false;
		}
		unset($val);
		return rename($tmp, $this->directory . "/$key");
	}

	/**
	 * Delete an item from the cache by its unique key.
	 *
	 * @param string $key The unique cache key of the item to delete.
	 *
	 * @return bool True if the item was successfully removed. False if there was an error.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException if the $key string is not a legal value.
	 */
	public function delete($key) {
		$this->validateKey($key);
		unset($this->pool[$key]);
		return $this->remove($this->directory . '/' . $key);
	}

	/**
	 * Wipes clean the entire cache's keys.
	 *
	 * @return bool True on success and false on failure.
	 */
	public function clear() {
		$this->pool = [];
		return $this->remove($this->directory);
	}

	/**
	 * Obtains multiple cache items by their unique keys.
	 *
	 * @param iterable $keys    A list of keys that can obtained in a single operation.
	 * @param mixed    $default Default value to return for keys that do not exist.
	 *
	 * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException if $keys is neither an array nor a Traversable,
	 *                                                   or if any of the $keys are not a legal value.
	 */
	public function getMultiple($keys, $default = null) {
		$this->validateKeys($keys);
		$result = [];
		foreach ($keys as $key) {
			$result[$key] = $this->get($key, $default);
		}
		return $result;
	}

	/**
	 * Persists a set of key => value pairs in the cache, with an optional TTL.
	 *
	 * @param iterable               $values A list of key => value pairs for a multiple-set operation.
	 * @param null|int|\DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
	 *                                       the driver supports TTL then the library may set a default value
	 *                                       for it or let the driver take care of that.
	 *
	 * @return bool True on success and false on failure.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException if $values is neither an array nor a Traversable,
	 *                                                   or if any of the $values are not a legal value.
	 */
	public function setMultiple($values, $ttl = null) {
		$this->validateKeys($values);
		$success = true;
		foreach ($values as $key => $value) {
			$success = $this->set($key, $value, $ttl) && $success;
		}
		return $success;
	}

	/**
	 * Deletes multiple cache items in a single operation.
	 *
	 * @param iterable $keys A list of string-based keys to be deleted.
	 *
	 * @return bool True if the items were successfully removed. False if there was an error.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException if $keys is neither an array nor a Traversable,
	 *                                                   or if any of the $keys are not a legal value.
	 */
	public function deleteMultiple($keys) {
		$this->validateKeys($keys);
		$success = true;
		foreach ($keys as $key) {
			$success = $this->delete($key) && $success;
		}
		return $success;
	}

	/**
	 * Determines whether an item is present in the cache.
	 *
	 * NOTE: It is recommended that has() is only to be used for cache warming type purposes
	 * and not to be used within your live applications operations for get/set, as this method
	 * is subject to a race condition where your has() will return true and immediately after,
	 * another script can remove it, making the state of your app out of date.
	 *
	 * @param string $key The cache item key.
	 *
	 * @return bool
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException if the $key string is not a legal value.
	 */
	public function has($key) {
		$this->validateKey($key);
		if (isset($this->pool[$key])) return true;
		return is_file($this->directory . "/$key");
	}

	/**
	 * Check if key is valid
	 *
	 * @param string $key
	 * @return void
	 * @throws \Psr\SimpleCache\InvalidArgumentException if the $key string is not a legal value.
	 */
	protected function validateKey($key) {
		if (!is_string($key)) {
			$type = (is_object($key) ? get_class($key) . ' ' : '') . gettype($key);
			throw new InvalidArgumentException("Expected key to be a string, not $type");
		}
		if ($key === '' || preg_match('~[{}()*\\\\@:]~', $key)) {
			throw new InvalidArgumentException("Invalid key '$key'");
		}
	}

	/**
     * Check if keys is an array or traversable
     *
     * @param iterable $keys
     * @return void
     * @throws \Psr\SimpleCache\InvalidArgumentException if keys is not iterable
     */
    protected function validateKeys($keys) {
        $iterable = function_exists('is_iterable') ? is_iterable($keys) : is_array($keys) || $keys instanceof Traversable;
        if (!$iterable) {
            throw new InvalidArgumentException('$keys is not iterable');
        }
	}

	/**
	 * Delete recursively a directory
	 *
	 * @param string $dir
	 * @return bool
	 */
	protected function remove($dir) {
		if (is_dir($dir)) {
			array_map([$this, 'remove'], glob("$dir/*"));
			return rmdir($dir);
		} elseif (is_file($dir)) {
			return unlink($dir);
		} else {
			return true;
		}
	}

}

class InvalidArgumentException extends \InvalidArgumentException implements \Psr\SimpleCache\InvalidArgumentException {}