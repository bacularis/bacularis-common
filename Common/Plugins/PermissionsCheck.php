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
use Bacularis\Common\Modules\Miscellaneous;
use Bacularis\Common\Modules\RestoreDestinationCapability;

/**
 * Check file permissions condition.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Plugin
 */
class PermissionsCheck extends BacularisCommonPluginBase implements IBacularisVerificationCheckPlugin
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
		return 'Permissions check';
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
		$current_permissions = self::getCurrentPermissions($path);
		$expected = $expected_value;
		if ($operator == self::OPERATOR_EQUAL_CATALOG) {
			$expected = self::getCatalogPermissions($expected_value);
		} else {
			$expected = self::normalizePermissions($expected_value);
		}

		$ret = [
			'result' => false,
			'current' => $current_permissions,
			'expected' => $expected
		];

		if ($current_permissions === '' || $expected === '') {
			return $ret;
		}

		switch ($operator) {
			case self::OPERATOR_EQUAL_TO:
			case self::OPERATOR_EQUAL_CATALOG: {
				$ret['result'] = $current_permissions == $expected;
				break;
			}
			case self::OPERATOR_NOT_EQUAL_TO: {
				$ret['result'] = $current_permissions != $expected;
				break;
			}
		}
		return $ret;
	}

	/**
	 * Get current file permissions.
	 *
	 * @param string $path path to check
	 * @return string current permissions
	 */
	private static function getCurrentPermissions(string $path): string
	{
		$permissions = '';
		if (file_exists($path) || is_link($path)) {
			$lstat = lstat($path);
			if (is_array($lstat) && key_exists('mode', $lstat)) {
				$human_mode = Miscellaneous::get_human_mode($lstat['mode']);
				$permissions = substr($human_mode, 1);
			}
		}
		return $permissions;
	}

	/**
	 * Get catalog permissions from lstat mode.
	 *
	 * @param mixed $expected_value expected value with catalog lstat data
	 * @return string catalog permissions
	 */
	private static function getCatalogPermissions($expected_value): string
	{
		$permissions = '';
		if (isset($expected_value['lstat']['mode'])) {
			$permissions = substr($expected_value['lstat']['mode'], 1);
		}
		return $permissions;
	}

	/**
	 * Normalize user defined permissions.
	 *
	 * @param mixed $permissions permissions value
	 * @return string permissions without file type character
	 */
	private static function normalizePermissions($permissions): string
	{
		$permissions = (string) $permissions;
		if (strlen($permissions) === 10) {
			$permissions = substr($permissions, 1);
		}
		return $permissions;
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
		return 'Permissions';
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
