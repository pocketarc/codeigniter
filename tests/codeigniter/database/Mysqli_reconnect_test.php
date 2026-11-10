<?php

class Mysqli_reconnect_test extends CI_TestCase {

	public function test_reconnect_detects_dropped_connection()
	{
		if (DB_DRIVER !== 'mysqli')
		{
			return $this->markTestSkipped('Requires the mysqli driver.');
		}

		$db = Mock_Database_Schema_Skeleton::init(DB_DRIVER);
		$db->initialize();

		$notices = array();
		set_error_handler(function ($severity, $message) use (&$notices) {
			$notices[] = $message;
			return TRUE;
		});

		$db->reconnect();
		$alive_after_live_check = ($db->conn_id !== FALSE);

		$config = Mock_Database_DB::config(DB_DRIVER);
		$config = $config[DB_DRIVER];
		$admin = new mysqli($config['hostname'], $config['username'], $config['password'], $config['database']);
		$admin->query('KILL '.$db->conn_id->thread_id);
		$admin->close();
		usleep(200000);

		$db->reconnect();
		$dropped_after_dead_check = ($db->conn_id === FALSE);

		$query = $db->query('SELECT 1 AS one');

		restore_error_handler();

		$this->assertSame(array(), $notices);
		$this->assertTrue($alive_after_live_check);
		$this->assertTrue($dropped_after_dead_check);
		$this->assertEquals(1, $query->row()->one);
	}

}
