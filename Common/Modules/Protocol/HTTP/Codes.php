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

namespace Bacularis\Common\Modules\Protocol\HTTP;

/**
 * HTTP error code module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Codes
{
	/**
	 * 2xx success codes.
	 */
	public const CODE_OK = 200;

	/**
	 * 4xx client error codes.
	 */
	public const CODE_BAD_REQUEST = 400;
	public const CODE_FORBIDDEN = 403;

	/**
	 * 5xx server error codes.
	 */
	public const CODE_INTERNAL_SERVER_ERROR = 500;
	public const CODE_NETWORK_CONNECT_TIMEOUT_ERROR = 599;

	/**
	 * Check if given HTTP code is the server error code.
	 *
	 * @param int $code HTTP error code
	 * @return bool true if HTTP code is server code, false otherwise
	 */
	public static function isServerCode(int $code): bool
	{
		return ($code >= self::CODE_INTERNAL_SERVER_ERROR && $code <= self::CODE_NETWORK_CONNECT_TIMEOUT_ERROR);
	}
}
