<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2025 Marcin Haba
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

use Bacularis\Common\Modules\IBaculaBackupFileDaemonPlugin;
use Bacularis\Common\Modules\BacularisCommonPluginBase;
use Bacularis\Common\Modules\Miscellaneous;
use Bacularis\Common\Modules\Plugins;

/**
 * The Microsoft SQL Server database backup plugin.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Plugin
 */
class MSSQLDBBackup extends BacularisCommonPluginBase implements IBaculaBackupFileDaemonPlugin
{
	/**
	 * Database backup/restore tools.
	 */
	private const SQL_CLI_PROGRAM = 'sqlcmd';

	/**
	 * Backup tools.
	 */
	private const TOOL_BACKUP_OUTPUT = 'cat "%s"';

	/**
	 * Backup methods.
	 */
	private const BACKUP_METHOD_DUMP = 'dump';
	private const BACKUP_METHOD_LOG = 'log';
	private const BACKUP_METHOD_ENCRYPTION = 'encryption';

	/**
	 * File extensions.
	 */
	private const EXT_BACKUP_BINARY = '.bak';
	private const EXT_BACKUP_LOG = '.trn';
	private const EXT_BACKUP_KEY = '.key';
	private const EXT_BACKUP_CERT = '.crt';

	/**
	 * Plugin parameter categories
	 */
	private const PARAM_CAT_GENERAL = 'General';
	private const PARAM_CAT_BACKUP_OPTIONS = 'Backup options';
	private const PARAM_CAT_COMMON_BACKUP_RESTORE_OPTIONS = 'Common backup and restore options';
	private const PARAM_CAT_DUMP_BACKUP = 'Database backup';
	private const PARAM_CAT_TRANSACTION_LOG_BACKUP = 'Transaction log backup';
	private const PARAM_CAT_ENCRYPTION_BACKUP = 'Encryption data backup';
	private const PARAM_CAT_RESTORE = 'Dump restore';

	/**
	 * Backup database modes.
	 */
	private const SELECTED_DATABASES = 'databases';
	private const ALL_DATABASES = 'all-databases';

	/**
	 * Backup encryption data modes.
	 */
	private const ENCRYPTION_SELECTED_DATABASES = 'encryption-databases';
	private const ENCRYPTION_ALL_DATABASES = 'encryption-all-databases';

	/**
	 * Backup encryption data modes.
	 */
	private const LOG_SELECTED_DATABASES = 'log-databases';
	private const LOG_ALL_DATABASES = 'log-all-databases';

	/**
	 * Backup actions.
	 */
	private const ACTION_SYSTEM = 'system';
	private const ACTION_SQL_DATA = 'sql-data';
	private const ACTION_SQL_ALL_DBS = 'sql-all-databases';
	private const ACTION_ENCRYPTION = 'encryption';
	private const ACTION_TRANSACTION_LOG = 'transaction-log';

	/**
	 * System databases
	 */
	private const SYSTEM_DATABASE_MASTER = 'master';
	private const SYSTEM_DATABASE_MSDB = 'msdb';
	private const SYSTEM_DATABASE_MODEL = 'model';

	/**
	 * Encryption data types.
	 */
	private const ENCRYPTION_SERVICE_MASTER_KEY = 'service-master-key';
	private const ENCRYPTION_DATABASE_MASTER_KEY = 'database-master-key';
	private const ENCRYPTION_DATABASE_CERTIFICATE = 'database-cert';

	/**
	 * Default job level if not provided.
	 */
	private const DEFAULT_JOB_LEVEL = 'Full';

	/**
	 * SQL queries for backup and restore.
	 */
	private const QUERY_DATABASE_LIST = 'SET NOCOUNT ON; SELECT name FROM sys.databases';
	private const QUERY_CERTIFICATE_LIST = 'SET NOCOUNT ON; SELECT d.name, c.name FROM sys.dm_database_encryption_keys ddek INNER JOIN sys.databases d ON ddek.database_id = d.database_id LEFT JOIN master.sys.certificates c ON ddek.encryptor_thumbprint = c.thumbprint WHERE ddek.encryptor_type = \'CERTIFICATE\'';
	private const QUERY_DATABASE_WITH_MASTER_KEY = 'SET NOCOUNT ON; SELECT name FROM sys.databases WHERE is_master_key_encrypted_by_server = 1';
	private const QUERY_BACKUP_SINGLE_DATABASE = 'BACKUP DATABASE %database TO DISK = \'%path\' %params';
	private const QUERY_BACKUP_SERVICE_MASTER_KEY = 'BACKUP SERVICE MASTER KEY TO FILE = \'%path\' %params' ;
	private const QUERY_BACKUP_DATABASE_MASTER_KEY = 'BACKUP MASTER KEY TO FILE = \'%path\' %params' ;
	private const QUERY_BACKUP_CERTIFICATE = 'BACKUP CERTIFICATE %certname TO FILE = \'%path\' %params';
	private const QUERY_BACKUP_TRANSACTION_LOG = 'BACKUP LOG %database TO DISK = \'%path\' %params';
	private const QUERY_RESTORE_SINGLE_DATABASE = 'RESTORE DATABASE %database FROM DISK = \'%path\' %params';
	private const QUERY_RESTORE_TRANSACTION_LOG = 'RESTORE LOG %database FROM DISK = \'%path\' %params';
	private const QUERY_RESTORE_FILELIST_ONLY = 'SET NOCOUNT ON; RESTORE FILELISTONLY FROM DISK=\'%path\'';

	/**
	 * Databases that are ignored in all databases backup.
	 */
	private const IGNORE_SYSTEM_TABLES = [
		'master',
		'model',
		'msdb',
		'tempdb'
	];

	/**
	 * System SQL data directory name.
	 */
	private const SYSTEM_DATA_DIR = '.SYSTEM';

	/**
	 * Encryption data directory name.
	 */
	private const ENCRYPTION_DATA_DIR = '.ENCRYPTION';

	/**
	 * Debug mode variable.
	 * Debug modes:
	 *  0 - no debug
	 *  1 - debug to stdout
	 *  2 - debug to file
	 */
	private $_debug = 0;

