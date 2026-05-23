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

namespace Bacularis\Common\Modules\Cloud\Amazon\EC2;

use Prado\Prado;

/**
 * Amazon EC2 AMI (Amazon Machine Image) module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Image
{
	/**
	 * Register EC2 virtual machine image (AMI).
	 *
	 * @param string $account AWS account name
	 * @param array $props register image properties
	 * @return array image ID and creating image state
	 */
	public static function registerImage(string $account, array $props): array
	{
		// Prepare command parameters
		$params = [
			'ec2',
			'register-image'
		];
		if (key_exists('name', $props)) {
			$params[] = "--name \"{$props['name']}\"";
		}
		if (key_exists('boot_mode', $props)) {
			$params[] = "--boot-mode \"{$props['boot_mode']}\"";
		}
		if (key_exists('tpm_support', $props)) {
			$params[] = "--tpm-support '{$props['tpm_support']}'";
		}
		if (key_exists('architecture', $props)) {
			$params[] = "--architecture \"{$props['architecture']}\"";
		}
		if (key_exists('root_device_name', $props)) {
			$params[] = "--root-device-name \"{$props['root_device_name']}\"";
		}
		if (key_exists('virtualization_type', $props)) {
			$params[] = "--virtualization-type \"{$props['virtualization_type']}\"";
		}
		if (key_exists('sriov_net_support', $props)) {
			$params[] = "--sriov-net-support \"{$props['sriov_net_support']}\"";
		}
		if (key_exists('ena_support', $props)) {
			$params[] = "--ena-support";
		}
		$params[] = "--block-device-mappings '{$props['block_device_mappings']}'";

		// Run register image command
		$app = Prado::getApplication();
		$aws_cmd = $app->getModule('aws_command');
		$aws_cmd::addGlobalOptions($params);
		$image_id = null;
		$state = false;
		$result = $aws_cmd->execCommand($account, $params);
		if ($result['error'] == 0) {
			$image_id = $result['output']->ImageId ?? null;
			if ($image_id) {
				// Let's wait until image is ready
				$state = self::waitOnImageAvailable($account, $image_id);
			}
		}
		$ret = [
			'image_id' => $image_id,
			'state' => $state
		];
		return $ret;
	}

	/**
	 * Wait until virtual machine image becomes available.
	 * It will poll every 15 seconds until a successful state has been reached.
	 * This will return on success or after 40 failed checks.
	 *
	 * @param string $account AWS account name
	 * @param string $image_id image identifier
	 * @return bool true on success, false otherwise (timeout)
	 */
	private static function waitOnImageAvailable(string $account, string $image_id): bool
	{
		$action = 'image-available';
		$params = [
			"--image-ids \"{$image_id}\""
		];
		return Wait::waiting($account, $action, $params);
	}

	/**
	 * Deregister EC2 virtual machine image (AMI).
	 *
	 * @param string $account AWS account name
	 * @param string $image_id image ID to deregister
	 * @return bool true on success, false otherwise
	 */
	public static function deregisterImage(string $account, string $image_id): bool
	{
		// Prepare command parameters
		$params = [
			'ec2',
			'deregister-image',
			"--image-id \"{$image_id}\""
		];

		// Run deregister image command
		$app = Prado::getApplication();
		$aws_cmd = $app->getModule('aws_command');
		$aws_cmd::addGlobalOptions($params);
		$result = $aws_cmd->execCommand($account, $params);
		$ret = false;
		if ($result['error'] == 0) {
			$ret = $result['output']->Return ?? false;
		}
		return $ret;
	}
}
