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
 * Check file SHA1 checksum in hexadecimal format.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Plugin
 */
class SHA1ChecksumHexCheck extends BacularisCommonPluginBase implements IBacularisVerificationCheckPlugin
{
	private const OPERATOR_EQUAL_TO = '==';
	private const OPERATOR_DESC_EQUAL_TO = '== equal to';
	private const OPERATOR_NOT_EQUAL_TO = '!=';
	private const OPERATOR_DESC_NOT_EQUAL_TO = '!= not equal to';

	/**
	 * Get plugin name displayed in web interface.
	 *
	 * @return string plugin name
	 */
	public static function getName(): string
	{
		return 'SHA1 checksum check (hexadecimal)';
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
		if (!file_exists($current_value)) {
			return $ret;
		}
		$checksum = hash_file('sha1', $current_value);
		$ret['current'] = $checksum;
		$ret['expected'] = $expected_value;
		switch ($operator) {
			case self::OPERATOR_EQUAL_TO: {
				$ret['result'] = $checksum == $expected_value;
				break;
			}
			case self::OPERATOR_NOT_EQUAL_TO: {
				$ret['result'] = $checksum != $expected_value;
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
		return 'SHA1 checksum (HEX)';
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
			self::OPERATOR_NOT_EQUAL_TO => self::OPERATOR_DESC_NOT_EQUAL_TO
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
