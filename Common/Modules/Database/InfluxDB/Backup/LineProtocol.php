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

namespace Bacularis\Common\Modules\Database\InfluxDB\Backup;

use Bacularis\Common\Modules\Database\InfluxDB\Version2;
use Bacularis\Common\Modules\Errors\GenericError;
use Bacularis\Common\Modules\ExecuteCommand;
use Bacularis\Common\Modules\Miscellaneous;

/**
 * Offline InfluxDB engine reporting and stdout Line Protocol export commands.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class LineProtocol extends Version2
{
	/**
	 * Build a bucket-level cardinality report command for offline engine data.
	 *
	 * @param string $influxd_path InfluxDB server binary path
	 * @param string $engine_path offline engine root
	 * @return array literal command arguments, or empty array for invalid paths
	 */
	public function getReportCommand(string $influxd_path, string $engine_path): array
	{
		$is_valid = $this->isValidEngine($influxd_path, $engine_path);
		$data_path = rtrim($engine_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'data';
		if (!$is_valid || !is_dir($data_path) || !is_readable($data_path)) {
			return [];
		}
		return [
			$influxd_path,
			'inspect',
			'report-db',
			'--db-path',
			$data_path,
			'--rollup',
			'b'
		];
	}

	/**
	 * Discover sorted bucket IDs exclusively through the offline report-db command.
	 *
	 * @param string $influxd_path InfluxDB server binary path
	 * @param string $engine_path offline engine root
	 * @return array output, error status and validated bucket-ids
	 */
	public function getBucketIDs(string $influxd_path, string $engine_path): array
	{
		$command = $this->getReportCommand($influxd_path, $engine_path);
		if (count($command) === 0) {
			$result = ExecuteCommand::prepareResult(
				['Invalid offline engine or data directory.'],
				GenericError::ERROR_INVALID_PATH
			);
			$result['bucket-ids'] = [];
			return $result;
		}
		$result = ExecuteCommand::execProgram($command, ['LC_ALL' => 'C']);
		$result['bucket-ids'] = [];
		if ($result['error'] === 0) {
			$result = $this->parseBucketReport($result['output']);
		}
		return $result;
	}

	/**
	 * Parse the non-detailed bucket rollup, rejecting unexpected or malformed rows.
	 * Report diagnostics are not bucket rows and must not hide an incomplete report.
	 *
	 * @param array $output report-db output lines
	 * @return array output, error status and unique lowercase bucket-ids in sorted order
	 */
	public function parseBucketReport(array $output): array
	{
		$ids = [];
		$is_valid = true;
		$has_header = false;
		$has_divider = false;
		$has_total = false;
		foreach ($output as $line) {
			if (!is_string($line)) {
				$is_valid = false;
				break;
			}
			$line = trim($line);
			if ($line === '') {
				continue;
			}
			if (!$has_header) {
				$has_header = preg_match('/\Abucket\s+retention policy\s+measurement\s+series\z/D', $line) === 1;
				if (!$has_header) {
					$is_valid = false;
					break;
				}
				continue;
			}
			if (!$has_divider) {
				$has_divider = preg_match('/\A-+\s+-+\s+-+\s+-+\z/D', $line) === 1;
				if (!$has_divider) {
					$is_valid = false;
					break;
				}
				continue;
			}
			if ($has_total) {
				$is_valid = false;
				break;
			}
			if (preg_match('/\ATotal(?: \(est\.\))?\s+[0-9]+\z/D', $line) === 1) {
				$has_total = true;
				continue;
			}
			if (preg_match('/\A"([0-9a-fA-F]{16})"\s+[0-9]+\z/D', $line, $matches) !== 1) {
				$is_valid = false;
				break;
			}
			$ids[] = strtolower($matches[1]);
		}
		if (!$is_valid || !$has_header || !$has_divider || !$has_total) {
			$result = ExecuteCommand::prepareResult(
				['Invalid or incomplete InfluxDB bucket report.'],
				GenericError::ERROR_INTERNAL_ERROR
			);
			$result['bucket-ids'] = [];
		} else {
			$ids = array_unique($ids);
			$ids = array_values($ids);
			sort($ids, SORT_STRING);
			$result = ExecuteCommand::prepareResult($output, 0);
			$result['bucket-ids'] = $ids;
		}
		return $result;
	}

	/**
	 * Build a whole-bucket stdout export, optionally compressed by influxd itself.
	 * The caller must stream stdout without capturing it or merging stderr.
	 *
	 * @param string $influxd_path InfluxDB server binary path
	 * @param string $engine_path offline engine root
	 * @param string $bucket_id validated source bucket ID
	 * @param bool $compress enable native InfluxDB gzip compression
	 * @return array literal command arguments, or empty array for invalid values
	 */
	public function getExportCommand(string $influxd_path, string $engine_path, string $bucket_id, bool $compress = false): array
	{
		$is_valid = $this->isValidEngine($influxd_path, $engine_path);
		if (!$is_valid || preg_match('/\A[0-9a-fA-F]{16}\z/D', $bucket_id) !== 1) {
			return [];
		}
		$bucket_id = strtolower($bucket_id);
		$command = [
			$influxd_path,
			'inspect',
			'export-lp',
			'--engine-path',
			$engine_path,
			'--bucket-id',
			$bucket_id
		];
		if ($compress) {
			$command[] = '--compress';
		}
		$command[] = '--output-path';
		$command[] = '-';
		return $command;
	}

	/**
	 * Check local engine and executable path values without accessing an InfluxDB API.
	 *
	 * @param string $influxd_path InfluxDB server binary path or program resolved through PATH
	 * @param string $engine_path offline engine root
	 * @return bool true if the engine directory is readable and values are valid
	 */
	private function isValidEngine(string $influxd_path, string $engine_path): bool
	{
		$is_valid = true;
		foreach ([$influxd_path, $engine_path] as $value) {
			$is_control_char = Miscellaneous::isASCIControlChar($value);
			if (trim($value) === '' || $is_control_char) {
				$is_valid = false;
				break;
			}
		}
		return $is_valid && is_dir($engine_path) && is_readable($engine_path);
	}
}
