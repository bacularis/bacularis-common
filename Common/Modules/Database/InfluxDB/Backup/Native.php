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
use Exception;

/**
 * Reusable InfluxDB 2.x Native backup and restore mechanics.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Native extends Version2
{
	/**
	 * Build a backup argv list for the entire instance or one bucket.
	 *
	 * @param string $influx_path InfluxDB CLI binary path
	 * @param string $host server URL
	 * @param string $organization organization containing the selected bucket
	 * @param string $directory backup destination
	 * @param bool $verify_tls verify the TLS certificate
	 * @param string $bucket bucket name or ID, or empty for the entire instance
	 * @param bool $use_bucket_id interpret the bucket argument as an ID
	 * @return array command arguments without credentials or shell quoting
	 */
	public function getBackupCommand(string $influx_path, string $host, string $organization, string $directory, bool $verify_tls = true, string $bucket = '', bool $use_bucket_id = false): array
	{
		$connection_args = $this->getConnectionArguments($host, $organization, $verify_tls);
		$command = array_merge([$influx_path, 'backup'], $connection_args);
		if ($bucket !== '') {
			$command[] = $use_bucket_id ? '--bucket-id' : '--bucket';
			$command[] = $bucket;
		}
		$command[] = '--';
		$command[] = $directory;
		return $command;
	}

	/**
	 * Build a restore argv list for an extracted Native backup directory.
	 * Full restore replaces the entire instance and cannot have bucket selectors.
	 *
	 * @param string $influx_path InfluxDB CLI binary path
	 * @param string $host server URL
	 * @param string $organization original organization name
	 * @param string $directory extracted backup directory
	 * @param bool $verify_tls verify the TLS certificate
	 * @param bool $full restore the entire instance
	 * @param string $bucket original bucket name or ID, or empty for all backup data
	 * @param bool $use_bucket_id interpret the bucket argument as an ID
	 * @param string $new_bucket optional new bucket name
	 * @param string $new_org optional new organization name
	 * @return array command arguments, or empty array for incompatible options
	 */
	public function getRestoreCommand(string $influx_path, string $host, string $organization, string $directory, bool $verify_tls = true, bool $full = false, string $bucket = '', bool $use_bucket_id = false, string $new_bucket = '', string $new_org = ''): array
	{
		if (($full && ($bucket !== '' || $new_bucket !== '' || $new_org !== '')) || ($new_bucket !== '' && $bucket === '')) {
			return [];
		}
		$connection_args = $this->getConnectionArguments($host, $organization, $verify_tls);
		$command = array_merge([$influx_path, 'restore'], $connection_args);
		if ($full) {
			$command[] = '--full';
		}
		if ($bucket !== '') {
			$command[] = $use_bucket_id ? '--bucket-id' : '--bucket';
			$command[] = $bucket;
		}
		if ($new_bucket !== '') {
			$command[] = '--new-bucket';
			$command[] = $new_bucket;
		}
		if ($new_org !== '') {
			$command[] = '--new-org';
			$command[] = $new_org;
		}
		$command[] = '--';
		$command[] = $directory;
		return $command;
	}

	/**
	 * Back up to a caller-provided empty directory, retaining files even on failure.
	 *
	 * @param string $influx_path InfluxDB CLI binary path
	 * @param string $host server URL
	 * @param string $organization organization containing the selected bucket
	 * @param string $token connection token
	 * @param string $directory backup destination
	 * @param bool $verify_tls verify the TLS certificate
	 * @param string $ca_cert_path optional CA certificate file
	 * @param string $bucket bucket name or ID, or empty for the entire instance
	 * @param bool $use_bucket_id interpret the bucket argument as an ID
	 * @return array command output and error status, with zero indicating success
	 */
	public function backup(string $influx_path, string $host, string $organization, string $token, string $directory, bool $verify_tls = true, string $ca_cert_path = '', string $bucket = '', bool $use_bucket_id = false): array
	{
		$is_valid = $this->isValidConnection($host, $token, $ca_cert_path);
		if (!$is_valid) {
			return ExecuteCommand::prepareResult(
				['Invalid InfluxDB connection values or CA certificate file.'],
				GenericError::ERROR_INVALID_COMMAND
			);
		}
		$path = $directory !== '' ? realpath($directory) : false;
		if ($path === false || !is_dir($path) || !is_writable($path)) {
			return ExecuteCommand::prepareResult(
				['InfluxDB backup destination is not a writable directory.'],
				GenericError::ERROR_INVALID_PATH
			);
		}
		$files = @scandir($path);
		if ($files === false || count($files) !== 2) {
			return ExecuteCommand::prepareResult(
				['InfluxDB backup destination must be empty.'],
				GenericError::ERROR_INVALID_PATH
			);
		}
		$command = $this->getBackupCommand(
			$influx_path,
			$host,
			$organization,
			$path,
			$verify_tls,
			$bucket,
			$use_bucket_id
		);
		$environment = $this->getConnectionEnvironment($token, $ca_cert_path);
		return ExecuteCommand::execProgram($command, $environment);
	}

	/**
	 * Restore from a caller-provided extracted Native backup directory.
	 * Separate operator-token restores are not supported.
	 *
	 * @param string $influx_path InfluxDB CLI binary path
	 * @param string $host server URL
	 * @param string $organization original organization name
	 * @param string $token connection token
	 * @param string $directory extracted backup directory
	 * @param bool $verify_tls verify the TLS certificate
	 * @param string $ca_cert_path optional CA certificate file
	 * @param bool $full restore the entire instance
	 * @param string $bucket original bucket name or ID, or empty for all backup data
	 * @param bool $use_bucket_id interpret the bucket argument as an ID
	 * @param string $new_bucket optional new bucket name
	 * @param string $operator_token separate full-restore credential, currently unsupported
	 * @param string $new_org optional new organization name
	 * @return array command output and error status, with zero indicating success
	 */
	public function restore(string $influx_path, string $host, string $organization, string $token, string $directory, bool $verify_tls = true, string $ca_cert_path = '', bool $full = false, string $bucket = '', bool $use_bucket_id = false, string $new_bucket = '', string $new_org = ''): array
	{
		$is_valid = $this->isValidConnection($host, $token, $ca_cert_path);
		if (!$is_valid) {
			return ExecuteCommand::prepareResult(
				['Invalid InfluxDB connection values or CA certificate file.'],
				GenericError::ERROR_INVALID_COMMAND
			);
		}
		$path = $directory !== '' ? realpath($directory) : false;
		if ($path === false || !is_dir($path) || !is_readable($path)) {
			return ExecuteCommand::prepareResult(
				['InfluxDB restore source is not a readable directory.'],
				GenericError::ERROR_INVALID_PATH
			);
		}
		$command = $this->getRestoreCommand(
			$influx_path,
			$host,
			$organization,
			$path,
			$verify_tls,
			$full,
			$bucket,
			$use_bucket_id,
			$new_bucket,
			$new_org
		);
		if (count($command) === 0) {
			return ExecuteCommand::prepareResult(
				['Incompatible InfluxDB restore options.'],
				GenericError::ERROR_INVALID_COMMAND
			);
		}
		$environment = $this->getConnectionEnvironment($token, $ca_cert_path);
		return ExecuteCommand::execProgram($command, $environment);
	}

	/**
	 * Create a private, unique child directory under an existing writable base path.
	 * Working files remain for administrator-managed cleanup.
	 *
	 * @param string $base_path configured Native working directory
	 * @return string created absolute directory path, or empty string on failure
	 */
	public function createWorkingDirectory(string $base_path): string
	{
		$base = $base_path !== '' ? realpath($base_path) : false;
		if ($base === false || !is_dir($base) || !is_writable($base)) {
			return '';
		}
		try {
			$bytes = random_bytes(16);
			$suffix = bin2hex($bytes);
		} catch (Exception $e) {
			return '';
		}
		$directory = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'influxdb-native.' . $suffix;
		if (!@mkdir($directory, 0700)) {
			return '';
		}
		return $directory;
	}
}
