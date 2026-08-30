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
 * Check file type condition.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Plugin
 */
class TypeCheck extends BacularisCommonPluginBase implements IBacularisVerificationCheckPlugin
{
	private const OPERATOR_EQUAL_TO = '==';
	private const OPERATOR_DESC_EQUAL_TO = '== equal to';
	private const OPERATOR_NOT_EQUAL_TO = '!=';
	private const OPERATOR_DESC_NOT_EQUAL_TO = '!= not equal to';
	private const OPERATOR_EQUAL_CATALOG = 'ECV';
	private const OPERATOR_DESC_EQUAL_CATALOG = 'C== equal catalog value';

	private const TYPE_DIRECTORY = 'Directory';
	private const TYPE_FILE = 'File';
	private const TYPE_SYMBOLIC_LINK = 'Symbolic link';

	/**
	 * Get plugin name displayed in web interface.
	 *
	 * @return string plugin name
	 */
	public static function getName(): string
	{
		return 'Type check';
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
		$current_type = self::getPathType($path);
		$expected = $expected_value;
		if ($operator == self::OPERATOR_EQUAL_CATALOG) {
			$expected = self::getCatalogType($expected_value);
		}

		$ret = [
			'result' => false,
			'current' => $current_type,
			'expected' => $expected
		];

		if ($current_type === '' || $expected === '') {
			return $ret;
		}

		switch ($operator) {
			case self::OPERATOR_EQUAL_TO:
			case self::OPERATOR_EQUAL_CATALOG: {
				$ret['result'] = $current_type == $expected;
				break;
			}
			case self::OPERATOR_NOT_EQUAL_TO: {
				$ret['result'] = $current_type != $expected;
				break;
			}
		}
		return $ret;
	}

	/**
	 * Get path type.
	 *
	 * @param string $path path to check
	 * @return string path type
	 */
	private static function getPathType(string $path): string
	{
		$type = '';
		if (is_link($path)) {
			$type = self::TYPE_SYMBOLIC_LINK;
		} elseif (is_dir($path)) {
			$type = self::TYPE_DIRECTORY;
		} elseif (is_file($path)) {
			$type = self::TYPE_FILE;
		}
		return $type;
	}

	/**
	 * Get catalog type from lstat mode.
	 *
	 * @param mixed $expected_value expected value with catalog lstat data
	 * @return string catalog type
	 */
	private static function getCatalogType($expected_value): string
	{
		$type = '';
		if (!isset($expected_value['lstat']['mode'])) {
			return $type;
		}

		$type_chr = substr($expected_value['lstat']['mode'], 0, 1);
		switch ($type_chr) {
			case 'd': {
				$type = self::TYPE_DIRECTORY;
				break;
			}
			case '-': {
				$type = self::TYPE_FILE;
				break;
			}
			case 'l': {
				$type = self::TYPE_SYMBOLIC_LINK;
				break;
			}
		}
		return $type;
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
		return 'Type';
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
		return [
			'type' => 'list',
			'values' => [
				self::TYPE_DIRECTORY,
				self::TYPE_FILE,
				self::TYPE_SYMBOLIC_LINK
			]
		];
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
