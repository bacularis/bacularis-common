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

namespace Bacularis\Common\Modules;

/**
 * Restore destination capability names.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class RestoreDestinationCapability
{
	/**
	 * File restore checks capability.
	 */
	public const FILE_CHECK = 'file_check';

	/**
	 * Native Bacula verify capability.
	 */
	public const NATIVE_VERIFY = 'native_verify';

	/**
	 * Plugin checks capability.
	 */
	public const PLUGIN_CHECK = 'plugin_check';

	/**
	 * Custom command checks capability.
	 */
	public const CUSTOM_CHECK = 'custom_check';

	/**
	 * Capability descriptions.
	 */
	public const FILE_CHECK_DEST = 'File restore checks';
	public const NATIVE_VERIFY_DEST = 'Native Bacula verify';
	public const PLUGIN_CHECK_DEST = 'Plugin checks';
	public const CUSTOM_CHECK_DEST = 'Custom command checks';

	/**
	 * Get capability description by name.
	 *
	 * @return string capability description or empty string if capability not found.
	 */
	public static function getDescription(string $name): string
	{
		$desc = '';
		switch ($name) {
			case self::FILE_CHECK: {
				$desc = self::FILE_CHECK_DEST;
				break;
			}
			case self::NATIVE_VERIFY: {
				$desc = self::NATIVE_VERIFY_DEST;
				break;
			}
			case self::PLUGIN_CHECK: {
				$desc = self::PLUGIN_CHECK_DEST;
				break;
			}
			case self::CUSTOM_CHECK: {
				$desc = self::CUSTOM_CHECK_DEST;
				break;
			}
		}
		return $desc;
	}
}
