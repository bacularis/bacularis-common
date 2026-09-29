<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2026 Marcin Haba
 *
 * The main author of Bacularis is Marcin Haba, with contributors, whose
 * full list can be found in the AUTHORS file.
 *
 * You may use this file and others of this release according to the
 * license defined in the LICENSE file, which includes the Affero General
 * Public License, v3.0 ("AGPLv3") and some additional permissions and
 * terms pursuant to its AGPLv3 Section 7.
 */

namespace Bacularis\Common\Modules;

use Prado\Prado;

/**
 * Restore verification result file module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class RestoreVerificationResult extends CommonModule
{
	/**
	 * Restore test result file path and extension.
	 */
	private const RESULT_FILE = 'Bacularis.Common.Working.RestoreTestState-%test_id';
	private const RESULT_FILE_EXT = '.json';

	/**
	 * Save restore verification result.
	 *
	 * @param string $test_id restore test identifier
	 * @param array $result checker results
	 * @return bool true on success, otherwise false
	 */
	public static function save(string $test_id, array $result): bool
	{
		if (!RestoreVerification::isValidTestId($test_id)) {
			return false;
		}
		$content = json_encode($result);
		if ($content === false) {
			return false;
		}
		$path = self::getFileByTestId($test_id);
		$written = file_put_contents($path, $content, LOCK_EX);
		if ($written !== strlen($content)) {
			return false;
		}
		$permissions = Prado::getDefaultFilePermissions();
		return chmod($path, $permissions);
	}

	/**
	 * Read restore verification result.
	 *
	 * @param string $test_id restore test identifier
	 * @return null|array result structure or null on error
	 */
	public static function get(string $test_id): ?array
	{
		if (!RestoreVerification::isValidTestId($test_id)) {
			return null;
		}
		$path = self::getFileByTestId($test_id);
		$handle = @fopen($path, 'rb');
		if ($handle === false) {
			return null;
		}
		$content = false;
		if (flock($handle, LOCK_SH)) {
			$content = stream_get_contents($handle);
			flock($handle, LOCK_UN);
		}
		fclose($handle);
		if (!is_string($content)) {
			return null;
		}
		$result = json_decode($content, true);
		return is_array($result) ? $result : null;
	}

	/**
	 * Check if restore verification result exists.
	 *
	 * @param string $test_id restore test identifier
	 * @return bool true if result exists, otherwise false
	 */
	public static function exists(string $test_id): bool
	{
		if (!RestoreVerification::isValidTestId($test_id)) {
			return false;
		}
		$path = self::getFileByTestId($test_id);
		return is_file($path);
	}

	/**
	 * Delete restore verification result.
	 *
	 * @param string $test_id restore test identifier
	 * @return bool true on success or if result does not exist, otherwise false
	 */
	public static function delete(string $test_id): bool
	{
		if (!RestoreVerification::isValidTestId($test_id)) {
			return false;
		}
		$path = self::getFileByTestId($test_id);
		return (!file_exists($path) || unlink($path));
	}

	/**
	 * Get restore verification result file path by test id.
	 *
	 * @param string $test_id restore test identifier
	 * @return string result file path
	 */
	private static function getFileByTestId(string $test_id): string
	{
		$file = str_replace(
			'%test_id',
			$test_id,
			self::RESULT_FILE
		);
		return Prado::getPathOfNamespace($file, self::RESULT_FILE_EXT);
	}
}
