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
use Bacularis\Common\Modules\BacularisDataFormat;
use Bacularis\Common\Modules\BitSet;
use Bacularis\Common\Modules\BWorkerPool;
use Bacularis\Common\Modules\BWorkerPoolException;
use Bacularis\Common\Modules\BWorkerTaskException;
use Bacularis\Common\Modules\Cloud\Amazon\EBS\EBS as AmazonEBS;
use Bacularis\Common\Modules\Cloud\Amazon\EBS\DirectAPI as AmazonEBSDirectAPI;
use Bacularis\Common\Modules\Cloud\Amazon\EBS\DirectAPIException as AmazonEBSDirectAPIException;
use Bacularis\Common\Modules\Cloud\Amazon\EBS\Snapshot as AmazonEBSSnapshot;
use Bacularis\Common\Modules\Cloud\Amazon\EBS\Volume as AmazonEBSVolume;
use Bacularis\Common\Modules\Cloud\Amazon\EC2\EC2 as AmazonEC2;
use Bacularis\Common\Modules\Cloud\Amazon\EC2\Image as AmazonEC2Image;
use Bacularis\Common\Modules\Cloud\Amazon\EC2\Instance as AmazonEC2Instance;
use Bacularis\Common\Modules\Cloud\Amazon\EC2\Tag as AmazonEC2Tag;
use Bacularis\Common\Modules\Cloud\Amazon\Region as AmazonRegion;
use Bacularis\Common\Modules\Cloud\Amazon\SigV4 as AmazonSigV4;
use Bacularis\Common\Modules\HTTPBWorkerPool;
use Bacularis\Common\Modules\IBaculaBackupFileDaemonPlugin;
use Bacularis\Common\Modules\Logging;
use Bacularis\Common\Modules\Plugins;
use Bacularis\Common\Modules\Protocol\HTTP\Codes as HTTPCodes;
use DateTimeInterface;
use Prado\Prado;
use stdClass;

/**
 * The Bacularis EC2 backup plugin module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Plugin
 */
class AmazonEC2Backup extends BacularisCommonPluginBase implements IBaculaBackupFileDaemonPlugin
{
	/**
	 * Plugin parameter categories
	 */
	private const PARAM_CAT_GENERAL = 'General';
	private const PARAM_CAT_EBS_OPTIONS = 'EBS options';
	private const PARAM_CAT_EC2_INSTANCE_BACKUP = 'EC2 instance backup';
	private const PARAM_CAT_EBS_VOLUME_BACKUP = 'EBS volume backup';
	private const PARAM_CAT_EC2_INSTANCE_RESTORE = 'EC2 instance restore';
	private const PARAM_CAT_EBS_VOLUME_RESTORE = 'EBS volume restore';
	private const PARAM_CAT_BACKUP = 'Backup options';
	private const PARAM_CAT_RESTORE = 'Restore options';

	/**
	 * HTTP worker class settings.
	 * This is used to backup/restore from/to AWS EC2.
	 */
	private const HTTP_WORKER_CLASS = '\Bacularis\Common\Modules\HTTPBWorker';
	private const HTTP_WORKER_BACKUP_TASK_CLASS = '\Bacularis\API\Modules\Cloud\Amazon\BWorkerTaskAWSEC2Backup';
	private const HTTP_WORKER_RESTORE_TASK_CLASS = '\Bacularis\API\Modules\Cloud\Amazon\BWorkerTaskAWSEC2Restore';
	private const HTTP_WORKER_POOL_DEF_MAX_BACKUP_WORKERS = 8;
	private const HTTP_WORKER_POOL_DEF_MAX_RESTORE_WORKERS = 8;

	/**
	 * Worker class settings.
	 * This is used to local file restore.
	 */
	private const WORKER_CLASS = '\Bacularis\Common\Modules\BWorker';
	private const WORKER_LOCAL_FILE_RESTORE_TASK_CLASS = '\Bacularis\API\Modules\Cloud\Amazon\BWorkerTaskLocalFileRestore';
	private const WORKER_POOL_MAX_WORKERS = 24;

	/**
	 * Supported backup methods.
	 */
	private const BACKUP_METHOD_INSTANCE = 'instance';
	private const BACKUP_METHOD_VOLUME = 'volume';

	/**
	 * Supported backup data types.
	 */
	private const BACKUP_DATA_TYPE_INSTANCES = 'instances';
	private const BACKUP_DATA_TYPE_VOLUMES = 'volumes';

	/**
	 * Default job level if not provided.
	 */
	private const DEFAULT_JOB_LEVEL = 'Full';

	/**
	 * Supported job types.
	 */
	private const JOB_TYPE_BACKUP = 'backup';
	private const JOB_TYPE_RESTORE = 'restore';

	/**
	 * Backup actions.
	 */
	private const ACTION_VOLUME_DATA = 'volume-data';
	private const ACTION_VOLUME_METADATA = 'volume-metadata';
	private const ACTION_VOLUME_INFO_METADATA = 'volume-info-metadata';
	private const ACTION_INSTANCE_VOLUME_DATA = 'instance-volume-data';
	private const ACTION_INSTANCE_VOLUME_METADATA = 'instance-volume-metadata';
	private const ACTION_INSTANCE_VOLUME_INFO_METADATA = 'instance-volume-info-metadata';
	private const ACTION_INSTANCE_METADATA = 'instance-metadata';

	/**
	 * Backup data block types.
	 */
	private const RECORD_INSTANCE_METADATA = 'instance_metadata';
	private const RECORD_VOLUME_METADATA = 'volume_metadata';

	private const WORKER_POOL_MODE_BACKUP = 'backup';
	private const WORKER_POOL_MODE_RESTORE = 'restore';
	private const WORKER_POOL_MODE_LOCAL_FILE_RESTORE = 'local-file-restore';

	/**
	 * Stores worker pool instance.
	 */
	private $worker_pool;

	/**
	 * Stores HTTP worker pool instance.
	 */
	private $http_worker_pool;

	/**
	 * HTTP request signing class instance.
	 */
	private static $http_worker_req_signer;

