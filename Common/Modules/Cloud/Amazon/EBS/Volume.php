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
 * Amazon EBS (Amazon Elastic Block Store) volume module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Volume
{
	/**
	 * Parse and prepare EBS volume object structure.
	 *
	 * @param object $volume EBS volume object
	 * @param null|string $empty_val_mark empty value mark
	 * @return array parsed instance object
	 */
	public static function parseObject(object $volume, ?string $empty_val_mark = null): array
	{
		$vol = [];
		$vol['availability_zone'] = $volume->AvailabilityZone ?? $empty_val_mark;
		$vol['availability_zone_id'] = $volume->AvailabilityZoneId ?? $empty_val_mark;
		$vol['iops'] = $volume->Iops ?? 0;
		$tags = $volume->Tags ?? [];
		$tag_list = [];
		for ($i = 0; $i < count($tags); $i++) {
			$tag = [];
			$tag['Key'] = $tags[$i]->Key;
			$tag['Value'] = $tags[$i]->Value;
			$tag_list[] = $tag;
		}
		$vol['tags'] = $tag_list;
		$vol['volume_type'] = $volume->VolumeType ?? $empty_val_mark;
		$vol['fast_restored'] = $volume->FastRestored ?? false;
		$vol['multi_attach_enabled'] = $volume->MultiAttachEnabled ?? false;
		$vol['throughput'] = $volume->Throughput ?? 0;
		$vol['volume_id'] = $volume->VolumeId ?? $empty_val_mark;
		$vol['size'] = $volume->Size ?? 0;
		$vol['snapshot_id'] = $volume->SnapshotId ?? $empty_val_mark;
		$vol['state'] = $volume->State ?? $empty_val_mark;
		$vol['create_time'] = $volume->CreateTime ?? $empty_val_mark;
		$attachments = $volume->Attachments ?? [];
		$attachment_list = [];
		for ($i = 0; $i < count($tags); $i++) {
			$attachment = [];
			$attachment['delete_on_termination'] = $attachments[$i]->DeleteOnTermination ?? false;
			$attachment['associated_resource'] = $attachments[$i]->AssociatedResource ?? $empty_val_mark;
			$attachment['instance_owning_service'] = $attachments[$i]->InstanceOwningService ?? $empty_val_mark;
			$attachment['ebs_card_index'] = $attachments[$i]->EbsCardIndex ?? 0;
			$attachment['volume_id'] = $attachments[$i]->VolumeId ?? $empty_val_mark;
			$attachment['instance_id'] = $attachments[$i]->InstanceId ?? $empty_val_mark;
			$attachment['device_id'] = $attachments[$i]->Device ?? $empty_val_mark;
			$attachment['state'] = $attachments[$i]->State ?? $empty_val_mark;
			$attachment['attach_time'] = $attachments[$i]->AttachTime ?? 0;
			$attachment_list[] = $attachment;
		}
		$vol['attachments'] = $attachment_list;
		$vol['encrypted'] = $volume->Encrypted ?? false;
		$vol['kms_key_id'] = $volume->KmsKeyId ?? $empty_val_mark;
		return $vol;
	}

	/**
	 * Get EBS volume details.
	 *
	 * @param string $account AWS account name
	 * @param string $volume_id EBS volume identifier
	 * @return array volume details or empty array on error
	 */
	public static function describe(string $account, string $volume_id): array
	{
		$command = [
			'ec2',
			'describe-volumes',
			"--volume-ids {$volume_id}"
		];
		$app = Prado::getApplication();
		$aws_cmd = $app->getModule('aws_command');
		$result = $aws_cmd->execCommand($account, $command);

		$volume = [];
		if ($result['error'] == 0) {
			$volume_obj = $result['output']->Volumes[0] ?? null;
			if ($volume_obj) {
				$volume = self::parseObject($volume_obj);
			}
		}
		return $volume;
	}
}
