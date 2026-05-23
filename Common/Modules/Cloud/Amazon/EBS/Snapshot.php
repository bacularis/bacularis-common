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

namespace Bacularis\Common\Modules\Cloud\Amazon\EBS;

use Prado\Prado;

/**
 * Amazon EBS (Amazon Elastic Block Store) snapshot module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Snapshot
{
	/**
	 * Snapshot states.
	 */
	public const STATE_PENDING = 'pending';
	public const STATE_COMPLETED = 'completed';
	public const STATE_ERROR = 'error';
	public const STATE_RECOVERABLE = 'recoverable';
	public const STATE_RECOVERING = 'recovering';

	/**
	 * Parse and prepare EBS snapshot object structure.
	 *
	 * @param object $snapshot EBS snapshot object
	 * @return array parsed snapshot object
	 */
	public static function parseObject(object $snapshot): array
	{
		$snap = [];
		$snap['description'] = $snapshot->Description ?? '';
		$snap['encrypted'] = $snapshot->Encrypted ?? false;
		$snap['volume_id'] = $snapshot->VolumeId ?? EBS::EMPTY_VALUE_MARK;
		$snap['state'] = $snapshot->State ?? EBS::EMPTY_VALUE_MARK;
		$snap['status'] = $snapshot->Status ?? EBS::EMPTY_VALUE_MARK; // used for create-snapshot
		$snap['volume_size'] = $snapshot->VolumeSize ?? 0;
		$snap['start_time'] = $snapshot->StartTime ?? EBS::EMPTY_VALUE_MARK;
		$snap['progress'] = $snapshot->Progress ?? EBS::EMPTY_VALUE_MARK;
		$snap['owner_id'] = $snapshot->OwnerId ?? EBS::EMPTY_VALUE_MARK;
		$snap['snapshot_id'] = $snapshot->SnapshotId ?? EBS::EMPTY_VALUE_MARK;
		$tags = $snapshot->Tags ?? [];
		$tag_list = [];
		for ($i = 0; $i < count($tags); $i++) {
			$tag = [];
			$tag['key'] = $tags[$i]->Key;
			$tag['value'] = $tags[$i]->Value;
			$tag_list[] = $tag;
		}
		$snap['tags'] = $tag_list;
		return $snap;
	}

	/**
	 * Get EBS snapshot details.
	 *
	 * @param string $account AWS account name
	 * @param array $snapshot_ids snapshot identifiers
	 * @return array snapshot descriptions or empty array
	 */
	public static function describe(string $account, array $snapshot_ids): array
	{
		$params = [
			'ec2',
			'describe-snapshots',
		];
		if ($snapshot_ids) {
			$snap_ids = '"' . implode('" "', $snapshot_ids) . '"';
			$params[] = "--snapshot-ids $snap_ids";
		}
		$app = Prado::getApplication();
		$aws_cmd = $app->getModule('aws_command');
		$aws_cmd::addGlobalOptions($params);
		$snapshots = [];
		$result = $aws_cmd->execCommand($account, $params);
		if ($result['error'] == 0) {
			$snaps = $result['output']->Snapshots ?? [];
			for ($i = 0; $i < count($snaps); $i++) {
				$snapshots[$i] = self::parseObject($snaps[$i]);
			}
		}
		return $snapshots;
	}

	/**
	 * Check if snapshot is completed (ready to use).
	 *
	 * @param array $snapshot single snapshot properties
	 * @return bool true if snapshot is completed, false otherwise
	 */
	public static function isSnapshotCompleted(array $snapshot): bool
	{
		return ($snapshot['state'] === self::STATE_COMPLETED);
	}

	/**
	 * Check if snapshot finished with error.
	 *
	 * @param array $snapshot single snapshot properties
	 * @return bool true if snapshot contains errors, false otherwise
	 */
	public static function isSnapshotError(array $snapshot): bool
	{
		return ($snapshot['state'] === self::STATE_ERROR);
	}
}
