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
 * Copyright (C) 2013-2021 Kern Sibbald
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

/**
 * Basic authentication auth module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class AuthBasic extends AuthBase implements IAuthModule
{
	/**
	 * Generic name (used e.g. in config files).
	 */
	public const NAME = 'basic';

	/**
	 * Realms for particular application services.
	 */
	public const REALM_API = 'Bacularis API';
	public const REALM_PANEL = 'Bacularis Panel';
	public const REALM_WEB = 'Bacularis Web';

	/**
	 * Request header value pattern.
	 */
	public const REQUEST_HEADER_CREDENTIALS_PATTERN = '/^(?:[A-Za-z0-9+\/]{4})*(?:[A-Za-z0-9+\/]{2}==|[A-Za-z0-9+\/]{3}=|[A-Za-z0-9+\/]{4})$/';

	/**
	 * Get auth type.
	 *
	 * @return string auth type.
	 */
	public function getAuthType()
	{
		return 'Basic';
	}

	/**
	 * Validate auth request header.
	 *
	 * @param string $header auth request header value (ex: 'Basic dGVzdGVyOnRlc3Q=')
	 * @return bool true - valid, false - validation error
	 */
	public function validateRequestHeader($header)
	{
		$valid = false;
		$value = $this->getRequestHeaderValue($header);
		if (is_array($value)) {
			$valid = ($value['type'] === $this->getAuthType() && preg_match(self::REQUEST_HEADER_CREDENTIALS_PATTERN, $value['credentials']) === 1);
		}
		return $valid;
	}

	/**
	 * Get parsed request header value.
	 *
	 * @param string $header auth request header value (ex: 'Basic dGVzdGVyOnRlc3Q=')
	 * @return null|array list with type and credentials or null if header is invalid
	 */
	public function getRequestHeaderValue($header)
	{
		$ret = null;
		if (is_string($header)) {
			$values = explode(' ', $header, 2);
			if (count($values) == 2) {
				[$type, $credentials] = $values;
				$ret = ['type' => $type, 'credentials' => $credentials];
			}
		}
		return $ret;
	}

	/**
	 * Authenticate method.
	 * It is responsible for authenticating basic users.
	 * In case authentication error send appropriate headers.
	 *
	 * @param object $auth_mod module responsible for handling basic config
	 * @param string realm realm name
	 * @param bool $check_conf check if user exists in basic user config
	 * @param mixed $realm
	 * @return bool true if user authenticated successfully, false otherwise
	 */
	public function authenticate($auth_mod, $realm, $check_conf = true)
	{
		$is_auth = false;
		$username = $_SERVER['PHP_AUTH_USER'] ?? null;
		$password = $_SERVER['PHP_AUTH_PW'] ?? null;
		if ($auth_mod instanceof IUserConfig && $auth_mod->validateUsernamePassword($username, $password, $check_conf)) {
			// authentication valid
			$is_auth = true;
		}
		if (!$is_auth) {
			$h1 = sprintf('WWW-Authenticate: Basic realm="%s"', $realm);
			header($h1);
			header('HTTP/1.0 401 Unauthorized');
		}
		return $is_auth;
	}
}
