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

/**
 * Asynchronous command output file helper.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class AsyncOutput
{
	/**
	 * Output file prefix.
	 */
	public const OUTPUT_FILE_PREFIX = 'output_';

	/**
	 * Number of random bytes used for an output capability.
	 */
	private const OUTPUT_ID_BYTES = 32;

	/**
	 * Maximum number of output file creation attempts.
	 */
	private const FILE_CREATE_ATTEMPTS = 3;

	/**
	 * Create an asynchronous output file with a cryptographically strong ID.
	 *
	 * @param string $directory output file directory
	 * @return array output file path and public output ID
	 * @throws \RuntimeException when the output file cannot be created securely
	 */
	public static function createOutputFile(string $directory): array
	{
		$directory = rtrim($directory, '/\\');
		for ($attempt = 0; $attempt < self::FILE_CREATE_ATTEMPTS; $attempt++) {
			$random = random_bytes(self::OUTPUT_ID_BYTES);
			$out_id = bin2hex($random);
			$file = $directory . DIRECTORY_SEPARATOR . self::OUTPUT_FILE_PREFIX . $out_id;

			$previous_umask = umask(0077);
			try {
				$handle = @fopen($file, 'x+b');
			} finally {
				umask($previous_umask);
			}

			if (!is_resource($handle)) {
				continue;
			}

			$permissions_set = @chmod($file, 0600);
			$file_closed = fclose($handle);
			if (!$permissions_set || !$file_closed) {
				@unlink($file);
				throw new \RuntimeException('Could not securely create asynchronous output file.');
			}

			return [
				'path' => $file,
				'out_id' => $out_id
			];
		}

		throw new \RuntimeException('Could not securely create asynchronous output file.');
	}

	/**
	 * Get the path for an asynchronous output ID.
	 *
	 * @param string $directory output file directory
	 * @param string $out_id public output ID
	 * @return string output file path
	 */
	public static function getOutputFilePath(string $directory, string $out_id): string
	{
		$directory = rtrim($directory, '/\\');
		return $directory . DIRECTORY_SEPARATOR . self::OUTPUT_FILE_PREFIX . $out_id;
	}

	/**
	 * Validate an asynchronous output capability ID.
	 *
	 * @param mixed $out_id public output ID
	 * @return bool true for an exact 64-character lowercase hexadecimal ID
	 */
	public static function isValidOutputID($out_id): bool
	{
		return is_string($out_id) && preg_match('/^[0-9a-f]{64}$/', $out_id) === 1;
	}
}
