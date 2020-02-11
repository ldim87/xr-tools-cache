<?php
/**
 * @author Dmitriy Lukin <lukin.d87@gmail.com>
 */

namespace XrTools;

/**
 * Adapter for \XrTools\CacheManager Interface
 */
class MemcachedAdapter implements CacheManager {
	/**
	 * [$connection description]
	 * @var [type]
	 */
	private $connection;

	/**
	 * [$connectionParams description]
	 * @var [type]
	 */
	private $connectionParams;
	
	/**
	 * [$defaultExpirationSeconds description]
	 * @var integer
	 */
	private $defaultExpirationSeconds = 3600;

	/**
	 * Default return on null result
	 * @var boolean
	 */
	private $returnOnNull = false;

	/**
	 * [__construct description]
	 * @param array|null $connectionParams [description]
	 */
	function __construct(array $connectionParams = null){
		// connection settings
		if(isset($connectionParams)){
			$this->setConnectionParams($connectionParams);
		}
	}

	/**
	 * [validateSettings description]
	 * @param  array  $settings [description]
	 * @return [type]           [description]
	 */
	public function validateSettings(array $settings){
		// mandatory settings
		if(empty($settings['servers']) || !is_array($settings['servers'])){
			throw new \Exception('Servers list is empty or invalid!');
		}

		return $settings;
	}

	/**
	 * [setConnectionParams description]
	 * @param array $settings [description]
	 */
	public function setConnectionParams(array $settings){
		$this->connectionParams = $this->validateSettings($settings);
	}

	/**
	 * [setDefaultExpiration description]
	 * @param int $seconds [description]
	 */
	public function setDefaultExpiration(int $seconds){
		// set default expiration
		$this->defaultExpirationSeconds = $this->getExpiration($seconds);
	}

	/**
	 * [getExpiration description]
	 * @param  int|null $seconds [description]
	 * @return [type]            [description]
	 */
	public function getExpiration(int $seconds = null){
		// get default expiration
		if(!isset($seconds)){
			return $this->defaultExpirationSeconds;
		}
		// validate
		elseif(!$seconds){
			throw new \Exception('Invalid expiration time! Need to be positive number in seconds');
		}

		return $seconds;
	}

	/**
	 * mc_get()
	 * 
	 * @param  [type]       $key    [description]
	 * @param  bool|boolean $unjson [description]
	 * @return [type]               [description]
	 */
	public function get($key, bool $unjson = false){
		// skip empty entries
		if(!$key){
			return false;
		}

		if(is_array($key)){
			return $this->getMulti($key, $unjson);
		}

		// get connection
		$cache = $this->getConnection();

		// default result
		$result = false;

		$data = $cache->get($key);
		
		if($cache->getResultCode() == 0){
			if($unjson){
				$result = json_decode($data, true);
			} else{
				$result = $data ?? $this->returnOnNull;
			}
		}

		return $result;
	}

	/**
	 * [getMulti description]
	 * @param  array        $keys   [description]
	 * @param  bool|boolean $unjson [description]
	 * @return [type]               [description]
	 */
	public function getMulti(array $keys, bool $unjson = false){
		// skip empty entries
		if(!$keys){
			return false;
		}

		// get connection
		$cache = $this->getConnection();

		// default result
		$result = false;

		$data = $cache->getMulti($keys);
		
		if($cache->getResultCode() == 0){
			// default array
			$result = [];

			if($unjson){
				foreach ($data as $k => $v){
					$result[$k] = json_decode($v, true);
				}
			} else{
				$result = $data;
			}
		}

		return $result;
	}

	/**
	 * mc_set()
	 * 
	 * @param [type]       $key        [description]
	 * @param [type]       $value      [description]
	 * @param int|null     $expiration [description]
	 * @param bool|boolean $json       [description]
	 */
	public function set($key, $value = null, int $expiration = null, bool $json = false){
		// skip empty entries
		if(!$key){
			return false;
		}

		$expiration = $this->getExpiration($expiration);

		if(is_array($key)){
			return $this->setMulti($key, $expiration, $json);
		}

		// get connection
		$cache = $this->getConnection();

		$cache->set($key, $json ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value, $expiration);

		return $cache->getResultCode() == 0;
	}

