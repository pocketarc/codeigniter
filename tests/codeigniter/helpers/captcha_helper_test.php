<?php

class Captcha_helper_test extends CI_TestCase {

	private $_img_path = NULL;

	public function set_up()
	{
		if ( ! extension_loaded('gd'))
		{
			$this->markTestSkipped('GD extension is not available.');
		}

		$this->helper('captcha');

		$this->_img_path = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('ci_captcha_', TRUE).DIRECTORY_SEPARATOR;
		mkdir($this->_img_path);
	}

	// --------------------------------------------------------------------

	public function tear_down()
	{
		if ($this->_img_path !== NULL)
		{
			foreach (scandir($this->_img_path) as $file)
			{
				if ($file !== '.' && $file !== '..')
				{
					unlink($this->_img_path.$file);
				}
			}

			rmdir($this->_img_path);
			$this->_img_path = NULL;
		}
	}

	// --------------------------------------------------------------------

	public function test_create_captcha_names_image_after_time()
	{
		$captcha = $this->_create_captcha();

		$this->assertTrue(is_array($captcha));
		$this->assertSame($captcha['time'].'.png', $captcha['filename']);
		$this->assertTrue(is_file($this->_img_path.$captcha['filename']));
	}

	// --------------------------------------------------------------------

	public function test_create_captcha_removes_expired_images()
	{
		$expired = time() - 10000;
		$files = array(
			$expired.'.png',
			$expired.'.123456.png',
			$expired.',123456.png',
			$expired.'.123456.jpg',
			$expired.'.123456.PNG'
		);

		foreach ($files as $file)
		{
			touch($this->_img_path.$file);
		}

		$captcha = $this->_create_captcha();

		foreach ($files as $file)
		{
			$this->assertFalse(is_file($this->_img_path.$file), "Expired '{$file}' was not removed.");
		}

		$this->assertTrue(is_file($this->_img_path.$captcha['filename']));
	}

	// --------------------------------------------------------------------

	public function test_create_captcha_keeps_unexpired_images()
	{
		$recent = time() - 10;
		$files = array(
			$recent.'.png',
			$recent.'.123456.png',
			$recent.',123456.png',
			$recent.'.123456.jpg'
		);

		foreach ($files as $file)
		{
			touch($this->_img_path.$file);
		}

		$this->_create_captcha();

		foreach ($files as $file)
		{
			$this->assertTrue(is_file($this->_img_path.$file), "Unexpired '{$file}' was removed.");
		}
	}

	// --------------------------------------------------------------------

	public function test_create_captcha_keeps_unrelated_files()
	{
		$expired = time() - 10000;
		$files = array(
			'index.html',
			'captcha.png',
			$expired.'.txt',
			$expired.'.123456.gif',
			$expired.'.png.bak',
			'123456789.png',
			'12345678901.png',
			$expired.'.12.34.png',
			$expired.'..png'
		);

		foreach ($files as $file)
		{
			touch($this->_img_path.$file);
		}

		$this->_create_captcha();

		foreach ($files as $file)
		{
			$this->assertTrue(is_file($this->_img_path.$file), "Unrelated '{$file}' was removed.");
		}
	}

	// --------------------------------------------------------------------

	public function test_create_captcha_respects_custom_expiration()
	{
		$timestamp = time() - 100;
		$file = $timestamp.'.123456.png';
		touch($this->_img_path.$file);

		$this->_create_captcha(array('expiration' => 3600));
		$this->assertTrue(is_file($this->_img_path.$file));

		$this->_create_captcha(array('expiration' => 50));
		$this->assertFalse(is_file($this->_img_path.$file));
	}

	// --------------------------------------------------------------------

	public function test_create_captcha_returns_data_uri_without_paths()
	{
		$captcha = create_captcha(array('word' => 'test'));

		$this->assertTrue(is_array($captcha));
		$this->assertNull($captcha['filename']);
		$this->assertEquals(1, preg_match('#src="data:image/png;base64,([A-Za-z0-9+/=]+)"#', $captcha['image'], $match));
		$this->assertSame("\x89PNG", substr(base64_decode($match[1]), 0, 4));

		$this->assertFalse(create_captcha(array('word' => 'test', 'img_url' => 'http://example.com/captcha/')));
	}

	// --------------------------------------------------------------------

	private function _create_captcha(array $data = array())
	{
		return create_captcha(array_merge(array(
			'word' => 'test',
			'img_path' => $this->_img_path,
			'img_url' => 'http://example.com/captcha/'
		), $data));
	}

}