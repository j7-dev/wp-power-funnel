#!/usr/bin/env php
<?php
/**
 * Clean up problematic directories before running Strauss
 */

$options          = getopt('', [ 'dir:' ]);
$dir_from_option = $options['dir'] ?? null;

if (!$dir_from_option) {
	throw new Exception('No directory specified');
}

$dir = "./{$dir_from_option}";

echo "Cleaning up: $dir\n";
echo is_dir($dir) ? 'Directory exists' : 'Directory does not exist';

if (is_dir($dir)) {
	delete_directory($dir);
}


/**
 * Recursively delete a directory and its contents.
 *
 * @param string $dir The directory path to delete.
 *
 * @return bool Whether the directory was successfully deleted.
 */
function delete_directory( $dir ) {
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

		if (!delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
			return false;
		}
	}

	return rmdir($dir);
}

echo "Cleanup completed!\n";
