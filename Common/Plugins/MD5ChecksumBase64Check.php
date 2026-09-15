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
use Bacularis\Common\Modules\IBacularisVerificationCheckPlugin;
use Bacularis\Common\Modules\RestoreDestinationCapability;

/**
 * Check file MD5 checksum in base64 format (binary encoded form).
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Plugin
 */
class MD5ChecksumBase64Check extends BacularisCommonPluginBase implements IBacularisVerificationCheckPlugin
{
	private const OPERATOR_EQUAL_TO = '==';
	private const OPERATOR_DESC_EQUAL_TO = '== equal to';
	private const OPERATOR_NOT_EQUAL_TO = '!=';
	private const OPERATOR_DESC_NOT_EQUAL_TO = '!= not equal to';
	private const OPERATOR_EQUAL_CATALOG = 'ECV';
	private const OPERATOR_DESC_EQUAL_CATALOG = 'C== equal catalog value';

	/**
	 * Get plugin name displayed in web interface.
	 *
	 * @return string plugin name
	 */
	public static function getName(): string
	{
		return 'MD5 checksum check (base64)';
	}

	/**
	 * Get plugin type.
	 *
	 * @return string plugin type
	 */
	public static function getType(): string
	{
		return 'verification';
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
	 * Main check command.
	 * It checks if item is valid or not.
	 *
	 * @param string $operator check operator
	 * @param mixed $current_value item value to check
	 * @param mixed $expected_value expected value
	 * @return array current value, expected value and check result: true on succes, false otherwise
	 */
	public static function check(string $operator, $current_value, $expected_value): array
	{
		$ret = ['result' => false, 'current' => '', 'expected' => $expected_value];
		if ($operator == self::OPERATOR_EQUAL_CATALOG) {
			$ret['expected'] = $expected_value['checksum'] ?? '';
		}

		$is_link = is_link($current_value);
		$is_dir = is_dir($current_value);

		if ((!file_exists($current_value) && !$is_link) || $is_dir) {
			return $ret;
		}

		switch ($operator) {
			case self::OPERATOR_EQUAL_TO: {
				if ($is_link) {
					break;
				}
				$checksum_bin = md5_file($current_value, true);
				$checksum_b64 = base64_encode($checksum_bin);
				$checksum = rtrim($checksum_b64, '=');
				$ret['current'] = $checksum;
				$ret['result'] = $checksum == $expected_value;
				break;
			}
			case self::OPERATOR_NOT_EQUAL_TO: {
				if ($is_link) {
					break;
				}
				$checksum_bin = md5_file($current_value, true);
				$checksum_b64 = base64_encode($checksum_bin);
				$checksum = rtrim($checksum_b64, '=');
				$ret['current'] = $checksum;
				$ret['result'] = $checksum != $expected_value;
				break;
			}
			case self::OPERATOR_EQUAL_CATALOG: {
				if ($is_link) {
					$checksum = '0';
				} else {
					$checksum_bin = md5_file($current_value, true);
					$checksum_b64 = base64_encode($checksum_bin);
					$checksum = rtrim($checksum_b64, '=');
				}
				$ret['current'] = $checksum;
				$ret['result'] = $checksum == $expected_value['checksum'];
				break;
			}
		}
		return $ret;
	}

	/**
	 * Get main plugin attribute.
	 * Main attribute answers on question what the attribute is used
	 * in the check action.
	 * This is the first parameter defined in the verification rules.
	 *
	 * @return string main attribute
	 */
	public static function getAttribute(): string
	{
		return 'MD5 checksum (Base64)';
	}

	/**
	 * Get all supported operators by plugin.
	 *
	 * @return array operator list
	 */
	public static function getOperators(): array
	{
		return [
			self::OPERATOR_EQUAL_TO => self::OPERATOR_DESC_EQUAL_TO,
			self::OPERATOR_NOT_EQUAL_TO => self::OPERATOR_DESC_NOT_EQUAL_TO,
			self::OPERATOR_EQUAL_CATALOG => self::OPERATOR_DESC_EQUAL_CATALOG
		];
	}

	/**
	 * Get possible values to select.
	 *
	 * @return array values to select or type
	 */
	public static function getValues(): array
	{
		return ['type' => 'text', 'values' => ''];
	}

	/**
	 * Get checker capabilities.
	 * Capabilities define what data types is able to check and where
	 * it can be used.
	 *
	 * @return array check capabilities
	 */
	public static function getCapabilities(): array
	{
		return [
			RestoreDestinationCapability::FILE_CHECK
		];
	}

	/**
	 * Get checker requirements.
	 * Requirements define what this checker requires to corret working.
	 *
	 * @return array check requirements
	 */
	public static function getRequirements(): array
	{
		return [];
	}
}