	/**
	 * Refresh AWS credentials lock.
	 */
	private static $refresh_creds_lock = false;

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
		return 'Amazon EC2 backup plugin';
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
				'name' => 'account',
				'type' => 'string',
				'default' => '',
				'label' => 'AWS account name',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'region',
				'type' => 'array',
				'default' => '',
				'label' => 'AWS region',
				'data' => AmazonRegion::getRegions(),
				'category' => [self::PARAM_CAT_GENERAL, self::PARAM_CAT_RESTORE]
			],
			[
				'name' => 'ebs-endpoint-type',
				'type' => 'array',
				'default' => 'ipv4',
				'label' => 'Service endpoints for EBS APIs',
				'data' => AmazonEBS::getEndpoints('name'),
				'category' => [self::PARAM_CAT_EBS_OPTIONS, self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'backup-workers',
				'type' => 'integer',
				'default' => self::HTTP_WORKER_POOL_DEF_MAX_BACKUP_WORKERS,
				'label' => 'Max. HTTP workers for backup',
				'category' => [self::PARAM_CAT_EBS_OPTIONS, self::PARAM_CAT_BACKUP]
			],
			[
				'name' => 'restore-workers',
				'type' => 'integer',
				'default' => self::HTTP_WORKER_POOL_DEF_MAX_RESTORE_WORKERS,
				'label' => 'Max. HTTP workers for restore',
				'category' => [self::PARAM_CAT_EBS_OPTIONS, self::PARAM_CAT_RESTORE]
			],
			[
				'name' => 'instance-method',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Enable EC2 instance backup method',
				'category' => [self::PARAM_CAT_EC2_INSTANCE_BACKUP]
			],
			[
				'name' => 'instance-ids',
				'type' => 'string',
				'default' => '',
				'label' => 'Instance IDs to back up (comma separated)',
				'placeholder' => 'ex: i-xxxx',
				'category' => [self::PARAM_CAT_EC2_INSTANCE_BACKUP]
			],
			[
				'name' => 'exclude-data-volume-ids',
				'type' => 'string',
				'default' => '',
				'label' => 'Exclude data volume IDs (comma separated)',
				'placeholder' => 'ex: vol-xxxx,vol-yyyy',
				'category' => [self::PARAM_CAT_EC2_INSTANCE_BACKUP]
			],
			[
				'name' => 'description',
				'type' => 'string',
				'default' => '',
				'label' => 'Instance snapshot description',
				'placeholder' => 'ex: My main EC2 instance EBS snapshots',
				'category' => [self::PARAM_CAT_EC2_INSTANCE_BACKUP]
			],
			[
				'name' => 'snapshot-tags',
				'type' => 'string',
				'default' => '',
				'label' => 'Snapshot tags',
				'placeholder' => 'ex: TagNameA=ValueA,TagNameB=ValueB',
				'category' => [self::PARAM_CAT_EC2_INSTANCE_BACKUP]
			],
			[
				'name' => 'copy-tags-from-source',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Copy tags from the volumes to corresponding snapshots',
				'category' => [self::PARAM_CAT_EC2_INSTANCE_BACKUP]
			],
			[
				'name' => 'volume-method',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Enable EBS volume backup method',
				'category' => [self::PARAM_CAT_EBS_VOLUME_BACKUP]
			],
			[
				'name' => 'volume-ids',
				'type' => 'string',
				'default' => '',
				'label' => 'Volume IDs to back up (comma separated)',
				'placeholder' => 'ex: vol-xxxx,vol-yyyy',
				'category' => [self::PARAM_CAT_EBS_VOLUME_BACKUP]
			],
			[
				'name' => 'instance-type',
				'type' => 'array',
				'default' => '',
				'data' => array_merge([''], AmazonEC2::getInstanceTypes()),
				'label' => 'Instance type',
				'category' => [self::PARAM_CAT_EC2_INSTANCE_RESTORE]
			],
			[
				'name' => 'placement-group-id',
				'type' => 'string',
				'default' => '',
				'label' => 'Placement group ID',
				'placeholder' => 'ex: pg-xxxx',
				'category' => [self::PARAM_CAT_EC2_INSTANCE_RESTORE]
			],
			[
				'name' => 'placement-partition-number',
				'type' => 'array',
				'default' => '',
				'data' => ['', 1, 2, 3, 4, 5, 6, 7],
				'label' => 'Placement group partition number',
				'category' => [self::PARAM_CAT_EC2_INSTANCE_RESTORE]
			],
			[
				'name' => 'subnet-id',
				'type' => 'string',
				'default' => '',
				'label' => 'Subnet ID',
				'placeholder' => 'ex: subnet-xxxx',
				'category' => [self::PARAM_CAT_EC2_INSTANCE_RESTORE]
			],
			[
				'name' => 'security-group-ids',
				'type' => 'string',
				'default' => '',
				'label' => 'Security group IDs (comma separated)',
				'placeholder' => 'ex: sg-xxxx,sg-yyyy',
				'category' => [self::PARAM_CAT_EC2_INSTANCE_RESTORE]
			],
			[
				'name' => 'key-name',
				'type' => 'string',
				'default' => '',
				'label' => 'Name of key pair',
				'category' => [self::PARAM_CAT_EC2_INSTANCE_RESTORE]
			],
			[
				'name' => 'keep-image',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Keep restore AMI after restoring instance',
				'category' => [self::PARAM_CAT_EC2_INSTANCE_RESTORE]
			]
		];
	}

	/**
	 * Plugin module initialize method.
	 *
	 * @param array $args plugin arguments
	 */
	public function initialize(array $args): void
	{
		$this->_debug = $args['debug'] ?? Plugins::LOG_DEST_DISABLED;
		Logging::$debug_enabled = (key_exists('debug', $args) && in_array(
			$args['debug'],
			[
				Plugins::LOG_DEST_FILE,
				Plugins::LOG_DEST_STDOUT
			]
		));
	}

	/**
	 * Inititialize HTTP worker pool instance.
	 *
	 * @param array $args plugin arguments
	 * @param string $mode worker pool mode (backup|restore)
	 */
	private function initHTTPWorkerPool(array $args, string $mode): void
	{
		Plugins::log(Plugins::LOG_INFO, 'Initialize HTTP worker pool');

		// Initialize HTTP request signer first
		self::initHTTPWorkerRequestSigner($args);

		$http_worker_pool = new HTTPBWorkerPool();
		if ($mode === self::WORKER_POOL_MODE_BACKUP) {
			$backup_workers = (int) $args['backup-workers'];
			$http_worker_pool->setMaxWorkers($backup_workers);
			$http_worker_pool->setWorkerTaskClass(self::HTTP_WORKER_BACKUP_TASK_CLASS);
		} elseif ($mode === self::WORKER_POOL_MODE_RESTORE) {
			$restore_workers = (int) $args['restore-workers'];
			$http_worker_pool->setMaxWorkers($restore_workers);
			$http_worker_pool->setWorkerTaskClass(self::HTTP_WORKER_RESTORE_TASK_CLASS);
		}
		$http_worker_pool->setWorkerClass(self::HTTP_WORKER_CLASS);

		$resp_valid_func = function ($ch) use ($args) {
			$http_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
			$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
			$content = curl_multi_getcontent($ch);
			$headers = substr($content, 0, $header_size);
			$body = substr($content, $header_size);
			$valid = !AmazonEBSDirectAPI::isRetryRequired($body, $http_code);
			if (!$valid && $http_code == HTTPCodes::CODE_FORBIDDEN && self::$refresh_creds_lock == false) {
				$resp = ['headers' => $headers, 'body' => $body, 'http_code' => $http_code];
				$this->debug($resp);
				self::refreshAWSCredentials($args);
			}
			return $valid;
		};
		$http_worker_pool->setIsResponseValidFunc($resp_valid_func);
		$this->http_worker_pool = $http_worker_pool;
	}

	/**
	 * Inititialize worker pool instance.
	 *
	 * @param string $mode worker pool mode (local-file-restore)
	 */
	private function initWorkerPool(string $mode): void
	{
		Plugins::log(Plugins::LOG_INFO, 'Initialize worker pool');
		$worker_pool = new BWorkerPool();
		$worker_pool->setMaxWorkers(self::WORKER_POOL_MAX_WORKERS);
		$worker_pool->setWorkerClass(self::WORKER_CLASS);
		if ($mode === self::WORKER_POOL_MODE_LOCAL_FILE_RESTORE) {
			$worker_pool->setWorkerTaskClass(self::WORKER_LOCAL_FILE_RESTORE_TASK_CLASS);
		}
		$this->worker_pool = $worker_pool;
	}

	/**
	 * Run full backup with HTTP worker pool.
	 *
	 * @param array $args plugin arguments
	 */
	private function runFullBackupHTTPWorkerPool(array $args): bool
	{
		Plugins::log(Plugins::LOG_INFO, 'Run full backup HTTP worker pool');
		$snapshot_id = $args['snapshot-id'] ?? '';
		$volume_id = $args['volume-id'] ?? '';
		$url = AmazonEBSDirectAPI::getEndpoint(
			$args['ebs-endpoint-type'],
			$args['region']
		);
		$url .= sprintf(
			'/snapshots/%s/blocks/',
			$snapshot_id
		);

		$blocks = [];
		try {
			$blocks = $this->listSnapshotBlocks($args);
		} catch (AmazonEBSDirectAPIException $e) {
			$emsg = $e->getErrorMessage();
			Plugins::log(
				Plugins::LOG_ERROR,
				$emsg
			);
			return false;
		}

		if (!$blocks) {
			Plugins::log(
				Plugins::LOG_INFO,
				'List snapshot blocks returned no blocks to backup'
			);
		}

		// Function to get HTTP headers
		$headers_func = $this->getHTTPHeaderFunc();

		$block_len = count($blocks);
		Plugins::log(Plugins::LOG_INFO, 'Read block data');
		for ($i = 0; $i < $block_len; $i++) {
			$task = [
				'block' => $blocks[$i],
				'source' => ['url', $url],
				'destination' => ['resource', STDOUT],
				'header_func' => &$headers_func
			];
			$this->debug(
				"[{$i}] Add worker pool task to read snapshot data.",
				Plugins::LOG_DEST_FILE
			);
			$this->http_worker_pool->addTask($task);
		}

		$ret = false;
		try {
			// Run HTTP worker pool
			$this->http_worker_pool->run();
			$ret = true;
		} catch (BWorkerPoolException | BWorkerTaskException | AmazonEBSDirectAPIException $e) {
			$emsg = $e->getErrorMessage();
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}

		if ($ret) {
			$props = $this->getJobState($args['job-name'], self::JOB_TYPE_BACKUP);
			if (!key_exists('volumes', $props)) {
				$props['volumes'] = [];
			}
			$props['volumes'][$volume_id] = [
				'snapshot_id' => $snapshot_id,
				'parent_snapshot_id' => ''
			];
			$this->setJobState($props, $args['job-name'], self::JOB_TYPE_BACKUP);
		}

		Plugins::log(Plugins::LOG_INFO, "[{$i}] HTTP worker pool finished.");
		return $ret;
	}

	/**
	 * Run incremental backup with HTTP worker pool.
	 *
	 * @param array $args plugin arguments
	 */
	private function runIncrementalBackupHTTPWorkerPool(array $args): bool
	{
		Plugins::log(Plugins::LOG_INFO, 'Run incremental backup HTTP worker pool.');
		$snapshot_id = $args['snapshot-id'] ?? '';
		$volume_id = $args['volume-id'] ?? '';
		$props = $this->getJobState($args['job-name'], self::JOB_TYPE_BACKUP);
		$parent_snapshot_id = $props['volumes'][$volume_id]['snapshot_id'] ?? null;
		$args['first-snapshot-id'] = $parent_snapshot_id ?? '';
		$args['second-snapshot-id'] = $snapshot_id;
		$url = AmazonEBSDirectAPI::getEndpoint(
			$args['ebs-endpoint-type'],
			$args['region']
		);
		$url .= sprintf(
			'/snapshots/%s/blocks/',
			$snapshot_id
		);

		$blocks = [];
		try {
			$blocks = $this->listSnapshotChangedBlocks($args);
		} catch (AmazonEBSDirectAPIException $e) {
			$emsg = $e->getErrorMessage();
			Plugins::log(
				Plugins::LOG_ERROR,
				$emsg
			);
			return false;
		}

		if (!$blocks) {
			Plugins::log(
				Plugins::LOG_INFO,
				'List snapshot blocks returned no blocks to backup'
			);
		}

		// Function to get HTTP headers
		$headers_func = $this->getHTTPHeaderFunc();

		$block_len = count($blocks);
		Plugins::log(Plugins::LOG_INFO, 'Read block data');
		for ($i = 0; $i < $block_len; $i++) {
			$block_url = $url;
			if (is_null($blocks[$i]['BlockToken'])) {
				// Local zero data block task
				$block_url = '';
			}
			$task = [
				'block' => $blocks[$i],
				'source' => ['url', $block_url],
				'destination' => ['resource', STDOUT],
				'header_func' => &$headers_func
			];
			$this->debug(
				"[{$i}] Add worker pool task to read snapshot data.",
				Plugins::LOG_DEST_FILE
			);
			$this->http_worker_pool->addTask($task);
		}

		$ret = false;
		try {
			// Run worker pool
			$this->http_worker_pool->run();
			$ret = true;
		} catch (BWorkerPoolException | BWorkerTaskException | AmazonEBSDirectAPIException $e) {
			$emsg = $e->getErrorMessage();
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}

		if ($ret) {
			$props = $this->getJobState($args['job-name'], self::JOB_TYPE_BACKUP);
			if (!key_exists('volumes', $props)) {
				$props['volumes'] = [];
			}
			if (!key_exists($volume_id, $props['volumes'])) {
				$props['volumes'][$volume_id] = [];
			}
			$props['volumes'][$volume_id]['parent_snapshot_id'] = $parent_snapshot_id;
			$props['volumes'][$volume_id]['snapshot_id'] = $snapshot_id;
			$this->setJobState($props, $args['job-name'], self::JOB_TYPE_BACKUP);
		}

		Plugins::log(Plugins::LOG_INFO, "[{$i}] HTTP worker pool finished.");
		return $ret;
	}


	/**
	 * Run restore volume data with HTTP worker pool.
	 * This is restore to AWS EC2.
	 *
	 * @param array $args plugin arguments
	 * @return bool true on success, false otherwise
	 */
	private function runRestoreVolumeDataHTTPWorkerPool(array $args): bool
	{
		Plugins::log(Plugins::LOG_INFO, 'Run restore volume data with HTTP worker pool');
		$result = false;
		try {
			$result = $this->readRestoreStream($args);
		} catch (BWorkerPoolException | BWorkerTaskException | AmazonEBSDirectAPIException $e) {
			$emsg = $e->getErrorMessage();
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}
		return $result;
	}

	/**
	 * Run restore volume meta-data.
	 * This is restore to AWS EC2.
	 *
	 * @param array $args plugin arguments
	 * @return bool true on success, false otherwise
	 */
	private function runRestoreVolumeMetaData(array $args): bool
	{
		Plugins::log(Plugins::LOG_INFO, 'Run restore volume meta-data');
		$result = false;
		$md = fgets(STDIN);
		$metadata = json_decode($md, true);
		if (is_array($metadata)) {
			$props = $this->getJobState($args['job-name'], self::JOB_TYPE_RESTORE);
			$volume_id = $metadata['volume_id'];
			$rand = $props['volumes'][$volume_id]['rand'] ?? bin2hex(random_bytes(8));
			$client_token = sprintf('restore-job-%s-%s', $rand, $metadata['volume_id']);
			$snapshot = $this->startSnapshot($args, $metadata, $client_token);
			if ($snapshot) {
				if (!key_exists('volumes', $props)) {
					$props['volumes'] = [];
				}
				$props['volumes'][$volume_id] = [
					'snapshot_id' => $snapshot['snapshot_id'],
					'status' => $snapshot['status'],
					'volume_type' => $metadata['volume_type'],
					'encrypted' => $metadata['encrypted'],
					'kms_key_id' => $metadata['kms_key_id'],
					'iops' => $metadata['iops'],
					'throughput' => $metadata['throughput'],
					'tags' => $metadata['tags'],
					'rand' => $rand

				];
				$result = $this->setJobState($props, $args['job-name'], self::JOB_TYPE_RESTORE);
			} else {
				Plugins::log(Plugins::LOG_ERROR, 'Error while creating snapshot for restore');
			}
		} else {
			Plugins::log(Plugins::LOG_ERROR, 'Error while reading volume meta-data for restore');
		}
		return $result;
	}

	/**
	 * Run worker pool for local file restore.
	 *
	 * @param string $destination full destination file path
	 * @return bool true on success, false otherwise
	 */
	private function runRestoreLocalFileWorkerPool($destination): bool
	{
		Plugins::log(Plugins::LOG_INFO, 'Run local file restore worker pool');
		$result = false;
		try {
			$result = $this->readRestoreLocalFileStream($destination);
		} catch (BWorkerTaskException $e) {
			$emsg = $e->getErrorMessage();
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}
		return $result;
	}

	/**
	 * List EBS snapshot blocks.
	 *
	 * @param array $args backup command arguments
	 * @return array list snapshot blocks or empty array on error
	 */
	private function listSnapshotBlocks(array $args): array
	{
		Plugins::log(Plugins::LOG_INFO, 'List snapshot blocks');
		$blocks = [];
		$snapshot_id = $args['snapshot-id'] ?? '';
		$url = AmazonEBSDirectAPI::getEndpoint(
			$args['ebs-endpoint-type'],
			$args['region']
		);
		$url .= sprintf(
			'/snapshots/%s/blocks',
			$snapshot_id
		);
		if (!$url) {
			$emsg = "EBS endpoint type '{$args['ebs-endpoint-type']}' is not supported in AWS region: '{$args['region']}'.";
			Plugins::log(
				Plugins::LOG_ERROR,
				$emsg
			);
			return $blocks;
		}
		$params = [
			'maxResults' => 1000
		];
		$url .= '?' . http_build_query($params);

		$result = AmazonEBSDirectAPI::get($url, [], $args);

		if ($result['error']['error'] == 0 && in_array($result['error']['http_code'], [0, HTTPCodes::CODE_OK])) {
			$block_len = count($result['result']);
			for ($i = 0; $i < $block_len; $i++) {
				$blocks = array_merge($blocks, $result['result'][$i]['Blocks']);
			}
		}
		return $blocks;
	}

	/**
	 * List EBS changed snapshot blocks.
	 * This block selection method is used for incremental snapshot backup.
	 *
	 * @param array $args backup command arguments
	 * @return array list changed snapshot blocks or empty array on error
	 */
	private function listSnapshotChangedBlocks(array $args): array
	{
		Plugins::log(Plugins::LOG_INFO, 'List snapshot changed blocks');
		$blocks = [];
		$first_snapshot_id = $args['first-snapshot-id'] ?? '';
		$second_snapshot_id = $args['second-snapshot-id'] ?? '';
		$url = AmazonEBSDirectAPI::getEndpoint(
			$args['ebs-endpoint-type'],
			$args['region']
		);
		$url .= sprintf(
			'/snapshots/%s/changedblocks',
			$second_snapshot_id
		);
		if (!$url) {
			$emsg = "EBS endpoint type '{$args['ebs-endpoint-type']}' is not supported in AWS region: '{$args['region']}'.";
			Plugins::log(
				Plugins::LOG_ERROR,
				$emsg
			);
			return $blocks;
		}
		$params = [
			'firstSnapshotId' => $first_snapshot_id,
			'maxResults' => 1000
		];
		$url .= '?' . http_build_query($params);

		$result = AmazonEBSDirectAPI::get($url, [], $args);

		if ($result['error']['error'] == 0 && in_array($result['error']['http_code'], [0, HTTPCodes::CODE_OK])) {
			$block_len = count($result['result']);
			for ($i = 0; $i < $block_len; $i++) {
				if (!isset($result['result'][$i]['ChangedBlocks'])) {
					// empty result, normally should not happen - skip it
					continue;
				}
				$block_result_len = count($result['result'][$i]['ChangedBlocks']);
				for ($j = 0; $j < $block_result_len; $j++) {
					if (!isset($result['result'][$i]['ChangedBlocks'][$j]['SecondBlockToken'])) {
						// Block does no longer exist in data
						$blocks[] = [
							'BlockIndex' => $result['result'][$i]['ChangedBlocks'][$j]['BlockIndex'],
							'BlockToken' => null
						];
					} else {
						// Block has been changed (or created) in data
						$blocks[] = [
							'BlockIndex' => $result['result'][$i]['ChangedBlocks'][$j]['BlockIndex'],
							'BlockToken' => $result['result'][$i]['ChangedBlocks'][$j]['SecondBlockToken']
						];
					}
				}
			}
		}
		return $blocks;
	}

	/**
	 * Create new empty snapshot to restore purpose.
	 *
	 * @param array $args plugin parameters
	 * @param array $volume_metadata EBS volume metadata
	 * @param string $client_token client token
	 * @param string $description snapshot description
	 * @return array new snapshot properties or empty list on error
	 */
	private function startSnapshot(array $args, array $volume_metadata, string $client_token, $description = ''): array
	{
		$params = [
			'ebs',
			'start-snapshot',
			"--client-token \"$client_token\"",
			"--volume-size \"{$volume_metadata['volume_size']}\""
		];
		if ($description) {
			$params[] = "--description \"{$description}\"";
		}
		if (isset($volume_metadata['encrypted']) && $volume_metadata['encrypted'] === true) {
			$params[] = '--encrypted';
		}
		if (isset($volume_metadata['kms_key_id']) && $volume_metadata['kms_key_id']) {
			$params[] = "--kms-key-arn \"{$volume_metadata['kms_key_id']}\"";
		}
		if (isset($args['region']) && $args['region']) {
			$params[] = "--region \"{$args['region']}\"";
		}
		$aws_cmd = $this->getModule('aws_command');
		$aws_cmd::addGlobalOptions($params);
		$snapshot = [];
		$result = $aws_cmd->execCommand($args['account'], $params);
		if ($result['error'] == 0) {
			$snap = $result['output'] ? $result['output'] : new stdClass();
			$snapshot = AmazonEBSSnapshot::parseObject($snap);
			Plugins::log(Plugins::LOG_INFO, "Create snapshot '{$snapshot['snapshot_id']}' for restore.");
		} else {
			$msg = var_export($result['output'], true);
			$emsg = "Error: {$result['error']}, Msg: {$msg}";
			Plugins::log(Plugins::LOG_ERROR, "Error while starting new volume snapshot. {$emsg}");
		}
		return $snapshot;
	}

	/**
	 * Complete snapshot for restore purpose.
	 *
	 * @param array $args plugin parameters
	 * @param string $snapshot_id snapshot identifier
	 * @param int $changed_block_count total number of changed blocks in snapshot
	 * @return bool true on success, false otherwise
	 */
	private function completeSnapshot(array $args, string $snapshot_id, int $changed_block_count): bool
	{
		$params = [
			'ebs',
			'complete-snapshot',
			"--snapshot-id \"$snapshot_id\"",
			"--changed-blocks-count \"$changed_block_count\""
		];
		if (isset($args['region']) && $args['region']) {
			$params[] = "--region \"{$args['region']}\"";
		}
		$aws_cmd = $this->getModule('aws_command');
		$aws_cmd::addGlobalOptions($params);
		$result = $aws_cmd->execCommand($args['account'], $params);
		$ret = false;
		if ($result['error'] == 0) {
			Plugins::log(Plugins::LOG_INFO, "Completing snapshot '{$snapshot_id}'.");
			$ret = true;
		} else {
			$msg = var_export($result['output'], true);
			$emsg = "Error: {$result['error']}, Msg: {$msg}";
			Plugins::log(Plugins::LOG_ERROR, "Error while completing snapshot '{$snapshot_id}'. {$emsg}");
		}
		return $ret;
	}

	/**
	 * Complete all pending snapshots for current restore job.
	 *
	 * @param array $args plugin arguments
	 * @param array $job_metadata job metadata
	 * @return bool true on success, false otherwise
	 */
	private function completeSnapshotRestore(array $args, array $job_metadata): bool
	{
		if (!key_exists('volumes', $job_metadata)) {
			Plugins::log(Plugins::LOG_ERROR, "Job metadata is incomplete. Missing 'volumes' section.");
			return false;
		}
		$ret = false;

		$snapshot_id = $job_metadata['volumes'][$args['volume-id']]['snapshot_id'] ?? '';
		if (!$snapshot_id) {
			Plugins::log(Plugins::LOG_ERROR, "Job metadata is incomplete. Missing 'snapshot_id'.");
			return $ret;
		}
		if ($job_metadata['volumes'][$args['volume-id']]['status'] != AmazonEBSSnapshot::STATE_PENDING) {
			Plugins::log(Plugins::LOG_ERROR, "Snapshot is not ready to complete.");
			return $ret;
		}
		$changed_blocks = $job_metadata['changed_blocks'][$snapshot_id] ?? null;
		$changed_block_count = 0;
		if ($changed_blocks) {
			$chb = base64_decode($changed_blocks);
			$bs = new BitSet($chb);
			$changed_block_count = $bs->count();
		}
		$ret = $this->completeSnapshot(
			$args,
			$snapshot_id,
			$changed_block_count
		);
		return $ret;
	}

	/**
	 * Read restore stream and send it to AWS EC2 service.
	 *
	 * @param array $args plugin arguments
	 * @return true on success, false otherwise
	 */
	private function readRestoreStream(array $args): bool
	{
		$result = true;
		$volume_id = $args['volume-id'] ?? '';
		$props = $this->getJobState($args['job-name'], self::JOB_TYPE_RESTORE);
		$snapshot_id = $props['volumes'][$volume_id]['snapshot_id'] ?? '';
		$url = AmazonEBSDirectAPI::getEndpoint(
			$args['ebs-endpoint-type'],
			$args['region']
		);
		$url .= sprintf(
			'/snapshots/%s/blocks/',
			$snapshot_id
		);
		$changed_blocks = null;
		if (isset($props['changed_blocks'][$snapshot_id])) {
			$changed_blocks = base64_decode($props['changed_blocks'][$snapshot_id]);
		}
		$changed_block_bs = new BitSet($changed_blocks);

		// Function to get HTTP headers
		$headers_func = $this->getHTTPHeaderFunc();

		do {
			if ($this->http_worker_pool->isAvailableWorker()) {
				[$header, $block] = BacularisDataFormat::readBlockFromStream(STDIN);

				if ($header !== null && $block !== null) {
					$task = [
						'source' => ['resouce', STDIN],
						'destination' => ['url', $url],
						'block_index' => $header['block_index'],
						'hash' => $header['hash'],
						'state' => $header['state'],
						'block' => $block,
						'changed_block_bs' => &$changed_block_bs,
						'header_func' => &$headers_func
					];
					$this->http_worker_pool->addTask($task);
				}
			}

			$state = $this->http_worker_pool->getRunningState();
			$running = $this->http_worker_pool->runOne();
		} while ($state || $running > 0);

		if ($result) {
			// Set changed block bitset
			if (!key_exists('changed_blocks', $props)) {
				$props['changed_blocks'] = [];
			}
			$bitset = $changed_block_bs->getData();
			$props['changed_blocks'][$snapshot_id] = base64_encode($bitset);
			$this->setJobState($props, $args['job-name'], self::JOB_TYPE_RESTORE);
		}
		return $result;
	}


	/**
	 * Read restore stream and send it to file (local file restore).
	 *
	 * @param string $destination full destination file path
	 * @return true on success, false otherwise
	 */
	private function readRestoreLocalFileStream($destination): bool
	{
		$result = true;
		do {
			if ($this->worker_pool->isAvailableWorker()) {
				[$header, $block] = BacularisDataFormat::readBlockFromStream(STDIN);

				if ($header !== null && $block !== null) {
					$task = [
						'destination' => ['file', $destination],
						'block_index' => $header['block_index'],
						'block_size' => $header['block_size'],
						'hash' => $header['hash'],
						'state' => $header['state'],
						'block' => $block,
					];
					$this->worker_pool->addTask($task);
				}
			}
			$state = $this->worker_pool->getRunningState();
			$running = $this->worker_pool->runOne();
		} while ($state || $running > 0);
		return $result;
	}

	/**
	 * Backup command.
	 *
	 * @param array $args backup command arguments
	 * @return bool true on success, false otherwise
	 */
	public function doBackup(array $args): bool
	{
		$this->debug($args);
		$st_instance_metadata = $st_volume_data = $st_volume_metadata = $st_volume_info_metadata = true;
		if (key_exists(self::ACTION_INSTANCE_VOLUME_DATA, $args) || key_exists(self::ACTION_VOLUME_DATA, $args)) {
			// Volume data backup
			$st_volume_data = $this->doVolumeDataBackup($args);
		}
		if (key_exists(self::ACTION_INSTANCE_VOLUME_METADATA, $args) || key_exists(self::ACTION_VOLUME_METADATA, $args)) {
			// Volume meta-data backup
			$st_volume_metadata = $this->doVolumeMetaDataBackup($args);
		}
		if (key_exists(self::ACTION_INSTANCE_VOLUME_INFO_METADATA, $args) || key_exists(self::ACTION_VOLUME_INFO_METADATA, $args)) {
			// Volume info meta-data backup
			$st_volume_info_metadata = $this->doVolumeInfoMetaDataBackup($args);
		}
		if (key_exists(self::ACTION_INSTANCE_METADATA, $args)) {
			// Volume instance backup
			$st_instance_metadata = $this->doInstanceMetaDataBackup($args);
		}
		$this->debug([
			'instance_metadata' => $st_instance_metadata,
			'volume_metadata' => $st_volume_metadata,
			'volume_info_metadata' => $st_volume_info_metadata,
			'volume_data' => $st_volume_data
		]);
		return ($st_instance_metadata && $st_volume_metadata && $st_volume_data && $st_volume_info_metadata);
	}

	/**
	 * Run EBS volume data backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doVolumeDataBackup(array $args): bool
	{
		$result = $this->waitOnSnapshotReady($args['account'], [$args['snapshot-id']]);
		if (!$result) {
			return $result;
		}
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		switch ($level) {
			case 'Full': {
				$result = $this->doFullVolumeDataBackup($args);
				break;
			}
			case 'Incremental': {
				$result = $this->doIncrementalVolumeDataBackup($args);
				break;
			}
			case 'Differential': {
				// Differential level is not supported in volume data backup
				break;
			}
		}
		return $result;
	}

	/**
	 * Do full volume data backup.
	 * Volume data is backed up from snapshot.
	 *
	 * @param array $args plugin options
	 * @return bool true on success, false otherwise
	 */
	private function doFullVolumeDataBackup(array $args): bool
	{
		$imsg = 'Start full volume data backup.';
		Plugins::log(Plugins::LOG_INFO, $imsg);

		// Initialize backup worker pool
		$this->initHTTPWorkerPool($args, self::WORKER_POOL_MODE_BACKUP);

		// Run worker pool
		$result = $this->runFullBackupHTTPWorkerPool($args);

		return $result;
	}

	/**
	 * Do incremental volume data backup.
	 * Volume data is backed up from snapshot.
	 *
	 * @param array $args plugin options
	 * @return bool true on success, false otherwise
	 */
	private function doIncrementalVolumeDataBackup(array $args): bool
	{
		$imsg = 'Start incremental volume data backup.';
		Plugins::log(Plugins::LOG_INFO, $imsg);

		// Initialize backup worker pool
		$this->initHTTPWorkerPool($args, self::WORKER_POOL_MODE_BACKUP);

		// Run worker pool
		$result = $this->runIncrementalBackupHTTPWorkerPool($args);

		return $result;
	}

	/**
	 * Run EBS volume meta-data backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doVolumeMetaDataBackup(array $args): bool
	{
		$result = false;
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		switch ($level) {
			case 'Full': {
				$result = $this->doFullVolumeMetaDataBackup($args);
				break;
			}
			case 'Incremental': {
				// Incremental level is not supported in volume meta-data backup
				break;
			}
			case 'Differential': {
				// Differential level is not supported in volume meta-data backup
				break;
			}
		}
		return $result;
	}

	/**
	 * Do full volume meta-data backup.
	 * NOTE: Volume meta-data are backed up for full backup level only.
	 *
	 * @param array $args plugin options
	 * @return bool true on success, false otherwise
	 */
	private function doFullVolumeMetaDataBackup(array $args): bool
	{
		$imsg = 'Start full volume meta data backup.';
		Plugins::log(Plugins::LOG_INFO, $imsg);

		// Prepare volume meta-data
		$account = $args['account'] ?? '';
		$volume_id = $args['volume-id'] ?? '';
		$props = [
			'region' => ($args['region'] ?? '')
		];
		$metadata = self::createVolumeMetaData(
			$account,
			$volume_id,
			$props
		);

		// Send meta-data
		$result = self::streamData($metadata);

		return $result;
	}

	/**
	 * Run EBS volume info meta-data backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doVolumeInfoMetaDataBackup(array $args): bool
	{
		$result = false;
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		switch ($level) {
			case 'Full': {
				$result = $this->doFullVolumeInfoMetaDataBackup($args);
				break;
			}
			case 'Incremental': {
				$result = $this->doIncrementalVolumeInfoMetaDataBackup($args);
				break;
			}
			case 'Differential': {
				// Differential level is not supported in volume data backup
				break;
			}
		}
		return $result;
	}

	/**
	 * Run EBS volume info meta-data full backup.
	 *
	 * @param array $args plugin options
	 * @return bool true on success, false otherwise
	 */
	private function doFullVolumeInfoMetaDataBackup($args): bool
	{
		return $this->doVolumeInfoMetaDataBackupLevel($args, 'Full');
	}

	/**
	 * Run EBS volume info meta-data infremental backup.
	 *
	 * @param array $args plugin options
	 * @return bool true on success, false otherwise
	 */
	private function doIncrementalVolumeInfoMetaDataBackup($args): bool
	{
		return $this->doVolumeInfoMetaDataBackupLevel($args, 'Incremental');
	}

	/**
	 * Do volume info meta-data backup for given level.
	 *
	 * @param array $args plugin options
	 * @param string $level job level ('Full', 'Incremental')
	 * @return bool true on success, false otherwise
	 */
	private function doVolumeInfoMetaDataBackupLevel(array $args, string $level): bool
	{
		$imsg = 'Start full volume info meta data backup.';
		Plugins::log(Plugins::LOG_INFO, $imsg);

		// Prepare volume meta-data
		$account = $args['account'] ?? '';
		$volume_id = $args['volume-id'] ?? '';
		$props = [
			'region' => ($args['region'] ?? '')
		];
		$metadata = self::createVolumeMetaData(
			$account,
			$volume_id,
			$props
		);

		// Send meta-data
		$result = self::streamData($metadata);

		return $result;
	}

	/**
	 * Run EC2 instance meta-data backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doInstanceMetaDataBackup(array $args): bool
	{
		$result = false;
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		switch ($level) {
			case 'Full': {
				$result = $this->doFullInstanceMetaDataBackup($args);
				break;
			}
			case 'Incremental': {
				$result = $this->doIncrementalInstanceMetaDataBackup($args);
				break;
			}
			case 'Differential': {
				// Differential level is not supported in instance backup
				break;
			}
		}
		return $result;
	}

	/**
	 * Run EC2 instance meta-data full backup.
	 *
	 * @param array $args plugin options
	 * @return bool true on success, false otherwise
	 */
	private function doFullInstanceMetaDataBackup(array $args): bool
	{
		return $this->doInstanceMetaDataBackupLevel($args, 'Full');
	}

	/**
	 * Run EC2 instance meta-data incremental backup.
	 *
	 * @param array $args plugin options
	 * @return bool true on success, false otherwise
	 */
	private function doIncrementalInstanceMetaDataBackup(array $args): bool
	{
		return $this->doInstanceMetaDataBackupLevel($args, 'Incremental');
	}

	/**
	 * Run EC2 instance meta-data backup with given level.
	 *
	 * @param array $args plugin options
	 * @param string $level job level ('Full', 'Incremental')
	 * @return bool true on success, false otherwise
	 */
	private function doInstanceMetaDataBackupLevel(array $args, string $level): bool
	{
		$instance_id = $args['instance-id'] ?? '';
		$props = $this->getJobState($args['job-name'], self::JOB_TYPE_BACKUP);
		$metadata = $props['instances'][$instance_id] ?? [];

		// Send meta-data
		$result = self::streamData($metadata);

		if ($result) {
			unset($props['instances'][$instance_id]);
			$result = $this->setJobState(
				$props,
				$args['job-name'],
				self::JOB_TYPE_BACKUP
			);
		}
		return $result;
	}

	/**
	 * Restore command.
	 *
	 * @param array $args restore command arguments
	 * @return bool true on success, false otherwise
	 */
	public function doRestore(array $args): bool
	{
		$this->debug($args);
		$result = false;
		if (in_array($args['restore-action'], [self::ACTION_INSTANCE_METADATA])) {
			// Instance meta-data restore
			$result = $this->doInstanceMetaDataRestore($args);
		} elseif (in_array($args['restore-action'], [self::ACTION_INSTANCE_VOLUME_METADATA, self::ACTION_VOLUME_METADATA])) {
			// Volume meta-data restore
			$result = $this->doVolumeMetaDataRestore($args);
		} elseif (in_array($args['restore-action'], [self::ACTION_INSTANCE_VOLUME_INFO_METADATA, self::ACTION_VOLUME_INFO_METADATA])) {
			// Volume info meta-data restore
			$result = $this->doVolumeInfoMetaDataRestore($args);
		} elseif (in_array($args['restore-action'], [self::ACTION_INSTANCE_VOLUME_DATA, self::ACTION_VOLUME_DATA])) {
			// Volume data restore
			$result = $this->doVolumeDataRestore($args);
		}

		$this->debug(['result' => $result]);
		return $result;
	}

	/**
	 * Do volume data restore.
	 *
	 * @param array $args plugin options
	 */
	private function doVolumeDataRestore(array $args): bool
	{
		$imsg = 'Start restore volume data backup.';
		Plugins::log(Plugins::LOG_INFO, $imsg);

		if (key_exists('where', $args) && $args['where'] != '/') {
			// do restore to local FD file system
			$restore_result = $this->doLocalFileDataRestore($args);
			return $restore_result;
		}

		// Initialize restore worker pool
		$this->initHTTPWorkerPool($args, self::WORKER_POOL_MODE_RESTORE);

		// Run worker pool
		$result = $this->runRestoreVolumeDataHTTPWorkerPool($args);

		return $result;
	}

	/**
	 * Do volume meta-data restore.
	 *
	 * @param array $args plugin options
	 * @param bool true on success, false otherwise
	 */
	private function doVolumeMetaDataRestore(array $args): bool
	{
		$imsg = 'Start restore volume meta-data backup.';
		Plugins::log(Plugins::LOG_INFO, $imsg);

		if (key_exists('where', $args) && $args['where'] != '/') {
			// do restore to local FD file system
			$restore_result = $this->doLocalFileMetaDataRestore($args);
			return $restore_result;
		}

		// Run restore volume meta-data
		$result = $this->runRestoreVolumeMetaData($args);
		return $result;
	}

	/**
	 * Do volume info meta-data restore.
	 *
	 * @param array $args plugin options
	 * @param bool true on success, false otherwise
	 */
	private function doVolumeInfoMetaDataRestore(array $args): bool
	{
		$imsg = 'Start restore volume info meta-data backup.';
		Plugins::log(Plugins::LOG_INFO, $imsg);

		if (key_exists('where', $args) && $args['where'] != '/') {
			// do restore to local FD file system
			$restore_result = $this->doLocalFileMetaDataRestore($args);
			return $restore_result;
		}

		// Read stdin to finish successfully. but do nothing with it
		$md = fgets(STDIN);

		$job_metadata = $this->getJobState(
			$args['job-name'],
			self::JOB_TYPE_RESTORE
		);

		// Complete all snapshots for restore prupose (state: pending -> completed)
		$result = $this->completeSnapshotRestore($args, $job_metadata);

		return $result;
	}

	/**
	 * Run instance meta-data restore.
	 * This is restore to AWS EC2.
	 *
	 * @param array $args plugin arguments
	 * @return bool true on success, false otherwise
	 */
	private function doInstanceMetaDataRestore(array $args): bool
	{
		$imsg = 'Start EC2 instance restore.';
		Plugins::log(Plugins::LOG_INFO, $imsg);

		if (key_exists('where', $args) && $args['where'] != '/') {
			// do restore to local FD file system
			$restore_result = $this->doLocalFileMetaDataRestore($args);
			return $restore_result;
		}

		$result = false;
		$md = fgets(STDIN);
		$instance_metadata = json_decode($md, true);
		if (is_array($instance_metadata)) {
			$job_metadata = $this->getJobState(
				$args['job-name'],
				self::JOB_TYPE_RESTORE
			);

			// Register AMI
			$image_id = $this->registerImage(
				$args,
				$instance_metadata,
				$job_metadata,
			);
			if ($image_id) {
				// Run new instance
				$instance = $this->runInstance(
					$image_id,
					$args,
					$instance_metadata,
					$job_metadata,
				);
				if ($instance) {
					$result = true;
					if (!isset($args['keep-image']) || !$args['keep-image']) {
						$this->deregisterImage($args['account'], $image_id);
					}
				}
			}
		}

		// Clean up restore job state file
		$this->clearJobState(
			$args['job-name'],
			self::JOB_TYPE_RESTORE
		);

		return $result;
	}


	/**
	 * Deregister EC2 AMI.
	 * This AMI was created only for restore purpose.
	 *
	 * @param string $account AWS account name
	 * @param string $image_id AMI ID
	 * @return bool true on success, false otherwise
	 */
	private function deregisterImage(string $account, string $image_id): bool
	{
		$result = AmazonEC2Image::deregisterImage($account, $image_id);
		if ($result) {
			Plugins::log(
				Plugins::LOG_INFO,
				"Restore AMI {$image_id} has been deregistered successfully."
			);
		} else {
			Plugins::log(
				Plugins::LOG_ERROR,
				"Error while deregistering restore AMI {$image_id}."
			);
		}
		return $result;
	}

	/**
	 * Run local file meta-data restore.
	 *
	 * @param array $args plugin options
	 * @return array result status (boolean) and restored path (string)
	 */
	private function doLocalFileMetaDataRestore(array $args): bool
	{
		$dir = $args['restore-item'] ?? '';
		$file = $item_type = $dir = '';
		if ($args['restore-action'] == self::ACTION_INSTANCE_VOLUME_METADATA || $args['restore-action'] == self::ACTION_INSTANCE_VOLUME_INFO_METADATA) {
			$item_type = self::BACKUP_DATA_TYPE_INSTANCES;
			$dir = $args['instance-id'];
			$file = self::ACTION_VOLUME_METADATA;
		} elseif ($args['restore-action'] == self::ACTION_VOLUME_METADATA || $args['restore-action'] == self::ACTION_VOLUME_INFO_METADATA) {
			$item_type = self::BACKUP_DATA_TYPE_VOLUMES;
			$dir = $args['region'];
			$file = self::ACTION_VOLUME_METADATA;
		} elseif ($args['restore-action'] == self::ACTION_INSTANCE_METADATA) {
			$item_type = self::BACKUP_DATA_TYPE_INSTANCES;
			$dir = $args['instance-id'];
			$file = self::ACTION_INSTANCE_METADATA;
		}
		$volume_id = $args['volume-id'] ?? '';
		$item_fm = '';
		if (in_array($args['restore-action'], [self::ACTION_INSTANCE_VOLUME_INFO_METADATA, self::ACTION_VOLUME_INFO_METADATA, self::ACTION_INSTANCE_METADATA])) {
			$item_fm = $this->getFormattedMetaDataFile($file);
		} else {
			$item_fm = $this->getFormattedFile(
				$file,
				$args['job-starttime'],
				$args['job-id'],
				$args['job-level']
			);
		}

		$restore_path = '';
		if (in_array($args['restore-action'], [self::ACTION_INSTANCE_VOLUME_METADATA, self::ACTION_VOLUME_METADATA, self::ACTION_INSTANCE_VOLUME_INFO_METADATA, self::ACTION_VOLUME_INFO_METADATA])) {
			// volume meta-data file path
			$restore_path = $this->getBackupVolumeMetaDataPath(
				$args,
				$item_type,
				$dir,
				$volume_id,
				$item_fm
			);
		} elseif (in_array($args['restore-action'], [self::ACTION_INSTANCE_METADATA])) {
			// instance meta-data file path
			$restore_path = $this->getBackupInstanceMetaDataPath(
				$args,
				$item_type,
				$dir,
				$item_fm
			);
		}

		$filename = basename($restore_path);
		$restore_dir = implode(DIRECTORY_SEPARATOR, [
			$args['where'],
			dirname($restore_path)
		]);
		if (!file_exists($restore_dir)) {
			if (!mkdir($restore_dir, 0750, true)) {
				Plugins::log(
					Plugins::LOG_ERROR,
					"Error while creating restore path {$restore_dir}."
				);
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
			$emsg = "Error while running local meta-data file restore. Output: '{$output}' ExitCode: '{$result['exitcode']}'.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}

		return $success;
	}

	/**
	 * Run local file data restore.
	 *
	 * @param array $args plugin options
	 * @return array result status (boolean) and restored path (string)
	 */
	private function doLocalFileDataRestore(array $args): bool
	{
		$dir = $args['restore-item'] ?? '';
		$file = $item_type = $dir = '';
		if ($args['restore-action'] == self::ACTION_INSTANCE_VOLUME_DATA) {
			$item_type = self::BACKUP_DATA_TYPE_INSTANCES;
			$dir = $args['instance-id'];
			$file = self::ACTION_VOLUME_DATA;
		} elseif ($args['restore-action'] == self::ACTION_VOLUME_DATA) {
			$item_type = self::BACKUP_DATA_TYPE_VOLUMES;
			$dir = $args['region'];
			$file = self::ACTION_VOLUME_DATA;
		}

		$volume_id = $args['volume-id'] ?? '';
		$item_fm = $this->getFormattedFile(
			$file,
			$args['job-starttime'],
			$args['job-id'],
			$args['job-level']
		);
		$restore_path = $this->getBackupVolumeDataPath(
			$args,
			$item_type,
			$dir,
			$volume_id,
			$item_fm
		);
		$filename = basename($restore_path);
		$restore_dir = implode(DIRECTORY_SEPARATOR, [
			$args['where'],
			dirname($restore_path)
		]);
		if (!file_exists($restore_dir)) {
			if (!mkdir($restore_dir, 0750, true)) {
				Plugins::log(
					Plugins::LOG_ERROR,
					"Error while creating restore path {$restore_dir}."
				);
				return false;
			}
		}
		$full_restore_path = implode(DIRECTORY_SEPARATOR, [
			$restore_dir,
			$filename
		]);

		// Initialize local restore worker pool
		$this->initWorkerPool(self::WORKER_POOL_MODE_LOCAL_FILE_RESTORE);

		// Run worker pool
		$result = $this->runRestoreLocalFileWorkerPool($full_restore_path, $args);

		return $result;
	}

	/**
	 * Get plugin command list.
	 * (@see IBaculaBackupPlugin::getPluginCommand);
	 *
	 * @param array $args plugin command parameters
	 * @return array plugin commands
	 */
	public function getPluginCommands(array $args): array
	{
		$this->debug($args, Plugins::LOG_DEST_FILE);
		if (!$this->checkRequiredArgs($args)) {
			return [];
		}
		$cmds = [];
		if (key_exists('instance-method', $args)) {
			$cmds = array_merge($cmds, $this->getInstancePluginCommands($args));
		}
		if (key_exists('volume-method', $args)) {
			$cmds = array_merge($cmds, $this->getVolumePluginCommands($args));
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
		$required = ['plugin-name', 'plugin-config', 'job-id', 'account'];
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
	 * Get instance backup plugin commands.
	 *
	 * @param array $args plugin options
	 * @return array plugin commands
	 */
	private function getInstancePluginCommands(array $args): array
	{
		$cmds = [];
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		$this->preparePluginCommandArgs($args, self::BACKUP_METHOD_INSTANCE);
		switch ($level) {
			case 'Full': {
				$cmds = $this->getFullInstanceBackupPluginCommands($args);
				break;
			}
			case 'Incremental': {
				$cmds = $this->getIncrementalInstanceBackupPluginCommands($args);
				break;
			}
			case 'Differential': {
				// not supported
				break;
			}
		}
		return $cmds;
	}

	/**
	 * Common method to get instance backup commands.
	 * It is for all supported backup levels.
	 *
	 * @param array $args plugin options
	 * @param string $level backup level (ex. 'Full', 'Incremental' or 'Differential')
	 * @return array backup commands
	 */
	private function getInstanceBackupLevelCommand(array $args, string $level): array
	{
		$instance_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_EC2_INSTANCE_BACKUP
		], false);
		$account = $instance_args['account'] ?? '';
		$instance_ids = [];
		if (key_exists('instance-ids', $args)) {
			$instance_ids = explode(',', $args['instance-ids']);
			$instance_ids = array_map('trim', $instance_ids);
		};
		$description = $args['description'] ?? '';
		$tag_specs = $args['snapshot-tags'] ?? '';
		$copy_tags_from_source = key_exists('copy-tags-from-source', $instance_args);
		$exclude_boot_volume = key_exists('exclude-boot-volume', $instance_args);
		$exclude_data_volume_ids = [];
		if (key_exists('exclude-data-volume-ids', $instance_args)) {
			$exclude_data_volume_ids = explode(',', $instance_args['exclude-data-volume-ids']);
			$exclude_data_volume_ids = array_map('trim', $exclude_data_volume_ids);
		}
		$instance_props = [
			'description' => $description,
			'snapshot-tags' => $tag_specs,
			'copy-tags-from-source' => $copy_tags_from_source,
			'exclude-boot-volume' => $exclude_boot_volume,
			'exclude-data-volume-ids' => $exclude_data_volume_ids
		];
		$cmds = [];
		for ($i = 0; $i < count($instance_ids); $i++) {
			$snapshots = $this->createSnapshots(
				$account,
				$instance_ids[$i],
				$instance_props
			);
			for ($j = 0; $j < count($snapshots); $j++) {
				$args['snapshot-id'] = $snapshots[$j]['snapshot_id'];
				$args['volume-id'] = $snapshots[$j]['volume_id'];
				$args['instance-id'] = $instance_ids[$i];
				$cmds[] = $this->getSinglePluginCommand(
					$args,
					self::ACTION_INSTANCE_VOLUME_METADATA,
					$instance_ids[$i]
				);
				$cmds[] = $this->getSinglePluginCommand(
					$args,
					self::ACTION_INSTANCE_VOLUME_DATA,
					$instance_ids[$i]
				);
				$cmds[] = $this->getSinglePluginCommand(
					$args,
					self::ACTION_INSTANCE_VOLUME_INFO_METADATA,
					$instance_ids[$i]
				);
			}
			if ($snapshots) {
				$result = $this->setInstanceMetaData(
					$account,
					$instance_ids[$i],
					$snapshots,
					$args
				);
				if (!$result) {
					// error while setting meta-data, stop here
					break;
				}
				$cmds[] = $this->getSinglePluginCommand(
					$args,
					self::ACTION_INSTANCE_METADATA,
					$instance_ids[$i]
				);
			}
		}
		return $cmds;
	}

	/**
	 * Get volume backup plugin commands.
	 *
	 * @param array $args plugin options
	 * @return array plugin commands
	 */
	private function getVolumePluginCommands(array $args): array
	{
		$cmds = [];
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		$this->preparePluginCommandArgs($args, self::BACKUP_METHOD_VOLUME);
		switch ($level) {
			case 'Full': {
				$cmds = $this->getFullVolumeBackupPluginCommands($args);
				break;
			}
			case 'Incremental': {
				$cmds = $this->getIncrementalVolumeBackupPluginCommands($args);
				break;
			}
			case 'Differential': {
				// not supported
				break;
			}
		}
		return $cmds;
	}

	/**
	 * Common method to get volume backup commands.
	 * It is for all supported backup levels.
	 *
	 * @param array $args plugin options
	 * @param string $level backup level (ex. 'Full', 'Incremental' or 'Differential')
	 * @return array backup commands
	 */
	private function getVolumeBackupLevelCommand(array $args, string $level): array
	{
		$volume_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_EBS_VOLUME_BACKUP
		], false);
		$account = $volume_args['account'] ?? '';
		$volume_ids = [];
		if (key_exists('volume-ids', $args)) {
			$volume_ids = explode(',', $args['volume-ids']);
			$volume_ids = array_map('trim', $volume_ids);
		};
		$description = $args['description'] ?? '';
		$volume_props = [
			'description' => $description
		];
		$cmds = [];
		for ($i = 0; $i < count($volume_ids); $i++) {
			$snapshot = $this->createSnapshot(
				$account,
				$volume_ids[$i],
				$volume_props
			);
			// @TODO
			$args['snapshot-id'] = $snapshot['snapshot_id'];
			$args['volume-id'] = $volume_ids[$i];
			$cmds[] = $this->getSinglePluginCommand(
				$args,
				self::ACTION_VOLUME_METADATA,
				$volume_ids[$i]
			);
			$cmds[] = $this->getSinglePluginCommand(
				$args,
				self::ACTION_VOLUME_DATA,
				$volume_ids[$i]
			);
			$cmds[] = $this->getSinglePluginCommand(
				$args,
				self::ACTION_VOLUME_INFO_METADATA,
				$volume_ids[$i]
			);
		}
		return $cmds;
	}

	/**
	 * Common method to prepare plugin command parameters.
	 *
	 * @param array $args plugin options
	 * @param string $method backup method (ex. 'instance', 'volume' ...)
	 */
	private function preparePluginCommandArgs(array &$args, string $method): void
	{
		$methods = [
			self::BACKUP_METHOD_INSTANCE,
			self::BACKUP_METHOD_VOLUME
		];
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
	 * Get full EC2 instance backup commands.
	 *
	 * @param array $args plugin options
	 * @return array backup commands
	 */
	private function getFullInstanceBackupPluginCommands($args): array
	{
		return $this->getInstanceBackupLevelCommand($args, 'Full');
	}

	/**
	 * Get incremental EC2 instance backup commands.
	 *
	 * @param array $args plugin options
	 * @return array backup commands
	 */
	private function getIncrementalInstanceBackupPluginCommands($args): array
	{
		return $this->getInstanceBackupLevelCommand($args, 'Incremental');
	}

	/**
	 * Get full EBS volume backup commands.
	 *
	 * @param array $args plugin options
	 * @return array backup commands
	 */
	private function getFullVolumeBackupPluginCommands($args): array
	{
		return $this->getVolumeBackupLevelCommand($args, 'Full');
	}

	/**
	 * Get incremental EBS volume backup commands.
	 *
	 * @param array $args plugin options
	 * @return array backup commands
	 */
	private function getIncrementalVolumeBackupPluginCommands($args): array
	{
		return $this->getVolumeBackupLevelCommand($args, 'Incremental');
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
			case self::ACTION_INSTANCE_VOLUME_DATA: {
				$item_type = self::BACKUP_DATA_TYPE_INSTANCES;
				$dir = $args['instance-id'];
				$backup_cmd = $this->getBackupInstanceVolumeDataCommand($args, $args['snapshot-id']);
				$restore_cmd = $this->getRestoreInstanceVolumeCommand($args, $action, $item);
				$file = self::ACTION_VOLUME_DATA;
				$item_fm = $this->getFormattedFile(
					$file,
					$args['job-starttime'],
					$args['job-id'],
					$args['job-level']
				);
				$backup_path = $this->getBackupVolumeDataPath(
					$args,
					$item_type,
					$dir,
					$args['volume-id'],
					$item_fm
				);
				break;
			}
			case self::ACTION_VOLUME_DATA: {
				$item_type = self::BACKUP_DATA_TYPE_VOLUMES;
				$dir = $args['region'];
				$backup_cmd = $this->getBackupVolumeDataCommand($args, $args['snapshot-id']);
				$restore_cmd = $this->getRestoreVolumeCommand($args, $action, $item);
				$file = self::ACTION_VOLUME_DATA;
				$item_fm = $this->getFormattedFile(
					$file,
					$args['job-starttime'],
					$args['job-id'],
					$args['job-level']
				);
				$backup_path = $this->getBackupVolumeDataPath(
					$args,
					$item_type,
					$dir,
					$args['volume-id'],
					$item_fm
				);
				break;
			}
			case self::ACTION_INSTANCE_VOLUME_METADATA: {
				$item_type = self::BACKUP_DATA_TYPE_INSTANCES;
				$dir = $args['instance-id'];
				$backup_cmd = $this->getBackupInstanceVolumeMetaDataCommand($args, $args['snapshot-id']);
				$restore_cmd = $this->getRestoreInstanceVolumeCommand($args, $action, $item);
				$file = self::ACTION_VOLUME_METADATA;
				$item_fm = $this->getFormattedFile(
					$file,
					$args['job-starttime'],
					$args['job-id'],
					$args['job-level']
				);
				$backup_path = $this->getBackupVolumeMetaDataPath(
					$args,
					$item_type,
					$dir,
					$args['volume-id'],
					$item_fm
				);
				break;
			}
			case self::ACTION_INSTANCE_VOLUME_INFO_METADATA: {
				$item_type = self::BACKUP_DATA_TYPE_INSTANCES;
				$dir = $args['instance-id'];
				$backup_cmd = $this->getBackupInstanceVolumeMetaDataCommand($args, $args['snapshot-id']);
				$restore_cmd = $this->getRestoreInstanceVolumeCommand($args, $action, $item);
				$file = $this->getFormattedMetaDataFile(self::ACTION_VOLUME_METADATA);
				$backup_path = $this->getBackupVolumeMetaDataPath(
					$args,
					$item_type,
					$dir,
					$args['volume-id'],
					$file
				);
				break;
			}
			case self::ACTION_VOLUME_METADATA: {
				$item_type = self::BACKUP_DATA_TYPE_VOLUMES;
				$dir = $args['region'];
				$backup_cmd = $this->getBackupVolumeMetaDataCommand($args, $args['snapshot-id']);
				$restore_cmd = $this->getRestoreVolumeCommand($args, $action, $item);
				$file = self::ACTION_VOLUME_METADATA;
				$item_fm = $this->getFormattedFile(
					$file,
					$args['job-starttime'],
					$args['job-id'],
					$args['job-level']
				);
				$backup_path = $this->getBackupVolumeMetaDataPath(
					$args,
					$item_type,
					$dir,
					$args['volume-id'],
					$item_fm
				);
				break;
			}
			case self::ACTION_VOLUME_INFO_METADATA: {
				$item_type = self::BACKUP_DATA_TYPE_VOLUMES;
				$dir = $args['region'];
				$backup_cmd = $this->getBackupVolumeMetaDataCommand($args, $args['snapshot-id']);
				$restore_cmd = $this->getRestoreVolumeCommand($args, $action, $item);
				$file = $this->getFormattedMetaDataFile(self::ACTION_VOLUME_METADATA);
				$backup_path = $this->getBackupVolumeMetaDataPath(
					$args,
					$item_type,
					$dir,
					$args['volume-id'],
					$file
				);
				break;
			}
			case self::ACTION_INSTANCE_METADATA: {
				$dir = $args['instance-id'];
				$backup_cmd = $this->getBackupInstanceMetaDataCommand($args, $args['instance-id']);
				$restore_cmd = $this->getRestoreInstanceCommand($args, $action, $item);
				$backup_path = $this->getBackupInstanceMetaDataPath(
					$args,
					self::BACKUP_DATA_TYPE_INSTANCES,
					$dir,
					$action
				);
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
	 * Get backup EBS volume data path.
	 *
	 * @param array $args plugin options
	 * @param array $type item type
	 * @param string $main_dir item main directory
	 * @param string $dir volume directory name
	 * @param string $file volume data file name (without extension)
	 * @return string path
	 */
	private function getBackupVolumeDataPath(array $args, string $type, string $main_dir, string $dir, string $file): string
	{
		$pname = $this->getPluginName();
		$ext = 'ebs';
		$path = sprintf(
			'/#%s/%s/%s/%s/%s/%s.%s',
			$pname,
			$args['plugin-config'] ?? '',
			$type,
			$main_dir,
			$dir,
			$file,
			$ext
		);
		return $path;
	}

	/**
	 * Get backup EBS volume meta-data path.
	 *
	 * @param array $args plugin options
	 * @param string $type item type
	 * @param string $main_dir item main directory
	 * @param string $dir volume directory name
	 * @param string $file volume meta-data file name (without extension)
	 * @return string path
	 */
	private function getBackupVolumeMetaDataPath(array $args, string $type, string $main_dir, string $dir, string $file): string
	{
		$pname = $this->getPluginName();
		$ext = 'json';
		$path = sprintf(
			'/#%s/%s/%s/%s/%s/%s.%s',
			$pname,
			$args['plugin-config'] ?? '',
			$type,
			$main_dir,
			$dir,
			$file,
			$ext
		);
		return $path;
	}

	/**
	 * Get backup EC2 instance volume meta-data path.
	 *
	 * @param array $args plugin options
	 * @param string $type item type
	 * @param string $dir instance directory
	 * @param string $file meta-data file name (without extension)
	 * @return string path
	 */
	private function getBackupInstanceMetaDataPath(array $args, string $type, string $dir, string $file): string
	{
		$pname = $this->getPluginName();
		$ext = 'json';
		$path = sprintf(
			'/#%s/%s/%s/%s/%s.%s',
			$pname,
			$args['plugin-config'] ?? '',
			$type,
			$dir,
			$file,
			$ext
		);
		return $path;
	}

	/**
	 * Get EC2 instance EBS volume data backup plugin command.
	 *
	 * @param array $args plugin options
	 * @param string $snapshot_id snapshot identifier
	 * @return string backup snapshot data command
	 */
	private function getBackupInstanceVolumeDataCommand(array $args, string $snapshot_id): string
	{
		$action = 'command/backup';
		$backup_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_BACKUP
		], false);
		$backup_args['volume-data'] = true;
		$backup_args['job-name'] = $args['job-name'];
		$backup_args['job-level'] = $args['job-level'];
		$backup_args['instance-id'] = $args['instance-id'];
		$backup_args['volume-id'] = $args['volume-id'];
		$backup_args['snapshot-id'] = $snapshot_id;
		$cmd = $this->getPluginCommand($action, $backup_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get EC2 instance EBS volume meta-data backup plugin command.
	 *
	 * @param array $args plugin options
	 * @param string $snapshot_id snapshot identifier
	 * @return string backup snapshot meta-data command
	 */
	private function getBackupInstanceVolumeMetaDataCommand(array $args, string $snapshot_id): string
	{
		$action = 'command/backup';
		$backup_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_BACKUP
		], false);
		$backup_args['volume-metadata'] = true;
		$backup_args['instance-id'] = $args['instance-id'];
		$backup_args['volume-id'] = $args['volume-id'];
		$backup_args['snapshot-id'] = $snapshot_id;
		$cmd = $this->getPluginCommand($action, $backup_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get EC2 snapshot data backup plugin command.
	 *
	 * @param array $args plugin options
	 * @param string $snapshot_id snapshot identifier
	 * @return string backup snapshot data command
	 */
	private function getBackupVolumeDataCommand(array $args, string $snapshot_id): string
	{
		$action = 'command/backup';
		$backup_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_BACKUP
		], false);
		$backup_args['volume-data'] = true;
		$backup_args['job-name'] = $args['job-name'];
		$backup_args['job-level'] = $args['job-level'];
		$backup_args['volume-id'] = $args['volume-id'];
		$backup_args['snapshot-id'] = $snapshot_id;
		$cmd = $this->getPluginCommand($action, $backup_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get EC2 snapshot meta-data backup plugin command.
	 *
	 * @param array $args plugin options
	 * @param string $snapshot_id snapshot identifier
	 * @return string backup snapshot meta-data command
	 */
	private function getBackupVolumeMetaDataCommand(array $args, string $snapshot_id): string
	{
		$action = 'command/backup';
		$backup_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_BACKUP
		], false);
		$backup_args['volume-metadata'] = true;
		$backup_args['volume-id'] = $args['volume-id'];
		$backup_args['snapshot-id'] = $snapshot_id;
		$cmd = $this->getPluginCommand($action, $backup_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get EC2 instance meta-data backup plugin command.
	 *
	 * @param array $args plugin options
	 * @param string $instance_id instance identifier
	 * @return string backup command
	 */
	private function getBackupInstanceMetaDataCommand(array $args, string $instance_id): string
	{
		$action = 'command/backup';
		$backup_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_BACKUP
		], false);
		$backup_args['instance-metadata'] = true;
		$backup_args['job-name'] = $args['job-name'];
		$backup_args['instance-id'] = $instance_id;
		$cmd = $this->getPluginCommand($action, $backup_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get EC2 instance restore plugin command.
	 *
	 * @param array $args plugin options
	 * @param string $raction restore action
	 * @param string $item restore item
	 * @return string restore command
	 */
	private function getRestoreInstanceCommand(array $args, string $raction, string $item): string
	{
		$action = 'command/restore';
		$restore_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_RESTORE,
			self::PARAM_CAT_EC2_INSTANCE_RESTORE

		], false);
		$restore_args['plugin-config'] = $args['plugin-config'];
		$restore_args['job-starttime'] = $args['job-starttime'];
		$restore_args['job-id'] = $args['job-id'];
		$restore_args['job-name'] = $args['job-name'];
		$restore_args['job-level'] = $args['job-level'];
		$restore_args['restore-action'] = $raction;
		$restore_args['instance-id'] = $args['instance-id'];
		$restore_args['where'] = '%w';
		$cmd = $this->getPluginCommand($action, $restore_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get instance EBS volume restore plugin command.
	 *
	 * @param array $args plugin options
	 * @param string $raction restore action
	 * @param string $item restore item
	 * @return string restore command
	 */
	private function getRestoreInstanceVolumeCommand(array $args, string $raction, string $item): string
	{
		$action = 'command/restore';
		$restore_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_RESTORE

		], false);
		$restore_args['plugin-config'] = $args['plugin-config'];
		$restore_args['job-starttime'] = $args['job-starttime'];
		$restore_args['job-id'] = $args['job-id'];
		$restore_args['job-name'] = $args['job-name'];
		$restore_args['job-level'] = $args['job-level'];
		$restore_args['restore-action'] = $raction;
		$restore_args['volume-id'] = $args['volume-id'];
		$restore_args['instance-id'] = $args['instance-id'];
		$restore_args['where'] = '%w';
		$cmd = $this->getPluginCommand($action, $restore_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get EBS volume restore plugin command.
	 *
	 * @param array $args plugin options
	 * @param string $raction restore action
	 * @param string $item restore item
	 * @return string restore command
	 */
	private function getRestoreVolumeCommand(array $args, string $raction, string $item): string
	{
		$action = 'command/restore';
		$restore_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_RESTORE

		], false);
		$restore_args['plugin-config'] = $args['plugin-config'];
		$restore_args['job-starttime'] = $args['job-starttime'];
		$restore_args['job-id'] = $args['job-id'];
		$restore_args['job-name'] = $args['job-name'];
		$restore_args['job-level'] = $args['job-level'];
		$restore_args['restore-action'] = $raction;
		$restore_args['volume-id'] = $args['volume-id'];
		$restore_args['region'] = $args['region'];
		$restore_args['where'] = '%w';
		$cmd = $this->getPluginCommand($action, $restore_args);
		return implode(' ', $cmd);
	}

	/**
	 * Create crash-consistent EBS volume snapshot.
	 *
	 * @param string $account AWS account name
	 * @param string $volume_id EBS volume identifier
	 * @param array $props parsed snapshot properties or empty array on error
	 */
	private function createSnapshot(string $account, string $volume_id, array $props): array
	{
		$params = [
			'ec2',
			'create-snapshot',
			"--volume-id \"$volume_id\""
		];
		if ($props['description']) {
			$params[] = "--description \"{$props['description']}\"";
		}
		$aws_cmd = $this->getModule('aws_command');
		$aws_cmd::addGlobalOptions($params);
		$snapshot = [];
		$result = $aws_cmd->execCommand($account, $params);
		if ($result['error'] == 0) {
			$snap = $result['output'] ?? [];
			$snapshot = AmazonEBSSnapshot::parseObject($snap);
		} else {
			$msg = var_export($result['output'], true);
			$emsg = "Error: {$result['error']}, Msg: {$msg}";
			Plugins::log(Plugins::LOG_ERROR, "Error while creating volume snapshot. {$emsg}");
		}
		return $snapshot;
	}

	/**
	 * Create crash-consistent EC2 instance snapshots.
	 *
	 * @param string $account AWS account name
	 * @param string $instance_id EC2 instance identifier
	 * @param array $props parsed snapshot properties or empty array on error
	 */
	private function createSnapshots(string $account, string $instance_id, array $props): array
	{
		$instance_spec = AmazonEC2Instance::getInstanceSpecification(
			$instance_id,
			$props['exclude-boot-volume'],
			$props['exclude-data-volume-ids']
		);
		$params = [
			'ec2',
			'create-snapshots',
			"--instance-specification '{$instance_spec}'"
		];
		if ($props['description']) {
			$params[] = "--description \"{$props['description']}\"";
		}
		if ($props['snapshot-tags']) {
			$tag_specs = AmazonEC2Tag::getTagSpecificationsFromString('snapshot', $props['snapshot-tags']);
			$params[] = "--tag-specifications '{$tag_specs}'";
		}
		if ($props['copy-tags-from-source']) {
			$params[] = '--copy-tags-from-source volume';
		}
		$aws_cmd = $this->getModule('aws_command');
		$aws_cmd::addGlobalOptions($params);
		$snapshots = [];
		$result = $aws_cmd->execCommand($account, $params);
		if ($result['error'] == 0) {
			$snaps = $result['output']->Snapshots ?? [];
			$snapshot_ids = [];
			for ($i = 0; $i < count($snaps); $i++) {
				$snapshots[$i] = AmazonEBSSnapshot::parseObject($snaps[$i]);
				$snapshot_ids[] = $snapshots[$i]['snapshot_id'];
			}
		} else {
			$msg = var_export($result['output'], true);
			$emsg = "Error: {$result['error']}, Msg: {$msg}";
			Plugins::log(Plugins::LOG_ERROR, "Error while creating instance snapshots. {$emsg}");
		}
		return $snapshots;
	}

	/**
	 * Wait until all given snapshots will be fully created and ready to back up.
	 * NOTE: This is intentional to not give ending after timeout. Creating EBS
	 * snapshots for extremely big volumes (>10TB) can take even 36 hours and longer.
	 * If something goes wrong, all the time user can cancel the job and stop it.
	 *
	 * @param string $account AWS account name
	 * @param array $snapshot_ids snapshots identifiers
	 * @return bool true on success, false otherwise
	 */
	private function waitOnSnapshotReady(string $account, array $snapshot_ids): bool
	{
		$result = true;
		while (true) {
			$completed_ids = self::getCompletedSnapshots($account, $snapshot_ids);
			if (is_null($completed_ids)) {
				$result = false;
				break;
			}
			$snapshot_ids = array_diff($snapshot_ids, $completed_ids);
			if (count($snapshot_ids) == 0) {
				// All snapshots are ready to use
				break;
			}
			sleep(15); // 15 seconds
		}
		return $result;
	}

	/**
	 * Get completed snapshot list.
	 *
	 * @param string $account AWS account name
	 * @param array $snapshot_ids snapshot identifiers to check
	 * @return null|array completed snapshot identifiers or null on error
	 */
	private static function getCompletedSnapshots(string $account, array $snapshot_ids): ?array
	{
		$snapshots = AmazonEBSSnapshot::describe($account, $snapshot_ids);
		if (!$snapshots) {
			$snap_ids = var_export($snapshot_ids, true);
			Plugins::log(
				Plugins::LOG_WARNING,
				"No result from describe snapshots. SnapshotIds: '{$snap_ids}'."
			);
		}
		$snapshot_len = count($snapshots);
		$completed_ids = [];
		for ($i = 0; $i < $snapshot_len; $i++) {
			$is_completed = AmazonEBSSnapshot::isSnapshotCompleted($snapshots[$i]);
			$is_error = AmazonEBSSnapshot::isSnapshotError($snapshots[$i]);
			if ($is_completed) {
				$completed_ids[] = $snapshots[$i]['snapshot_id'];
				$imsg = sprintf('Snapshot %s completed successfully.', $snapshots[$i]['snapshot_id']);
				Plugins::log(Plugins::LOG_INFO, $imsg);
			} elseif ($is_error) {
				$imsg = sprintf('Snapshot %s finished with ERROR state.', $snapshots[$i]['snapshot_id']);
				Plugins::log(Plugins::LOG_ERROR, $imsg);
				return null;
			} else {
				$imsg = sprintf(
					'Snapshot %s - %s completed. Please wait...',
					$snapshots[$i]['snapshot_id'],
					$snapshots[$i]['progress']
				);
				Plugins::log(Plugins::LOG_INFO, $imsg);
			}
		}
		return $completed_ids;
	}

	/**
	 * Register EC2 instance AMI.
	 *
	 * @param array $args plugin command parameters
	 * @param array $instance_metadata instance meta-data
	 * @param array $job_metadata job meta-data
	 * @return null|string new AMI ID or null or error
	 */
	private function registerImage(array $args, array $instance_metadata, array $job_metadata): ?string
	{
		// Prepare EBS block device mappings
		$dev_mappings = [];
		$snapshot_ids = [];
		foreach ($instance_metadata['volumes'] as $volume_id => $params) {
			if (!key_exists($volume_id, $job_metadata['volumes'])) {
				Plugins::log(
					Plugins::LOG_WARNING,
					"Instance backed up volume '{$volume_id}' not selected to restore. Restored instance may be incompleted."
				);
				continue;
			}
			$volume = $job_metadata['volumes'][$volume_id] ?? [];
			$snapshot_id = $volume['snapshot_id'] ?? '';
			if (!$snapshot_id) {
				Plugins::log(
					Plugins::LOG_WARNING,
					"Snapshot from volume '{$volume_id}' for instance restore not found."
				);
				continue;
			}
			$snapshot_ids[] = $snapshot_id;
			$ebs = [
				'SnapshotId' => $snapshot_id,
				'DeleteOnTermination' => $params['delete_on_termination'],
			];
			if (isset($volume['volume_type'])) {
				$ebs['VolumeType'] = $volume['volume_type'];
			}
			if (isset($params['ebs_card_index'])) {
				$ebs['EbsCardIndex'] = $params['ebs_card_index'];
			}
			if (isset($volume['iops'])) {
				$ebs['Iops'] = $volume['iops'];
			}
			if (isset($volume['throughput'])) {
				$ebs['Throughput'] = $volume['throughput'];
			}
			$dev_mappings[] = [
				'DeviceName' => $params['device_name'],
				'Ebs' => $ebs
			];
		}
		$block_device_mappings = json_encode($dev_mappings);

		// Wait until snapshots are completed
		$this->waitOnSnapshotReady($args['account'], $snapshot_ids);

		$name = sprintf('restore-bacularis-ami-%s', date('Y-m-d_His'));

		$props = [
			'name' => $name,
		];
		if (isset($instance_metadata['boot_mode'])) {
			$props['boot_mode'] = $instance_metadata['boot_mode'];
		}
		if (isset($instance_metadata['tpm_mode'])) {
			$props['tpm_mode'] = $instance_metadata['tpm_mode'];
		}
		if (isset($instance_metadata['architecture'])) {
			$props['architecture'] = $instance_metadata['architecture'];
		}
		if (isset($instance_metadata['root_device_name'])) {
			$props['root_device_name'] = $instance_metadata['root_device_name'];
		}
		if (isset($instance_metadata['virtualization_type'])) {
			$props['virtualization_type'] = $instance_metadata['virtualization_type'];
		}
		if (isset($instance_metadata['sriov_net_support'])) {
			$props['sriov_net_support'] = $instance_metadata['sriov_net_support'];
		}
		if (isset($instance_metadata['ena_support'])) {
			$props['ena_support'] = $instance_metadata['ena_support'];
		}
		if (isset($instance_metadata['metadata_options_http_tokens']) && $instance_metadata['metadata_options_http_tokens'] == 'required') {
			$props['imds_support'] = 'v2.0';
		}
		$props['block_device_mappings'] = $block_device_mappings;

		$result = AmazonEC2Image::registerImage($args['account'], $props);
		if ($result['state']) {
			Plugins::log(
				Plugins::LOG_INFO,
				"New AMI '{$result['image_id']}' has been registered."
			);
		} else {
			Plugins::log(
				Plugins::LOG_WARNING,
				"Command for waiting on register AMI timed out. AMI '{$result['image_id']}' may be not ready yet."
			);
		}
		return $result['image_id'];
	}

	/**
	 * Run EC2 instance from restored snapshots.
	 *
	 * @param string $image_id AMI ID to run
	 * @param array $args plugin command parameters
	 * @param array $instance_metadata instance meta-data
	 * @param array $job_metadata job meta-data parameters
	 * @return array new instance meta-data or empty list on error
	 */
	private function runInstance(string $image_id, array $args, array $instance_metadata, array $job_metadata): array
	{
		// Instance type (t3.micro, t2.small ...etc.)
		$instance_type = '';
		if (key_exists('instance-type', $args)) {
			$instance_type = $args['instance-type'];
		} elseif (isset($instance_metadata['instance_type'])) {
			$instance_type = $instance_metadata['instance_type'];
		}

		// Availability zone ID (or name) to place the restored instance
		$placement_props = [];
		$apply_subnet = true;
		if (key_exists('availability-zone-id', $args)) {
			$placement_props['availability_zone_id'] = $args['availability-zone-id'];
			$apply_subnet = false;
		} elseif (key_exists('availability-zone', $args)) {
			$placement_props['availability_zone'] = $args['availability-zone'];
			$apply_subnet = false;
		} elseif (isset($instance_metadata['availability_zone_id'])) {
			$placement_props['availability_zone_id'] = $instance_metadata['availability_zone_id'];
			$apply_subnet = false;
		}

		// Placement group ID (or name)
		if (key_exists('placement-group-id', $args)) {
			$placement_props['group_id'] = $args['placement-group-id'];
		} elseif (key_exists('placement-group', $args)) {
			$placement_props['group'] = $args['placement-group'];
		} elseif (isset($instance_metadata['placement_group_id'])) {
			$placement_props['group_id'] = $instance_metadata['placement_group_id'];
		}

		// Partition number
		if (key_exists('placement-partition-number', $args)) {
			$placement_props['partition_number'] = $args['placement-partition-number'];
		} elseif (isset($instance_metadata['placement_partition_number'])) {
			$placement_props['partition_number'] = $instance_metadata['placement_partition_number'];
		}

		// Instance placement
		$placement = AmazonEC2Instance::getPlacement($placement_props);

		// Subnet ID
		$subnet_id = '';
		if (key_exists('subnet-id', $args)) {
			$subnet_id = $args['subnet-id'];
		} elseif (isset($instance_metadata['subnet_id'])) {
			$subnet_id = $instance_metadata['subnet_id'];
		}

		// Security groups IDs (or names)
		$sgs_ids = $sgs_names = [];
		if (key_exists('security-group-ids', $args)) {
			$sgs_list = explode(',', $args['security-group-ids']);
			$sgs_ids = array_map('trim', $sgs_list);
		} elseif (key_exists('security-groups', $args)) {
			$sgs_list = explode(',', $args['security-groups']);
			$sgs_names = array_map('trim', $sgs_list);
		} elseif (isset($instance_metadata['security_groups'])) {
			$sgs_ids = array_map(fn ($item) => $item['group_id'], $instance_metadata['security_groups']);
		}
		$security_group_ids = $security_groups = [];
		if ($sgs_ids) {
			$security_group_ids = '"' . implode('" "', $sgs_ids) . '"';
		} elseif ($sgs_names) {
			$security_groups = '"' . implode('" "', $sgs_names) . '"';
		}

		// SSH key pair name
		$key_name = '';
		if (key_exists('key-name', $args)) {
			$key_name = $args['key-name'];
		} elseif (isset($instance_metadata['key_name'])) {
			$key_name = $instance_metadata['key_name'];
		}

		// Launch template
		$lt_props = [];
		if (key_exists('launch-template', $args)) {
			$lt_props['launch_template_name'] = $args['launch-template'];
		} elseif (key_exists('launch-template-id', $args)) {
			$lt_props['launch_template_id'] = $args['launch-template-id'];
		}
		if (key_exists('launch-template-version', $args)) {
			$lt_props['launch_template_version'] = $args['launch-template-version'];
		}
		$launch_template = AmazonEC2Instance::getLaunchTemplate($lt_props);

		// Private IP address (used for single instance restore)
		$private_ip_addr = '';
		if (key_exists('private-ip-address', $args)) {
			$private_ip_addr = $args['private-ip-address'];
		}

		// Instance metadata options
		$mdo_props = [];
		if (key_exists('metadata_options_http_tokens', $instance_metadata)) {
			$mdo_props['http_tokens'] = $instance_metadata['metadata_options_http_tokens'];
		}
		if (key_exists('metadata_options_http_endpoint', $instance_metadata)) {
			$mdo_props['http_endpoint'] = $instance_metadata['metadata_options_http_endpoint'];
		}
		if (key_exists('metadata_options_http_put_response_hop_limit', $instance_metadata)) {
			$mdo_props['http_put_response_hop_limit'] = $instance_metadata['metadata_options_http_put_response_hop_limit'];
		}
		if (key_exists('metadata_options_http_protocol_ipv6', $instance_metadata)) {
			$mdo_props['http_protocol_ipv6'] = $instance_metadata['metadata_options_http_protocol_ipv6'];
		}
		if (key_exists('metadata_options_instance_metadata_tags', $instance_metadata)) {
			$mdo_props['instance_metadata_tags'] = $instance_metadata['metadata_options_instance_metadata_tags'];
		}
		$metadata_options = AmazonEC2Instance::getMetadataOptions($mdo_props);

		// User data
		$user_data = $user_data_file = '';
		if (key_exists('user_data', $instance_metadata)) {
			[$tmpdir, $tmpprefix] = $this->getFileParams('user-data');
			$user_data_file = tempnam($tmpdir, $tmpprefix);
			$user_data_body = base64_decode($instance_metadata['user_data']);
			if ($user_data_body === false) {
				Plugins::log(Plugins::LOG_ERROR, "Unable to decode user data script '{$instance_metadata['user_data']}'.");
			} elseif (file_put_contents($user_data_file, $user_data_body) === false) {
				Plugins::log(Plugins::LOG_ERROR, "Unable to write to '{$user_data_file}' file.");
			} else {
				$user_data = sprintf('file://%s', $user_data_file);
			}
		}

		// CPU options
		$co_props = [];
		if (key_exists('cpu_options_core_count', $instance_metadata)) {
			$co_props['core_count'] = $instance_metadata['cpu_options_core_count'];
		}
		if (key_exists('cpu_options_threads_per_core', $instance_metadata)) {
			$co_props['threads_per_core'] = $instance_metadata['cpu_options_threads_per_core'];
		}
		if (key_exists('cpu_options_amd_sev_snp', $instance_metadata)) {
			$co_props['amd_sev_snp'] = $instance_metadata['cpu_options_amd_sev_snp'];
		}
		if (key_exists('cpu_options_nested_virtualization', $instance_metadata)) {
			$co_props['nested_virtualization'] = $instance_metadata['cpu_options_nested_virtualization'];
		}
		$cpu_options = AmazonEC2Instance::getCPUOptions($co_props);

		// EBS optimized
		$ebs_optimized = false;
		if (key_exists('ebs_optimized', $instance_metadata)) {
			$ebs_optimized = $instance_metadata['ebs_optimized'];
		}

		// IAM instance profile
		$iam_instance_profile_arn = '';
		if (key_exists('iam_instance_profile_arn', $instance_metadata)) {
			$iam_instance_profile_arn = $instance_metadata['iam_instance_profile_arn'];
		}

		// Tags
		$tags = [];
		if (key_exists('tags', $instance_metadata)) {
			$tags = $instance_metadata['tags'];
		}

		// Prepare run instance properties
		$props = [];
		if ($image_id) {
			$props['image_id'] = $image_id;
		}
		if ($instance_type) {
			$props['instance_type'] = $instance_type;
		}
		if ($placement) {
			$props['placement'] = $placement;
		}
		if ($subnet_id && $apply_subnet) {
			$props['subnet_id'] = $subnet_id;
		}
		if ($security_groups) {
			$props['security_groups'] = $security_groups;
		} elseif ($security_group_ids) {
			$props['security_group_ids'] = $security_group_ids;
		}
		if ($key_name) {
			$props['key_name'] = $key_name;
		}
		if ($launch_template) {
			$props['launch_template'] = $launch_template;
		}
		if ($private_ip_addr) {
			$props['private_ip_addr'] = $private_ip_addr;
		}
		if ($metadata_options) {
			$props['metadata_options'] = $metadata_options;
		}
		if ($user_data) {
			$props['user_data'] = $user_data;
		}
		if ($cpu_options) {
			$props['cpu_options'] = $cpu_options;
		}
		if ($tags) {
			$props['tag_specifications'] = AmazonEC2Tag::getTagSpecifications('instance', $tags);
		}
		if ($ebs_optimized) {
			$props['ebs_optimized'] = $ebs_optimized;
		}
		if ($iam_instance_profile_arn) {
			$props['iam_instance_profile'] = json_encode([
				'Arn' => $iam_instance_profile_arn
			]);
		}

		// Run instance
		$instance = [];
		$result = AmazonEC2Instance::runInstance($args['account'], $props);
		if ($result['error'] == 0) {
			if ($user_data_file) {
				unlink($user_data_file);
			}
			$instances = $result['instances'];
			for ($i = 0; $i < count($instances); $i++) {
				Plugins::log(
					Plugins::LOG_INFO,
					"New EC2 instance '{$instances[$i]['instance_id']}' is starting."
				);

				/**
				 * Describe it here because description from run-instance
				 * does not contain block device mappings.
				 */
				$instance = AmazonEC2Instance::describe(
					$args['account'],
					$instances[$i]['instance_id']
				);
				if (!$instance) {
					Plugins::log(
						Plugins::LOG_ERROR,
						"Errow while describing instance '{$instances[$i]['instance_id']}'."
					);
					continue;
				}

				// Add volume tags
				$this->createVolumeTags(
					$args['account'],
					$instance,
					$instance_metadata['volumes'],
					$job_metadata['volumes']
				);

				// Add Bacularis tags
				$this->createBacularisRestoreTags(
					$args['account'],
					$instances[$i]['instance_id']
				);
			}
		} else {
			$msg = var_export($result['output'], true);
			$emsg = "Error: {$result['error']}, Msg: {$msg}";
			Plugins::log(
				Plugins::LOG_ERROR,
				"Error while creating new EC2 instance. {$emsg}"
			);
		}
		return $instance;
	}

	/**
	 * Create volume tags.
	 *
	 * @param string $account AWS account name
	 * @param array $instance new instance meta-data
	 * @param array $imetadata original instance meta-data (created on backup)
	 * @param array $vmetadata original volume metadata (created on backup)
	 * @return bool true on success, false otherwise
	 */
	private function createVolumeTags(string $account, array $instance, array $imetadata, array $vmetadata): bool
	{
		$success = true;
		$new_volumes = $instance['block_device_mappings'];
		for ($i = 0; $i < count($new_volumes); $i++) {
			$device_name = $new_volumes[$i]['device_name'];
			$vol_id = null;
			foreach ($imetadata as $volume_id => $params) {
				if ($params['device_name'] === $device_name) {
					$vol_id = $volume_id;
					break;
				}
			}
			if (isset($vmetadata[$vol_id]['tags']) && $vmetadata[$vol_id]['tags']) {
				/**
				 * Apply tags on new volumes.
				 * This is done this way because AWS CLI does not support
				 * adding multi-tags on selected volumes on instance launch.
				 */
				$ret = AmazonEC2Tag::createTags(
					$account,
					[$new_volumes[$i]['volume_id']],
					$vmetadata[$vol_id]['tags']
				);
				if ($ret) {
					// Add Bacularis tags
					$ret = $this->createBacularisRestoreTags(
						$account,
						$new_volumes[$i]['volume_id']
					);
					if (!$ret) {
						$success = false;
					}
				} else {
					Plugins::log(
						Plugins::LOG_WARNING,
						"Errow while adding user-defined tags to volume '{$new_volumes[$i]['volume_id']}'."
					);
					$success = false;
					// do nothing
				}
			}
		}
		return $success;
	}

	/**
	 * Create Bacularis restore tags.
	 *
	 * @param string $account AWS account name
	 * @param string $resource_id resource identifier
	 * @return bool true on success, false otherwise
	 */
	private function createBacularisRestoreTags(string $account, string $resource_id): bool
	{
		$tags = [
			[
				'Key' => 'BacularisRestore',
				'Value' => "true"
			],
			[
				'Key' => 'BacularisRestoreDate',
				'Value' => date(DateTimeInterface::RFC3339)
			]
		];
		$ret = AmazonEC2Tag::createTags(
			$account,
			[$resource_id],
			$tags
		);
		if (!$ret) {
			Plugins::log(
				Plugins::LOG_WARNING,
				"Errow while adding Bacularis tags to resource '{$resource_id}'."
			);
		}
		return $ret;
	}

	private static function createVolumeMetaData(string $account, string $volume_id, array $props): array
	{
		$volume = AmazonEBSVolume::describe($account, $volume_id);
		if (!$volume) {
			$emsg = "Unable to get volume details. VolumeId: '{$volume_id}'";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
			return [];
		}
		// User tags may not begin with 'aws:'
		$tags = array_filter(
			$volume['tags'],
			fn ($item) => preg_match('/^aws:/', $item['Key']) === 0
		);
		$metadata = [
			'type' => self::RECORD_VOLUME_METADATA,
			'volume_id' => $volume['volume_id'],
			'volume_size' => $volume['size'],
			'volume_type' => $volume['volume_type'],
			'iops' => $volume['iops'],
			'throughput' => $volume['throughput'],
			'encrypted' => $volume['encrypted'],
			'kms_key_id' => $volume['kms_key_id'],
			'tags' => $tags,
			'region' => $props['region']
		];
		return $metadata;
	}


	/**
	 * Create instance meta-data.
	 *
	 * @param string $account AWS account name
	 * @param string $instance_id EC2 instance identifier
	 * @param array $snapshots snapshot details
	 * @param array $args plugin command parameters
	 * @return array instance meta-data
	 */
	private function createInstanceMetaData(string $account, string $instance_id, array $snapshots, array $args): array
	{
		$instance = AmazonEC2Instance::describe($account, $instance_id);
		$user_data = AmazonEC2Instance::describeAttribute($account, $instance_id, 'userData');
		$block_device_mappings = self::getBlockDeviceMappings($instance['block_device_mappings'], $snapshots);

		// User tags may not begin with 'aws:'
		$tags = array_filter(
			$instance['tags'],
			fn ($item) => preg_match('/^aws:/', $item['Key']) === 0
		);

		$metadata = [
			'type' => self::RECORD_INSTANCE_METADATA,
			'instance_type' => $instance['instance_type'],
			'instance_id' => $instance['instance_id'],
			'image_id' => $instance['image_id'],
			'subnet_id' => $instance['subnet_id'],
			'security_groups' => $instance['security_groups'],
			'key_name' => $instance['key_name'],
			'region' => ($args['region'] ?? ''),
			'availability_zone_id' => $instance['placement_availability_zone_id'],
			'availability_zone' => $instance['placement_availability_zone'],
			'placement_group' => $instance['placement_group_name'],
			'placement_group_id' => $instance['placement_group_id'],
			'placement_partition_number' => $instance['placement_patrition_number'],
			'placement_tenancy' => $instance['placement_tenancy'],
			'root_device_type' => $instance['root_device_type'],
			'root_device_name' => $instance['root_device_name'],
			'virtualization_type' => $instance['virtualization_type'],
			'cpu_options_core_count' => $instance['cpu_options_core_count'],
			'cpu_options_threads_per_core' => $instance['cpu_options_threads_per_core'],
			'boot_mode' => $instance['current_instance_boot_mode'],
			'ena_support' => $instance['ena_support'],
			'tpm_support' => $instance['tpm_support'],
			'ebs_optimized' => $instance['ebs_optimized'],
			'architecture' => $instance['architecture'],
			'tags' => $tags,
			'iam_instance_profile_arn' => $instance['iam_instance_profile_arn'],
			'metadata_options_http_tokens' => $instance['metadata_options_http_tokens'],
			'metadata_options_http_put_response_hop_limit' => $instance['metadata_options_http_put_response_hop_limit'],
			'metadata_options_http_endpoint' => $instance['metadata_options_http_endpoint'],
			'metadata_options_http_protocol_ipv6' => $instance['metadata_options_http_protocol_ipv6'],
			'metadata_options_instance_metadata_tags' => $instance['metadata_options_instance_metadata_tags'],
			'sriov_net_support' => $instance['sriov_net_support'],
			'user_data' => $user_data->UserData->Value ?? '',
			'volumes' => $block_device_mappings
		];
		return $metadata;
	}

	/**
	 * Set instance meta-data in backup meta-data file.
	 *
	 * @param string $account AWS account name
	 * @param string $instance_id EC2 instance identifier
	 * @param array $snapshots snapshot details
	 * @param array $args plugin command parameters
	 * @return bool true on success, false otherwise
	 */
	private function setInstanceMetaData(string $account, string $instance_id, array $snapshots, array $args): bool
	{
		$metadata = $this->createInstanceMetaData(
			$account,
			$instance_id,
			$snapshots,
			$args
		);
		$props = $this->getJobState($args['job-name'], self::JOB_TYPE_BACKUP);
		if (!key_exists('instances', $props)) {
			$props['instances'] = [];
		}
		$props['instances'][$instance_id] = $metadata;
		$result = $this->setJobState($props, $args['job-name'], self::JOB_TYPE_BACKUP);
		if (!$result) {
			Plugins::log(
				Plugins::LOG_ERROR,
				"Error while setting instance meta data."
			);
		}
		return $result;
	}

	/**
	 * Get block device mappings.
	 *
	 * @param array $devices devices description
	 * @param array $snapshots snapshots definition
	 * @return array volume mappings
	 */
	private static function getBlockDeviceMappings(array $devices, array $snapshots): array
	{
		$mappings = [];
		for ($i = 0; $i < count($devices); $i++) {
			$snap = [];
			for ($j = 0; $j < count($snapshots); $j++) {
				if ($devices[$i]['volume_id'] === $snapshots[$j]['volume_id']) {
					$snap = $snapshots[$j];
					break;
				}
			}
			if ($snap) {
				$mapping = [
					'device_name' => $devices[$i]['device_name'],
					'delete_on_termination' => $devices[$i]['delete_on_termination'],
					'ebs_card_index' => $devices[$i]['ebs_card_index'],
					'snapshot_id' => $snapshots[$j]['snapshot_id']
				];
				$mappings[$devices[$i]['volume_id']] = $mapping;
			}
		}
		return $mappings;
	}

	/**
	 * Stream data to standard output.
	 *
	 * @param mixed $output data to send to STDOUT. This can be string|array|object type.
	 * @return bool true on success, false otherwise
	 */
	private static function streamData($output): bool
	{
		$data = null;
		if (is_string($output)) {
			$data = $output;
		} elseif (is_array($output) || is_object($output)) {
			$data = json_encode($output);
		} else {
			// Unsupported data type to stream
			$emsg = var_export($output, true);
			Plugins::log(Plugins::LOG_ERROR, "Unsupported data type to stream: {$emsg}");
		}
		$ret = false;
		if (is_string($data)) {
			// Stream to standard output
			$result = fwrite(STDOUT, $data);
			$ret = ($result !== false);
			if (!$ret) {
				// Something went wrong with streaming...
				$emsg = var_export($output, true);
				Plugins::log(Plugins::LOG_ERROR, "Error while streaming data to STDOUT: {$emsg}");
			}
		}
		return $ret;
	}

	/**
	 * Refresh AWS credentials.
	 *
	 * @param array $args plugin arguments
	 */
	private static function refreshAWSCredentials(array $args): void
	{
		self::$refresh_creds_lock = true;
		Plugins::log(Plugins::LOG_INFO, 'Refreshing AWS credentials.');
		self::initHTTPWorkerRequestSigner($args);
		self::$refresh_creds_lock = false;
	}

	/**
	 * Initialize HTTP worker request signer.
	 * It creates SigV4 signatures.
	 *
	 * @param array $args plugin arguments
	 */
	private static function initHTTPWorkerRequestSigner(array $args): void
	{
		$app = Prado::getApplication();
		$aws_cmd = $app->getModule('aws_command');
		$creds = $aws_cmd->getAccountCredentials($args['account']);
		if (!$creds) {
			return;
		}
		$sigv4 = new AmazonSigV4(
			$creds['AccessKeyId'],
			$creds['SecretAccessKey'],
			$args['region'],
			'ebs',
			($creds['SessionToken'] ?? null)
		);
		self::$http_worker_req_signer = $sigv4;
	}

	/**
	 * Get SigV4 siganture signed HTTP headers.
	 *
	 * @param string $method HTTP method ('GET', 'POST'...)
	 * @param string $url request URL
	 * @param array $headers default HTTP headers
	 * @param string $body request body (for 'GET' method this is empty string)
	 * @return array signed HTTP headers
	 */
	private static function getSignedHeaders(string $method, string $url, array $headers = [], string $body = ''): array
	{
		return self::$http_worker_req_signer->sign(
			$method,
			$url,
			$headers,
			$body
		);

	}

	/**
	 * Get function to prepare HTTP headers.
	 *
	 * @return callable header function
	 */
	private function getHTTPHeaderFunc(): callable
	{
		return function ($method, $url, $headers, $body) {
			return self::getSignedHeaders(
				$method,
				$url,
				$headers,
				$body
			);
		};
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
	 * Get formatted meta-data file name.
	 *
	 * @param string $file file name
	 * @return string formatted meta-data file name
	 */
	private function getFormattedMetaDataFile(string $file): string
	{
		// So far nothing to do
		return $file;
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
	 * Get plugin restore parameter categories.
	 * It should return all parameter categories that are used in restore.
	 *
	 * @return array plugin parameter categories
	 */
	public static function getRestoreParameterCategories(): array
	{
		return [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_RESTORE,
			self::PARAM_CAT_EC2_INSTANCE_RESTORE
		];
	}

	/**
	 * Get job state properties.
	 *
	 * @param string $job_name job name
	 * @param string $job_type job type
	 * @return array job state properties
	 */
	private function getJobState(string $job_name, string $job_type): array
	{
		$result = [];
		$statepath = $this->getJobStatePath($job_name, $job_type);
		if (file_exists($statepath)) {
			$content = file_get_contents($statepath);
			$result = json_decode($content, true) ?: [];
		}
		return $result;
	}

	/**
	 * Set job state properties.
	 *
	 * @param array $props job state properties
	 * @param string $job_name job name
	 * @param string $job_type job type
	 * @return bool true on success, false otherwise
	 */
	private function setJobState(array $props, string $job_name, string $job_type): bool
	{
		$body = $this->getJobState($job_name, $job_type);
		$body = array_merge($body, $props);
		$value = json_encode($body);
		$statepath = $this->getJobStatePath($job_name, $job_type);
		$result = (file_put_contents($statepath, $value, LOCK_EX) !== false);
		if (!$result) {
			Plugins::log(
				Plugins::LOG_ERROR,
				"Error while saving meta-data '{$statepath}'."
			);
		}
		return $result;
	}

	/**
	 * Clear job state.
	 *
	 * @param string $job_name job name
	 * @param string $job_type job type
	 * @return bool true on success, false otherwise
	 */
	private function clearJobState(string $job_name, string $job_type): bool
	{
		$value = '';
		$statepath = $this->getJobStatePath($job_name, $job_type);
		$result = (file_put_contents($statepath, $value, LOCK_EX) !== false);
		if (!$result) {
			Plugins::log(
				Plugins::LOG_ERROR,
				"Error while clearing meta-data '{$statepath}'."
			);
		}
		return $result;
	}

	/**
	 * Get job state file path.
	 *
	 * @param string $job_name job name
	 * @param string $job_type job type
	 * @return string job state file path
	 */
	private function getJobStatePath(string $job_name, string $job_type): string
	{
		$path = $this->getFileParams($job_type);
		$stfile = implode(DIRECTORY_SEPARATOR, $path) . $job_name;
		return $stfile;
	}

	/**
	 * Get state file parameters.
	 *
	 * @param string $suffix file name suffix
	 * @return array with directory and file prefix
	 */
	private function getFileParams(string $suffix): array
	{
		$dir = Prado::getPathOfNamespace('Bacularis.Common.Working');
		$plugin_name = $this->getPluginName();
		$prefix = sprintf('%s-%s.', $plugin_name, $suffix);
		return [$dir, $prefix];
	}
}