	/**
	 * Get plugin name displayed in web interface.
	 *
	 * @return string plugin name
	 */
	public static function getName(): string
	{
		return 'Microsoft SQL Server database backup';
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
	 * return array plugin parameters
	 */
	public static function getParameters(): array
	{
		return [
			[
				'name' => 'binary-path',
				'type' => 'string',
				'default' => '',
				'label' => 'SQL Server command line tools path',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'server',
				'type' => 'string',
				'default' => '',
				'label' => 'Server address or DSN (Data Source Name)',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'dsn',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Interprets the server address as DSN',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'username',
				'type' => 'string',
				'default' => '',
				'label' => 'Database server user name',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'password',
				'type' => 'password',
				'default' => '',
				'label' => 'Database server user password',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'trust-cert',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Trust the server certificate without validation',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'server-name',
				'type' => 'string',
				'default' => 'main',
				'label' => 'User defined SQL Server name (any string)',
				'category' => [self::PARAM_CAT_BACKUP_OPTIONS]
			],
			[
				'name' => 'server-backup-path',
				'type' => 'string',
				'default' => '',
				'label' => 'Common backup directory to store backups on host with SQL Server (ex: C:\BACKUP)',
				'category' => [self::PARAM_CAT_COMMON_BACKUP_RESTORE_OPTIONS]
			],
			[
				'name' => 'client-backup-path',
				'type' => 'string',
				'default' => '',
				'label' => 'Common backup directory to get backups on host with Bacula client (ex: /mnt/BACKUP)',
				'category' => [self::PARAM_CAT_COMMON_BACKUP_RESTORE_OPTIONS]
			],
			[
				'name' => 'dump-method',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Enable dump backup method',
				'category' => [self::PARAM_CAT_DUMP_BACKUP]
			],
			[
				'name' => 'all-databases',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Backup all databases',
				'category' => [self::PARAM_CAT_DUMP_BACKUP]
			],
			[
				'name' => 'databases',
				'type' => 'string',
				'default' => '',
				'label' => 'Databases to backup (comma separated)',
				'category' => [self::PARAM_CAT_DUMP_BACKUP]
			],
			[
				'name' => 'delete-local-db-backup',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Delete local database backup copy at the end of backup',
				'category' => [self::PARAM_CAT_DUMP_BACKUP]
			],
			[
				'name' => 'compression',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Use compression',
				'category' => [self::PARAM_CAT_DUMP_BACKUP]
			],
			[
				'name' => 'copy-only',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Copy-only backup that is independent of the sequence of conventional SQL Server backups.',
				'category' => [self::PARAM_CAT_DUMP_BACKUP]
			],
			[
				'name' => 'log-method',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Enable transaction log backup method',
				'category' => [self::PARAM_CAT_TRANSACTION_LOG_BACKUP]
			],
			[
				'name' => 'log-all-databases',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Backup transaction logs for all databases',
				'category' => [self::PARAM_CAT_TRANSACTION_LOG_BACKUP]
			],
			[
				'name' => 'log-databases',
				'type' => 'string',
				'default' => '',
				'label' => 'Backup selected database transaction logs (comma separated)',
				'category' => [self::PARAM_CAT_TRANSACTION_LOG_BACKUP]
			],
			[
				'name' => 'delete-local-log-backup',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Delete local log backup copy at the end of backup',
				'category' => [self::PARAM_CAT_TRANSACTION_LOG_BACKUP]
			],
			[
				'name' => 'log-copy-only',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Copy-only backup that is independent of the sequence of conventional SQL Server log backups.',
				'category' => [self::PARAM_CAT_TRANSACTION_LOG_BACKUP]
			],
			[
				'name' => 'encryption-method',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Enable encryption data backup method',
				'category' => [self::PARAM_CAT_ENCRYPTION_BACKUP]
			],
			[
				'name' => 'service-master-key',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Backup Service Master Key (SMK)',
				'category' => [self::PARAM_CAT_ENCRYPTION_BACKUP]
			],
			[
				'name' => 'service-master-key-pwd',
				'type' => 'password',
				'default' => '',
				'label' => 'Protect Service Master Key (SMK) backup by password',
				'category' => [self::PARAM_CAT_ENCRYPTION_BACKUP]
			],
			[
				'name' => 'encryption-all-databases',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Backup Database Master Keys (DMK) for all databases',
				'category' => [self::PARAM_CAT_ENCRYPTION_BACKUP]
			],
			[
				'name' => 'encryption-databases',
				'type' => 'string',
				'default' => '',
				'label' => 'Backup Database Master Keys (DMK) for selected databases (comma separated)',
				'category' => [self::PARAM_CAT_ENCRYPTION_BACKUP]
			],
			[
				'name' => 'database-master-keys-pwd',
				'type' => 'password',
				'default' => '',
				'label' => 'Protect Database Master Key (DMK) backups by password',
				'category' => [self::PARAM_CAT_ENCRYPTION_BACKUP]
			],
			[
				'name' => 'database-certs',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Backup database TDE certificates',
				'category' => [self::PARAM_CAT_ENCRYPTION_BACKUP]
			],
			[
				'name' => 'database-certs-pwd',
				'type' => 'password',
				'default' => '',
				'label' => 'Protect TDE certificate backups by password',
				'category' => [self::PARAM_CAT_ENCRYPTION_BACKUP]
			],
			[
				'name' => 'database',
				'type' => 'string',
				'default' => '',
				'label' => 'New database name',
				'category' => [self::PARAM_CAT_RESTORE]
			],
			[
				'name' => 'norecovery',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Use NORECOVERY option (requires manual executing "RESTORE DATABASE xyz WITH RECOVERY" after restore)',
				'category' => [self::PARAM_CAT_RESTORE]
			],
			[
				'name' => 'stopat',
				'type' => 'string',
				'default' => '',
				'label' => 'Restore time for PITR (Point-in-Time Recovery). Used with transaction log restore (STOPAT option).',
				'category' => [self::PARAM_CAT_RESTORE]
			],
			[
				'name' => 'stopatmark',
				'type' => 'string',
				'default' => '',
				'label' => 'Recovery point (mark name or LSN) for PITR (Point-in-Time Recovery). Used with transaction log restore (STOPATMARK option).',
				'category' => [self::PARAM_CAT_RESTORE]
			],
			[
				'name' => 'stopbeforemark',
				'type' => 'string',
				'default' => '',
				'label' => 'Recovery up to a specified recovery point (mark name or LSN) for PITR (Point-in-Time Recovery). Used with transaction log restore (STOPBEFOREMARK option).',
				'category' => [self::PARAM_CAT_RESTORE]
			],
			[
				'name' => 'database-data-dir',
				'type' => 'string',
				'default' => '',
				'label' => 'Database data directory',
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
		$this->_debug = $args['debug'] ?? Plugins::LOG_DEST_DISABLED;
	}

	/**
	 * Common method to get dump backup commands.
	 * It is for all supported backup levels.
	 *
	 * @param array $args plugin options
	 * @param string $level backup level (ex. 'Full', 'Incremental' or 'Differential')
	 * @return array backup commands
	 */
	private function getDumpBackupLevelCommand(array $args, string $level): array
	{
		$cmds = [];
		if ($level == 'Full') {
			if (key_exists(self::ALL_DATABASES, $args)) {
				$sys_cmds = [
					self::SYSTEM_DATABASE_MASTER,
					self::SYSTEM_DATABASE_MSDB,
					self::SYSTEM_DATABASE_MODEL
				];
				for ($i = 0; $i < count($sys_cmds); $i++) {
					$cmds[] = $this->getSinglePluginCommand(
						$args,
						self::ACTION_SYSTEM,
						$sys_cmds[$i]
					);
				}
			}
		}

		$dbs = [];
		if (key_exists(self::ALL_DATABASES, $args)) {
			$db_list_args = $this->filterParametersByCategory($args, [
				self::PARAM_CAT_GENERAL
			]);
			$dbs = $this->getDatabases($db_list_args);
		} elseif (key_exists(self::SELECTED_DATABASES, $args)) {
			$dbs = explode(',', $args[self::SELECTED_DATABASES]);
			$dbs = array_map('trim', $dbs);
		}
		for ($i = 0; $i < count($dbs); $i++) {
			$args['databases'] = $dbs[$i];
			$cmds[] = $this->getSinglePluginCommand($args, self::ACTION_SQL_DATA, $dbs[$i]);
		}
		return $cmds;
	}

	/**
	 * Get full dump backup commands.
	 *
	 * @param array $args plugin options
	 * @return array backup commands
	 */
	private function getFullDumpBackupPluginCommands(array $args): array
	{
		return $this->getDumpBackupLevelCommand($args, 'Full');
	}

	/**
	 * Get differential dump backup commands.
	 *
	 * @param array $args plugin options
	 * @return array backup commands
	 */
	private function getDifferentialDumpBackupPluginCommands(array $args): array
	{
		return $this->getDumpBackupLevelCommand($args, 'Differential');
	}

	/**
	 * Common method to get encryption backup commands.
	 * It is for all supported backup levels.
	 *
	 * @param array $args plugin options
	 * @param string $level backup level (ex. 'Full', 'Incremental' or 'Differential')
	 * @return array backup commands
	 */
	private function getEncryptionBackupLevelCommand(array $args, string $level): array
	{
		$dbs = [];
		if ($level == 'Full') {
			$mdbs = [];

			// Get database list
			if (key_exists(self::ENCRYPTION_ALL_DATABASES, $args)) {
				$db_list_args = $this->filterParametersByCategory($args, [
					self::PARAM_CAT_GENERAL
				]);
				$mdbs = $this->getDatabases($db_list_args);
			} elseif (key_exists(self::ENCRYPTION_SELECTED_DATABASES, $args)) {
				$mdbs = explode(',', $args[self::ENCRYPTION_SELECTED_DATABASES]);
				$mdbs = array_map('trim', $mdbs);
			}

			// Get database certificates
			$cert_list_args = $this->filterParametersByCategory($args, [
				self::PARAM_CAT_GENERAL
			]);
			$certs = $this->getDatabaseCertificates($cert_list_args);

			// Get databases with master key created
			$dbs_mk_list_args = $this->filterParametersByCategory($args, [
				self::PARAM_CAT_GENERAL
			]);
			$dbs_mk = $this->getDatabaseWithMasterKey($dbs_mk_list_args);

			$enc_cmds = [
				self::ENCRYPTION_SERVICE_MASTER_KEY,
				self::ENCRYPTION_DATABASE_MASTER_KEY,
				self::ENCRYPTION_DATABASE_CERTIFICATE
			];
			for ($i = 0; $i < count($enc_cmds); $i++) {
				if (in_array($enc_cmds[$i], [self::ENCRYPTION_DATABASE_MASTER_KEY, self::ENCRYPTION_DATABASE_CERTIFICATE])) {
					$items = array_map(
						fn ($db) => [
							'type' => $enc_cmds[$i],
							'db' => $db,
							'cert' => (key_exists($db, $certs) ? $certs[$db] : '')
						],
						$mdbs
					);
					$dbs = array_merge($dbs, $items);
				} else {
					$dbs[] = [
						'type' => $enc_cmds[$i],
						'db' => self::SYSTEM_DATABASE_MASTER,
						'cert' => ''
					];
				}
			}
		}
		$cmds = [];
		for ($i = 0; $i < count($dbs); $i++) {
			if ($dbs[$i]['type'] == self::ENCRYPTION_DATABASE_MASTER_KEY && !in_array($dbs[$i]['db'], $dbs_mk)) {
				// database does not have master key, no command, skip it
				continue;
			}
			if ($dbs[$i]['type'] == self::ENCRYPTION_DATABASE_CERTIFICATE && empty($dbs[$i]['cert'])) {
				// certificate does not exists for given database, no command, skip it
				continue;
			}
			$args['encryption-databases'] = $dbs[$i]['db'];
			$args['encryption-cert'] = $dbs[$i]['cert'];
			$cmds[] = $this->getSinglePluginCommand($args, self::ACTION_ENCRYPTION, $dbs[$i]['type']);
		}
		return $cmds;
	}

	/**
	 * Get full encryption backup commands.
	 *
	 * @param array $args plugin options
	 * @return array backup commands
	 */
	private function getFullEncryptionBackupPluginCommands(array $args): array
	{
		return $this->getEncryptionBackupLevelCommand($args, 'Full');
	}

	/**
	 * Common method to get transaction log backup commands.
	 *
	 * @param array $args plugin options
	 * @param string $level backup level (ex. 'Full', 'Incremental' or 'Differential')
	 * @return array backup commands
	 */
	private function getLogBackupLevelCommand(array $args, string $level): array
	{
		$cmds = [];
		$dbs = [];
		if (key_exists(self::LOG_ALL_DATABASES, $args)) {
			$db_list_args = $this->filterParametersByCategory($args, [
				self::PARAM_CAT_GENERAL
			]);
			$dbs = $this->getDatabases($db_list_args);
		} elseif (key_exists(self::LOG_SELECTED_DATABASES, $args)) {
			$dbs = explode(',', $args[self::LOG_SELECTED_DATABASES]);
			$dbs = array_map('trim', $dbs);
		}
		for ($i = 0; $i < count($dbs); $i++) {
			$args['log-databases'] = $dbs[$i];
			$cmds[] = $this->getSinglePluginCommand($args, self::ACTION_TRANSACTION_LOG, $dbs[$i]);
		}
		return $cmds;
	}

	/**
	 * Get full transaction log backup commands.
	 *
	 * @param array $args plugin options
	 * @return array backup commands
	 */
	private function getFullLogBackupPluginCommands(array $args): array
	{
		return $this->getLogBackupLevelCommand($args, 'Full');
	}

	/**
	 * Get incremental transaction log backup commands.
	 * NOTE: Transaction log backup is always FULL, no incremental/differential.
	 * This method is for structure compatibility.
	 *
	 * @param array $args plugin options
	 * @return array backup commands
	 */
	private function getIncrementalLogBackupPluginCommands(array $args): array
	{
		return $this->getLogBackupLevelCommand($args, 'Full');
	}

	/**
	 * Get differential transaction log backup commands.
	 * NOTE: Transaction log backup is always FULL, no incremental/differential.
	 * This method is for structure compatibility.
	 *
	 * @param array $args plugin options
	 * @return array backup commands
	 */
	private function getDifferentialLogBackupPluginCommands(array $args): array
	{
		return $this->getLogBackupLevelCommand($args, 'Full');
	}

	/**
	 * Get single plugin command line.
	 *
	 * @param array $args plugin options
	 * @param string $action backup action
	 * @param string $item backup item
	 * @return array single backup command
	 */
	private function getSinglePluginCommand(array $args, string $action, string $item)
	{
		$bpipe = $this->getModule('bpipe');
		$backup_path = $backup_cmd = $restore_cmd = '';
		switch ($action) {
			case self::ACTION_SYSTEM: {
				$backup_cmd = $this->getBackupSystemCommand($args, $item);
				$restore_cmd = $this->getRestoreSQLCommand($args, $action, $item);
				$backup_path = $this->getBackupSQLPath($args, self::SYSTEM_DATA_DIR, $item);
				break;
			}
			case self::ACTION_SQL_ALL_DBS: {
				$backup_cmd = $this->getBackupSQLAllDbsCommand($args);
				$restore_cmd = $this->getRestoreSQLCommand($args, $action, $item);
				$action_fm = $this->getFormattedFile($action, $args['job-starttime'], $args['job-id'], $args['job-level']);
				$backup_path = $this->getBackupSQLPath($args, $item, $action_fm);
				break;
			}
			case self::ACTION_SQL_DATA: {
				$backup_cmd = $this->getBackupSQLDataCommand($args);
				$restore_cmd = $this->getRestoreSQLCommand($args, $action, $item);
				$action_fm = $this->getFormattedFile($action, $args['job-starttime'], $args['job-id'], $args['job-level']);
				$backup_path = $this->getBackupSQLPath($args, $item, $action_fm);
				break;
			}
			case self::ACTION_ENCRYPTION: {
				$backup_cmd = $this->getBackupEncryptionCommand($args, $item);
				$restore_cmd = $this->getRestoreEncryptionCommand($args, $action, $item);
				$backup_path = $this->getBackupEncryptionPath($args, self::ENCRYPTION_DATA_DIR, $item);
				break;
			}
			case self::ACTION_TRANSACTION_LOG: {
				$backup_cmd = $this->getBackupLogCommand($args);
				$restore_cmd = $this->getRestoreSQLCommand($args, $action, $item);
				$action_fm = $this->getFormattedFile($action, $args['job-starttime'], $args['job-id'], $args['job-level']);
				$backup_path = $this->getBackupLogPath($args, $item, $action_fm);
				break;
			}
		}
		return $bpipe->getPluginCommand(
			$backup_path,
			$backup_cmd,
			$restore_cmd
		);
	}

	/**
	 * Get system data backup plugin command.
	 *
	 * @param array $args plugin options
	 * @param string $system_cmd system command (ex. 'master', 'msdb'...etc.)
	 * @return string backup command
	 */
	private function getBackupSystemCommand(array $args, string $system_cmd): string
	{
		$action = 'command/backup';
		$backup_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_COMMON_BACKUP_RESTORE_OPTIONS,
			self::PARAM_CAT_DUMP_BACKUP
		]);
		$backup_args['dump-method'] = true;
		$backup_args['system'] = $system_cmd;
		$backup_args['job-id'] = $args['job-id'];
		$backup_args['job-level'] = $args['job-level'];
		$backup_args['job-name'] = $args['job-name'];
		$cmd = $this->getPluginCommand($action, $backup_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get encryption data (keys, certs) backup plugin command.
	 *
	 * @param array $args plugin options
	 * @param string $enc_cmd encryption command (ex. 'service-master-key', 'database-master-key'...etc.)
	 * @return string backup command
	 */
	private function getBackupEncryptionCommand(array $args, string $enc_cmd): string
	{
		$action = 'command/backup';
		$backup_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_COMMON_BACKUP_RESTORE_OPTIONS,
			self::PARAM_CAT_ENCRYPTION_BACKUP
		]);
		$backup_args['encryption-method'] = true;
		$backup_args['encryption'] = $enc_cmd;
		$backup_args['encryption-databases'] = $args['encryption-databases'];
		$backup_args['encryption-cert'] = $args['encryption-cert'];
		$backup_args['job-id'] = $args['job-id'];
		$backup_args['job-level'] = $args['job-level'];
		$backup_args['job-name'] = $args['job-name'];
		$cmd = $this->getPluginCommand($action, $backup_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get SQL data backup plugin command.
	 *
	 * @param array $args plugin options
	 * @return string backup command
	 */
	private function getBackupSQLDataCommand(array $args): string
	{
		$action = 'command/backup';
		$backup_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_COMMON_BACKUP_RESTORE_OPTIONS,
			self::PARAM_CAT_DUMP_BACKUP
		]);
		$backup_args['data'] = true;
		$backup_args['job-id'] = $args['job-id'];
		$backup_args['job-level'] = $args['job-level'];
		$backup_args['job-name'] = $args['job-name'];
		$cmd = $this->getPluginCommand($action, $backup_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get all databases backup plugin command.
	 *
	 * @param array $args plugin options
	 * @return string backup command
	 */
	private function getBackupSQLAllDbsCommand(array $args): string
	{
		$action = 'command/backup';
		$backup_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_COMMON_BACKUP_RESTORE_OPTIONS,
			self::PARAM_CAT_DUMP_BACKUP
		]);
		$backup_args['all-databases'] = true;
		$backup_args['databases'] = 'all-databases';
		$backup_args['job-level'] = $args['job-level'];
		$backup_args['job-name'] = $args['job-name'];
		$cmd = $this->getPluginCommand($action, $backup_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get transaction log backup plugin command.
	 *
	 * @param array $args plugin options
	 * @return string backup command
	 */
	private function getBackupLogCommand(array $args): string
	{
		$action = 'command/backup';
		$backup_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_COMMON_BACKUP_RESTORE_OPTIONS,
			self::PARAM_CAT_TRANSACTION_LOG_BACKUP
		]);
		$backup_args['log-databases'] = $args['log-databases'];
		$backup_args['job-id'] = $args['job-id'];
		$backup_args['job-level'] = $args['job-level'];
		$backup_args['job-name'] = $args['job-name'];
		$cmd = $this->getPluginCommand($action, $backup_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get database restore plugin command.
	 *
	 * @param array $args plugin options
	 * @param string $raction restore action
	 * @param string $item restore item
	 * @return string restore command
	 */
	private function getRestoreSQLCommand(array $args, string $raction, string $item): string
	{
		$action = 'command/restore';
		$restore_args = $this->filterParametersByCategory($args, []);
		$restore_args['plugin-config'] = $args['plugin-config'];
		$restore_args['job-starttime'] = $args['job-starttime'];
		$restore_args['job-id'] = $args['job-id'];
		$restore_args['job-level'] = $args['job-level'];
		$restore_args['server-name'] = $args['server-name'];
		$restore_args['restore-item'] = $item;
		$restore_args['restore-action'] = $raction;
		$restore_args['where'] = '%w';
		$cmd = $this->getPluginCommand($action, $restore_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get encryption data restore plugin command.
	 *
	 * @param array $args plugin options
	 * @param string $raction restore action
	 * @param string $item restore item
	 * @return string restore command
	 */
	private function getRestoreEncryptionCommand(array $args, string $raction, string $item): string
	{
		$action = 'command/restore';
		$restore_args = $this->filterParametersByCategory($args, []);
		$restore_args['plugin-config'] = $args['plugin-config'];
		$restore_args['encryption-databases'] = $args['encryption-databases'];
		$restore_args['server-name'] = $args['server-name'];
		$restore_args['restore-item'] = $item;
		$restore_args['restore-action'] = $raction;
		$restore_args['where'] = '%w';
		$cmd = $this->getPluginCommand($action, $restore_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get plugin name.
	 *
	 * @return string plugin name
	 */
	private function getPluginName(): string
	{
		$rc = new \ReflectionClass($this);
		$cls = $rc->getShortName();
		return $cls;
	}

	/**
	 * Get SQL dump backup path.
	 *
	 * @param array $args plugin options
	 * @param string $dir SQL dump backup directory name
	 * @param string $file SQL dump backup file name (without extension)
	 * @return string path
	 */
	private function getBackupSQLPath(array $args, string $dir, string $file): string
	{
		$path_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_BACKUP_OPTIONS
		]);
		$pname = $this->getPluginName();
		$path = sprintf(
			'/#%s/%s/%s/%s/%s%s',
			$pname,
			$args['plugin-config'] ?? '',
			$path_args['server-name'],
			$dir,
			$file,
			self::EXT_BACKUP_BINARY
		);
		return $path;
	}

	/**
	 * Get backup encryption path.
	 *
	 * @param array $args plugin options
	 * @param string $dir backup directory name
	 * @param string $file backup file name (without extension)
	 * @return string path
	 */
	private function getBackupEncryptionPath(array $args, string $dir, string $file): string
	{
		$path_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_BACKUP_OPTIONS
		]);
		$pname = $this->getPluginName();
		$ext = '';
		switch ($file) {
			case self::ENCRYPTION_SERVICE_MASTER_KEY:
			case self::ENCRYPTION_DATABASE_MASTER_KEY: {
				$ext = 'key';
				$file .= "-{$args['encryption-databases']}";
				break;
			}
			case self::ENCRYPTION_DATABASE_CERTIFICATE: {
				$ext = 'crt';
				$file .= "-{$args['encryption-databases']}";
				break;
			}
			default: $ext = 'key';
				break;
		}
		$path = sprintf(
			'/#%s/%s/%s/%s/%s.%s',
			$pname,
			$args['plugin-config'] ?? '',
			$path_args['server-name'],
			$dir,
			$file,
			$ext
		);
		return $path;
	}

	/**
	 * Get backup log path.
	 *
	 * @param array $args plugin options
	 * @param string $dir backup log directory name
	 * @param string $file backup log file name (without extension)
	 * @return string path
	 */
	private function getBackupLogPath(array $args, string $dir, string $file): string
	{
		$path_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_BACKUP_OPTIONS
		]);
		$pname = $this->getPluginName();
		$path = sprintf(
			'/#%s/%s/%s/%s/%s%s',
			$pname,
			$args['plugin-config'] ?? '',
			$path_args['server-name'],
			$dir,
			$file,
			self::EXT_BACKUP_LOG
		);
		return $path;
	}

	/**
	 * Get all plugin commands.
	 *
	 * @param array $args plugin options
	 * @return array plugin commands
	 */
	public function getPluginCommands(array $args): array
	{
		$this->debug($args, Plugins::LOG_DEST_FILE);
		if (!$this->checkRequiredArgs($args)) {
			return [];
		}
		$cmds = [];
		if (key_exists('dump-method', $args)) {
			$cmds = array_merge($cmds, $this->getDumpPluginCommands($args));
		}
		if (key_exists('encryption-method', $args)) {
			$cmds = array_merge($cmds, $this->getEncryptionPluginCommands($args));
		}
		if (key_exists('log-method', $args)) {
			$cmds = array_merge($cmds, $this->getLogPluginCommands($args));
		}
		$this->debug($cmds, Plugins::LOG_DEST_FILE);
		return $cmds;
	}

	/**
	 * Check required plugin options.
	 *
	 * @param array $args plugin options
	 * @return bool true on validation success, false otherwise
	 */
	private function checkRequiredArgs(array $args): bool
	{
		$valid = true;
		$required = ['plugin-name', 'plugin-config', 'job-id'];
		for ($i = 0; $i < count($required); $i++) {
			if (!key_exists($required[$i], $args)) {
				$valid = false;
				Plugins::log(Plugins::LOG_ERROR, "Missing required parameter '{$required[$i]}'.");
				break;
			}
		}
		return $valid;
	}

	/**
	 * Get dump backup plugin commands.
	 *
	 * @param array $args plugin options
	 * @return array plugin commands
	 */
	private function getDumpPluginCommands(array $args): array
	{
		$cmds = [];
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		$this->preparePluginCommandArgs($args, self::BACKUP_METHOD_DUMP);
		switch ($level) {
			case 'Full': {
				$cmds = $this->getFullDumpBackupPluginCommands($args);
				break;
			}
			case 'Incremental': {
				// Incremental method is not supported in dump method
				break;
			}
			case 'Differential': {
				$cmds = $this->getDifferentialDumpBackupPluginCommands($args);
				break;
			}
		}
		return $cmds;
	}

	/**
	 * Get encryption data backup plugin commands.
	 *
	 * @param array $args plugin options
	 * @return array plugin commands
	 */
	private function getEncryptionPluginCommands(array $args): array
	{
		$cmds = [];
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		$this->preparePluginCommandArgs($args, self::BACKUP_METHOD_ENCRYPTION);
		switch ($level) {
			case 'Full': {
				$cmds = $this->getFullEncryptionBackupPluginCommands($args);
				break;
			}
			case 'Incremental': {
				// Incremental method is not supported in encryption method
				break;
			}
			case 'Differential': {
				// Differential method is not supported in encryption method
				break;
			}
		}
		return $cmds;
	}

	/**
	 * Get transaction log backup plugin commands.
	 *
	 * @param array $args plugin options
	 * @return array plugin commands
	 */
	private function getLogPluginCommands(array $args): array
	{
		$cmds = [];
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		$this->preparePluginCommandArgs($args, self::BACKUP_METHOD_LOG);
		switch ($level) {
			case 'Full': {
				$cmds = $this->getFullLogBackupPluginCommands($args);
				break;
			}
			case 'Incremental': {
				$cmds = $this->getIncrementalLogBackupPluginCommands($args);
				break;
			}
			case 'Differential': {
				$cmds = $this->getDifferentialLogBackupPluginCommands($args);
				break;
			}
		}
		return $cmds;
	}

	/**
	 * Common method to prepare plugin command parameters.
	 *
	 * @param array $args plugin options
	 * @param string $method backup method (ex. 'dump')
	 */
	private function preparePluginCommandArgs(array &$args, string $method): void
	{
		$methods = [];
		for ($i = 0; $i < count($methods); $i++) {
			if ($methods[$i] == $method) {
				continue;
			}
			$key = "{$methods[$i]}-method";
			if (key_exists($key, $args)) {
				unset($args[$key]);
			}
		}
	}

	/**
	 * Main method to do backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	public function doBackup(array $args): bool
	{
		$this->debug($args);
		$st_dump = $st_enc = $st_log = true;
		if (key_exists('dump-method', $args)) {
			$st_dump = $this->doDumpBackup($args);
		}
		if (key_exists('encryption-method', $args)) {
			$st_enc = $this->doEncryptionBackup($args);
		}
		if (key_exists('log-method', $args)) {
			$st_log = $this->doLogBackup($args);
		}
		$this->debug(['dump' => $st_dump, 'enc' => $st_enc, 'log' => $st_log]);
		return ($st_dump && $st_enc);
	}

	/**
	 * Run dump backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doDumpBackup(array $args): bool
	{
		$result = false;
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		switch ($level) {
			case 'Full': {
				$result = $this->doFullDumpBackup($args);
				break;
			}
			case 'Incremental': {
				// Incremental is not supported for dump method
				$result = $this->doIncrementalDumpBackup($args);
				break;
			}
			case 'Differential': {
				$result = $this->doDifferentialDumpBackup($args);
				break;
			}
		}
		return $result;
	}

	/**
	 * Run full dump backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doFullDumpBackup(array $args): bool
	{
		$imsg = 'Start full dump backup.';
		Plugins::log(Plugins::LOG_INFO, $imsg);

		$action = self::ACTION_SQL_DATA;
		if (key_exists('databases', $args)) {
			if (key_exists('data', $args)) {
				Plugins::log(Plugins::LOG_INFO, "Doing '{$args['databases']}' database data backup.");
			}
		} elseif (key_exists('system', $args)) {
			$args['databases'] = $args['system'];
			Plugins::log(Plugins::LOG_INFO, "Doing '{$args['system']}' system data backup.");
		}

		// Prepare general parameters
		$cmd_params = $this->getGeneralSQLCommandParams($args);

		// Prepare rest parameters
		if (key_exists('databases', $args)) {
			$cmd_params['d'] = $args['databases'];
		}

		// Prepare file path to backup
		$dfile = $this->getSQLDumpFile($args);

		// Prepare backup SQL query command
		$query_params = [
			'database' => $args['databases'],
			'path' => $dfile['path_srv'],
			'params' => ['INIT']
		];
		if (key_exists('compression', $args)) {
			$query_params['params'][] = 'COMPRESSION';
		}
		if (key_exists('copy-only', $args)) {
			$query_params['params'][] = 'COPY_ONLY';
		}
		$cmd_params['Q'] = $this->getBackupSQLQueryCommand(
			$action,
			$query_params
		);

		// Set parameters
		$cmd = $this->prepareCommandParameters($cmd_params);
		$bin = $this->getBinPath($args, self::SQL_CLI_PROGRAM);
		$this->addCommandPassword($bin, $args['password']);
		array_unshift($cmd, $bin);

		// Run full backup
		$result = $this->execCommand($cmd);
		$out = implode(PHP_EOL, $result['output']);
		Plugins::log(Plugins::LOG_INFO, "Raw backup output: '{$out}'.");
		$success = ($result['exitcode'] === 0);

		if ($success) {
			// Do binary data streaming to FD
			$state = $this->streamBinaryBackup($args, $dfile['path_cli']);
			if ($state) {
				if (key_exists('delete-local-db-backup', $args) && $args['delete-local-db-backup'] == 1) {
					// Delete local backup file
					if (file_exists($dfile['path_cli'])) {
						if (!unlink($dfile['path_cli'])) {
							$success = false;
							Plugins::log(
								Plugins::LOG_ERROR,
								"Error while removing local backup file: '{$dfile['path_cli']}'."
							);
						}
					} else {
						$success = false;
						Plugins::log(
							Plugins::LOG_ERROR,
							"Local backup file to remove does not exist: '{$dfile['path_cli']}'."
						);
					}
				}
			} else {
				$success = false;
				Plugins::log(
					Plugins::LOG_ERROR,
					"Error while streaming full backup from target-dir '{$dfile['path_cli']}'."
				);
			}
		} else {
			Plugins::log(
				Plugins::LOG_ERROR,
				"Error while running full dump backup. ExitCode: '{$result['exitcode']}'."
			);
		}
		return $success;
	}

	/**
	 * Run incremental dump backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doIncrementalDumpBackup(array $args): bool
	{
		Plugins::log(
			Plugins::LOG_WARNING,
			'Incremental backup level is not supported in the dump method backups.'
		);
		return true;
	}

	/**
	 * Run differential dump backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doDifferentialDumpBackup(array $args): bool
	{
		$imsg = 'Start differential dump backup.';
		Plugins::log(Plugins::LOG_INFO, $imsg);

		$action = self::ACTION_SQL_DATA;
		if (key_exists('databases', $args)) {
			Plugins::log(Plugins::LOG_INFO, "Doing '{$args['databases']}' database data backup.");
		}

		// Prepare general parameters
		$cmd_params = $this->getGeneralSQLCommandParams($args);

		// Prepare rest parameters
		if (key_exists('databases', $args)) {
			$cmd_params['d'] = $args['databases'];
		}

		// Prepare file path to backup
		$dfile = $this->getSQLDumpFile($args);

		// Prepare backup SQL query command
		$query_params = [
			'database' => $args['databases'],
			'path' => $dfile['path_srv'],
			'params' => ['DIFFERENTIAL']
		];
		if (key_exists('compression', $args)) {
			$query_params['params'][] = 'COMPRESSION';
		}
		$cmd_params['Q'] = $this->getBackupSQLQueryCommand(
			$action,
			$query_params
		);

		// Set parameters
		$cmd = $this->prepareCommandParameters($cmd_params);
		$bin = $this->getBinPath($args, self::SQL_CLI_PROGRAM);
		$this->addCommandPassword($bin, $args['password']);
		array_unshift($cmd, $bin);

		// Run differential backup
		$result = $this->execCommand($cmd);
		$out = implode(PHP_EOL, $result['output']);
		Plugins::log(Plugins::LOG_INFO, "Raw backup output: '{$out}'.");
		$success = ($result['exitcode'] === 0);

		if ($success) {
			// Do binary data streaming to FD
			$state = $this->streamBinaryBackup($args, $dfile['path_cli']);
			if ($state) {
				if (key_exists('delete-local-db-backup', $args) && $args['delete-local-db-backup'] == 1) {
					// Delete local backup file
					if (file_exists($dfile['path_cli'])) {
						if (!unlink($dfile['path_cli'])) {
							$success = false;
							Plugins::log(
								Plugins::LOG_ERROR,
								"Error while removing local differential database backup file: '{$dfile['path_cli']}'."
							);
						}
					} else {
						$success = false;
						Plugins::log(
							Plugins::LOG_ERROR,
							"Local differential database backup file to remove does not exist: '{$dfile['path_cli']}'."
						);
					}
				}
			} else {
				$success = false;
				Plugins::log(
					Plugins::LOG_ERROR,
					"Error while streaming differential database backup from target-dir '{$dfile['path_cli']}'."
				);
			}
		} else {
			Plugins::log(
				Plugins::LOG_ERROR,
				"Error while running differential dump backup. ExitCode: '{$result['exitcode']}'."
			);
		}
		return $success;
	}

	/**
	 * Run encryption backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doEncryptionBackup(array $args): bool
	{
		$result = false;
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		switch ($level) {
			case 'Full': {
				$result = $this->doFullEncryptionBackup($args);
				break;
			}
			case 'Incremental': {
				// Incremental is not supported for dump method
				$result = $this->doIncrementalEncryptionBackup($args);
				break;
			}
			case 'Differential': {
				// Differential is not supported for dump method
				$result = $this->doDifferentialEncryptionBackup($args);
				break;
			}
		}
		return $result;
	}

	/**
	 * Run full encryption backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doFullEncryptionBackup(array $args): bool
	{
		// Prepare file path to backup
		$dfile = $this->getEncryptionFile($args);

		$result = $this->doBackupEncryption(
			$args,
			$dfile['path_srv']
		);

		$success = ($result['exitcode'] === 0);
		if ($success) {
			// Do binary data streaming to FD
			$state = $this->streamBinaryBackup($args, $dfile['path_cli']);
			if ($state) {
				// Delete local encryption file
				if (file_exists($dfile['path_cli'])) {
					if (!unlink($dfile['path_cli'])) {
						$success = false;
						Plugins::log(
							Plugins::LOG_ERROR,
							"Error while removing local encryption data file: '{$dfile['path_cli']}'."
						);
					}
				} else {
					$success = false;
					Plugins::log(
						Plugins::LOG_ERROR,
						"Local encryption data file to remove does not exist: '{$dfile['path_cli']}'."
					);
				}
			} else {
				$success = false;
				Plugins::log(
					Plugins::LOG_ERROR,
					"Error while streaming full encryption data from target-dir '{$dfile['path_cli']}'."
				);
			}
		} else {
			Plugins::log(
				Plugins::LOG_ERROR,
				"Error while running encryption data backup. ExitCode: '{$result['exitcode']}'."
			);
		}
		return $success;
	}

	/**
	 * Run incremental encryption backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doIncrementalEncryptionBackup(array $args): bool
	{
		Plugins::log(
			Plugins::LOG_WARNING,
			'Incremental backup level is not supported in the encryption method backups.'
		);
		return true;
	}

	/**
	 * Run differential encryption backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doDifferentialEncryptionBackup(array $args): bool
	{
		Plugins::log(
			Plugins::LOG_WARNING,
			'Differential backup level is not supported in the encryption method backups.'
		);
		return true;
	}

	/**
	 * Run encryption backup.
	 *
	 * @param array $args plugin options
	 * @param string $path_srv backup destination path on the Windows server side
	 * @return array command results (output, error...);
	 */
	private function doBackupEncryption(array $args, string $path_srv)
	{
		// Prepare general parameters
		$cmd_params = $this->getGeneralSQLCommandParams($args);

		// Prepare backup SQL query command
		$query_params = [
			'path' => $path_srv,
			'dbname' => $args['encryption-databases'],
			'certname' => $args['encryption-cert'],
			'params' => []
		];
		$query = '';
		switch ($args['encryption']) {
			case self::ENCRYPTION_SERVICE_MASTER_KEY: {
				$query = self::QUERY_BACKUP_SERVICE_MASTER_KEY;
				if (key_exists('service-master-key-pwd', $args) && $args['service-master-key-pwd']) {
					$query_params['params'] = "ENCRYPTION BY PASSWORD = '{$args['service-master-key-pwd']}'";
				}
				break;
			}
			case self::ENCRYPTION_DATABASE_MASTER_KEY: {
				$query = self::QUERY_BACKUP_DATABASE_MASTER_KEY;
				if (key_exists('database-master-keys-pwd', $args) && $args['database-master-keys-pwd']) {
					$query_params['params'] = "ENCRYPTION BY PASSWORD = '{$args['database-master-keys-pwd']}'";
				}
				break;
			}
			case self::ENCRYPTION_DATABASE_CERTIFICATE: {
				$query = self::QUERY_BACKUP_CERTIFICATE;
				if (key_exists('database-certs-pwd', $args) && $args['database-certs-pwd']) {
					$query_params['params'] = [
						"FORMAT = 'PFX'",
						"PRIVATE KEY ( ENCRYPTION BY PASSWORD = '{$args['database-certs-pwd']}', ALGORITHM = 'AES_256' )"
					];
				}
				break;
			}
		}
		$this->addSQLQueryParams($query, $query_params);
		$cmd_params['Q'] = $query;

		// Prepare rest parameters
		if (key_exists('encryption-databases', $args)) {
			if ($args['encryption'] == self::ENCRYPTION_DATABASE_CERTIFICATE) {
				$cmd_params['d'] = self::SYSTEM_DATABASE_MASTER;
			} else {
				$cmd_params['d'] = $args['encryption-databases'];
			}
		}

		$cmd = $this->prepareCommandParameters($cmd_params);
		$bin = $this->getBinPath($args, self::SQL_CLI_PROGRAM);
		$this->addCommandPassword($bin, $args['password']);
		array_unshift($cmd, $bin);

		$result = $this->execCommand($cmd);
		$out = implode(PHP_EOL, $result['output']);
		Plugins::log(Plugins::LOG_INFO, "Raw backup output: '{$out}'.");

		return $result;
	}

	/**
	 * Run log backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doLogBackup(array $args): bool
	{
		$result = false;
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		switch ($level) {
			case 'Full': {
				$result = $this->doFullLogBackup($args);
				break;
			}
			case 'Incremental': {
				$result = $this->doIncrementalLogBackup($args);
				break;
			}
			case 'Differential': {
				$result = $this->doDifferentialLogBackup($args);
				break;
			}
		}
		return $result;
	}

	/**
	 * Run full log backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doFullLogBackup(array $args): bool
	{
		$imsg = 'Start transaction log backup.';
		Plugins::log(Plugins::LOG_INFO, $imsg);

		$action = self::ACTION_TRANSACTION_LOG;
		Plugins::log(Plugins::LOG_INFO, "Doing '{$args['log-databases']}' database transaction log backup.");

		// Prepare general parameters
		$cmd_params = $this->getGeneralSQLCommandParams($args);

		// Prepare rest parameters
		$cmd_params['d'] = $args['log-databases'];

		// Prepare file path to backup
		$dfile = $this->getTransactionLogFile($args);

		// Prepare log backup SQL query command
		$query_params = [
			'database' => $args['log-databases'],
			'path' => $dfile['path_srv'],
			'params' => []
		];
		if (key_exists('log-copy-only', $args)) {
			$query_params['params'][] = 'COPY_ONLY';
		}

		$cmd_params['Q'] = $this->getBackupSQLQueryCommand(
			$action,
			$query_params
		);

		// Set parameters
		$cmd = $this->prepareCommandParameters($cmd_params);
		$bin = $this->getBinPath($args, self::SQL_CLI_PROGRAM);
		$this->addCommandPassword($bin, $args['password']);
		array_unshift($cmd, $bin);

		// Run log backup
		$result = $this->execCommand($cmd);
		$out = implode(PHP_EOL, $result['output']);
		Plugins::log(Plugins::LOG_INFO, "Raw backup output: '{$out}'.");
		$success = ($result['exitcode'] === 0);

		if ($success) {
			// Do binary data streaming to FD
			$state = $this->streamBinaryBackup($args, $dfile['path_cli']);
			if ($state) {
				if (key_exists('delete-local-log-backup', $args) && $args['delete-local-log-backup'] == 1) {
					// Delete local backup file
					if (file_exists($dfile['path_cli'])) {
						if (!unlink($dfile['path_cli'])) {
							$success = false;
							Plugins::log(
								Plugins::LOG_ERROR,
								"Error while removing local transaction log backup file: '{$dfile['path_cli']}'."
							);
						}
					} else {
						$success = false;
						Plugins::log(
							Plugins::LOG_ERROR,
							"Local transaction log backup file to remove does not exist: '{$dfile['path_cli']}'."
						);
					}
				}
			} else {
				$success = false;
				Plugins::log(
					Plugins::LOG_ERROR,
					"Error while streaming local transaction log backup from target-dir '{$dfile['path_cli']}'."
				);
			}
		} else {
			Plugins::log(
				Plugins::LOG_ERROR,
				"Error while running transaction log backup. ExitCode: '{$result['exitcode']}'."
			);
		}
		return $success;
	}

	/**
	 * Run incremental transaction log backup.
	 * Transaction log supports full backups only.
	 * For incremental level backup level, run full transaction log backup
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doIncrementalLogBackup(array $args): bool
	{
		return $this->doFullLogBackup($args);
	}

	/**
	 * Run differential transaction log backup.
	 * Transaction log supports full backups only.
	 * For differential level backup level, run full transaction log backup
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doDifferentialLogBackup(array $args): bool
	{
		return $this->doFullLogBackup($args);
	}

	/**
	 * Main restore command.
	 *
	 * @param array $args plugin options
	 * @return bool restore status - true on success, false otherwise
	 */
	public function doRestore(array $args): bool
	{
		$this->debug($args);
		$result = false;
		if (in_array($args['restore-action'], [self::ACTION_SQL_DATA, self::ACTION_SYSTEM])) {
			// SQL dump backup restore
			$result = $this->doSQLRestore($args);
		} elseif (in_array($args['restore-action'], [self::ACTION_ENCRYPTION])) {
			// Encryption backup restore
			$result = $this->doEncryptionRestore($args);
		} elseif (in_array($args['restore-action'], [self::ACTION_TRANSACTION_LOG])) {
			// Transaction log backup restore
			$result = $this->doLogRestore($args);
		}

		$this->debug(['result' => $result]);
		return $result;
	}

	/**
	 * Run SQL dump restore command.
	 *
	 * @param array $args plugin options
	 * @return bool restore status - true on success, false otherwise
	 */
	private function doSQLRestore(array $args): bool
	{
		if (key_exists('where', $args) && $args['where'] != '/') {
			// do restore to local FD file system
			$restore_result = $this->doLocalFileRestore($args);
			return $restore_result['status'];
		}

		// Prepare file path to restore
		$dfile = $this->getSQLDumpFile($args);
		$args['where'] = $dfile['path_cli'];
		$restore_result = $this->doLocalFileRestore($args);
		if (!$restore_result['status']) {
			$emsg = "Error while doing local restore data.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
			return false;
		}

		// Prepare general parameters
		$cmd_params = $this->getGeneralSQLCommandParams($args);

		// Prepare restore specific arguments
		$database = '';
		$is_new = false;
		if (in_array($args['restore-action'], [self::ACTION_SQL_DATA, self::ACTION_SYSTEM, self::ACTION_TRANSACTION_LOG])) {
			if (key_exists('database', $args) && $args['database']) {
				// restore to typed new database
				$database = $args['database'];
				$is_new = true;
				Plugins::log(Plugins::LOG_INFO, "Doing restore to new '{$database}' database.");
			} else {
				// restore to original database from backup
				$database = $args['restore-item'];
				Plugins::log(Plugins::LOG_INFO, "Doing restore '{$database}' database.");
			}
		}

		// Prepare server side path
		$rpath = implode('', [
			$dfile['path_srv'],
			$restore_result['path']
		]);
		if (Miscellaneous::isWindowsPath($rpath)) {
			$rpath = str_replace('/', '\\', $rpath);
		}

		$qparams = [];
		// Restore with a new defined database name
		$fn_get_pn_path = function ($path) use ($database) {
			$sep = Miscellaneous::isWindowsPath($path) ? '\\' : '/';
			$p = explode($sep, $path);
			array_pop($p);
			array_push($p, $database);
			return implode($sep, $p);
		};
		$fn_get_data_path = function ($path, $file) {
			$sep = Miscellaneous::isWindowsPath($path) ? '\\' : '/';
			$path = rtrim($path, '\\/');
			return implode($sep, [$path, $file]);
		};
		$info = $this->getDatabaseFileListInfo($args, $rpath);
		if ($is_new) {
			// Restore with database rename.
			if (count($info) > 0) {
				$db_dest_db = $db_dest_log = '';
				if (key_exists('database-data-dir', $args)) {
					/**
					 * User defined destination path for files.
					 * Useful for restoring to different database server.
					 */
					$db_dest_db = $db_dest_log = $fn_get_data_path($args['database-data-dir'], $database);
				} else {
					/**
					 * Restore data path is preserved, only the name is changed.
					 * Useful for restoring to the same server with renamed database name.
					 */
					$db_dest_db = $fn_get_pn_path($info['db']['physical_name']);
					$db_dest_log = $fn_get_pn_path($info['log']['physical_name']);
				}
				$qparams = [
					"MOVE '{$info['db']['logical_name']}' TO '{$db_dest_db}.mdf'",
					"MOVE '{$info['log']['logical_name']}' TO '{$db_dest_log}_log.ldf'"
				];
			}
		} else {
			/**
			 * Restore with the same database name but with file relocation to user-defined path.
			 * This is useful if database is restored to different database server but the original name is preserved.
			 */
			if (key_exists('database-data-dir', $args)) {
				$db_dest_db = $db_dest_log = $fn_get_data_path($args['database-data-dir'], $database);
				$qparams = [
					"MOVE '{$info['db']['logical_name']}' TO '{$db_dest_db}.mdf'",
					"MOVE '{$info['log']['logical_name']}' TO '{$db_dest_log}_log.ldf'"
				];
			}
		}

		// Prepare restore options
		if (key_exists('norecovery', $args) && $args['norecovery']) {
			$qparams[] = 'NORECOVERY';
		}

		// Prepare restore SQL query command
		$query_params = [
			'database' => $database,
			'path' => $rpath,
			'params' => $qparams
		];

		$cmd_params['Q'] = $this->getRestoreSQLQueryCommand(
			$args['restore-action'],
			$query_params
		);

		// Set parameters
		$cmd = $this->prepareCommandParameters($cmd_params);
		$bin = $this->getBinPath($args, self::SQL_CLI_PROGRAM);
		$this->addCommandPassword($bin, $args['password']);
		array_unshift($cmd, $bin);

		// Run command
		$result = $this->execCommand($cmd, true);

		$success = ($result['exitcode'] === 0);
		if (!$success) {
			$emsg = "Error while doing restore data. ExitCode: '{$result['exitcode']}'.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}
		return $success;
	}

	/**
	 * Run encryption data restore command.
	 *
	 * @param array $args plugin options
	 * @return bool restore status - true on success, false otherwise
	 */
	private function doEncryptionRestore(array $args): bool
	{
		if (key_exists('where', $args) && $args['where'] != '/') {
			// do restore to local FD file system
			$restore_result = $this->doLocalFileRestore($args);
			return $restore_result['status'];
		}
		return false;
	}

	/**
	 * Run transaction log restore command.
	 *
	 * @param array $args plugin options
	 * @return bool restore status - true on success, false otherwise
	 */
	private function doLogRestore(array $args): bool
	{
		if (key_exists('where', $args) && $args['where'] != '/') {
			// do restore to local FD file system
			$restore_result = $this->doLocalFileRestore($args);
			return $restore_result['status'];
		}

		// Prepare file path to restore
		$dfile = $this->getTransactionLogFile($args);
		$args['where'] = $dfile['path_cli'];
		$restore_result = $this->doLocalFileRestore($args);
		if (!$restore_result['status']) {
			$emsg = "Error while doing local transaction log restore.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
			return false;
		}

		// Prepare general parameters
		$cmd_params = $this->getGeneralSQLCommandParams($args);

		// Prepare restore specific arguments
		$database = '';
		if (in_array($args['restore-action'], [self::ACTION_TRANSACTION_LOG])) {
			if (key_exists('database', $args) && $args['database']) {
				// restore to typed new database
				$database = $args['database'];
				Plugins::log(Plugins::LOG_INFO, "Doing restore transaction log to a new '{$database}' database.");
			} else {
				// restore to original database from backup
				$database = $args['restore-item'];
				Plugins::log(Plugins::LOG_INFO, "Doing restore transaction log to '{$database}' database.");
			}
		}

		// Prepare server side path
		$rpath = implode('', [
			$dfile['path_srv'],
			$restore_result['path']
		]);
		if (Miscellaneous::isWindowsPath($rpath)) {
			$rpath = str_replace('/', '\\', $rpath);
		}


		// Prepare restore options
		$qparams = [];

		if (key_exists('stopat', $args) && $args['stopat']) {
			$qparams[] = "STOPAT = '{$args['stopat']}'";
		} elseif (key_exists('stopbeforemark', $args) && $args['stopbeforemark']) {
			$qparams[] = "STOPBEFOREMARK = '{$args['stopbeforemark']}'";
		} elseif (key_exists('stopatmark', $args) && $args['stopatmark']) {
			$qparams[] = "STOPATMARK = '{$args['stopatmark']}'";
		}
		if (key_exists('norecovery', $args) && $args['norecovery']) {
			$qparams[] = 'NORECOVERY';
		}

		// Prepare restore SQL query command
		$query_params = [
			'database' => $database,
			'path' => $rpath,
			'params' => $qparams
		];

		$cmd_params['Q'] = $this->getRestoreSQLQueryCommand(
			$args['restore-action'],
			$query_params
		);

		// Set parameters
		$cmd = $this->prepareCommandParameters($cmd_params);
		$bin = $this->getBinPath($args, self::SQL_CLI_PROGRAM);
		$this->addCommandPassword($bin, $args['password']);
		array_unshift($cmd, $bin);

		// Run command
		$result = $this->execCommand($cmd, true);

		$success = ($result['exitcode'] === 0);
		if (!$success) {
			$emsg = "Error while doing transaction log restore. ExitCode: '{$result['exitcode']}'.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}
		return $success;
	}

	/**
	 * Run local file restore.
	 *
	 * @param array $args plugin options
	 * @return array result status (boolean) and restored path (string)
	 */
	private function doLocalFileRestore(array $args): array
	{
		$dir = $args['restore-item'] ?? '';
		$file = $args['restore-action'];
		$restore_path = '';
		if ($args['restore-action'] == self::ACTION_SYSTEM) {
			$dir = self::SYSTEM_DATA_DIR;
			$file = $args['restore-item'];
			$restore_path = $this->getBackupSQLPath($args, $dir, $file);
		} elseif ($args['restore-action'] == self::ACTION_ENCRYPTION) {
			$dir = self::ENCRYPTION_DATA_DIR;
			$file = $args['restore-item'];
			$restore_path = $this->getBackupEncryptionPath($args, $dir, $file);
		} elseif (in_array($args['restore-action'], [self::ACTION_SQL_ALL_DBS, self::ACTION_SQL_DATA])) {
			$file = $this->getFormattedFile($file, $args['job-starttime'], $args['job-id'], $args['job-level']);
			$restore_path = $this->getBackupSQLPath($args, $dir, $file);
		} elseif (in_array($args['restore-action'], [self::ACTION_TRANSACTION_LOG])) {
			$file = $this->getFormattedFile($file, $args['job-starttime'], $args['job-id'], $args['job-level']);
			$restore_path = $this->getBackupLogPath($args, $dir, $file);
		}
		$filename = basename($restore_path);
		$restore_dir = implode(DIRECTORY_SEPARATOR, [
			$args['where'],
			dirname($restore_path)
		]);
		if (!file_exists($restore_dir)) {
			if (!mkdir($restore_dir, 0750, true)) {
				Plugins::log(Plugins::LOG_ERROR, "Error while creating restore path {$restore_dir}.");
				return false;
			}
		}
		$full_restore_path = implode(DIRECTORY_SEPARATOR, [
			$restore_dir,
			$filename
		]);
		$cmd = ['tee', '"' . $full_restore_path . '"', '>/dev/null'];

		$result = $this->execCommand($cmd);
		$success = ($result['exitcode'] === 0);
		if (!$success) {
			$output = implode(PHP_EOL, $result['output']);
			$emsg = "Error while running binary restore. Output: '{$output}' ExitCode: '{$result['exitcode']}'.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}
		$result = ['status' => $success, 'path' => $restore_path];
		return $result;
	}

	/**
	 * Get SQL dump file paths.
	 * Returned is path on the SQL Server side and on the Bacula FD side.
	 *
	 * @param array $args plugin options
	 * @return array SQL server and Bacula client file paths
	 */
	private function getSQLDumpFile(array $args): array
	{
		// Prepare file name
		$ffile = $this->getFormattedFile(
			$args['databases'] ?? $args['database'] ?? '', // databases for backup, database for restore
			$args['job-name'] ?? 'backup',
			$args['job-id'],
			$args['job-level']
		) . self::EXT_BACKUP_BINARY;

		// Prepare paths
		$paths = $this->getFormattedPaths(
			$ffile,
			$args['server-backup-path'],
			$args['client-backup-path']
		);
		return $paths;
	}

	/**
	 * Get encryption file paths.
	 * Returned is path on the SQL Server side and on the Bacula FD side.
	 *
	 * @param array $args plugin options
	 * @return array SQL server and Bacula client file paths
	 */
	private function getEncryptionFile(array $args): array
	{
		$name = '';
		$ext = '';
		switch ($args['encryption']) {
			case self::ENCRYPTION_SERVICE_MASTER_KEY: {
				$name = sprintf(self::ENCRYPTION_SERVICE_MASTER_KEY . '-%s', $args['encryption-databases']);
				$ext = self::EXT_BACKUP_KEY;
				break;
			}
			case self::ENCRYPTION_DATABASE_MASTER_KEY: {
				$name = sprintf(self::ENCRYPTION_DATABASE_MASTER_KEY . '-%s', $args['encryption-databases']);
				$ext = self::EXT_BACKUP_KEY;
				break;
			}
			case self::ENCRYPTION_DATABASE_CERTIFICATE: {
				$name = sprintf(self::ENCRYPTION_DATABASE_CERTIFICATE . '-%s', $args['encryption-databases']);
				$ext = self::EXT_BACKUP_CERT;
				break;
			}
		}

		// Prepare file name
		$ffile = $this->getFormattedFile(
			$name,
			$args['job-name'] ?? 'backup',
			$args['job-id'],
			$args['job-level']
		) . $ext;

		// Prepare paths
		$paths = $this->getFormattedPaths(
			$ffile,
			$args['server-backup-path'],
			$args['client-backup-path']
		);

		return $paths;
	}

	/**
	 * Get transaction log file paths.
	 * Returned is path on the SQL Server side and on the Bacula FD side.
	 *
	 * @param array $args plugin options
	 * @return array SQL server and Bacula client file paths
	 */
	private function getTransactionLogFile(array $args): array
	{
		// Prepare file name
		$name = sprintf(
			self::ACTION_TRANSACTION_LOG . '-%s',
			($args['log-databases'] ?? $args['database'] ?? '')
		);
		$ffile = $this->getFormattedFile(
			$name,
			$args['job-name'] ?? 'backup',
			$args['job-id'],
			$args['job-level']
		) . self::EXT_BACKUP_LOG;

		// Prepare paths
		$paths = $this->getFormattedPaths(
			$ffile,
			$args['server-backup-path'],
			$args['client-backup-path']
		);
		return $paths;
	}

	/**
	 * Get all database list.
	 *
	 * @param array $args plugin options
	 * @return array database list
	 */
	private function getDatabases(array $args): array
	{
		// Prepare general parameters
		$cmd_params = $this->getGeneralSQLCommandParams($args);

		// Prepare getting database arguments
		$cmd_params['Q'] = self::QUERY_DATABASE_LIST;
		$cmd = $this->prepareCommandParameters($cmd_params);
		$bin = $this->getBinPath($args, self::SQL_CLI_PROGRAM);
		$this->addCommandPassword($bin, $args['password']);
		array_unshift($cmd, $bin);

		$dbs = [];
		// Run command
		$result = $this->execCommand($cmd);
		if ($result['exitcode'] == 0) {
			$dbs = $result['output'];
			$dbs = array_map('trim', $dbs);
			$dbs = array_filter($dbs, fn ($db) => ($db != '' && !in_array($db, self::IGNORE_SYSTEM_TABLES)));
			$dbs = array_values($dbs);
		}
		return $dbs;
	}

	/**
	 * Get database file list info.
	 *
	 * @param array $args plugin options
	 * @param string $rpath database backup file path to get info
	 * @param array database and log logical and physical names or empty array on error
	 */
	private function getDatabaseFileListInfo(array $args, string $rpath): array
	{
		// Prepare general parameters
		$cmd_params = $this->getGeneralSQLCommandParams($args);

		// Prepare getting database arguments
		$query = self::QUERY_RESTORE_FILELIST_ONLY;
		$params = [
			'path' => $rpath
		];
		$this->addSQLQueryParams($query, $params);
		$cmd_params['Q'] = $query;
		$cmd = $this->prepareCommandParameters($cmd_params);
		$bin = $this->getBinPath($args, self::SQL_CLI_PROGRAM);
		$this->addCommandPassword($bin, $args['password']);
		array_unshift($cmd, $bin);

		$info = [];
		// Run command
		$result = $this->execCommand($cmd);
		if ($result['exitcode'] == 0 && count($result['output']) >= 2) {
			$out_part_db = explode('|', $result['output'][0]);
			$out_part_log = explode('|', $result['output'][1]);
			if (count($out_part_db) > 1) {
				$info = [
					'db' => [
						'logical_name' => $out_part_db[0],
						'physical_name' => $out_part_db[1]
					],
					'log' => [
						'logical_name' => $out_part_log[0],
						'physical_name' => $out_part_log[1]
					]
				];
			}
		}
		return $info;
	}

	/**
	 * Get database certificate list.
	 *
	 * @param array $args plugin options
	 * @return array get database certificates
	 */
	private function getDatabaseCertificates(array $args): array
	{
		// Prepare general parameters
		$cmd_params = $this->getGeneralSQLCommandParams($args);

		// Prepare getting database arguments
		$cmd_params['Q'] = self::QUERY_CERTIFICATE_LIST;
		$cmd = $this->prepareCommandParameters($cmd_params);
		$bin = $this->getBinPath($args, self::SQL_CLI_PROGRAM);
		$this->addCommandPassword($bin, $args['password']);
		array_unshift($cmd, $bin);

		$certs = [];
		// Run command
		$result = $this->execCommand($cmd);
		if ($result['exitcode'] == 0) {
			$lines = $result['output'];
			$lines = array_map('trim', $lines);
			for ($i = 0; $i < count($lines); $i++) {
				[$db, $cert] = explode('|', $lines[$i], 2);
				$certs[$db] = $cert;
			}
		}
		return $certs;
	}

	/**
	 * Get database list with master key created.
	 *
	 * @param array $args plugin options
	 * @return array get database list with master key created
	 */
	private function getDatabaseWithMasterKey(array $args): array
	{
		// Prepare general parameters
		$cmd_params = $this->getGeneralSQLCommandParams($args);

		// Prepare getting database arguments
		$cmd_params['Q'] = self::QUERY_DATABASE_WITH_MASTER_KEY;
		$cmd = $this->prepareCommandParameters($cmd_params);
		$bin = $this->getBinPath($args, self::SQL_CLI_PROGRAM);
		$this->addCommandPassword($bin, $args['password']);
		array_unshift($cmd, $bin);

		$dbs = [];
		// Run command
		$result = $this->execCommand($cmd);
		if ($result['exitcode'] == 0) {
			$lines = $result['output'];
			$dbs = array_map('trim', $lines);
		}
		return $dbs;
	}

	/**
	 * Get general backup tool parameters.
	 *
	 * @param array $args plugin options
	 * @return array backup tool parameters
	 */
	private function getGeneralSQLCommandParams(array $args): array
	{
		$params = [];
		if (key_exists('server', $args) && !empty($args['server'])) {
			$params['S'] = $args['server'];
		}
		if (key_exists('dsn', $args) && $args['dsn'] == 1) {
			$params['D'] = true;
		}
		if (key_exists('username', $args) && !empty($args['username'])) {
			$params['U'] = $args['username'];
		}
		if (key_exists('trust-cert', $args) && $args['trust-cert'] == 1) {
			$params['C'] = true;
		}
		$params['h'] = '-1';
		$params['W'] = true;
		$params['s'] = '|';
		return $params;
	}

	/**
	 * Add password to backup/restore command.
	 *
	 * @param string $bin dump command
	 * @param string $password server password
	 */
	private function addCommandPassword(&$bin, $password): void
	{
		if ($password) {
			$bin = 'SQLCMDPASSWORD="' . $password . '" ' . $bin;
		}
	}

	/**
	 * Get backup SQL query command by command type.
	 *
	 * @param string $type command type
	 * @param array $params command parameters ([param => value, ...])
	 * @return string query command or empty string if type not found
	 */
	private function getBackupSQLQueryCommand(string $type, array $params = []): string
	{
		$query = '';
		switch ($type) {
			case self::ACTION_SYSTEM:
			case self::ACTION_SQL_DATA: {
				$query = self::QUERY_BACKUP_SINGLE_DATABASE;
				break;
			}
			case self::ACTION_TRANSACTION_LOG: {
				$query = self::QUERY_BACKUP_TRANSACTION_LOG;
				break;
			}
		}
		$this->addSQLQueryParams($query, $params);
		return $query;
	}

	/**
	 * Get restore SQL query command by command type.
	 *
	 * @param string $type command type
	 * @param array $params command parameters ([param => value, ...])
	 * @return string query command or empty string if type not found
	 */
	private function getRestoreSQLQueryCommand(string $type, array $params = []): string
	{
		$query = '';
		switch ($type) {
			case self::ACTION_SQL_DATA:
			case self::ACTION_SYSTEM: {
				$query = self::QUERY_RESTORE_SINGLE_DATABASE;
				break;
			}
			case self::ACTION_TRANSACTION_LOG: {
				$query = self::QUERY_RESTORE_TRANSACTION_LOG;
				break;
			}
		}
		$this->addSQLQueryParams($query, $params);
		return $query;
	}

	/**
	 * Add to SQL query command parameters.
	 *
	 * @param string $query command query
	 * @param array $params command parameters ([param => value, ...])
	 */
	private function addSQLQueryParams(string &$query, array $params): void
	{
		foreach ($params as $key => $value) {
			if (is_array($value)) {
				if (count($value) > 0) {
					$val = ' WITH ' . implode(', ', $value);
					$query = str_replace("%{$key}", $val, $query);
				} else {
					$query = str_replace("%{$key}", '', $query);
				}
			} else {
				$query = str_replace("%{$key}", $value, $query);
			}
		}
	}

	/**
	 * Run binary backup streaming.
	 *
	 * @param array $args plugin options
	 * @param string $backup_file backup file to stream
	 * @return bool true on success, otherwise false
	 */
	private function streamBinaryBackup(array $args, string $backup_file): bool
	{
		$cmd = [];
		$cmd[] = sprintf(
			self::TOOL_BACKUP_OUTPUT,
			$backup_file
		);
		$result = $this->execCommand($cmd, true);
		$success = ($result['exitcode'] === 0);
		if (!$success) {
			$emsg = "Error while streaming binary backup data. Path: '{$backup_file}' ExitCode: '{$result['exitcode']}'.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}
		return $success;
	}

	/**
	 * Get formatted backup file name.
	 *
	 * @param string $file file name
	 * @param string $starttime backup job start time
	 * @param string $jobid backup job identifier
	 * @param string $level backup job level
	 * @return string formatted backup file name
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
	 * Get formatted SQL server-side and Bacula client-side paths.
	 *
	 * @param string $ffile formatted file name
	 * @param string $srv_bcp_path database server backup path
	 * @param string $cli_bcp_path Bacula backup backup path
	 * @return string formatted server-side and client-side paths
	 */
	private function getFormattedPaths(string $ffile, string $srv_bcp_path, string $cli_bcp_path): array
	{
		$serv_sep = '/'; // Unix path
		if (Miscellaneous::isWindowsPath($srv_bcp_path)) {
			// Windows path
			$serv_sep = '\\';
		}
		$path_srv = implode(
			$serv_sep,
			[
				$srv_bcp_path,
				$ffile
			]
		);
		$path_cli = implode(
			DIRECTORY_SEPARATOR,
			[
				$cli_bcp_path,
				$ffile
			]
		);
		return [
			'path_srv' => $path_srv,
			'path_cli' => $path_cli
		];
	}

	/**
	 * Write debug log.
	 *
	 * @param mixed $msg debug message
	 * @param null|int $force_dest force log destination
	 */
	private function debug($msg, ?int $force_dest = null): void
	{
		if ($this->_debug) {
			$debug_dest = (int) $this->_debug;
			if (is_null($force_dest) || $force_dest === $debug_dest) {
				Plugins::debug($msg, $debug_dest);
			}
		}
	}

	/**
	 * Get full database tool path.
	 *
	 * @param array $args plugin options
	 * @param string $bin tool binary file name
	 * @return string full database tool path
	 */
	private function getBinPath(array $args, string $bin): string
	{
		$path = $bin;
		if (key_exists('binary-path', $args) && $args['binary-path']) {
			$path = implode(DIRECTORY_SEPARATOR, [
				rtrim($args['binary-path'], '/'),
				$bin
			]);
		}
		$this->addSystemUser($args, $path);
		return $path;
	}

	/**
	 * Add executing command as given system user.
	 *
	 * @param string $args command parameters
	 * @param string $cmd command
	 */
	private function addSystemUser(array $args, string &$cmd): void
	{
		if (key_exists('system-user', $args) && !empty($args['system-user']) && $args['system-user'] != 'root') {
			$cmd = "sudo -u '{$args['system-user']}' $cmd";
		}
	}

	/**
	 * Get plugin restore parameter categories.
	 * It should return all parameter categories that are used in restore.
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
