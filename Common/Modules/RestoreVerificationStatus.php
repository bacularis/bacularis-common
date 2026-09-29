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
 * Restore verification status file module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class RestoreVerificationStatus extends CommonModule
{
	/**
	 * Restore test status file path and extension.
	 */
	private const STATUS_FILE = 'Bacularis.Common.Working.RestoreTestStatus-%test_id';
	private const STATUS_FILE_EXT = '.json';

	/**
	 * Restore test execution states.
	 */
	public const STATE_READY = 'ready';
	public const STATE_RUNNING = 'running';
	public const STATE_DONE = 'done';
	private const STATES = [
		self::STATE_READY,
		self::STATE_RUNNING,
		self::STATE_DONE
	];

	/**
	 * Save restore verification execution status.
	 *
	 * @param string $test_id restore test identifier
	 * @param string $state restore test execution state
	 * @return bool true on success, otherwise false
	 */
	public static function save(string $test_id, string $state): bool
	{
		if (!RestoreVerification::isValidTestId($test_id) || !in_array($state, self::STATES, true)) {
			return false;
		}
		$content = json_encode(['state' => $state]);
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
	 * Read restore verification execution status.
	 *
	 * @param string $test_id restore test identifier
	 * @return null|array status structure or null on error
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
		$status = json_decode($content, true);
		if (
			!is_array($status) ||
			!key_exists('state', $status) ||
			!in_array($status['state'], self::STATES, true)
		) {
			return null;
		}
		return $status;
	}

	/**
	 * Check if restore verification execution status exists.
	 *
	 * @param string $test_id restore test identifier
	 * @return bool true if status exists, otherwise false
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
	 * Delete restore verification execution status.
	 *
	 * @param string $test_id restore test identifier
	 * @return bool true on success or if status does not exist, otherwise false
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
	 * Get restore verification status file path by test id.
	 *
	 * @param string $test_id restore test identifier
	 * @return string status file path
	 */
	private static function getFileByTestId(string $test_id): string
	{
		$file = str_replace(
			'%test_id',
			$test_id,
			self::STATUS_FILE
		);
		return Prado::getPathOfNamespace($file, self::STATUS_FILE_EXT);
	}
}
