<?php

class Cache_file_test extends CI_TestCase {

	public function set_up()
	{
		class_exists('CI_Driver_Library', TRUE);
		$this->helper('file');

		$loader = $this->getMockBuilder('CI_Loader')->setMethods(array('helper'))->getMock();
		$this->ci_instance_var('load', $loader);
	}

	// --------------------------------------------------------------------

	public function test_cache_path_without_trailing_slash()
	{
		$this->ci_set_config('cache_path', APPPATH.'filecache');
		$dir = $this->ci_vfs_mkdir('filecache', $this->ci_app_root);
		$cache = new CI_Cache_file();

		$this->assertTrue($cache->save('foo', 'bar'));
		$this->assertTrue($dir->hasChild('foo'));
		$this->assertEquals('bar', $cache->get('foo'));
	}

	// --------------------------------------------------------------------

	public function test_cache_path_without_config_key()
	{
		$this->ci_set_config();
		$dir = $this->ci_vfs_mkdir('cache', $this->ci_app_root);
		$cache = new CI_Cache_file();

		$this->assertTrue($cache->save('foo', 'bar'));
		$this->assertTrue($dir->hasChild('foo'));
	}

}
