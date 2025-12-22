#!/usr/bin/env php
<?php
/**
 * Clean up problematic directories before running Strauss
 */

$options = getopt('', ['dir:']);
$dirFromOption = $options['dir'] ?? null;

if(!$dirFromOption){
    throw new Exception('No directory specified');
}

$dir = "./{$dirFromOption}";

echo "Cleaning up: $dir\n";
echo is_dir($dir) ? 'Directory exists' : 'Directory does not exist';

if (is_dir($dir)) {
    deleteDirectory($dir);
}


function deleteDirectory( $dir ) {
	if (!file_exists($dir)) {
		return true;
	}

	if (!is_dir($dir)) {
		return unlink($dir);
	}

	foreach (scandir($dir) as $item) {
		if ($item == '.' || $item == '..') {
			continue;
		}

		if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
			return false;
		}
	}

	return rmdir($dir);
}

echo "Cleanup completed!\n";
