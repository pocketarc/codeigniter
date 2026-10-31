<?php
class Log_test extends CI_TestCase {

	protected $original_timezone;

	public function tear_down()
	{
		if ($this->original_timezone !== NULL)
		{
			date_default_timezone_set($this->original_timezone);
		}
	}

	public function test_configuration()
	{
		$path       = new ReflectionProperty('CI_Log', '_log_path');
		$threshold  = new ReflectionProperty('CI_Log', '_threshold');
		$date_fmt   = new ReflectionProperty('CI_Log', '_date_fmt');
		$filename   = new ReflectionProperty('CI_Log', '_log_filename');
		$file_perms = new ReflectionProperty('CI_Log', '_file_permissions');
		$enabled    = new ReflectionProperty('CI_Log', '_enabled');
		if (PHP_VERSION_ID < 80100)
		{
			$path->setAccessible(TRUE);
			$threshold->setAccessible(TRUE);
			$date_fmt->setAccessible(TRUE);
			$filename->setAccessible(TRUE);
			$file_perms->setAccessible(TRUE);
			$enabled->setAccessible(TRUE);
		}

		$this->ci_set_config('log_path', $this->ci_readonly_dir->url());
		$this->ci_set_config('log_threshold', 'z');
		$this->ci_set_config('log_date_format', 'd.m.Y');
		$this->ci_set_config('log_filename', '');
		$this->ci_set_config('log_file_permissions', '');
		$instance = new CI_Log();

		$this->assertNotFalse(strpos($path->getValue($instance), 'application/readonly'));
		$this->assertEquals($threshold->getValue($instance), 1);
		$this->assertEquals($date_fmt->getValue($instance), 'd.m.Y');
		$this->assertSame('', $filename->getValue($instance));
		$get_filename = new ReflectionMethod('CI_Log', '_get_log_filename');
		if (PHP_VERSION_ID < 80100)
		{
			$get_filename->setAccessible(TRUE);
		}
		$this->assertSame('log-'.date('Y-m-d').'.php', $get_filename->invoke($instance));
		$this->assertEquals($file_perms->getValue($instance), 0644);
		$this->assertFalse($enabled->getValue($instance));

		$this->ci_set_config('log_path', '');
		$this->ci_set_config('log_threshold', '0');
		$this->ci_set_config('log_date_format', '');
		$this->ci_set_config('log_filename', 'testname.log');
		$this->ci_set_config('log_file_permissions', 0600);
		$instance = new CI_Log();

		$this->assertEquals($path->getValue($instance), $this->ci_vfs_root->url().'application/logs'.DIRECTORY_SEPARATOR);
		$this->assertEquals($threshold->getValue($instance), 0);
		$this->assertEquals($date_fmt->getValue($instance), 'Y-m-d H:i:s');
		$this->assertEquals($filename->getValue($instance), 'testname.log');
		$this->assertEquals($file_perms->getValue($instance), 0600);
		$this->assertEquals($enabled->getValue($instance), TRUE);
	}

	public function test_log_file_extension_backward_compatibility()
	{
		$filename = new ReflectionMethod('CI_Log', '_get_log_filename');
		if (PHP_VERSION_ID < 80100)
		{
			$filename->setAccessible(TRUE);
		}

		$this->ci_set_config('log_path', '');
		$this->ci_set_config('log_threshold', 0);
		$this->ci_set_config('log_filename', '');
		$this->ci_set_config('log_file_extension', 'txt');
		$instance = new CI_Log();

		$this->assertSame('log-'.date('Y-m-d').'.txt', $filename->invoke($instance));

		$this->ci_set_config('log_file_extension', '.log');
		$instance = new CI_Log();

		$this->assertSame('log-'.date('Y-m-d').'.log', $filename->invoke($instance));

		$this->ci_set_config('log_filename', 'custom.log');
		$this->ci_set_config('log_file_extension', 'txt');
		$instance = new CI_Log();

		$this->assertSame('custom.log', $filename->invoke($instance));
	}

	public function test_format_line()
	{
		$this->ci_set_config('log_path', '');
		$this->ci_set_config('log_threshold', 0);
		$instance = new CI_Log();

		$format_line = new ReflectionMethod($instance, '_format_line');
		if (PHP_VERSION_ID < 80100)
		{
			$format_line->setAccessible(TRUE);
		}
		$this->assertEquals(
			$format_line->invoke($instance, 'LEVEL', 'Timestamp', 'Message'),
			"LEVEL - Timestamp --> Message".PHP_EOL
		);
	}

