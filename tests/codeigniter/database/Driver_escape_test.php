<?php

class Driver_escape_test extends CI_TestCase {

	public function set_up()
	{
		class_exists('Mock_Database_DB_Driver', TRUE);

		$this->mysql = new CI_DB_mysqli_driver(array());
		$this->postgre = new CI_DB_postgre_driver(array());
	}

	// --------------------------------------------------------------------

	public function test_escape_identifiers_per_driver()
	{
		$this->assertSame('`table`.`col`', $this->mysql->escape_identifiers('table.col'));
		$this->assertSame('"table"."col"', $this->postgre->escape_identifiers('table.col'));
		$this->assertSame('`table`.`col`', $this->mysql->escape_identifiers('table.col'));
	}

	// --------------------------------------------------------------------

	public function test_is_literal_per_driver()
	{
		$is_literal = new ReflectionMethod('CI_DB_query_builder', '_is_literal');
		if (PHP_VERSION_ID < 80100)
		{
			$is_literal->setAccessible(TRUE);
		}

		$this->assertTrue($is_literal->invoke($this->mysql, '"foo"'));
		$this->assertFalse($is_literal->invoke($this->postgre, '"foo"'));
		$this->assertTrue($is_literal->invoke($this->mysql, '"foo"'));
	}

}
