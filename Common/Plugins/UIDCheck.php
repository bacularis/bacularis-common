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
 * Check file owner UID condition.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Plugin
 */
class UIDCheck extends BacularisCommonPluginBase implements IBacularisVerificationCheckPlugin
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
		return 'UID check';
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
	 * @return array current value, expected value and check result: true on success, false otherwise
	 */
	public static function check(string $operator, $current_value, $expected_value): array
	{
		$path = (string) $current_value;
		$current_uid = self::getCurrentValue($path, 'uid');
		$expected = $expected_value;
		if ($operator == self::OPERATOR_EQUAL_CATALOG) {
			$expected = self::getCatalogValue($expected_value, 'uid');
		}

		$ret = [
			'result' => false,
			'current' => $current_uid,
			'expected' => $expected
		];

		if ($current_uid === '' || $expected === '') {
			return $ret;
		}

		switch ($operator) {
			case self::OPERATOR_EQUAL_TO:
			case self::OPERATOR_EQUAL_CATALOG: {
				$ret['result'] = $current_uid == $expected;
				break;
			}
			case self::OPERATOR_NOT_EQUAL_TO: {
				$ret['result'] = $current_uid != $expected;
				break;
			}
		}
		return $ret;
	}

	/**
	 * Get current lstat value.
	 *
	 * @param string $path path to check
	 * @param string $key lstat key
	 * @return mixed current value or empty string if unavailable
	 */
	private static function getCurrentValue(string $path, string $key)
	{
		$value = '';
		if (file_exists($path) || is_link($path)) {
			$lstat = lstat($path);
			if (is_array($lstat) && key_exists($key, $lstat)) {
				$value = $lstat[$key];
			}
		}
		return $value;
	}

	/**
	 * Get catalog value from lstat.
	 *
	 * @param mixed $expected_value expected value with catalog lstat data
	 * @param string $key lstat key
	 * @return mixed catalog value or empty string if unavailable
	 */
	private static function getCatalogValue($expected_value, string $key)
	{
		return $expected_value['lstat'][$key] ?? '';
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
		return 'UID';
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
		return ['type' => 'text', 'values' => 0];
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
