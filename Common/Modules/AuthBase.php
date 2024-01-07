<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2024 Marcin Haba
 *
 * The main author of Bacularis is Marcin Haba, with contributors, whose
 * full list can be found in the AUTHORS file.
 *
 * Bacula(R) - The Network Backup Solution
 * Baculum   - Bacula web interface
 *
 * Copyright (C) 2013-2019 Kern Sibbald
 *
 * The main author of Baculum is Marcin Haba.
 * The original author of Bacula is Kern Sibbald, with contributions
 * from many others, a complete list can be found in the file AUTHORS.
 *
 * You may use this file and others of this release according to the
 * license defined in the LICENSE file, which includes the Affero General
 * Public License, v3.0 ("AGPLv3") and some additional permissions and
 * terms pursuant to its AGPLv3 Section 7.
 *
 * This notice must be preserved when any source code is
 * conveyed and/or propagated.
 *
 * Bacula(R) is a registered trademark of Kern Sibbald.
 */

namespace Bacularis\Common\Modules;

use Prado\Web\THttpRequest;

/**
 * Abstraction that defines common methods to inheirt by auth modules.
 * Descendant classes can implement auth module interface.
 * @see Application.Common.Modules.Interfaces.AuthModule
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
abstract class AuthBase extends CommonModule
{
	/**
	 * Stores HTTP request object.
	 */
	private static $req;

	/**
	 * Public constructor.
	 *
	 * @param THttpRequest $request HTTP request object.
	 */
	public function initialize(THttpRequest $request)
	{
		self::$req = $request;
	}

	/**
	 * Get all HTTP request headers.
	 *
	 * @return array request headers
	 */
	private function getRequestHeaders()
	{
		return self::$req->getHeaders(CASE_LOWER);
	}

	/**
	 * Check if HTTP request contains authorization header
	 * ex: 'Authorization: Basic dGVzdGVyOnRlc3Q='
	 *
	 * @return bool true if request contains valid authorization header
	 */
	public function isAuthRequest()
	{
		return ($this->getRequestHeader() !== null);
	}

	/**
	 * Get authorization request header.
	 *
	 * @return null|string authorization header or null if header is invalid
	 */
	public function getRequestHeader()
	{
		$auth_header = null;
		$headers = $this->getRequestHeaders();
		if (is_array($headers) && key_exists('authorization', $headers)) {
			if ($this->validateRequestHeader($headers['authorization'])) {
				$auth_header = $headers['authorization'];
			}
		}
		return $auth_header;
	}

	/**
	 * Validate request header.
	 *
	 * @param mixed $header
	 * @return bool true - success, false - validation error
	 */
	abstract protected function validateRequestHeader($header);
}
