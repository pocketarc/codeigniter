<?php

class File_helper_Test extends CI_TestCase {

	private $_real_dir = NULL;

	public function set_up()
	{
		$this->helper('file');

		$this->_test_dir = vfsStream::setup('');
	}

	// --------------------------------------------------------------------

	public function tear_down()
	{
		if ($this->_real_dir !== NULL)
		{
			$this->_delete_real_dir($this->_real_dir);
			$this->_real_dir = NULL;
		}
	}

	// --------------------------------------------------------------------

	public function test_octal_permissions()
	{
		$content = 'Jack and Jill went up the mountain to fight a billy goat.';

		$file = vfsStream::newFile('my_file.txt', 0777)
			->withContent($content)
			->lastModified(time() - 86400)
			->at($this->_test_dir);

		$this->assertEquals('777', octal_permissions($file->getPermissions()));
	}

	// --------------------------------------------------------------------

	/**
	 * More tests should happen here, since I'm not hitting the whole function.
	 */
	public function test_symbolic_permissions()
	{
		$content = 'Jack and Jill went up the mountain to fight a billy goat.';

		$file = vfsStream::newFile('my_file.txt', 0777)
			->withContent($content)
			->lastModified(time() - 86400)
			->at($this->_test_dir);

		$this->assertEquals('urwxrwxrwx', symbolic_permissions($file->getPermissions()));
	}

	// --------------------------------------------------------------------

	public function test_get_mime_by_extension()
	{
		$content = 'Jack and Jill went up the mountain to fight a billy goat.';

		$file = vfsStream::newFile('my_file.txt', 0777)
			->withContent($content)
			->lastModified(time() - 86400)
			->at($this->_test_dir);

		$this->assertEquals('text/plain', get_mime_by_extension(vfsStream::url('my_file.txt')));

		// Test a mime with an array, such as png
		$file = vfsStream::newFile('foo.png')->at($this->_test_dir);

		$this->assertEquals('image/png', get_mime_by_extension(vfsStream::url('foo.png')));

		// Test a file not in the mimes array
		$file = vfsStream::newFile('foo.blarfengar')->at($this->_test_dir);

		$this->assertFalse(get_mime_by_extension(vfsStream::url('foo.blarfengar')));
	}

	// --------------------------------------------------------------------

	public function test_get_file_info()
	{
		// Test Bad File
		$this->assertFalse(get_file_info('i_am_bad_boo'));

		// Test the rest

		// First pass in an array
		$vals = array(
			'name', 'server_path', 'size', 'date',
			'readable', 'writable', 'executable', 'fileperms'
		);

		$this->_test_get_file_info($vals);

		// Test passing in vals as a string.
		$this->_test_get_file_info(implode(', ', $vals));
	}

	private function _test_get_file_info($vals)
	{
		$content = 'Jack and Jill went up the mountain to fight a billy goat.';
		$last_modified = time() - 86400;

		$file = vfsStream::newFile('my_file.txt', 0777)
			->withContent($content)
			->lastModified($last_modified)
			->at($this->_test_dir);

		$ret_values = array(
			'name'        => 'my_file.txt',
			'server_path' => 'vfs://my_file.txt',
			'size'        => 57,
			'date'        => $last_modified,
			'readable'    => TRUE,
			'writable'    => TRUE,
			'executable'  => TRUE,
			'fileperms'   => 33279
		);

		$info = get_file_info(vfsStream::url('my_file.txt'), $vals);

		foreach ($info as $k => $v)
		{
			$this->assertEquals($ret_values[$k], $v);
		}
	}

	// --------------------------------------------------------------------

	public function test_get_dir_file_info()
	{
		$this->assertFalse(get_dir_file_info('i_am_not_a_directory'));

		$dir = $this->_create_real_dir();
		mkdir($dir.'sub');
		file_put_contents($dir.'top.txt', 'a');
		file_put_contents($dir.'shared.txt', 'bb');
		file_put_contents($dir.'sub'.DIRECTORY_SEPARATOR.'shared.txt', 'ccc');

		// Default is top-level only: sub/ gets an entry of its own, its contents don't
		$info = get_dir_file_info($dir);

		$this->assertEquals(array('shared.txt', 'sub', 'top.txt'), $this->_sorted_keys($info));
		$this->assertEquals('shared.txt', $info['shared.txt']['name']);
		$this->assertEquals(2, $info['shared.txt']['size']);
		$this->assertEquals($dir, $info['shared.txt']['relative_path']);
	}

	// --------------------------------------------------------------------

	public function test_get_dir_file_info_recursive()
	{
		$dir = $this->_create_real_dir();
		mkdir($dir.'sub');
		file_put_contents($dir.'top.txt', 'a');
		file_put_contents($dir.'shared.txt', 'bb');
		file_put_contents($dir.'sub'.DIRECTORY_SEPARATOR.'shared.txt', 'ccc');

		$info = get_dir_file_info($dir, FALSE);
		$nested = 'sub'.DIRECTORY_SEPARATOR.'shared.txt';

		// Identically named files in different folders must not overwrite each other
		$this->assertEquals(array('shared.txt', $nested, 'top.txt'), $this->_sorted_keys($info));
		$this->assertEquals(2, $info['shared.txt']['size']);
		$this->assertEquals(3, $info[$nested]['size']);
		$this->assertEquals('shared.txt', $info[$nested]['name']);
	}

	// --------------------------------------------------------------------

	public function test_write_file()
	{
		$content = 'Jack and Jill went up the mountain to fight a billy goat.';

		$file = vfsStream::newFile('write.txt', 0777)
			->withContent('')
			->lastModified(time() - 86400)
			->at($this->_test_dir);

		$this->assertTrue(write_file(vfsStream::url('write.txt'), $content));
	}

	// --------------------------------------------------------------------

	/**
	 * get_dir_file_info() resolves its source path with realpath(), which returns
	 * FALSE for vfsStream URLs, so these tests can't use vfsStream like the rest.
	 */
	private function _create_real_dir()
	{
		$this->_real_dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('ci_file_helper_', TRUE).DIRECTORY_SEPARATOR;
		mkdir($this->_real_dir);

		return $this->_real_dir;
	}

	private function _delete_real_dir($dir)
	{
		foreach (scandir($dir) as $file)
		{
			if ($file === '.' OR $file === '..')
			{
				continue;
			}

			$path = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$file;
			is_dir($path) ? $this->_delete_real_dir($path) : unlink($path);
		}

		rmdir($dir);
	}

	private function _sorted_keys($info)
	{
		$keys = array_keys($info);
		sort($keys);

		return $keys;
	}

}
