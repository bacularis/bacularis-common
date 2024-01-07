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
 * OAuth2 authorization auth module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class AuthOAuth2 extends AuthBase implements IAuthModule
{
	/**
	 * Generic name (used e.g. in config files).
	 */
	public const NAME = 'oauth2';

	/**
	 * Get auth type.
	 *
	 * @return string auth type.
	 */
	public function getAuthType()
	{
		return 'Bearer';
	}

	/**
	 * Validate auth request header.
	 *
	 * @param string $header auth request header value (ex: 'Bearer 39607568825eba6b72088b1ab054ed9d0f857eb7')
	 * @return bool true - valid, false - validation error
	 */
	public function validateRequestHeader($header)
	{
		$valid = false;
		$value = $this->getRequestHeaderValue($header);
		if (is_array($value)) {
			$valid = ($value['type'] === $this->getAuthType() && OAuth2::validateAccessToken($value['token']) === true);
		}
		return $valid;
	}

	/**
	 * Get parsed request header value.
	 *
	 * @param string $header auth request header value (ex: 'Basic 39607568825eba6b72088b1ab054ed9d0f857eb7')
	 * @return null|array list with type and token or null if header is invalid
	 */
	public function getRequestHeaderValue($header)
	{
		$ret = null;
		if (is_string($header)) {
			$values = explode(' ', $header, 2);
			if (count($values) == 2) {
				[$type, $token] = $values;
				$ret = ['type' => $type, 'token' => $token];
			}
		}
		return $ret;
	}

	/**
	 * Get token from authorization header.
	 *
	 * @return string token value or empty string if header is invalid
	 */
	public function getToken()
	{
		$token = '';
		$header = $this->getRequestHeader();
		$value = $this->getRequestHeaderValue($header);
		if (is_array($value)) {
			$token = $value['token'];
		}
		return $token;
	}

	/**
	 * Check if request is allowed to access basing on OAuth2 scopes.
	 * Note, public endpoints are available for every client that uses
	 * valid token.
	 *
	 * @param string $path requested URL path
	 * @param string $tscopes scopes assigned to token
	 * @param array $public_endpoints endpoints that are public for all valid clients
	 * @return bool true if scope in path and scope assigned to token are valid, otherwise false
	 */
	public function isScopeValid($path, $tscopes, $public_endpoints)
	{
		$valid = false;
		$scopes = explode(' ', $tscopes);
		$params = explode('/', $path);
		if (count($params) >= 3 && $params[1] === 'api') {
			$endpoint = $params[2];
			if (preg_match('/^v\d+$/', $params[2]) === 1 && count($params) >= 4) {
				// for versioned API (v1, v2 ...etc.)
				$endpoint = $params[3];
			}
			if (in_array($endpoint, $public_endpoints)) {
				$valid = true;
			} else {
				for ($i = 0; $i < count($scopes); $i++) {
					if ($endpoint === $scopes[$i]) {
						$valid = true;
						break;
					}
				}
			}
		}
		return $valid;
	}
}
