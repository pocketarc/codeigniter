<?php

class Cache_redis_test extends CI_TestCase {

	public function set_up()
	{
		class_exists('CI_Driver_Library', TRUE);

		$this->redis = new Cache_redis_server_stub();

		$class = new ReflectionClass('CI_Cache_redis');
		$this->cache = $class->newInstanceWithoutConstructor();

		$property = new ReflectionProperty('CI_Cache_redis', '_redis');
		if (PHP_VERSION_ID < 80100)
		{
			$property->setAccessible(TRUE);
		}
		$property->setValue($this->cache, $this->redis);
	}

	// --------------------------------------------------------------------

	public function test_increment_creates_plain_counter()
	{
		$this->assertSame(1, $this->cache->increment('hits'));
		$this->assertSame(3, $this->cache->increment('hits', 2));
		$this->assertSame(2, $this->cache->decrement('hits'));

		$this->assertSame('2', $this->redis->strings['hits']);
		$this->assertEquals(2, $this->cache->get('hits'));
	}

	// --------------------------------------------------------------------

	public function test_increment_existing_plain_counter()
	{
		$this->redis->strings['hits'] = '5';

		$this->assertSame(6, $this->cache->increment('hits'));
		$this->assertSame(4, $this->cache->decrement('hits', 2));
	}

	// --------------------------------------------------------------------

	public function test_increment_saved_value()
	{
		$this->redis->hashes['hits'] = array('__ci_type' => 'integer', '__ci_value' => '5');

		$this->assertSame(6, $this->cache->increment('hits'));
		$this->assertSame(5, $this->cache->decrement('hits'));
		$this->assertSame(5, $this->cache->get('hits'));
	}

	// --------------------------------------------------------------------

	public function test_increment_non_numeric_value()
	{
		$this->redis->strings['name'] = 'bar';
		$this->redis->hashes['data'] = array('__ci_type' => 'string', '__ci_value' => 'bar');

		$this->assertFalse($this->cache->increment('name'));
		$this->assertFalse($this->cache->increment('data'));
		$this->assertSame('string', $this->redis->hashes['data']['__ci_type']);
	}

}

class Cache_redis_server_stub {

	public $strings = array();
	public $hashes = array();

	public function incrBy($key, $offset)
	{
		if (isset($this->hashes[$key]) OR (isset($this->strings[$key]) && ! ctype_digit(ltrim($this->strings[$key], '-'))))
		{
			return FALSE;
		}

		$value = (isset($this->strings[$key]) ? (int) $this->strings[$key] : 0) + $offset;
		$this->strings[$key] = (string) $value;
		return $value;
	}

	public function hIncrBy($key, $field, $offset)
	{
		if (isset($this->strings[$key]) OR (isset($this->hashes[$key][$field]) && ! ctype_digit(ltrim($this->hashes[$key][$field], '-'))))
		{
			return FALSE;
		}

		$value = (isset($this->hashes[$key][$field]) ? (int) $this->hashes[$key][$field] : 0) + $offset;
		$this->hashes[$key][$field] = (string) $value;
		return $value;
	}

	public function hSet($key, $field, $value)
	{
		if (isset($this->strings[$key]))
		{
			return FALSE;
		}

		$this->hashes[$key][$field] = (string) $value;
		return 1;
	}

	public function hMGet($key, $fields)
	{
		if (isset($this->strings[$key]))
		{
			return FALSE;
		}

		$values = array();
		foreach ($fields as $field)
		{
			$values[$field] = isset($this->hashes[$key][$field]) ? $this->hashes[$key][$field] : FALSE;
		}

		return $values;
	}

	public function get($key)
	{
		if (isset($this->hashes[$key]))
		{
			return FALSE;
		}

		return isset($this->strings[$key]) ? $this->strings[$key] : FALSE;
	}

	public function sIsMember($key, $member)
	{
		return FALSE;
	}

	public function close()
	{
		return TRUE;
	}
}
