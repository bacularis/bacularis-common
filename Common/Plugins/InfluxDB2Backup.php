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

namespace Bacularis\Common\Plugins;

use Bacularis\Common\Modules\BacularisCommonPluginBase;
use Bacularis\Common\Modules\BPluginRuntimeException;
use Bacularis\Common\Modules\Database\InfluxDB\Backup\LineProtocol as InfluxDBLineProtocol;
use Bacularis\Common\Modules\Database\InfluxDB\Backup\Native as InfluxDBNative;
use Bacularis\Common\Modules\Database\InfluxDB\Recovery as InfluxDBRecovery;
use Bacularis\Common\Modules\IBaculaBackupFileDaemonPlugin;
use Bacularis\Common\Modules\Miscellaneous;
use Bacularis\Common\Modules\Plugins;
use ReflectionClass;

/**
 * The InfluxDB 2.x backup plugin module.
 * Native Full Instance, All Buckets One Archive, Selected Buckets and All Buckets
 * Separately backups and local archive restore are supported.
 * Direct Native single-bucket and All Buckets One Archive restores are supported.
 * Full Line Protocol offline export streams one object per reported bucket.
 * Offline recovery listings provide instance-level reference metadata.
 * Direct Full Instance restore and Line Protocol import are not supported.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Plugin
 */
class InfluxDB2Backup extends BacularisCommonPluginBase implements IBaculaBackupFileDaemonPlugin
{
	/**
	 * Plugin parameter categories.
	 */
	private const PARAM_CAT_GENERAL = 'General';
	private const PARAM_CAT_COMMON_BACKUP_RESTORE_OPTIONS = 'Common backup and restore options';
	private const PARAM_CAT_NATIVE_BACKUP = 'Native backup';
	private const PARAM_CAT_LINE_PROTOCOL_EXPORT = 'Line Protocol offline export';
	private const PARAM_CAT_RESTORE = 'Restore';

	/**
	 * Native backup action and default job level.
	 */
	private const ACTION_NATIVE_FULL_INSTANCE = 'native-full-instance';
	private const ACTION_NATIVE_ALL_BUCKETS_ONE_ARCHIVE = 'native-all-buckets-one-archive';
	private const ACTION_NATIVE_SELECTED_BUCKETS = 'native-selected-buckets';
	private const ACTION_LINE_PROTOCOL_BUCKET = 'line-protocol-bucket';
	private const ACTION_LINE_PROTOCOL_ORGANIZATIONS = 'line-protocol-organizations';
	private const ACTION_LINE_PROTOCOL_USERS = 'line-protocol-users';
	private const ACTION_LINE_PROTOCOL_AUTHORIZATIONS = 'line-protocol-authorizations';

	private const DEFAULT_JOB_LEVEL = 'Full';
	private const DEFAULT_HOST = 'http://127.0.0.1:8086';

	/**
	 * Backup data archiver.
	 */
	private const BACKUP_DATA_ARCHIVER = 'tar';

	/**
	 * Get plugin name displayed in web interface.
	 *
	 * @return string plugin name
	 */
	public static function getName(): string
	{
		return 'InfluxDB 2.x database backup';
	}

	/**
	 * Get plugin version.
	 *
	 * @return string plugin version
	 */
	public static function getVersion(): string
	{
		return '1.0.0';
	}

	/**
	 * Get plugin type.
	 *
	 * @return string plugin type
	 */
	public static function getType(): string
	{
		return 'backup';
	}

	/**
	 * Get plugin configuration parameters.
	 *
	 * @return array plugin parameters
	 */
	public static function getParameters(): array
	{
		return [
			[
				'name' => 'instance-name',
				'type' => 'string',
				'default' => 'main',
				'label' => 'Instance name',
				'placeholder' => 'main',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'token',
				'type' => 'password',
				'default' => '',
				'label' => 'Influx Token',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'org',
				'type' => 'string',
				'default' => '',
				'label' => 'Organization',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'host',
				'type' => 'string',
				'default' => self::DEFAULT_HOST,
				'label' => 'Host',
				'placeholder' => 'http://127.0.0.1:8086',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'binary-path',
				'type' => 'string',
				'default' => '/usr/bin',
				'label' => 'InfluxDB binaries path (directory containing influx and influxd)',
				'placeholder' => '/usr/bin',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'engine-path',
				'type' => 'string',
				'default' => '/var/lib/influxdb/engine',
				'label' => 'Engine path',
				'placeholder' => '/var/lib/influxdb/engine',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'bolt-path',
				'type' => 'string',
				'default' => '/var/lib/influxdb/influxd.bolt',
				'label' => 'BoltDB path',
				'placeholder' => '/var/lib/influxdb/influxd.bolt',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'verify-tls',
				'type' => 'boolean',
				'default' => true,
				'label' => 'Verify TLS certificate',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'ca-cert-path',
				'type' => 'string',
				'default' => '',
				'label' => 'CA certificate path',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'native-backup-path',
				'type' => 'string',
				'default' => '',
				'label' => 'Common backup directory',
				'placeholder' => 'ex: /tmp/common-backup-dir',
				'category' => [self::PARAM_CAT_COMMON_BACKUP_RESTORE_OPTIONS]
			],
			[
				'name' => 'native-method',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Enable native InfluxDB backup',
				'category' => [self::PARAM_CAT_NATIVE_BACKUP]
			],
			[
				'name' => 'native-full-instance',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Full instance backup',
				'category' => [self::PARAM_CAT_NATIVE_BACKUP]
			],
			[
				'name' => 'native-all-buckets-one-archive',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Backup all buckets in one archive',
				'category' => [self::PARAM_CAT_NATIVE_BACKUP]
			],
			[
				'name' => 'native-all-buckets-separately',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Backup all buckets separately',
				'category' => [self::PARAM_CAT_NATIVE_BACKUP]
			],
			[
				'name' => 'native-buckets',
				'type' => 'string',
				'default' => '',
				'label' => 'Selected buckets to backup (comma separated)',
				'placeholder' => 'ex: bucket-a,bucket-b',
				'category' => [self::PARAM_CAT_NATIVE_BACKUP]
			],
			[
				'name' => 'line-protocol-method',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Enable Line Protocol offline export',
				'category' => [self::PARAM_CAT_LINE_PROTOCOL_EXPORT]
			],
			[
				'name' => 'line-protocol-all-buckets',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Export all buckets',
				'category' => [self::PARAM_CAT_LINE_PROTOCOL_EXPORT]
			],
			[
				'name' => 'line-protocol-compress',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Compress Line Protocol export with gzip',
				'category' => [self::PARAM_CAT_LINE_PROTOCOL_EXPORT]
			],
			[
				'name' => 'line-protocol-export-organizations',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Export organizations',
				'category' => [self::PARAM_CAT_LINE_PROTOCOL_EXPORT]
			],
			[
				'name' => 'line-protocol-export-users',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Export users',
				'category' => [self::PARAM_CAT_LINE_PROTOCOL_EXPORT]
			],
			[
				'name' => 'line-protocol-export-authorizations',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Export authorizations',
				'category' => [self::PARAM_CAT_LINE_PROTOCOL_EXPORT]
			],
			[
				'name' => 'new-bucket-name',
				'type' => 'string',
				'default' => '',
				'label' => 'New bucket name',
				'category' => [self::PARAM_CAT_RESTORE]
			],
			[
				'name' => 'new-org',
				'type' => 'string',
				'default' => '',
				'label' => 'New organization',
				'category' => [self::PARAM_CAT_RESTORE]
			]
		];
	}

	/**
	 * Initialize pseudo-constructor.
	 *
	 * @param array $args plugin options
	 */
	public function initialize(array $args): void
	{
	}

	/**
	 * Dispatch backup actions executed by the BPIPE reader.
	 *
	 * @param array $args backup command arguments
	 * @return bool true on success, false otherwise
	 */
	public function doBackup(array $args): bool
	{
		$result = false;
		$action = $args['backup-action'] ?? '';
		switch ($action) {
			case self::ACTION_NATIVE_FULL_INSTANCE: {
				$result = $this->doNativeFullInstanceBackup($args);
				break;
			}
			case self::ACTION_NATIVE_ALL_BUCKETS_ONE_ARCHIVE: {
				$result = $this->doNativeAllBucketsOneArchiveBackup($args);
				break;
			}
			case self::ACTION_NATIVE_SELECTED_BUCKETS: {
				$result = $this->doNativeSelectedBucketBackup($args);
				break;
			}
			case self::ACTION_LINE_PROTOCOL_BUCKET: {
				$result = $this->doLineProtocolBucketExport($args);
				break;
			}
			case self::ACTION_LINE_PROTOCOL_ORGANIZATIONS:
			case self::ACTION_LINE_PROTOCOL_USERS:
			case self::ACTION_LINE_PROTOCOL_AUTHORIZATIONS: {
				$result = $this->doLineProtocolSystemExport($args);
				break;
			}
			default: {
				Plugins::log(Plugins::LOG_ERROR, 'Unsupported InfluxDB backup action.');
				break;
			}
		}
		return $result;
	}

	/**
	 * Dispatch restore actions to the appropriate restore family.
	 *
	 * @param array $args restore command arguments
	 * @return bool true on success, false otherwise
	 */
	public function doRestore(array $args): bool
	{
		$result = false;
		$action = $args['restore-action'] ?? '';
		switch ($action) {
			case self::ACTION_NATIVE_FULL_INSTANCE:
			case self::ACTION_NATIVE_ALL_BUCKETS_ONE_ARCHIVE:
			case self::ACTION_NATIVE_SELECTED_BUCKETS: {
				$result = $this->doNativeRestore($args);
				break;
			}
			case self::ACTION_LINE_PROTOCOL_BUCKET: {
				$result = $this->doLineProtocolLocalFileRestore($args);
				break;
			}
			case self::ACTION_LINE_PROTOCOL_ORGANIZATIONS:
			case self::ACTION_LINE_PROTOCOL_USERS:
			case self::ACTION_LINE_PROTOCOL_AUTHORIZATIONS: {
				$result = $this->doLineProtocolSystemLocalFileRestore($args);
				break;
			}
			default: {
				Plugins::log(Plugins::LOG_ERROR, 'Unsupported InfluxDB restore action.');
				break;
			}
		}
		return $result;
	}

