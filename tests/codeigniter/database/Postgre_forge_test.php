<?php

class Postgre_forge_test extends CI_TestCase {

	/**
	 * @dataProvider forge_provider
	 */
	public function test_modify_column_comments_renamed_column($forge_class)
	{
		$db = new Postgre_forge_db_stub();
		$forge = new $forge_class($db);

		$this->assertTrue($forge->modify_column('users', array(
			'old_name' => array('name' => 'new_name', 'comment' => 'Renamed column')
		)));

		$this->assertSame(array(
			'ALTER TABLE "users" RENAME COLUMN "old_name" TO "new_name"',
			'COMMENT ON COLUMN "users"."new_name" IS \'Renamed column\''
		), $db->queries);
	}

	public function forge_provider()
	{
		return array(array('CI_DB_postgre_forge'), array('CI_DB_pdo_pgsql_forge'));
	}

}

class Postgre_forge_db_stub {

	public $dbprefix = '';
	public $db_debug = FALSE;
	public $queries = array();

	public function version()
	{
		return '15.0';
	}

	public function escape_identifiers($item)
	{
		return '"'.$item.'"';
	}

	public function escape($str)
	{
		return "'".str_replace("'", "''", $str)."'";
	}

	public function query($sql)
	{
		$this->queries[] = $sql;
		return TRUE;
	}
}
