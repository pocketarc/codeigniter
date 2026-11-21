<?php

/**
 * Checks that each driver's updateTimestamp() creates a missing record and
 * only touches an existing one. PHP 8.6+ calls it instead of write() for a
 * new, empty session.
 */
class Session_update_timestamp_test extends CI_TestCase {

	protected $params = array(
		'save_path' => 'ci_sessions',
		'match_ip' => FALSE,
		'expiration' => 7200,
		'cookie_name' => 'ci_session'
	);

	protected $id = 'abcdef0123456789abcdef0123456789';

	public function set_up()
	{
		if ( ! is_php('7.0'))
		{
			$this->markTestSkipped('PHP calls updateTimestamp() only from 7.0 onwards.');
		}
	}

	// --------------------------------------------------------------------

	public function test_database_creates_new_empty_session()
	{
		$db = new Session_update_timestamp_db_stub();
		$driver = new Session_update_timestamp_database_driver($this->params, $db);

		$this->assertSame('', $driver->read($this->id));
		$this->assertTrue($driver->updateTimestamp($this->id, ''));
		$this->assertArrayHasKey($this->id, $db->rows);
		$this->assertSame('', $db->rows[$this->id]['data']);
	}

	// --------------------------------------------------------------------

	public function test_database_touches_existing_session()
	{
		$db = new Session_update_timestamp_db_stub();
		$db->rows[$this->id] = array('id' => $this->id, 'data' => 'foo|s:3:"bar";', 'timestamp' => 1);
		$driver = new Session_update_timestamp_database_driver($this->params, $db);

		$this->assertSame('foo|s:3:"bar";', $driver->read($this->id));
		$this->assertTrue($driver->updateTimestamp($this->id, 'foo|s:3:"bar";'));
		$this->assertSame(0, $db->inserts);
		$this->assertGreaterThan(1, $db->rows[$this->id]['timestamp']);
	}

	// --------------------------------------------------------------------

	public function test_redis_creates_new_empty_session()
	{
		$redis = new Session_update_timestamp_redis_stub();
		$driver = new Session_update_timestamp_redis_driver($this->params, $redis);

		$this->assertSame('', $driver->read($this->id));
		$this->assertTrue($driver->updateTimestamp($this->id, ''));
		$this->assertSame('', $redis->data['ci_session:'.$this->id]);
	}

	// --------------------------------------------------------------------

	public function test_redis_touches_existing_session()
	{
		$redis = new Session_update_timestamp_redis_stub();
		$redis->data['ci_session:'.$this->id] = 'foo|s:3:"bar";';
		$driver = new Session_update_timestamp_redis_driver($this->params, $redis);

		$this->assertSame('foo|s:3:"bar";', $driver->read($this->id));
		$this->assertTrue($driver->updateTimestamp($this->id, 'foo|s:3:"bar";'));
		$this->assertSame(0, $redis->sets);
		$this->assertSame(7200, $redis->ttls['ci_session:'.$this->id]);
	}

	// --------------------------------------------------------------------

	public function test_redis_does_not_recreate_expired_session()
	{
		$redis = new Session_update_timestamp_redis_stub();
		$redis->data['ci_session:'.$this->id] = 'foo|s:3:"bar";';
		$driver = new Session_update_timestamp_redis_driver($this->params, $redis);

		$this->assertSame('foo|s:3:"bar";', $driver->read($this->id));
		$redis->del('ci_session:'.$this->id);
		$this->assertFalse($driver->updateTimestamp($this->id, 'foo|s:3:"bar";'));
		$this->assertSame(0, $redis->sets);
	}

	// --------------------------------------------------------------------

