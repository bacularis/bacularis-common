<?php
/*
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

Prado::using('Application.Common.Class.AuthBase');
Prado::using('Application.Common.Class.Interfaces');

/**
 * Basic authentication auth module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 * @package Baculum Common
 */
class AuthBasic extends AuthBase implements AuthModule {

	/**
	 * Generic name (used e.g. in config files).
	 */
	const NAME = 'basic';

	/**
	 * Request header value pattern.
	 */
	const REQUEST_HEADER_CREDENTIALS_PATTERN = '/^(?:[A-Za-z0-9+\/]{4})*(?:[A-Za-z0-9+\/]{2}==|[A-Za-z0-9+\/]{3}=|[A-Za-z0-9+\/]{4})$/';

	/**
	 * Get auth type.
	 *
	 * @return string auth type.
	 */
	public function getAuthType() {
		return 'Basic';
	}

	/**
	 * Validate auth request header.
	 *
	 * @param string $header auth request header value (ex: 'Basic dGVzdGVyOnRlc3Q=')
	 * @return boolean true - valid, false - validation error
	 */
	public function validateRequestHeader($header) {
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
	 * @return array|null list with type and credentials or null if header is invalid
	 */
	public function getRequestHeaderValue($header) {
		$ret = null;
		if (is_string($header)) {
			$values = explode(' ', $header, 2);
			if (count($values) == 2) {
				list($type, $credentials) = $values;
				$ret = ['type' => $type, 'credentials' => $credentials];
			}
		}
		return $ret;
	}
}
?>
