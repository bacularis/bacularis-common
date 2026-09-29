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
 * Data entropy module.
 *
 * Calculates Shannon entropy for files and binary data.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class DataEntropy extends CommonModule
{
	/**
	 * Default sample size in bytes (256 KiB).
	 */
	public const DEFAULT_SAMPLE_SIZE = 262144;

	/**
	 * Default number of samples.
	 */
	public const DEFAULT_SAMPLES = 3;

	/**
	 * Read data maximum buffer size.
	 */
	private const READ_BUFFER_SIZE = 1048576; // 1 MiB

	/**
	 * Calculate Shannon entropy for file.
	 *
	 * Small files are read completely. Large files are analyzed using
	 * evenly distributed samples.
	 *
	 * @param string $path file path
	 * @param int $sample_size sample size in bytes
	 * @param int $samples number of samples
	 * @return null|float entropy in range 0.0-8.0 or null on error
	 */
	public static function calculateFile(string $path, int $sample_size = self::DEFAULT_SAMPLE_SIZE, int $samples = self::DEFAULT_SAMPLES): ?float
	{
		if ($sample_size <= 0 || $samples <= 0) {
			return null;
		}

		$handle = @fopen($path, 'rb');
		if ($handle === false) {
			return null;
		}

		$stat = fstat($handle);
		if ($stat === false || !isset($stat['size'])) {
			fclose($handle);
			return null;
		}

		$file_size = (int) $stat['size'];

		if ($file_size === 0) {
			fclose($handle);
			return 0.0;
		}

		$counts = array_fill(0, 256, 0);
		$bytes_read = 0;

		/*
		 * If all requested samples would cover the whole file anyway,
		 * analyze the entire file.
		 */
		if ($file_size <= ($sample_size * $samples)) {
			$result = self::readRange(
				$handle,
				0,
				$file_size,
				$counts,
				$bytes_read
			);

			fclose($handle);

			if (!$result) {
				return null;
			}

			return self::calculateFromCounts($counts, $bytes_read);
		}

		$offsets = self::getSampleOffsets(
			$file_size,
			$sample_size,
			$samples
		);

		foreach ($offsets as $offset) {
			$result = self::readRange(
				$handle,
				$offset,
				$sample_size,
				$counts,
				$bytes_read
			);

			if (!$result) {
				fclose($handle);
				return null;
			}
		}

		fclose($handle);

		return self::calculateFromCounts($counts, $bytes_read);
	}

	/**
	 * Calculate Shannon entropy for binary string.
	 *
	 * @param string $data binary data
	 * @return float entropy in range 0.0-8.0
	 */
	public static function calculate(string $data): float
	{
		$length = strlen($data);

		if ($length === 0) {
			return 0.0;
		}

		$counts = array_fill(0, 256, 0);

		self::addDataCounts($data, $counts);

		return self::calculateFromCounts($counts, $length);
	}

	/**
	 * Get evenly distributed sample offsets.
	 *
	 * For three samples this produces samples at the beginning,
	 * middle and end of the file.
	 *
	 * @param int $file_size file size
	 * @param int $sample_size sample size
	 * @param int $samples number of samples
	 * @return array sample offsets
	 */
	private static function getSampleOffsets(int $file_size, int $sample_size, int $samples): array
	{
		$max_offset = $file_size - $sample_size;

		if ($samples === 1) {
			return [(int) floor($max_offset / 2)];
		}

		$offsets = [];

		for ($i = 0; $i < $samples; $i++) {
			$offsets[] = (int) round(
				($max_offset * $i) / ($samples - 1)
			);
		}

		return $offsets;
	}

	/**
	 * Read file range and update byte occurrence counters.
	 *
	 * @param resource $handle file handle
	 * @param int $offset start offset
	 * @param int $length number of bytes to read
	 * @param array $counts byte occurrence counters
	 * @param int $bytes_read total number of analyzed bytes
	 * @return bool true on success, otherwise false
	 */
	private static function readRange($handle, int $offset, int $length, array &$counts, int &$bytes_read): bool
	{
		if (fseek($handle, $offset, SEEK_SET) !== 0) {
			return false;
		}

		$remaining = $length;

		while ($remaining > 0) {
			$read_size = min($remaining, self::READ_BUFFER_SIZE);

			$data = fread($handle, $read_size);
			if ($data === false) {
				return false;
			}

			$data_length = strlen($data);

			if ($data_length === 0) {
				break;
			}

			self::addDataCounts($data, $counts);

			$bytes_read += $data_length;
			$remaining -= $data_length;
		}

		return ($remaining === 0);
	}

	/**
	 * Add byte occurrence counts from binary data.
	 *
	 * @param string $data binary data
	 * @param array $counts byte occurrence counters
	 */
	private static function addDataCounts(string $data, array &$counts): void
	{
		$data_counts = count_chars($data, 1);

		foreach ($data_counts as $byte => $count) {
			$counts[$byte] += $count;
		}
	}

	/**
	 * Calculate Shannon entropy from byte occurrence counters.
	 *
	 * @param array $counts byte occurrence counters
	 * @param int $total total number of bytes
	 * @return float entropy in range 0.0-8.0
	 */
	private static function calculateFromCounts(array $counts, int $total): float
	{
		if ($total === 0) {
			return 0.0;
		}

		$entropy = 0.0;

		foreach ($counts as $count) {
			if ($count === 0) {
				continue;
			}

			$probability = $count / $total;

			$entropy -= $probability * log($probability, 2);
		}

		return $entropy;
	}
}