	public function test_memcached_creates_new_empty_session()
	{
		if ( ! class_exists('Memcached', FALSE))
		{
			$this->markTestSkipped('The Memcached extension is not available.');
		}

		$memcached = new Session_update_timestamp_memcached_stub();
		$driver = new Session_update_timestamp_memcached_driver($this->params, $memcached);

		$this->assertSame('', $driver->read($this->id));
		$this->assertTrue($driver->updateTimestamp($this->id, ''));
		$this->assertSame('', $memcached->data['ci_session:'.$this->id]);
	}

	// --------------------------------------------------------------------

	public function test_memcached_touches_existing_session()
	{
		if ( ! class_exists('Memcached', FALSE))
		{
			$this->markTestSkipped('The Memcached extension is not available.');
		}

		$memcached = new Session_update_timestamp_memcached_stub();
		$memcached->data['ci_session:'.$this->id] = 'foo|s:3:"bar";';
		$driver = new Session_update_timestamp_memcached_driver($this->params, $memcached);

		$this->assertSame('foo|s:3:"bar";', $driver->read($this->id));
		$this->assertTrue($driver->updateTimestamp($this->id, 'foo|s:3:"bar";'));
		$this->assertSame(0, $memcached->sets);
		$this->assertSame(7200, $memcached->ttls['ci_session:'.$this->id]);
	}

	// --------------------------------------------------------------------

	public function test_memcached_does_not_recreate_expired_session()
	{
		if ( ! class_exists('Memcached', FALSE))
		{
			$this->markTestSkipped('The Memcached extension is not available.');
		}

		$memcached = new Session_update_timestamp_memcached_stub();
		$memcached->data['ci_session:'.$this->id] = 'foo|s:3:"bar";';
		$driver = new Session_update_timestamp_memcached_driver($this->params, $memcached);

		$this->assertSame('foo|s:3:"bar";', $driver->read($this->id));
		$memcached->delete('ci_session:'.$this->id);
		$this->assertFalse($driver->updateTimestamp($this->id, 'foo|s:3:"bar";'));
		$this->assertSame(0, $memcached->sets);
	}

}

// --------------------------------------------------------------------

require_once(SYSTEM_PATH.'libraries/Session/CI_Session_driver_interface.php');
require_once(SYSTEM_PATH.'libraries/Session/Session_driver.php');
require_once(SYSTEM_PATH.'libraries/Session/drivers/Session_database_driver.php');
require_once(SYSTEM_PATH.'libraries/Session/drivers/Session_redis_driver.php');
require_once(SYSTEM_PATH.'libraries/Session/drivers/Session_memcached_driver.php');

class Session_update_timestamp_database_driver extends CI_Session_database_driver {

	public function __construct(&$params, $db)
	{
		CI_Session_driver::__construct($params);
		$this->_db = $db;
	}
}

class Session_update_timestamp_redis_driver extends CI_Session_redis_driver {

	public function __construct(&$params, $redis)
	{
		CI_Session_driver::__construct($params);
		$this->_redis = $redis;
		$this->_setTimeout_name = 'expire';
		$this->_delete_name = 'del';
	}
}

class Session_update_timestamp_memcached_driver extends CI_Session_memcached_driver {

	public function __construct(&$params, $memcached)
	{
		CI_Session_driver::__construct($params);
		$this->_memcached = $memcached;
	}
}

// --------------------------------------------------------------------

class Session_update_timestamp_db_stub {

	public $rows = array();
	public $inserts = 0;
	protected $where = array();

	public function reset_query()
	{
		$this->where = array();
		return $this;
	}

	public function select($select)
	{
		return $this;
	}

	public function from($from)
	{
		return $this;
	}

	public function where($key, $value)
	{
		$this->where[$key] = $value;
		return $this;
	}

	public function get()
	{
		$id = $this->where['id'];
		$this->where = array();
		return new Session_update_timestamp_db_result_stub(isset($this->rows[$id]) ? (object) $this->rows[$id] : NULL);
	}

	public function insert($table, $data)
	{
		$this->rows[$data['id']] = $data;
		$this->inserts++;
		return TRUE;
	}

