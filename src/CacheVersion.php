<?php

/**
 * @author Oleg Isaev
 * @contacts vk.com/id50416641, t.me/pandcar, github.com/pandcar
 */

namespace XrTools;

class CacheVersion
{
	/**
	 * @var CacheManager
	 */
	private $mc;

	/**
	 * @var string
	 */
	public $name;

	/**
	 * @var int
	 */
	public $exp;

	/**
	 * @var string
	 */
	private $cache_version;
	
	/**
	 * CacheVersion constructor.
	 * @param CacheManager $mc
	 * @param string       $name
	 * @param int          $exp
	 */
	public function __construct(CacheManager $mc, string $name, int $exp = 3600)
	{
		$this->mc   = $mc;
		$this->name = $name;
		$this->exp  = $exp;
	}

	/**
	 * @param $key
	 * @param array $param
	 * @return string
	 */
	public function getKey($key, $param = []): string
	{
		return $this->name.'_'.$key. (! empty($param) ? '_'.implode('_', $param) : null) .'_'.$this->get_version(! empty($param['skip_object_cache']));
	}

	/**
	 * @param bool $skip_object_cache
	 * @return string
	 */
	public function get_version($skip_object_cache = false): string
	{
		if (! $skip_object_cache && isset($this->cache_version)) {
			return $this->cache_version;
		}

		$this->cache_version = $this->mc->getStamp($this->name, $this->exp);

		return $this->cache_version;
	}

	/**
	 * Сброс кэша
	 */
	public function new()
	{
		return $this->mc->del($this->name);
	}
}
