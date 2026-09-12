<?php

/**
 * CodeIgniter uses vfsStream for running tests (fake filesystem).
 * vfsStream uses spl_object_hash(), which is deprecated in PHP 8.6,
 * so to keep tests passing we have to fake it.
 */

$patches = array(
	__DIR__ . '/../vendor/mikey179/vfsstream/src/main/php/org/bovigo/vfs/vfsStreamFile.php' => array(
		'return spl_object_hash($resource);' => 'return function_exists(\'spl_object_id\') ? (string) spl_object_id($resource) : spl_object_hash($resource);',
	),
);

foreach ($patches as $file => $replacements)
{
	if ( ! is_file($file))
	{
		continue;
	}

	$contents = file_get_contents($file);
	$patched = strtr($contents, $replacements);

	if ($patched !== $contents)
	{
		file_put_contents($file, $patched);
		echo 'Patched '.basename($file)."\n";
	}
}