	public function update($table, $data)
	{
		$id = $this->where['id'];
		$this->where = array();
		isset($this->rows[$id]) && $this->rows[$id] = array_merge($this->rows[$id], $data);
		return TRUE;
	}
}

class Session_update_timestamp_db_result_stub {

	protected $row;

	public function __construct($row)
	{
		$this->row = $row;
	}

	public function row()
	{
		return $this->row;
	}
}

// --------------------------------------------------------------------

class Session_update_timestamp_redis_stub {

	public $data = array();
	public $ttls = array();
	public $sets = 0;

	public function ttl($key)
	{
		return isset($this->data[$key]) ? (isset($this->ttls[$key]) ? $this->ttls[$key] : -1) : -2;
	}

	public function set($key, $value, $options = NULL)
	{
		if (is_array($options) && in_array('nx', $options, TRUE) && isset($this->data[$key]))
		{
			return FALSE;
		}

		$this->data[$key] = (string) $value;
		$this->ttls[$key] = is_array($options) ? $options['ex'] : $options;
		substr($key, -5) === ':lock' OR $this->sets++;
		return TRUE;
	}

	public function setex($key, $ttl, $value)
	{
		$this->data[$key] = (string) $value;
		$this->ttls[$key] = $ttl;
		return TRUE;
	}

	public function get($key)
	{
		return isset($this->data[$key]) ? $this->data[$key] : FALSE;
	}

	public function expire($key, $ttl)
	{
		if ( ! isset($this->data[$key]))
		{
			return FALSE;
		}

		$this->ttls[$key] = $ttl;
		return TRUE;
	}

	public function exists($key)
	{
		return (int) isset($this->data[$key]);
	}

	public function del($key)
	{
		$existed = isset($this->data[$key]);
		unset($this->data[$key], $this->ttls[$key]);
		return (int) $existed;
	}
}

// --------------------------------------------------------------------

class Session_update_timestamp_memcached_stub {

	public $data = array();
	public $ttls = array();
	public $sets = 0;
	protected $result_code = 0;

	public function getResultCode()
	{
		return $this->result_code;
	}

	public function get($key)
	{
		if (isset($this->data[$key]))
		{
			$this->result_code = Memcached::RES_SUCCESS;
			return $this->data[$key];
		}

		$this->result_code = Memcached::RES_NOTFOUND;
		return FALSE;
	}

	public function add($key, $value, $ttl)
	{
		if (isset($this->data[$key]))
		{
			$this->result_code = Memcached::RES_NOTSTORED;
			return FALSE;
		}

		return $this->store($key, $value, $ttl);
	}

	public function set($key, $value, $ttl)
	{
		substr($key, -5) === ':lock' OR $this->sets++;
		return $this->store($key, $value, $ttl);
	}

	public function replace($key, $value, $ttl)
	{
		if ( ! isset($this->data[$key]))
		{
			$this->result_code = Memcached::RES_NOTFOUND;
			return FALSE;
		}

		return $this->store($key, $value, $ttl);
	}

	public function touch($key, $ttl)
	{
		if ( ! isset($this->data[$key]))
		{
			$this->result_code = Memcached::RES_NOTFOUND;
			return FALSE;
		}

		$this->ttls[$key] = $ttl;
		$this->result_code = Memcached::RES_SUCCESS;
		return TRUE;
	}

	public function delete($key)
	{
		if ( ! isset($this->data[$key]))
		{
			$this->result_code = Memcached::RES_NOTFOUND;
			return FALSE;
		}

		unset($this->data[$key], $this->ttls[$key]);
		$this->result_code = Memcached::RES_SUCCESS;
		return TRUE;
	}

	protected function store($key, $value, $ttl)
	{
		$this->data[$key] = (string) $value;
		$this->ttls[$key] = $ttl;
		$this->result_code = Memcached::RES_SUCCESS;
		return TRUE;
	}
}
