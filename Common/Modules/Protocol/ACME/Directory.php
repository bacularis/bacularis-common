<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2025 Marcin Haba
 *
 * The main author of Bacularis is Marcin Haba, with contributors, whose
 * full list can be found in the AUTHORS file.
 *
 * You may use this file and others of this release according to the
 * license defined in the LICENSE file, which includes the Affero General
 * Public License, v3.0 ("AGPLv3") and some additional permissions and
 * terms pursuant to its AGPLv3 Section 7.
 */

namespace Bacularis\Common\Modules\Protocol\ACME;

use Bacularis\Common\Modules\AuditLog;
use Bacularis\Common\Modules\CommonModule;
use Bacularis\Common\Modules\Logging;

/**
 * SSL certificate management.
 * It enables to install, renew and uninstall SSL certificate.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Control
 */
class Directory extends CommonModule
{
	/**
	 * Stores directory object properties.
	 *
	 * @var array
	 */
	private static $directory;

	/**
	 * Get directory properties.
	 *
	 * @param string $url directory URL endpoint
	 * @return array directory properties
	 */
	public static function get(string $url): array
	{
		if (is_null(self::$directory)) {
			self::$directory = self::initialize($url);
		}
		return self::$directory;
	}

	/**
	 * Initialize directory properties.
	 *
	 * @param string $url directory URL endpoint
	 */
	private static function initialize(string $url): array
	{
		$ret = [];
		$result = Request::get($url);
		if ($result['error'] == 0) {
			$ret = $result['output'];
		}
		return $ret;
	}
}
