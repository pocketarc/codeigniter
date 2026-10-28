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

	public function test_create_captcha_writes_image_with_fractional_timestamp_filename()
	{
		$captcha = $this->_create_captcha();

		$this->assertTrue(is_array($captcha));
		$this->assertEquals(1, preg_match('#^\d{10}\.\d{6}\.png$#', $captcha['filename']));
		$this->assertTrue(is_file($this->_img_path.$captcha['filename']));
		$this->assertSame(sprintf('%.6F', $captcha['time']).'.png', $captcha['filename']);
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

	public function test_create_captcha_filename_is_locale_independent()
	{
		$original = setlocale(LC_NUMERIC, 0);
		$locale = setlocale(LC_NUMERIC, 'de_DE.UTF-8', 'de_DE.utf8', 'de_DE', 'de-DE', 'German_Germany.1252', 'German');

		if ($locale === FALSE)
		{
			$this->markTestSkipped('No locale with a comma decimal separator is available.');
		}

		$captcha = $this->_create_captcha();
		setlocale(LC_NUMERIC, $original);

		$this->assertEquals(1, preg_match('#^\d{10}\.\d{6}\.png$#', $captcha['filename']));
		$this->assertTrue(is_file($this->_img_path.$captcha['filename']));
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