	/**
	 * @dataProvider log_extension_provider
	 */
	public function test_daily_log_rotation($extension, $expected_extension)
	{
		$this->original_timezone = date_default_timezone_get();
		date_default_timezone_set('Etc/GMT+12');
		$this->ci_set_config('log_path', '');
		$this->ci_set_config('log_threshold', 4);
		$this->ci_set_config('log_filename', '');
		$this->ci_set_config('log_file_extension', $extension);
		$instance = new CI_Log();

		$filename = new ReflectionProperty('CI_Log', '_log_filename');
		if (PHP_VERSION_ID < 80100)
		{
			$filename->setAccessible(TRUE);
		}

		$previous_filename = 'log-'.date('Y-m-d').'.'.$expected_extension;
		$previous_path = APPPATH.'logs'.DIRECTORY_SEPARATOR.$previous_filename;
		$this->assertTrue($instance->write_log('error', 'previous entry'));
		$previous_contents = file_get_contents($previous_path);

		// These timezones are 26 hours apart, ensuring the calendar date advances.
		date_default_timezone_set('Pacific/Kiritimati');

		$this->ci_set_config('log_filename', 'changed.log');
		$this->ci_set_config('log_file_extension', 'changed');
		$this->assertTrue($instance->write_log('error', 'first entry'));
		$this->assertTrue($instance->write_log('error', 'second entry'));

		$expected_filename = 'log-'.date('Y-m-d').'.'.$expected_extension;
		$this->assertNotSame($previous_filename, $expected_filename);
		$this->assertSame('', $filename->getValue($instance));
		$this->assertSame($previous_contents, file_get_contents($previous_path));
		$contents = file_get_contents(APPPATH.'logs'.DIRECTORY_SEPARATOR.$expected_filename);
		$this->assertNotFalse(strpos($contents, 'first entry'));
		$this->assertNotFalse(strpos($contents, 'second entry'));
		$this->assertSame($expected_extension === 'php' ? 1 : 0, substr_count($contents, '<?php'));
		$this->assertFalse(file_exists(APPPATH.'logs'.DIRECTORY_SEPARATOR.'changed.log'));
	}

	public function log_extension_provider()
	{
		return array(array('', 'php'), array('txt', 'txt'), array('.log', 'log'));
	}

	/**
	 * @dataProvider custom_log_filename_provider
	 */
	public function test_custom_filename_is_fixed_at_construction($custom_filename)
	{
		$this->ci_set_config('log_path', '');
		$this->ci_set_config('log_threshold', 4);
		$this->ci_set_config('log_filename', $custom_filename);
		$this->ci_set_config('log_file_extension', 'txt');
		$instance = new CI_Log();

		$this->ci_set_config('log_filename', 'changed.log');
		$this->ci_set_config('log_file_extension', 'log');
		$this->ci_set_config('log_threshold', 0);
		$this->assertTrue($instance->write_log('error', 'custom entry'));
		$contents = file_get_contents(APPPATH.'logs'.DIRECTORY_SEPARATOR.$custom_filename);
		$this->assertNotFalse(strpos($contents, 'custom entry'));
		$this->assertSame(substr($custom_filename, -3) === 'php' ? 1 : 0, substr_count($contents, '<?php'));
		$this->assertFalse(file_exists(APPPATH.'logs'.DIRECTORY_SEPARATOR.'changed.log'));
		$this->assertFalse((new ReflectionClass('CI_Log'))->hasProperty('_config'));
	}

	public function custom_log_filename_provider()
	{
		return array(array('log-2000-01-01.php'), array('log-'.date('Y-m-d').'.php'), array('custom.log'));
	}

	public function test_subclass_filename_override()
	{
		$this->ci_set_config('log_path', '');
		$this->ci_set_config('log_threshold', 4);
		$this->ci_set_config('log_filename', '');
		$this->ci_set_config('log_file_extension', 'txt');
		$instance = new Log_filename_stub();

		$this->assertTrue($instance->write_log('error', 'subclass entry'));
		$this->assertTrue($instance->write_log('error', 'another entry'));
		$contents = file_get_contents(APPPATH.'logs'.DIRECTORY_SEPARATOR.'subclass.php');
		$this->assertNotFalse(strpos($contents, 'subclass entry'));
		$this->assertNotFalse(strpos($contents, 'another entry'));
		$this->assertSame(1, substr_count($contents, '<?php'));
		$this->assertFalse(file_exists(APPPATH.'logs'.DIRECTORY_SEPARATOR.'log-'.date('Y-m-d').'.txt'));
	}

	public function test_write_log_with_empty_message()
	{
		$this->ci_set_config('log_path', '');
		$this->ci_set_config('log_threshold', 4);
		$this->ci_set_config('log_filename', 'empty.log');
		$instance = new Log_empty_format_stub();

		// _format_line() is overridden to return '', so $message is empty and the
		// write loop never runs. Without $result initialised, is_int($result) returns FALSE,
		// and write_log() wrongly returns FALSE.
		$this->assertTrue($instance->write_log('error', 'discarded'));
	}
}

class Log_empty_format_stub extends CI_Log {

	protected function _format_line($level, $date, $message)
	{
		return '';
	}

}

class Log_filename_stub extends CI_Log {

	public function __construct()
	{
		parent::__construct();
		$this->_log_filename = 'subclass.php';
	}
}