	/**
	 * [setMulti description]
	 * @param array        $keys       [description]
	 * @param int|null     $expiration [description]
	 * @param bool|boolean $json       [description]
	 */
	public function setMulti(array $keys, int $expiration = null, bool $json = false){
		// skip empty entries
		if(!$keys){
			return false;
		}

		// get connection
		$cache = $this->getConnection();

		if($json){
			$data = [];
			foreach ($keys as $k => $v){
				$data[$k] = json_encode($v, JSON_UNESCAPED_UNICODE);
			}
		} else{
			$data = $keys;
		}
		
		$cache->setMulti($data, $this->getExpiration($expiration));

		return $cache->getResultCode() == 0;
	}
	
	/**
	 * mc_del()
	 * 
	 * @param  [type] $key [description]
	 * @return [type]      [description]
	 */
	public function del($key){
		// skip empty entries
		if(!$key){
			return false;
		}

		if(is_array($key)){
			return $this->delMulti($key);
		}

		// get connection
		$cache = $this->getConnection();

		return $cache->delete($key);
	}

	/**
	 * [delMulti description]
	 * @param  array  $keys [description]
	 * @return [type]       [description]
	 */
	public function delMulti(array $keys){
		// skip empty entries
		if(!$keys){
			return false;
		}

		// get connection
		$cache = $this->getConnection();

		return $cache->deleteMulti($keys);
	}

	/**
	 * [connect description]
	 * @param  array  $settings [description]
	 * @return [type]           [description]
	 */
	public function connect(array $settings){

		// validate settings
		$settings = $this->validateSettings($settings);

		$connection = new \Memcached();

		$connection->addServers($settings['servers']);
		
		$version = $connection->getVersion();

		if(empty($version)){
			throw new \Exception('Server connection error (getVersion)!');
		}

		$connectionKey = null;

		foreach ($version as $con_key){
			if(!$con_key){
				continue;
			}

			$connectionKey = $con_key;
		}

		if(!$connectionKey){
			throw new \Exception('Server connection error (connectionKey)!');
		}

		return $connection;
	}

	/**
	 * [getConnection description]
	 * @return [type] [description]
	 */
	public function getConnection(){
		// connect if not connected
		if(!isset($this->connection)){
			$this->connection = $this->connect($this->connectionParams);
		}

		return $this->connection;
	}

	/**
	 * Get version key stamp
	 * 
	 * @param	string		$key	Cache key
	 * @param	integer		$exp	Cache expiration time in seconds
	 * @return	string		Version time
	 */
	public function getStamp(string $key, int $exp = 3600){

		$time = $this->get($key);

		if ($time === false){

			$time = microtime(true) . mt_rand(10000, 99999);

			$this->set($key, $time, $exp);
		}

		return $time;
	}
	
	/**
	 * Increment numeric item's value
	 *
	 * @param string $key
	 * @param int    $offset
	 * @param int    $initial_value
	 * @param int    $expiry
	 *
	 * @return bool
	 * @see \Memcached::increment()
	 */
	public function increment(string $key, int $offset = 1, int $initial_value = 0, int $expiry = 0)
	{
		// skip empty entries
		if (empty($key)) {
			return false;
		}

		// get connection
		$cache = $this->getConnection();

		return $cache->increment($key, $offset, $initial_value, $expiry);
	}

	/**
	 * Decrement
	 * @param string $key
	 * @param int $offset
	 * @param int $initial_value
	 * @param int $expiry
	 * @return bool|int
	 */
	public function decrement(string $key, int $offset = 1, int $initial_value = 0, int $expiry = 0)
	{
		// skip empty entries
		if (empty($key)) {
			return false;
		}

		// get connection
		$cache = $this->getConnection();

		return $cache->decrement($key, $offset, $initial_value, $expiry);
	}

	/**
	 * Flush
	 * @return bool
	 */
	public function flush()
	{
		// get connection
		$cache = $this->getConnection();

		return $cache->flush();
	}
}