	/**
	 * Validate and dispatch Native restore requests.
	 *
	 * @param array $args Native restore command arguments
	 * @return bool true on success, false otherwise
	 */
	private function doNativeRestore(array $args): bool
	{
		$misc = $this->getModule('misc');
		// validate where parameter
		if (!key_exists('where', $args) || !$misc->isValidRestoreWhere($args['where']) || trim($args['where']) === '' || $args['where'] === '%w') {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Missing or invalid InfluxDB Native restore destination.'
			);
			return false;
		}

		// Validate parameter used with path. Do local file restore.
		if (key_exists('where', $args) && $args['where'] != '/') {
			return $this->doNativeLocalFileRestore($args);
		}

		$result = false;
		$action = $args['restore-action'] ?? '';
		switch ($action) {
			case self::ACTION_NATIVE_SELECTED_BUCKETS: {
				// Do selected bucket restore to InfluxDB server
				$result = $this->doNativeBucketRestore($args);
				break;
			}
			case self::ACTION_NATIVE_ALL_BUCKETS_ONE_ARCHIVE: {
				// Do all bucket restore to InfluxDB server
				$result = $this->doNativeAllBucketsOneArchiveRestore($args);
				break;
			}
			case self::ACTION_NATIVE_FULL_INSTANCE: {
				// Full instance restore is possible do to manually only after local file restore
				$emsg = 'Direct Full Instance restore is intentionally not supported. ';
				$emsg .= 'Full-instance archives can be restored to the local filesystem for manual disaster recovery.';
				Plugins::log(Plugins::LOG_ERROR, $emsg);
				break;
			}
			default: {
				Plugins::log(
					Plugins::LOG_ERROR,
					'Direct InfluxDB Native restore is not implemented for this action.'
				);
				break;
			}
		}
		return $result;
	}

	/**
	 * Extract a single-bucket archive from stdin and restore it to InfluxDB server.
	 * Working data remains for administrator-managed cleanup.
	 *
	 * @param array $args restore arguments with target connection values and source bucket ID
	 * @return bool true on successful extraction and Native restore
	 */
	private function doNativeBucketRestore(array $args): bool
	{
		// Check native method required parameters
		$is_valid = $this->checkNativeBucketArgs($args);
		if (!$is_valid) {
			return false;
		}

		// Check for new bucket name and new organization name parameters
		foreach (['new-bucket-name', 'new-org'] as $name) {
			if (!key_exists($name, $args)) {
				continue;
			}
			if (!is_string($args[$name])) {
				$is_valid = false;
				break;
			}
			$is_control_char = Miscellaneous::isASCIControlChar($args[$name]);
			if ($is_control_char) {
				$is_valid = false;
				break;
			}
		}

		// Invalid bucket name or oganization name
		if (!$is_valid) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Invalid InfluxDB Native restore target name.'
			);
			return false;
		}

		$native = new InfluxDBNative();

		// Prepare working directory for the single bucket restore
		$working_directory = $native->createWorkingDirectory($args['native-backup-path']);
		if ($working_directory === '') {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Unable to create InfluxDB Native restore working directory.'
			);
			return false;
		}

		// Create working directory bucket subdirectory in working directory
		$directory = $working_directory . DIRECTORY_SEPARATOR . 'native';
		if (!@mkdir($directory, 0700) || !is_dir($directory) || !is_writable($directory)) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Unable to create InfluxDB Native extraction directory.'
			);
			return false;
		}
		$quoted_directory = escapeshellarg($directory);

		// Prepare and run archiver command to extract bucket data
		$cmd = [
			self::BACKUP_DATA_ARCHIVER,
			'-C',
			$quoted_directory,
			'--no-same-owner',
			'--no-same-permissions',
			'-xf',
			'-'
		];
		$result = $this->execCommand($cmd);
		if ($result['exitcode'] !== 0) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Unable to extract InfluxDB Native restore archive.'
			);
			return false;
		}

		// Prepare the main restore action to InfluxDB server
		$influx_path = $native->getInfluxPath($args['binary-path'] ?? '');
		$verify_tls = key_exists('verify-tls', $args);
		$ca_cert_path = $args['ca-cert-path'] ?? '';
		$new_bucket = $args['new-bucket-name'] ?? '';
		$new_org = $args['new-org'] ?? '';
		$result = $native->restore(
			$influx_path,
			$args['host'],
			$args['org'],
			$args['token'],
			$directory,
			$verify_tls,
			$ca_cert_path,
			false,
			$args['bucket-id'],
			true,
			$new_bucket,
			$new_org
		);
		$success = ($result['error'] === 0);
		if (!$success) {
			Plugins::log(
				Plugins::LOG_ERROR,
				"InfluxDB Native bucket restore failed with exit code {$result['error']}."
			);
		}
		return $success;
	}

	/**
	 * Extract an archive from stdin and restore each contained Native bucket backup.
	 * Validate all bucket directories before restoring. Completed restores are not rolled back.
	 * Working data remains for administrator-managed cleanup.
	 *
	 * @param array $args restore arguments with target connection values
	 * @return bool true if extraction and every bucket restore succeed
	 */
	private function doNativeAllBucketsOneArchiveRestore(array $args): bool
	{
		// Check required method parameters
		$is_valid = $this->checkNativeAllBucketsOneArchiveArgs($args);
		if (!$is_valid) {
			return false;
		}

		// Check new organization name
		$new_org = $args['new-org'] ?? '';
		if (!is_string($new_org)) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Invalid InfluxDB Native restore target organization.'
			);
			return false;
		}
		$is_control_char = Miscellaneous::isASCIControlChar($new_org);
		if ($is_control_char) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Invalid InfluxDB Native restore target organization.'
			);
			return false;
		}

		$native = new InfluxDBNative();

		// Prepare working directory to restore data
		$working_directory = $native->createWorkingDirectory($args['native-backup-path']);
		if ($working_directory === '') {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Unable to create InfluxDB Native restore working directory.'
			);
			return false;
		}

		// Prepare subdirectory for restore in working directory
		$directory = $working_directory . DIRECTORY_SEPARATOR . 'native';
		if (!@mkdir($directory, 0700) || !is_dir($directory) || !is_writable($directory)) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Unable to create InfluxDB Native extraction directory.'
			);
			return false;
		}
		$quoted_directory = escapeshellarg($directory);

		// Main archiver command that extracts bucket data to working directory
		$cmd = [
			self::BACKUP_DATA_ARCHIVER,
			'-C',
			$quoted_directory,
			'--no-same-owner',
			'--no-same-permissions',
			'-xf',
			'-'
		];
		$result = $this->execCommand($cmd);
		if ($result['exitcode'] !== 0) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Unable to extract InfluxDB Native restore archive.'
			);
			return false;
		}

		// List the working directory content for bucket IDs and its subdirectories.
		$entries = @scandir($directory);
		if ($entries === false) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Unable to read InfluxDB Native restore bucket directories.'
			);
			return false;
		}

		// Build map [bucket ID => bucket path, ...]
		$bucket_directories = [];
		foreach ($entries as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}
			if (preg_match('/\A[0-9a-fA-F]{16}\z/D', $entry) !== 1) {
				$is_valid = false;
				break;
			}
			$bucket_directory = $directory . DIRECTORY_SEPARATOR . $entry;
			if (is_link($bucket_directory) || !is_dir($bucket_directory) || !is_readable($bucket_directory)) {
				$is_valid = false;
				break;
			}
			$bucket_id = strtolower($entry);
			if (key_exists($bucket_id, $bucket_directories)) {
				$is_valid = false;
				break;
			}
			$bucket_directories[$bucket_id] = $bucket_directory;
		}
		if (!$is_valid) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Invalid InfluxDB Native restore archive bucket directory structure.'
			);
			return false;
		}
		if (count($bucket_directories) === 0) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'No bucket backup directories found in InfluxDB Native restore archive.'
			);
			return false;
		}

		// Prepare bucket list to restore to InfluxDB server
		$bucket_ids = array_keys($bucket_directories);
		sort($bucket_ids, SORT_STRING);
		$influx_path = $native->getInfluxPath($args['binary-path'] ?? '');
		$host = $args['host'] ?? self::DEFAULT_HOST;
		$verify_tls = key_exists('verify-tls', $args);
		$ca_cert_path = $args['ca-cert-path'] ?? '';

		// Do restore buckets in loop to InfluxDB server
		$success = true;
		foreach ($bucket_ids as $bucket_id) {
			$bucket_directory = $bucket_directories[$bucket_id];
			$result = $native->restore(
				$influx_path,
				$host,
				$args['org'],
				$args['token'],
				$bucket_directory,
				$verify_tls,
				$ca_cert_path,
				false,
				$bucket_id,
				true,
				'',
				$new_org
			);
			if ($result['error'] !== 0) {
				Plugins::log(
					Plugins::LOG_ERROR,
					"InfluxDB Native bucket '{$bucket_id}' restore failed with exit code {$result['error']}."
				);
				$success = false;
				break;
			}
		}
		return $success;
	}

	/**
	 * Write the incoming Native archive to its original virtual path to 'where' directory.
	 * Stream stdin through tee without buffering or extracting the archive.
	 *
	 * @param array $args restore command arguments including original backup identity
	 * @return bool true on successful archive write, false otherwise
	 */
	private function doNativeLocalFileRestore(array $args): bool
	{
		// Check required local file restore parameters
		$is_valid = true;
		$required = ['plugin-config', 'job-starttime', 'job-id', 'job-level', 'org', 'instance-name'];
		foreach ($required as $name) {
			if (!key_exists($name, $args)) {
				Plugins::log(
					Plugins::LOG_ERROR,
					"Missing InfluxDB Native local restore parameter '{$name}'."
				);
				$is_valid = false;
				break;
			}
		}
		if (!$is_valid) {
			return false;
		}

		// Check required job parameters
		$is_valid = $this->checkJobArgs($args);
		if (!$is_valid) {
			return false;
		}

		// Validate organization and instance name
		foreach (['org', 'instance-name'] as $name) {
			if (!is_string($args[$name])) {
				Plugins::log(
					Plugins::LOG_ERROR,
					"Invalid InfluxDB Native local restore parameter '{$name}'."
				);
				$is_valid = false;
				break;
			}
			$is_control_char = Miscellaneous::isASCIControlChar($args[$name]);
			if ($is_control_char) {
				Plugins::log(
					Plugins::LOG_ERROR,
					"Invalid InfluxDB Native local restore parameter '{$name}'."
				);
				$is_valid = false;
				break;
			}
		}
		if (!$is_valid) {
			return false;
		}

		// Set local file restore path
		$restore_path = '';
		$action = $args['restore-action'] ?? '';
		switch ($action) {
			case self::ACTION_NATIVE_FULL_INSTANCE: {
				// Native full instance local file restore
				$restore_path = $this->getNativeFullInstanceBackupPath($args);
				break;
			}
			case self::ACTION_NATIVE_ALL_BUCKETS_ONE_ARCHIVE: {
				// Native all buckets local file restore
				$restore_path = $this->getNativeAllBucketsOneArchiveBackupPath($args);
				break;
			}
			case self::ACTION_NATIVE_SELECTED_BUCKETS: {
				// Native selected bucket local file restore
				if (!isset($args['restore-item']) || !is_string($args['restore-item']) || $args['restore-item'] === '') {
					Plugins::log(
						Plugins::LOG_ERROR,
						'Missing or invalid InfluxDB Native restore bucket name.'
					);
					break;
				}
				$is_control_char = Miscellaneous::isASCIControlChar($args['restore-item']);
				if ($is_control_char) {
					Plugins::log(
						Plugins::LOG_ERROR,
						'Invalid InfluxDB Native restore bucket name.'
					);
					break;
				}
				$restore_path = $this->getNativeSelectedBucketBackupPath($args, $args['restore-item']);
				break;
			}
			default: {
				Plugins::log(
					Plugins::LOG_ERROR,
					'Unsupported InfluxDB Native restore action.'
				);
				break;
			}
		}
		if ($restore_path === '') {
			return false;
		}
		$filename = basename($restore_path);
		$restore_dir = implode(
			DIRECTORY_SEPARATOR,
			[
				$args['where'],
				dirname($restore_path)
			]
		);
		if (!file_exists($restore_dir)) {
			if (!@mkdir($restore_dir, 0750, true)) {
				Plugins::log(
					Plugins::LOG_ERROR,
					'Unable to create InfluxDB Native local restore directory.'
				);
				return false;
			}
		}
		if (!is_dir($restore_dir) || !is_writable($restore_dir)) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'InfluxDB Native local restore directory is not writable.'
			);
			return false;
		}
		$restore_path = implode(
			DIRECTORY_SEPARATOR,
			[
				$restore_dir,
				$filename
			]
		);
		$quoted_path = escapeshellarg($restore_path);

		// Save restored path to local file
		$cmd = ['tee', '--', $quoted_path, '>/dev/null'];
		$result = $this->execCommand($cmd);
		$success = ($result['exitcode'] === 0);
		if (!$success) {
			Plugins::log(
				Plugins::LOG_ERROR,
				"InfluxDB Native local archive restore failed with exit code {$result['exitcode']}."
			);
		}
		return $success;
	}

	/**
	 * Get all plugin commands using independent backup method dispatch.
	 *
	 * @param array $args plugin command parameters
	 * @return array plugin commands
	 */
	public function getPluginCommands(array $args): array
	{
		$is_valid = $this->checkRequiredArgs($args);
		if (!$is_valid) {
			return [];
		}
		$cmds = [];
		if (key_exists('native-method', $args)) {
			$native_cmds = $this->getNativePluginCommands($args);
			$cmds = array_merge($cmds, $native_cmds);
		}
		if (key_exists('line-protocol-method', $args)) {
			$line_protocol_cmds = $this->getLineProtocolPluginCommands($args);
			$cmds = array_merge($cmds, $line_protocol_cmds);
		}
		return $cmds;
	}

	/**
	 * Check required plugin command options.
	 *
	 * @param array $args plugin options
	 * @return bool true on validation success, false otherwise
	 */
	private function checkRequiredArgs(array $args): bool
	{
		$is_valid = true;
		$required = ['plugin-name', 'plugin-config', 'job-id'];
		foreach ($required as $name) {
			if (!key_exists($name, $args)) {
				Plugins::log(
					Plugins::LOG_ERROR,
					"Missing required parameter '{$name}'."
				);
				$is_valid = false;
				break;
			}
		}
		return $is_valid;
	}

	/**
	 * Get Native backup plugin commands for the Bacula job level.
	 *
	 * @param array $args plugin options and job parameters
	 * @return array BPIPE plugin commands
	 */
	private function getNativePluginCommands(array $args): array
	{
		$cmds = [];
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		switch ($level) {
			case 'Full': {
				$cmds = $this->getFullNativeBackupPluginCommands($args);
				break;
			}
			case 'Incremental': {
				$cmds = $this->getIncrementalNativeBackupPluginCommands($args);
				break;
			}
			case 'Differential': {
				$cmds = $this->getDifferentialNativeBackupPluginCommands($args);
				break;
			}
		}
		return $cmds;
	}

	/**
	 * Get Full Native backup plugin commands using independent backup options.
	 *
	 * @param array $args plugin options and job parameters
	 * @return array BPIPE plugin commands
	 */
	private function getFullNativeBackupPluginCommands(array $args): array
	{
		$cmds = [];
		if (key_exists('native-full-instance', $args)) {
			$commands = $this->getNativeFullInstanceBackupPluginCommands($args);
			$cmds = array_merge($cmds, $commands);
		}
		if (key_exists('native-all-buckets-one-archive', $args)) {
			$commands = $this->getNativeAllBucketsOneArchiveBackupPluginCommands($args);
			$cmds = array_merge($cmds, $commands);
		}
		if (key_exists('native-buckets', $args) && is_string($args['native-buckets']) && trim($args['native-buckets']) !== '') {
			$commands = $this->getNativeSelectedBucketsBackupPluginCommands($args);
			$cmds = array_merge($cmds, $commands);
		}
		if (key_exists('native-all-buckets-separately', $args)) {
			$commands = $this->getNativeAllBucketsSeparatelyBackupPluginCommands($args);
			$cmds = array_merge($cmds, $commands);
		}
		return $cmds;
	}

	/**
	 * Get Incremental Native backup plugin commands.
	 *
	 * @param array $args plugin options and job parameters
	 * @return array BPIPE plugin commands
	 */
	private function getIncrementalNativeBackupPluginCommands(array $args): array
	{
		// Native Incremental backup is not supported.
		return [];
	}

	/**
	 * Get Differential Native backup plugin commands.
	 *
	 * @param array $args plugin options and job parameters
	 * @return array BPIPE plugin commands
	 */
	private function getDifferentialNativeBackupPluginCommands(array $args): array
	{
		// Native Differential backup is not supported.
		return [];
	}

	/**
	 * Get the Native Full Instance descriptor without performing a backup.
	 *
	 * @param array $args plugin options and job parameters
	 * @return array one BPIPE command, or empty array for invalid configuration
	 */
	private function getNativeFullInstanceBackupPluginCommands(array $args): array
	{
		$native_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_COMMON_BACKUP_RESTORE_OPTIONS,
			self::PARAM_CAT_NATIVE_BACKUP,
			self::PARAM_CAT_RESTORE
		], false);
		$native_args['plugin-config'] = $args['plugin-config'];
		$native_args['job-id'] = $args['job-id'];
		$native_args['job-starttime'] = $args['job-starttime'] ?? '';
		$native_args['job-level'] = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		$is_valid = $this->checkNativeFullInstanceArgs($native_args);
		if (!$is_valid) {
			return [];
		}
		$is_valid = $this->checkJobArgs($native_args);
		if (!$is_valid) {
			return [];
		}
		$cmds = [];
		$command = $this->getSinglePluginCommand(
			$native_args,
			self::ACTION_NATIVE_FULL_INSTANCE
		);
		if ($command !== '') {
			$cmds[] = $command;
		}
		return $cmds;
	}

	/**
	 * Get one Native All Buckets One Archive descriptor without discovering or backing up buckets.
	 *
	 * @param array $args plugin options and job parameters
	 * @return array one BPIPE command, or empty array for invalid configuration
	 */
	private function getNativeAllBucketsOneArchiveBackupPluginCommands(array $args): array
	{
		$native_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_COMMON_BACKUP_RESTORE_OPTIONS,
			self::PARAM_CAT_NATIVE_BACKUP,
			self::PARAM_CAT_RESTORE
		], false);
		$native_args['plugin-config'] = $args['plugin-config'];
		$native_args['job-id'] = $args['job-id'];
		$native_args['job-starttime'] = $args['job-starttime'] ?? '';
		$native_args['job-level'] = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		$is_valid = $this->checkNativeAllBucketsOneArchiveArgs($native_args);
		if (!$is_valid) {
			return [];
		}
		$is_valid = $this->checkJobArgs($native_args);
		if (!$is_valid) {
			return [];
		}
		$cmds = [];
		$command = $this->getSinglePluginCommand(
			$native_args,
			self::ACTION_NATIVE_ALL_BUCKETS_ONE_ARCHIVE
		);
		if ($command !== '') {
			$cmds[] = $command;
		}
		return $cmds;
	}

	/**
	 * Resolve all selected names before building any selected-bucket descriptors.
	 * Discovery is read-only; BPIPE readers execute the backups.
	 *
	 * @param array $args plugin options and job parameters
	 * @throws BPluginRuntimeException bucket does not exist exception
	 * @return array ordered BPIPE commands, or empty array if resolution or descriptor generation fails
	 */
	private function getNativeSelectedBucketsBackupPluginCommands(array $args): array
	{
		$native_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_COMMON_BACKUP_RESTORE_OPTIONS,
			self::PARAM_CAT_NATIVE_BACKUP,
			self::PARAM_CAT_RESTORE
		], false);
		$native_args['plugin-config'] = $args['plugin-config'];
		$native_args['job-id'] = $args['job-id'];
		$native_args['job-starttime'] = $args['job-starttime'] ?? '';
		$native_args['job-level'] = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		$is_valid = $this->checkNativeSelectedBucketsArgs($native_args);
		if (!$is_valid) {
			return [];
		}
		$is_valid = $this->checkJobArgs($native_args);
		if (!$is_valid) {
			return [];
		}
		$names = $this->getNativeSelectedBucketNames($native_args['native-buckets']);
		if (count($names) === 0) {
			return [];
		}
		$native = new InfluxDBNative();

		// List all buckets
		$host = $native_args['host'] ?? self::DEFAULT_HOST;
		$influx_path = $native->getInfluxPath($native_args['binary-path'] ?? '');
		$verify_tls = key_exists('verify-tls', $native_args);
		$ca_cert_path = $native_args['ca-cert-path'] ?? '';
		$result = $native->listBuckets(
			$influx_path,
			$host,
			$native_args['org'],
			$native_args['token'],
			$verify_tls,
			$ca_cert_path
		);
		if ($result['error'] !== 0) {
			Plugins::log(
				Plugins::LOG_ERROR,
				"InfluxDB selected bucket discovery failed with exit code {$result['error']}."
			);
			return [];
		}

		// Get selected bucket identifiers
		$ids_by_name = [];
		foreach ($result['buckets'] as $bucket) {
			$ids_by_name[$bucket['name']][] = $bucket['id'];
		}

		// Prepare selected bucket list
		$selected = [];
		foreach ($names as $index => $name) {
			$position = $index + 1;
			if (!key_exists($name, $ids_by_name)) {
				throw new BPluginRuntimeException(
					"Selected InfluxDB bucket at position {$position} was not found in the configured organization.",
					1
				);
			}
			if (count($ids_by_name[$name]) !== 1) {
				Plugins::log(
					Plugins::LOG_ERROR,
					"Ambiguous selected InfluxDB bucket name at position {$position}."
				);
				$is_valid = false;
				break;
			}
			$selected[] = ['id' => $ids_by_name[$name][0], 'name' => $name];
		}
		if (!$is_valid) {
			return [];
		}

		// Get single bucket commands, one per bucket
		$cmds = [];
		foreach ($selected as $bucket) {
			$bucket_args = $native_args;
			$bucket_args['bucket-id'] = $bucket['id'];
			$command = $this->getSinglePluginCommand(
				$bucket_args,
				self::ACTION_NATIVE_SELECTED_BUCKETS,
				$bucket['name']
			);
			if ($command === '') {
				Plugins::log(
					Plugins::LOG_ERROR,
					'Unable to create Native selected bucket BPIPE descriptor.'
				);
				$cmds = [];
				break;
			}
			$cmds[] = $command;
		}
		return $cmds;
	}

	/**
	 * Discover all organization buckets and build independent single-bucket descriptors.
	 *
	 * @param array $args plugin options and job parameters
	 * @return array ordered BPIPE commands, or empty array on failure
	 */
	private function getNativeAllBucketsSeparatelyBackupPluginCommands(array $args): array
	{
		$native_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_COMMON_BACKUP_RESTORE_OPTIONS,
			self::PARAM_CAT_NATIVE_BACKUP,
			self::PARAM_CAT_RESTORE
		], false);

		// Prepare command parameters
		$native_args['plugin-config'] = $args['plugin-config'];
		$native_args['job-id'] = $args['job-id'];
		$native_args['job-starttime'] = $args['job-starttime'] ?? '';
		$native_args['job-level'] = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		$is_valid = $this->checkNativeAllBucketsOneArchiveArgs($native_args);
		if (!$is_valid) {
			return [];
		}
		if (!key_exists('org', $native_args) || $native_args['org'] === '') {
			Plugins::log(Plugins::LOG_ERROR, 'Native All Buckets Separately backup requires an organization.');
			return [];
		}

		// Check required job parameters
		$is_valid = $this->checkJobArgs($native_args);
		if (!$is_valid) {
			return [];
		}

		$native = new InfluxDBNative();

		// List all buckets
		$host = $native_args['host'] ?? self::DEFAULT_HOST;
		$influx_path = $native->getInfluxPath($native_args['binary-path'] ?? '');
		$verify_tls = key_exists('verify-tls', $native_args);
		$ca_cert_path = $native_args['ca-cert-path'] ?? '';
		$result = $native->listBuckets(
			$influx_path,
			$host,
			$native_args['org'],
			$native_args['token'],
			$verify_tls,
			$ca_cert_path
		);
		if ($result['error'] !== 0) {
			Plugins::log(
				Plugins::LOG_ERROR,
				"InfluxDB All Buckets Separately discovery failed with exit code {$result['error']}."
			);
			return [];
		}
		$buckets = $result['buckets'];
		if (count($buckets) === 0) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'No InfluxDB buckets were found for Native All Buckets Separately backup.'
			);
			return [];
		}

		// Set bucket paths
		$paths = [];
		foreach ($buckets as $bucket) {
			$path = $this->getNativeSelectedBucketBackupPath(
				$native_args,
				$bucket['name']
			);
			if (key_exists($path, $paths)) {
				Plugins::log(
					Plugins::LOG_ERROR,
					'Conflicting bucket names in Native All Buckets Separately discovery.'
				);
				$is_valid = false;
				break;
			}
			$paths[$path] = true;
		}
		if (!$is_valid) {
			return [];
		}

		// Get all bucket commands, one per bucket
		$cmds = [];
		foreach ($buckets as $bucket) {
			$bucket_args = $native_args;
			$bucket_args['bucket-id'] = $bucket['id'];
			$command = $this->getSinglePluginCommand(
				$bucket_args,
				self::ACTION_NATIVE_SELECTED_BUCKETS,
				$bucket['name']
			);
			if ($command === '') {
				Plugins::log(
					Plugins::LOG_ERROR,
					'Unable to create Native All Buckets Separately BPIPE descriptor.'
				);
				$cmds = [];
				break;
			}
			$cmds[] = $command;
		}
		return $cmds;
	}

	/**
	 * Parse comma-separated names, rejecting empty entries and retaining first occurrences.
	 *
	 * @param string $selection configured bucket names
	 * @return array unique trimmed names in selection order, or empty array on failure
	 */
	private function getNativeSelectedBucketNames(string $selection): array
	{
		$entries = explode(',', $selection);
		$names = [];
		$seen = [];
		foreach ($entries as $index => $entry) {
			$name = trim($entry);
			$is_control_char = Miscellaneous::isASCIControlChar($name);
			if ($name === '' || $is_control_char) {
				$position = $index + 1;
				Plugins::log(
					Plugins::LOG_ERROR,
					"Empty or invalid Native selected bucket entry at position {$position}."
				);
				$names = [];
				break;
			}
			if (!key_exists($name, $seen)) {
				$seen[$name] = true;
				$names[] = $name;
			}
		}
		return $names;
	}

	/**
	 * Get a single BPIPE plugin command using action-specific command and path builders.
	 *
	 * @param array $args plugin options and job parameters
	 * @param string $action backup action
	 * @param string $item backup item
	 * @return string BPIPE command, or empty string for an unsupported action
	 */
	private function getSinglePluginCommand(array $args, string $action, string $item = ''): string
	{
		$bpipe = $this->getModule('bpipe');
		$backup_path = $backup_cmd = $restore_cmd = '';
		$is_valid = true;
		switch ($action) {
			case self::ACTION_NATIVE_FULL_INSTANCE: {
				$backup_cmd = $this->getBackupNativeFullInstanceCommand($args);
				$restore_cmd = $this->getRestoreNativeFullInstanceCommand($args);
				$backup_path = $this->getNativeFullInstanceBackupPath($args);
				break;
			}
			case self::ACTION_NATIVE_ALL_BUCKETS_ONE_ARCHIVE: {
				$backup_cmd = $this->getBackupNativeAllBucketsOneArchiveCommand($args);
				$restore_cmd = $this->getRestoreNativeAllBucketsOneArchiveCommand($args);
				$backup_path = $this->getNativeAllBucketsOneArchiveBackupPath($args);
				break;
			}
			case self::ACTION_NATIVE_SELECTED_BUCKETS: {
				$backup_cmd = $this->getBackupNativeSelectedBucketCommand($args);
				$restore_cmd = $this->getRestoreNativeSelectedBucketCommand($args, $item);
				$backup_path = $this->getNativeSelectedBucketBackupPath($args, $item);
				break;
			}
			case self::ACTION_LINE_PROTOCOL_BUCKET: {
				$backup_cmd = $this->getBackupLineProtocolBucketCommand($args);
				$restore_cmd = $this->getRestoreLineProtocolBucketCommand($args);
				$backup_path = $this->getLineProtocolBucketExportPath($args);
				break;
			}
			case self::ACTION_LINE_PROTOCOL_ORGANIZATIONS:
			case self::ACTION_LINE_PROTOCOL_USERS:
			case self::ACTION_LINE_PROTOCOL_AUTHORIZATIONS: {
				$backup_cmd = $this->getBackupLineProtocolSystemCommand($args, $action);
				$restore_cmd = $this->getRestoreLineProtocolSystemCommand($args, $action);
				$backup_path = $this->getLineProtocolSystemExportPath($args, $action);
				break;
			}
			default: {
				$is_valid = false;
				break;
			}
		}
		if (!$is_valid) {
			return '';
		}
		return $bpipe->getPluginCommand(
			$backup_path,
			$backup_cmd,
			$restore_cmd
		);
	}

	/**
	 * Get the Native Full Instance backup reader with explicit execution parameters.
	 *
	 * @param array $args locally filtered plugin options
	 * @return string backup command
	 */
	private function getBackupNativeFullInstanceCommand(array $args): string
	{
		$backup_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_COMMON_BACKUP_RESTORE_OPTIONS,
			self::PARAM_CAT_NATIVE_BACKUP
		], false);
		$backup_args['backup-action'] = self::ACTION_NATIVE_FULL_INSTANCE;
		unset($backup_args['bolt-path']);
		$backup_args['job-level'] = $args['job-level'];
		$cmd = $this->getPluginCommand('command/backup', $backup_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get the Native Full Instance writer with original backup identity and Where.
	 * Organization and instance retain the original local archive namespace.
	 *
	 * @param array $args locally filtered plugin options and job parameters
	 * @return string restore command
	 */
	private function getRestoreNativeFullInstanceCommand(array $args): string
	{
		$restore_args = $this->filterParametersByCategory($args, []);
		$restore_args['plugin-config'] = $args['plugin-config'];
		$restore_args['job-starttime'] = $args['job-starttime'];
		$restore_args['job-id'] = $args['job-id'];
		$restore_args['job-level'] = $args['job-level'];
		$restore_args['restore-action'] = self::ACTION_NATIVE_FULL_INSTANCE;
		$restore_args['org'] = $args['org'];
		$restore_args['where'] = '%w';
		$cmd = $this->getPluginCommand('command/restore', $restore_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get the Native All Buckets One Archive backup reader with explicit execution parameters.
	 *
	 * @param array $args locally filtered plugin options
	 * @return string backup command
	 */
	private function getBackupNativeAllBucketsOneArchiveCommand(array $args): string
	{
		$backup_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_COMMON_BACKUP_RESTORE_OPTIONS,
			self::PARAM_CAT_NATIVE_BACKUP
		], false);
		$backup_args['backup-action'] = self::ACTION_NATIVE_ALL_BUCKETS_ONE_ARCHIVE;
		unset($backup_args['bolt-path']);
		$backup_args['job-level'] = $args['job-level'];
		$cmd = $this->getPluginCommand('command/backup', $backup_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get the Native All Buckets One Archive writer with original backup identity and Where.
	 * Organization and instance retain the original local archive namespace.
	 *
	 * @param array $args locally filtered plugin options and job parameters
	 * @return string restore command
	 */
	private function getRestoreNativeAllBucketsOneArchiveCommand(array $args): string
	{
		$restore_args = $this->filterParametersByCategory($args, []);
		$restore_args['plugin-config'] = $args['plugin-config'];
		$restore_args['job-starttime'] = $args['job-starttime'];
		$restore_args['job-id'] = $args['job-id'];
		$restore_args['job-level'] = $args['job-level'];
		$restore_args['restore-action'] = self::ACTION_NATIVE_ALL_BUCKETS_ONE_ARCHIVE;
		$restore_args['org'] = $args['org'];
		$restore_args['where'] = '%w';
		$cmd = $this->getPluginCommand('command/restore', $restore_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get a selected bucket reader with an explicit whitelist and its resolved ID.
	 *
	 * @param array $args locally filtered plugin options and resolved bucket ID
	 * @return string backup command
	 */
	private function getBackupNativeSelectedBucketCommand(array $args): string
	{
		$backup_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_COMMON_BACKUP_RESTORE_OPTIONS,
			self::PARAM_CAT_NATIVE_BACKUP
		], false);
		$backup_args['backup-action'] = self::ACTION_NATIVE_SELECTED_BUCKETS;
		unset($backup_args['bolt-path']);
		$backup_args['bucket-id'] = $args['bucket-id'];
		$cmd = $this->getPluginCommand('command/backup', $backup_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get an individual bucket writer with original backup identity, bucket name and Where.
	 * Organization and instance retain the original local archive namespace.
	 *
	 * @param array $args locally filtered plugin options and resolved bucket ID
	 * @param string $bucket_name original bucket name used in the virtual path
	 * @return string restore command
	 */
	private function getRestoreNativeSelectedBucketCommand(array $args, string $bucket_name): string
	{
		$restore_args = $this->filterParametersByCategory($args, []);
		$restore_args['plugin-config'] = $args['plugin-config'];
		$restore_args['job-starttime'] = $args['job-starttime'];
		$restore_args['job-id'] = $args['job-id'];
		$restore_args['job-level'] = $args['job-level'];
		$restore_args['restore-action'] = self::ACTION_NATIVE_SELECTED_BUCKETS;
		$restore_args['restore-item'] = $bucket_name;
		$restore_args['org'] = $args['org'];
		$restore_args['bucket-id'] = $args['bucket-id'];
		$restore_args['where'] = '%w';
		$cmd = $this->getPluginCommand('command/restore', $restore_args);
		return implode(' ', $cmd);
	}

	/**
	 * Execute a Native Full Instance backup and stream its archive to BPIPE.
	 * Working directories and backup files remain for administrator-managed cleanup.
	 *
	 * @param array $args backup reader arguments
	 * @return bool true on backup and archive success, false otherwise
	 */
	private function doNativeFullInstanceBackup(array $args): bool
	{
		// Check native method parameters
		$is_valid = $this->checkNativeFullInstanceArgs($args);
		if (!$is_valid) {
			return false;
		}

		$native = new InfluxDBNative();

		// Prepare working directory
		$directory = $native->createWorkingDirectory($args['native-backup-path']);
		if ($directory === '') {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Unable to create InfluxDB Native working directory.'
			);
			return false;
		}

		// Backup full instance data
		$influx_path = $native->getInfluxPath($args['binary-path'] ?? '');
		$organization = $args['org'] ?? '';
		$verify_tls = key_exists('verify-tls', $args);
		$ca_cert_path = $args['ca-cert-path'] ?? '';
		$result = $native->backup(
			$influx_path,
			$args['host'],
			$organization,
			$args['token'],
			$directory,
			$verify_tls,
			$ca_cert_path,
			'',
			false
		);
		if ($result['error'] !== 0) {
			Plugins::log(Plugins::LOG_ERROR, "InfluxDB Native backup failed with exit code {$result['error']}.");
			return false;
		}

		// Stream full instance data to Bacula
		return $this->streamNativeBackupArchive($directory);
	}

	/**
	 * Perform Native All Buckets One Archive backup by ID and stream after all succeed.
	 * Native manifests retain bucket identities. No additional metadata is required.
	 *
	 * @param array $args backup reader arguments
	 * @return bool true on complete backup and archive success, false otherwise
	 */
	private function doNativeAllBucketsOneArchiveBackup(array $args): bool
	{
		// Check native method parameters
		$is_valid = $this->checkNativeAllBucketsOneArchiveArgs($args);
		if (!$is_valid) {
			return false;
		}

		$native = new InfluxDBNative();

		// Prepare backup working directory
		$directory = $native->createWorkingDirectory($args['native-backup-path']);
		if ($directory === '') {
			Plugins::log(Plugins::LOG_ERROR, 'Unable to create InfluxDB Native All Buckets One Archive working directory.');
			return false;
		}
		$influx_path = $native->getInfluxPath($args['binary-path'] ?? '');
		$organization = $args['org'];
		$verify_tls = key_exists('verify-tls', $args);
		$ca_cert_path = $args['ca-cert-path'] ?? '';

		// List all buckets
		$result = $native->listBuckets(
			$influx_path,
			$args['host'],
			$organization,
			$args['token'],
			$verify_tls,
			$ca_cert_path
		);
		if ($result['error'] !== 0) {
			Plugins::log(
				Plugins::LOG_ERROR,
				"InfluxDB bucket discovery failed with exit code {$result['error']}."
			);
			return false;
		}
		$buckets = $result['buckets'];
		if (count($buckets) === 0) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'No InfluxDB buckets were found for Native All Buckets One Archive backup.'
			);
			return false;
		}

		// Do backup every listed bucket to a separate file
		$status = true;
		foreach ($buckets as $bucket) {
			// listBuckets validates unique hexadecimal IDs. Names never become paths.
			$bucket_directory = $directory . DIRECTORY_SEPARATOR . $bucket['id'];
			if (!@mkdir($bucket_directory, 0700)) {
				Plugins::log(
					Plugins::LOG_ERROR,
					"Unable to create Native backup directory for InfluxDB bucket {$bucket['id']}."
				);
				$status = false;
				break;
			}
			Plugins::log(
				Plugins::LOG_INFO,
				"Prepare bucket {$bucket['name']} for backup."
			);
			$result = $native->backup(
				$influx_path,
				$args['host'],
				$organization,
				$args['token'],
				$bucket_directory,
				$verify_tls,
				$ca_cert_path,
				$bucket['id'],
				true
			);
			if ($result['error'] !== 0) {
				Plugins::log(
					Plugins::LOG_ERROR,
					"InfluxDB Native backup failed for bucket {$bucket['id']} with exit code {$result['error']}."
				);
				$status = false;
				break;
			}
		}
		if ($status) {
			// Everything is fine. Stream data to bacula
			$status = $this->streamNativeBackupArchive($directory);
		}
		return $status;
	}

	/**
	 * Back up one resolved bucket without repeating discovery and stream its Native archive.
	 *
	 * @param array $args backup reader arguments
	 * @return bool true on backup and archive success, false otherwise
	 */
	private function doNativeSelectedBucketBackup(array $args): bool
	{
		// Check native method parameters
		$is_valid = $this->checkNativeBucketArgs($args);
		if (!$is_valid) {
			return false;
		}

		$native = new InfluxDBNative();

		// Prepare working directory
		$working_directory = $native->createWorkingDirectory($args['native-backup-path']);
		if ($working_directory === '') {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Unable to create InfluxDB Native selected bucket working directory.'
			);
			return false;
		}

		// Prepare bucket subdirectory in working directory
		$bucket_id = strtolower($args['bucket-id']);
		$directory = $working_directory . DIRECTORY_SEPARATOR . $bucket_id;
		if (!@mkdir($directory, 0700)) {
			Plugins::log(
				Plugins::LOG_ERROR,
				"Unable to create Native backup directory for selected InfluxDB bucket {$bucket_id}."
			);
			return false;
		}

		Plugins::log(
			Plugins::LOG_INFO,
			"Native backup selected bucket {$bucket_id}."
		);

		// Do native selected bucket backup
		$influx_path = $native->getInfluxPath($args['binary-path'] ?? '');
		$verify_tls = key_exists('verify-tls', $args);
		$ca_cert_path = $args['ca-cert-path'] ?? '';
		$result = $native->backup(
			$influx_path,
			$args['host'],
			$args['org'],
			$args['token'],
			$directory,
			$verify_tls,
			$ca_cert_path,
			$bucket_id,
			true
		);
		if ($result['error'] !== 0) {
			Plugins::log(
				Plugins::LOG_ERROR,
				"InfluxDB Native backup failed for selected bucket {$bucket_id} with exit code {$result['error']}."
			);
			return false;
		}

		// Stream selected bucket backup to Bacula
		$status = $this->streamNativeBackupArchive($directory);
		if (!$status) {
			Plugins::log(
				Plugins::LOG_ERROR,
				"Unable to stream Native backup archive for selected InfluxDB bucket {$bucket_id}."
			);
		}
		return $status;
	}

	/**
	 * Check selected-bucket configuration before parsing names or contacting InfluxDB.
	 *
	 * @param array $args locally filtered plugin options
	 * @return bool true if the connection, organization and selection are present
	 */
	private function checkNativeSelectedBucketsArgs(array $args): bool
	{
		$is_valid = $this->checkNativeFullInstanceArgs($args);
		if ($is_valid && (!key_exists('org', $args) || $args['org'] === '')) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Native selected buckets backup requires an organization.'
			);
			$is_valid = false;
		}
		if ($is_valid && (!isset($args['native-buckets']) || !is_string($args['native-buckets']) || trim($args['native-buckets']) === '')) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Native selected buckets backup requires a non-empty comma-separated selection.'
			);
			$is_valid = false;
		}
		return $is_valid;
	}

	/**
	 * Check Native single-bucket backup and restore arguments without performing discovery.
	 *
	 * @param array $args single-bucket backup or restore arguments
	 * @return bool true if the connection, organization and bucket ID are valid
	 */
	private function checkNativeBucketArgs(array $args): bool
	{
		$is_valid = $this->checkNativeFullInstanceArgs($args);
		if ($is_valid && (!key_exists('org', $args) || $args['org'] === '')) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Native selected bucket backup requires an organization.'
			);
			$is_valid = false;
		}
		if ($is_valid && (!isset($args['bucket-id']) || !is_string($args['bucket-id']) || preg_match('/\A[0-9a-fA-F]{16}\z/D', $args['bucket-id']) !== 1)) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Missing or invalid InfluxDB Native selected bucket ID.'
			);
			$is_valid = false;
		}
		return $is_valid;
	}

	/**
	 * Check Native All Buckets One Archive arguments and require an explicit organization scope.
	 *
	 * @param array $args backup configuration or reader arguments
	 * @return bool true if All Buckets One Archive configuration is valid
	 */
	private function checkNativeAllBucketsOneArchiveArgs(array $args): bool
	{
		$is_valid = $this->checkNativeFullInstanceArgs($args);
		if ($is_valid && (!key_exists('org', $args) || $args['org'] === '')) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Native All Buckets One Archive requires an organization.'
			);
			$is_valid = false;
		}
		return $is_valid;
	}

	/**
	 * Check Native backup arguments without probing runtime dependencies.
	 *
	 * @param array $args backup configuration or reader arguments
	 * @return bool true if the required values are valid
	 */
	private function checkNativeFullInstanceArgs(array $args): bool
	{
		$is_valid = true;
		$required = ['token', 'native-backup-path'];
		foreach ($required as $name) {
			if (!isset($args[$name]) || !is_string($args[$name]) || $args[$name] === '') {
				Plugins::log(
					Plugins::LOG_ERROR,
					"Missing or invalid InfluxDB Native parameter '{$name}'."
				);
				$is_valid = false;
				break;
			}
		}
		$string_params = ['host', 'token', 'native-backup-path', 'instance-name', 'org', 'binary-path', 'ca-cert-path'];
		if ($is_valid) {
			foreach ($string_params as $name) {
				if (!key_exists($name, $args)) {
					continue;
				}
				if (!is_string($args[$name])) {
					Plugins::log(
						Plugins::LOG_ERROR,
						"Invalid InfluxDB Native parameter '{$name}'."
					);
					$is_valid = false;
					break;
				}
				$is_control_char = Miscellaneous::isASCIControlChar($args[$name]);
				if ($is_control_char) {
					Plugins::log(
						Plugins::LOG_ERROR,
						"Invalid InfluxDB Native parameter '{$name}'."
					);
					$is_valid = false;
					break;
				}
			}
		}
		return $is_valid;
	}

	/**
	 * Check configuration and job parameters used to construct backup object paths.
	 *
	 * @param array $args backup or restore configuration and backup job parameters
	 * @return bool true if the virtual filename components are valid
	 */
	private function checkJobArgs(array $args): bool
	{
		$is_valid = true;
		foreach (['plugin-config', 'job-starttime'] as $name) {
			if (!isset($args[$name]) || !is_string($args[$name]) || $args[$name] === '') {
				Plugins::log(
					Plugins::LOG_ERROR,
					"Missing or invalid InfluxDB job parameter '{$name}'."
				);
				$is_valid = false;
				break;
			}
			$is_control_char = Miscellaneous::isASCIControlChar($args[$name]);
			if ($is_control_char) {
				Plugins::log(
					Plugins::LOG_ERROR,
					"Invalid InfluxDB job parameter '{$name}'."
				);
				$is_valid = false;
				break;
			}
		}
		if ($is_valid) {
			$misc = $this->getModule('misc');
			$is_valid_id = (is_string($args['job-id']) || is_int($args['job-id'])) && $misc->isValidId($args['job-id']);
			$is_valid_time = preg_match('/\A\d{4}-\d{2}-\d{2}_\d{6}\z/D', $args['job-starttime']) === 1;
			$is_valid_level = in_array($args['job-level'], ['Full', 'Differential', 'Incremental'], true);
			if (!$is_valid_id || !$is_valid_time || !$is_valid_level) {
				Plugins::log(
					Plugins::LOG_ERROR,
					'Invalid InfluxDB job identifier, start time or level.'
				);
				$is_valid = false;
			}
		}
		return $is_valid;
	}

	/**
	 * Stream an uncompressed Native archive directly to the BPIPE reader's stdout.
	 *
	 * @param string $directory completed Native backup directory
	 * @return bool true on tar success, false otherwise
	 */
	private function streamNativeBackupArchive(string $directory): bool
	{
		// Prepare archiver command
		$command = $this->getNativeTarCommand($directory);
		if (count($command) === 0) {
			return false;
		}
		// Stream directly: ExecuteCommand captures output and would corrupt a tar payload.
		$result = $this->execCommand($command, true);
		return $result['exitcode'] === 0;
	}

	/**
	 * Get a safely quoted tar reader command for a prepared Native backup directory.
	 *
	 * @param string $directory prepared backup directory
	 * @return array shell command fragments, or empty array for an invalid directory
	 */
	private function getNativeTarCommand(string $directory): array
	{
		$is_control_char = Miscellaneous::isASCIControlChar($directory);
		if ($directory === '' || $is_control_char || !is_dir($directory) || !is_readable($directory)) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'InfluxDB Native archive directory is not readable.'
			);
			return [];
		}
		$quoted_directory = escapeshellarg($directory);
		return [
			self::BACKUP_DATA_ARCHIVER,
			'-C',
			$quoted_directory,
			'-cf',
			'-',
			'.'
		];
	}

	/**
	 * Get the Native Full Instance archive path from configuration and backup job parameters.
	 *
	 * @param array $args locally filtered plugin options and job parameters
	 * @return string virtual archive path
	 */
	private function getNativeFullInstanceBackupPath(array $args): string
	{
		$plugin_name = $this->getPluginName();
		$settings = $this->getVirtualPathComponent($args['plugin-config'], 'main', false);
		$organization = $this->getVirtualPathComponent($args['org'] ?? '', 'default');
		$instance = $this->getVirtualPathComponent($args['instance-name'] ?? '', 'main');
		$file = $this->getFormattedFile(
			'full-instance',
			$args['job-starttime'],
			$args['job-id'],
			$args['job-level']
		);
		return sprintf(
			'/#%s/%s/%s/%s/%s.tar',
			$plugin_name,
			$settings,
			$organization,
			$instance,
			$file
		);
	}

	/**
	 * Get the Native All Buckets One Archive path from configuration and backup job parameters.
	 *
	 * @param array $args locally filtered plugin options and job parameters
	 * @return string virtual archive path
	 */
	private function getNativeAllBucketsOneArchiveBackupPath(array $args): string
	{
		$plugin_name = $this->getPluginName();
		$settings = $this->getVirtualPathComponent($args['plugin-config'], 'main', false);
		$organization = $this->getVirtualPathComponent($args['org'], 'default');
		$instance = $this->getVirtualPathComponent($args['instance-name'] ?? '', 'main');
		$file = $this->getFormattedFile(
			'all-buckets',
			$args['job-starttime'],
			$args['job-id'],
			$args['job-level']
		);
		return sprintf(
			'/#%s/%s/%s/%s/%s.tar',
			$plugin_name,
			$settings,
			$organization,
			$instance,
			$file
		);
	}

	/**
	 * Get an identifiable selected bucket archive path without using names as disk paths.
	 *
	 * @param array $args locally filtered plugin options and job parameters
	 * @param string $bucket_name resolved bucket name
	 * @return string virtual archive path
	 */
	private function getNativeSelectedBucketBackupPath(array $args, string $bucket_name): string
	{
		$plugin_name = $this->getPluginName();
		$settings = $this->getVirtualPathComponent($args['plugin-config'], 'main', false);
		$organization = $this->getVirtualPathComponent($args['org'], 'default');
		$instance = $this->getVirtualPathComponent($args['instance-name'] ?? '', 'main');
		$bucket = $this->getVirtualPathComponent($bucket_name, 'bucket');
		$file = $this->getFormattedFile(
			'bucket',
			$args['job-starttime'],
			$args['job-id'],
			$args['job-level']
		);
		return sprintf(
			'/#%s/%s/%s/%s/buckets/%s/%s.tar',
			$plugin_name,
			$settings,
			$organization,
			$instance,
			$bucket,
			$file
		);
	}

	/**
	 * Get a virtual path component.
	 *
	 * @param string $value component value
	 * @param string $fallback value to use for an empty component
	 * @param bool $encode  whether to URL-encode the component
	 * @return string encoded component
	 */
	private function getVirtualPathComponent(string $value, string $fallback, bool $encode = true): string
	{
		$value = $value !== '' ? $value : $fallback;
		if ($encode && ($value === '.' || $value === '..')) {
			return str_replace('.', '%2E', $value);
		}
		return ($encode ? rawurlencode($value) : $value);
	}

	/**
	 * Get the plugin class name used in commands and virtual paths.
	 *
	 * @return string plugin class short name
	 */
	private function getPluginName(): string
	{
		$reflection = new ReflectionClass($this);
		return $reflection->getShortName();
	}

	/**
	 * Get a backup filename containing the object name, job ID, start time and level.
	 *
	 * @param string $file file name
	 * @param string $starttime backup job start time
	 * @param string $jobid backup job identifier
	 * @param string $level backup job level used as a label
	 * @return string formatted file name
	 */
	private function getFormattedFile(string $file, string $starttime, string $jobid, string $level): string
	{
		return sprintf(
			'%s-%d-%s-%s',
			$file,
			$jobid,
			$starttime,
			$level
		);
	}

	/**
	 * Dispatch Full offline export options; other job levels are unsupported.
	 *
	 * @param array $args plugin options and job parameters
	 * @return array BPIPE plugin commands
	 */
	private function getLineProtocolPluginCommands(array $args): array
	{
		$cmds = [];
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		switch ($level) {
			case 'Full': {
				if (key_exists('line-protocol-all-buckets', $args)) {
					$cmds = $this->getLineProtocolAllBucketsExportPluginCommands($args);
				}
				if (key_exists('line-protocol-export-organizations', $args)) {
					$commands = $this->getLineProtocolSystemExportPluginCommands($args, self::ACTION_LINE_PROTOCOL_ORGANIZATIONS);
					$cmds = array_merge($cmds, $commands);
				}
				if (key_exists('line-protocol-export-users', $args)) {
					$commands = $this->getLineProtocolSystemExportPluginCommands($args, self::ACTION_LINE_PROTOCOL_USERS);
					$cmds = array_merge($cmds, $commands);
				}
				if (key_exists('line-protocol-export-authorizations', $args)) {
					$commands = $this->getLineProtocolSystemExportPluginCommands($args, self::ACTION_LINE_PROTOCOL_AUTHORIZATIONS);
					$cmds = array_merge($cmds, $commands);
				}
				break;
			}
			case 'Incremental':
			case 'Differential': {
				// Time-based offline export is not supported.
				break;
			}
		}
		return $cmds;
	}

	/**
	 * Build one reference metadata descriptor without executing a recovery command.
	 *
	 * @param array $args plugin configuration and job parameters
	 * @param string $action explicit metadata export action
	 * @return array one BPIPE descriptor, or empty array on invalid configuration
	 */
	private function getLineProtocolSystemExportPluginCommands(array $args, string $action): array
	{
		$export_args = $this->filterParametersByCategory($args, [self::PARAM_CAT_GENERAL]);
		$export_args['plugin-config'] = $args['plugin-config'];
		$is_valid = $this->checkLineProtocolSystemExportArgs($export_args);
		if (!$is_valid) {
			return [];
		}

		$recovery = new InfluxDBRecovery();

		// Check Bolt path
		$is_valid = $recovery->isValidBoltPath($export_args['bolt-path']);
		if (!$is_valid) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'InfluxDB metadata export requires a readable regular BoltDB file that is not a symlink.'
			);
			return [];
		}

		// Get line protocol system export command
		$cmds = [];
		$command = $this->getSinglePluginCommand($export_args, $action);
		if ($command !== '') {
			$cmds[] = $command;
		} else {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Unable to create InfluxDB metadata export BPIPE descriptor.'
			);
		}
		return $cmds;
	}

	/**
	 * Validate only the path values needed by offline reference metadata readers.
	 *
	 * @param array $args metadata configuration or reader arguments
	 * @return bool true if required and optional values are valid strings
	 */
	private function checkLineProtocolSystemExportArgs(array $args): bool
	{
		if (!isset($args['bolt-path']) || !is_string($args['bolt-path']) || trim($args['bolt-path']) === '') {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Missing or invalid InfluxDB metadata export BoltDB path.'
			);
			return false;
		}
		$is_valid = true;
		foreach (['bolt-path', 'binary-path', 'plugin-config', 'instance-name'] as $name) {
			if (!key_exists($name, $args)) {
				continue;
			}
			if (!is_string($args[$name])) {
				$is_valid = false;
				break;
			}
			$is_control_char = Miscellaneous::isASCIControlChar($args[$name]);
			if ($is_control_char || ($name === 'plugin-config' && $args[$name] === '')) {
				$is_valid = false;
				break;
			}
		}
		if (!$is_valid) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Invalid InfluxDB metadata export configuration.'
			);
		}
		return $is_valid;
	}

	/**
	 * Get a metadata reader with only the values required for offline recovery listing.
	 *
	 * @param array $args locally filtered metadata configuration
	 * @param string $action explicit metadata action
	 * @return string backup reader command
	 */
	private function getBackupLineProtocolSystemCommand(array $args, string $action): string
	{
		$backup_args = [
			'plugin-name' => $this->getPluginName(),
			'backup-action' => $action,
			'binary-path' => $args['binary-path'] ?? '',
			'bolt-path' => $args['bolt-path']
		];
		$cmd = $this->getPluginCommand('command/backup', $backup_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get a local metadata writer; stable filenames need no backup job timestamp.
	 *
	 * @param array $args metadata configuration identifying the original virtual namespace
	 * @param string $action explicit metadata action
	 * @return string restore writer command
	 */
	private function getRestoreLineProtocolSystemCommand(array $args, string $action): string
	{
		$restore_args = [
			'plugin-name' => $this->getPluginName(),
			'plugin-config' => $args['plugin-config'],
			'instance-name' => $args['instance-name'] ?? 'main',
			'restore-action' => $action,
			'where' => '%w'
		];
		$cmd = $this->getPluginCommand('command/restore', $restore_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get the stable instance-level reference filename selected by an explicit action.
	 *
	 * @param array $args configuration identifying the original virtual namespace
	 * @param string $action metadata action
	 * @return string virtual path, or empty string for an unsupported action
	 */
	private function getLineProtocolSystemExportPath(array $args, string $action): string
	{
		$filename = '';
		switch ($action) {
			case self::ACTION_LINE_PROTOCOL_ORGANIZATIONS: {
				$filename = 'organizations.txt';
				break;
			}
			case self::ACTION_LINE_PROTOCOL_USERS: {
				$filename = 'users.txt';
				break;
			}
			case self::ACTION_LINE_PROTOCOL_AUTHORIZATIONS: {
				$filename = 'authorizations.txt';
				break;
			}
		}
		if ($filename === '') {
			return '';
		}
		$plugin_name = $this->getPluginName();
		$settings = $this->getVirtualPathComponent($args['plugin-config'], 'main', false);
		$instance = $this->getVirtualPathComponent($args['instance-name'] ?? '', 'main');
		return sprintf(
			'/#%s/%s/%s/line-protocol/.SYSTEM/%s',
			$plugin_name,
			$settings,
			$instance,
			$filename
		);
	}

	/**
	 * Stream official offline recovery output unchanged, without logging its payload.
	 * Service lifecycle is managed externally; authorizations may contain sensitive data.
	 *
	 * @param array $args metadata reader arguments
	 * @return bool true if the recovery command exits successfully
	 */
	private function doLineProtocolSystemExport(array $args): bool
	{
		$is_valid = $this->checkLineProtocolSystemExportArgs($args);
		if (!$is_valid) {
			return false;
		}

		$recovery = new InfluxDBRecovery();

		$influxd_path = $recovery->getInfluxdPath($args['binary-path'] ?? '');
		$command = [];
		$action = $args['backup-action'] ?? '';
		switch ($action) {
			case self::ACTION_LINE_PROTOCOL_ORGANIZATIONS: {
				$command = $recovery->getOrganizationListCommand(
					$influxd_path,
					$args['bolt-path']
				);
				break;
			}
			case self::ACTION_LINE_PROTOCOL_USERS: {
				$command = $recovery->getUserListCommand(
					$influxd_path,
					$args['bolt-path']
				);
				break;
			}
			case self::ACTION_LINE_PROTOCOL_AUTHORIZATIONS: {
				$command = $recovery->getAuthorizationListCommand(
					$influxd_path,
					$args['bolt-path']
				);
				break;
			}
		}
		if (count($command) === 0) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Invalid InfluxDB metadata recovery command or unreadable BoltDB file.'
			);
			return false;
		}
		$safe_command = array_map('escapeshellarg', $command);
		$previous_locale = getenv('LC_ALL');
		if (!putenv('LC_ALL=C')) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Unable to set the InfluxDB metadata export locale.'
			);
			return false;
		}
		try {
			// Passthrough keeps stdout out of command/debug logs and leaves stderr separate.
			$result = $this->execCommand($safe_command, true);
		} finally {
			if ($previous_locale === false) {
				putenv('LC_ALL');
			} else {
				putenv('LC_ALL=' . $previous_locale);
			}
		}
		$success = ($result['exitcode'] === 0);
		if (!$success) {
			Plugins::log(
				Plugins::LOG_ERROR,
				"InfluxDB metadata export failed with exit code {$result['exitcode']}."
			);
		}
		return $success;
	}

	/**
	 * Discover offline bucket IDs and build all independent stream descriptors.
	 *
	 * @param array $args plugin configuration and backup job identity
	 * @return array sorted BPIPE descriptors, or empty array on any failure
	 */
	private function getLineProtocolAllBucketsExportPluginCommands(array $args): array
	{
		$export_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_LINE_PROTOCOL_EXPORT
		]);

		// Prepare command parameters
		$export_args['plugin-config'] = $args['plugin-config'];
		$export_args['job-id'] = $args['job-id'];
		$export_args['job-starttime'] = $args['job-starttime'] ?? '';
		$export_args['job-level'] = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;

		// Check line protocol export parameters
		$is_valid = $this->checkLineProtocolExportArgs($export_args);
		if (!$is_valid) {
			return [];
		}

		// Check job parameters
		$is_valid = $this->checkJobArgs($export_args);
		if (!$is_valid) {
			return [];
		}

		$line_protocol = new InfluxDBLineProtocol();

		// Get bucket identifiers
		$influxd_path = $line_protocol->getInfluxdPath($export_args['binary-path'] ?? '');
		$result = $line_protocol->getBucketIDs($influxd_path, $export_args['engine-path']);
		if ($result['error'] !== 0) {
			Plugins::log(
				Plugins::LOG_ERROR,
				"Line Protocol report-db discovery failed with error {$result['error']}."
			);
			return [];
		}
		$bucket_ids = $result['bucket-ids'];
		if (count($bucket_ids) === 0) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'No bucket IDs were found in the Line Protocol report-db output.'
			);
			return [];
		}

		// Get plugin bucket commands, one command per bucket
		$cmds = [];
		foreach ($bucket_ids as $bucket_id) {
			$bucket_args = $export_args;
			$bucket_args['bucket-id'] = $bucket_id;
			$command = $this->getSinglePluginCommand(
				$bucket_args,
				self::ACTION_LINE_PROTOCOL_BUCKET
			);
			if ($command === '') {
				Plugins::log(
					Plugins::LOG_ERROR,
					'Unable to create Line Protocol export BPIPE descriptor.'
				);
				$cmds = [];
				break;
			}
			$cmds[] = $command;
		}
		return $cmds;
	}

	/**
	 * Validate offline export path values without requiring Native working data.
	 *
	 * @param array $args export configuration or reader arguments
	 * @return bool true if the required path values are valid
	 */
	private function checkLineProtocolExportArgs(array $args): bool
	{
		$is_valid = true;
		foreach (['engine-path'] as $name) {
			if (!isset($args[$name]) || !is_string($args[$name]) || trim($args[$name]) === '') {
				Plugins::log(
					Plugins::LOG_ERROR,
					"Missing or invalid Line Protocol export parameter '{$name}'."
				);
				$is_valid = false;
				break;
			}
		}
		if ($is_valid) {
			foreach (['engine-path', 'binary-path', 'instance-name'] as $name) {
				if (!key_exists($name, $args)) {
					continue;
				}
				if (!is_string($args[$name])) {
					$is_valid = false;
					break;
				}
				$is_control_char = Miscellaneous::isASCIControlChar($args[$name]);
				if ($is_control_char) {
					$is_valid = false;
					break;
				}
			}
			if (!$is_valid) {
				Plugins::log(
					Plugins::LOG_ERROR,
					'Invalid Line Protocol export path configuration.'
				);
			}
		}
		return $is_valid;
	}

	/**
	 * Check the source ID shared by a bucket stream reader and local file writer.
	 *
	 * @param array $args reader or writer arguments
	 * @return bool true if the source bucket ID is exactly sixteen hexadecimal characters
	 */
	private function checkLineProtocolBucketArgs(array $args): bool
	{
		$is_valid = isset($args['bucket-id']) && is_string($args['bucket-id']) && preg_match('/\A[0-9a-fA-F]{16}\z/D', $args['bucket-id']) === 1;
		if (!$is_valid) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Missing or invalid Line Protocol bucket ID.'
			);
		}
		return $is_valid;
	}

	/**
	 * Get a single-bucket reader with only the values required for stdout export.
	 *
	 * @param array $args export configuration
	 * @return string reader command
	 */
	private function getBackupLineProtocolBucketCommand(array $args): string
	{
		$backup_args = [
			'plugin-name' => $this->getPluginName(),
			'backup-action' => self::ACTION_LINE_PROTOCOL_BUCKET,
			'binary-path' => $args['binary-path'] ?? '',
			'engine-path' => $args['engine-path'],
			'bucket-id' => $args['bucket-id']
		];
		if (key_exists('line-protocol-compress', $args)) {
			$backup_args['line-protocol-compress'] = true;
		}
		$cmd = $this->getPluginCommand('command/backup', $backup_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get a local stream writer with source ID, compression and original backup identity.
	 *
	 * @param array $args export configuration and backup job parameters
	 * @return string restore writer command
	 */
	private function getRestoreLineProtocolBucketCommand(array $args): string
	{
		$restore_args = [
			'plugin-name' => $this->getPluginName(),
			'plugin-config' => $args['plugin-config'],
			'instance-name' => $args['instance-name'] ?? 'main',
			'job-id' => $args['job-id'],
			'job-starttime' => $args['job-starttime'],
			'job-level' => $args['job-level'],
			'restore-action' => self::ACTION_LINE_PROTOCOL_BUCKET,
			'bucket-id' => $args['bucket-id'],
			'where' => '%w'
		];
		if (key_exists('line-protocol-compress', $args)) {
			$restore_args['line-protocol-compress'] = true;
		}
		$cmd = $this->getPluginCommand('command/restore', $restore_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get an organization-neutral bucket stream path, retaining its source extension.
	 *
	 * @param array $args export configuration and backup job parameters
	 * @return string virtual Line Protocol file path
	 */
	private function getLineProtocolBucketExportPath(array $args): string
	{
		$plugin_name = $this->getPluginName();
		$settings = $this->getVirtualPathComponent($args['plugin-config'], 'main', false);
		$instance = $this->getVirtualPathComponent($args['instance-name'] ?? '', 'main');
		$bucket_id = strtolower($args['bucket-id']);
		$file = $this->getFormattedFile('bucket', $args['job-starttime'], $args['job-id'], $args['job-level']);
		$extension = key_exists('line-protocol-compress', $args) ? 'lp.gz' : 'lp';
		return sprintf(
			'/#%s/%s/%s/line-protocol/buckets/%s/%s.%s',
			$plugin_name,
			$settings,
			$instance,
			$bucket_id,
			$file,
			$extension
		);
	}

	/**
	 * Stream one offline bucket export directly to stdout without capturing payload data.
	 *
	 * @param array $args offline reader arguments
	 * @return bool true if export-lp exits successfully, including empty exports
	 */
	private function doLineProtocolBucketExport(array $args): bool
	{
		// Validate line protocol export parameters
		$is_valid = $this->checkLineProtocolExportArgs($args);
		if (!$is_valid) {
			return false;
		}

		// Validate line protocol bucket parameters
		$is_valid = $this->checkLineProtocolBucketArgs($args);
		if (!$is_valid) {
			return false;
		}

		$line_protocol = new InfluxDBLineProtocol();

		$influxd_path = $line_protocol->getInfluxdPath($args['binary-path'] ?? '');
		$compress = key_exists('line-protocol-compress', $args);
		$command = $line_protocol->getExportCommand(
			$influxd_path,
			$args['engine-path'],
			$args['bucket-id'],
			$compress
		);
		if (count($command) === 0) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Invalid Line Protocol export engine path or bucket ID.'
			);
			return false;
		}
		$safe_command = array_map('escapeshellarg', $command);
		// Offline export assumes a clean shutdown with WAL flush; service lifecycle is external.
		$result = $this->execCommand($safe_command, true);
		$success = ($result['exitcode'] === 0);
		if (!$success) {
			Plugins::log(
				Plugins::LOG_ERROR,
				"Line Protocol bucket export failed with exit code {$result['exitcode']}."
			);
		}
		return $success;
	}

	/**
	 * Write the incoming LP or gzip stream unchanged to its original local file path.
	 *
	 * @param array $args writer arguments with original backup identity and Where
	 * @return bool true on a successful local file write
	 */
	private function doLineProtocolLocalFileRestore(array $args): bool
	{
		$misc = $this->getModule('misc');

		// Validate where parameter
		if (!key_exists('where', $args) || !$misc->isValidRestoreWhere($args['where']) || trim($args['where']) === '' || $args['where'] === '%w') {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Missing or invalid Line Protocol restore destination.'
			);
			return false;
		}
		if ($args['where'] === '/') {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Direct Line Protocol import is not implemented.'
			);
			return false;
		}

		// Validate other parameters
		$is_valid = true;
		foreach (['plugin-config', 'job-id', 'job-starttime', 'job-level', 'instance-name'] as $name) {
			if (!isset($args[$name]) || !is_string($args[$name])) {
				Plugins::log(
					Plugins::LOG_ERROR,
					"Missing or invalid Line Protocol restore parameter '{$name}'."
				);
				$is_valid = false;
				break;
			}
		}
		if (!$is_valid) {
			return false;
		}

		// Validate job parameters
		$is_valid = $this->checkJobArgs($args);
		$is_control_char = Miscellaneous::isASCIControlChar($args['instance-name']);
		if (!$is_valid || $is_control_char) {
			return false;
		}

		// Validate line protocol bucket parameters
		$is_valid = $this->checkLineProtocolBucketArgs($args);
		if (!$is_valid) {
			return false;
		}

		// Get restore path
		$restore_path = $this->getLineProtocolBucketExportPath($args);

		// Line protocol local file restore
		return $this->writeLineProtocolLocalFile($args['where'], $restore_path);
	}

	/**
	 * Restore reference metadata unchanged to its explicit .SYSTEM virtual path.
	 * No source BoltDB file or InfluxDB connection is needed for local restore.
	 *
	 * @param array $args writer arguments identifying the source object and local destination
	 * @return bool true on a successful local file write
	 */
	private function doLineProtocolSystemLocalFileRestore(array $args): bool
	{
		$misc = $this->getModule('misc');

		// Validate where parameter
		if (!key_exists('where', $args) || !$misc->isValidRestoreWhere($args['where']) || trim($args['where']) === '' || $args['where'] === '%w') {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Missing or invalid InfluxDB metadata restore destination.'
			);
			return false;
		}
		if ($args['where'] === '/') {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Direct InfluxDB metadata import is not implemented; exports are reference data only.'
			);
			return false;
		}

		// Validate other parameters
		$is_valid = true;
		foreach (['plugin-config', 'instance-name'] as $name) {
			if (!isset($args[$name]) || !is_string($args[$name])) {
				$is_valid = false;
				break;
			}
			$is_control_char = Miscellaneous::isASCIControlChar($args[$name]);
			if ($is_control_char || ($name === 'plugin-config' && $args[$name] === '')) {
				$is_valid = false;
				break;
			}
		}
		if (!$is_valid) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Missing or invalid InfluxDB metadata restore identity.'
			);
			return false;
		}

		// Get restore path
		$restore_path = $this->getLineProtocolSystemExportPath($args, $args['restore-action']);
		if ($restore_path === '') {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Unsupported InfluxDB metadata restore action.'
			);
			return false;
		}

		// Line protocol system data local file restore
		return $this->writeLineProtocolLocalFile($args['where'], $restore_path);
	}

	/**
	 * Stream an incoming Line Protocol or reference object to a local file using tee.
	 *
	 * @param string $where local restore root
	 * @param string $restore_path original virtual path
	 * @return bool true on successful directory creation and unchanged stream writing
	 */
	private function writeLineProtocolLocalFile(string $where, string $restore_path): bool
	{
		// Prepare local file restore directory
		$filename = basename($restore_path);
		$restore_dir = implode(DIRECTORY_SEPARATOR, [$where, dirname($restore_path)]);
		if (!file_exists($restore_dir) && !@mkdir($restore_dir, 0750, true)) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Unable to create Line Protocol local restore directory.'
			);
			return false;
		}
		if (!is_dir($restore_dir) || !is_writable($restore_dir)) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Line Protocol local restore directory is not writable.'
			);
			return false;
		}
		$restore_path = implode(DIRECTORY_SEPARATOR, [$restore_dir, $filename]);

		// restore file to local file system
		$quoted_path = escapeshellarg($restore_path);
		$command = ['tee', '--', $quoted_path, '>/dev/null'];
		$result = $this->execCommand($command);
		$success = ($result['exitcode'] === 0);
		if (!$success) {
			Plugins::log(
				Plugins::LOG_ERROR,
				"Line Protocol local file restore failed with exit code {$result['exitcode']}."
			);
		}
		return $success;
	}

	/**
	 * Get plugin parameter categories used to configure the restore target.
	 *
	 * @return array plugin parameter categories
	 */
	public static function getRestoreParameterCategories(): array
	{
		return [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_COMMON_BACKUP_RESTORE_OPTIONS,
			self::PARAM_CAT_RESTORE
		];
	}
}
