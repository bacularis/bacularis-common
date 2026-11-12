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

namespace Bacularis\Common\Modules\Security\YARA;

use Bacularis\Common\Modules\Errors\GenericError;
use InvalidArgumentException;

/**
 * Common entry point for YARA operations.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Command extends Common
{
	/**
	 * Scan one filesystem target with one explicitly selected engine.
	 *
	 * @param string $type TYPE_YARA or TYPE_YARAX
	 * @param string $bin_path binary directory, empty for PATH
	 * @param string $rules_path source file/directory or compiled file
	 * @param string $target_path regular file or directory
	 * @param bool $compiled_rules load a single precompiled file
	 * @param int $timeout complete scanner process timeout in seconds
	 * @param string $config_path optional YARA-X configuration, empty for native lookup
	 * @param int $max_output_bytes combined capture limit
	 * @return array normalized Common::newResult() structure
	 */
	public static function runScan(string $type, string $bin_path, string $rules_path, string $target_path, bool $compiled_rules = false, int $timeout = self::DEFAULT_TIMEOUT, string $config_path = '', int $max_output_bytes = self::DEFAULT_OUTPUT_LIMIT): array
	{
		try {
			$class = self::getEngineClass($type);
		} catch (InvalidArgumentException $exception) {
			$result = self::newResult($type);
			$message = $exception->getMessage();
			return self::failResult(
				$result,
				GenericError::ERROR_INVALID_COMMAND,
				$message
			);
		}
		return $class::runScan(
			$bin_path,
			$rules_path,
			$target_path,
			$compiled_rules,
			$timeout,
			$config_path,
			$max_output_bytes
		);
	}

	/**
	 * Query the selected scanner version with a separate short process budget.
	 *
	 * @param string $type TYPE_YARA or TYPE_YARAX
	 * @param string $bin_path binary directory, empty for PATH
	 * @param int $timeout process timeout in seconds
	 * @param string $config_path optional YARA-X configuration
	 * @param int $max_output_bytes combined capture limit
	 * @return array normalized result with version populated on success
	 */
	public static function getVersion(string $type, string $bin_path = '', int $timeout = self::DEFAULT_VERSION_TIMEOUT, string $config_path = '', int $max_output_bytes = self::DEFAULT_VERSION_OUTPUT_LIMIT): array
	{
		try {
			$class = self::getEngineClass($type);
		} catch (InvalidArgumentException $exception) {
			$result = self::newResult($type);
			$message = $exception->getMessage();
			return self::failResult(
				$result,
				GenericError::ERROR_INVALID_COMMAND,
				$message
			);
		}
		return $class::getVersion(
			$bin_path,
			$timeout,
			$config_path,
			$max_output_bytes
		);
	}
}
