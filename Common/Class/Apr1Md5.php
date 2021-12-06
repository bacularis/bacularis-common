<?php
/*
 * Bacula(R) - The Network Backup Solution
 * Baculum   - Bacula web interface
 *
 * Copyright (C) 2013-2020 Kern Sibbald
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

Prado::using('Application.Common.Class.CommonModule');

/**
 * Cryptographic APR1-MD5 hashing function module.
 * Module is responsible for providing APR1-MD5 support.
 *
 * @category Module
 * @package Baculum Common
 */
class Apr1Md5 extends CommonModule {

	// APR-MD5 hash prefix
	const HASH_PREFIX = '$apr1';

	// Salt length
	const DEF_SALT_LEN = 8;

	/**
	 * Get hashed password using APR1-MD5 algorithm.
	 * This function is based on common sample using PHP implementation APR1-MD5.
	 * The original author is unknown.
	 * @see https://stackoverflow.com/questions/1038791/how-to-programmatically-build-an-apr1-md5-using-php
	 *
	 * @param string $password plain text password
	 * @param string $salt cryptographic salt
	 * @return string hashed password
	 */
	public function crypt($password, $salt = null) {
		if (is_null($salt)) {
			$salt = $this->getModule('crypto')->getRandomString(self::DEF_SALT_LEN);
		}
		$len = strlen($password);
		$text = sprintf('%s$apr1$%s', $password, $salt);
		$bin = pack('H32', md5($password . $salt . $password));
		for ($i = $len; $i > 0; $i -= 16) {
			$text .= substr($bin, 0, min(16, $i));
		}
		for ($i = $len; $i > 0; $i >>= 1) {
			$text .= ($i & 1) ? chr(0) : $password[0];
		}
		$bin = pack('H32', md5($text));
		for ($i = 0; $i < 1000; $i++) {
			$new = ($i & 1) ? $password : $bin;
			if ($i % 3) {
				$new .= $salt;
			}
			if ($i % 7) {
				$new .= $password;
			}
			$new .= ($i & 1) ? $bin : $password;
			$bin = pack('H32', md5($new));
		}
		$tmp = null;
		for ($i = 0; $i < 5; $i++) {
			$k = $i + 6;
			$j = $i + 12;
			if ($j == 16) {
				$j = 5;
			}
			$tmp = $bin[$i] . $bin[$k] . $bin[$j] . $tmp;
		}
		$tmp = chr(0) . chr(0) . $bin[11] . $tmp;
		$str = strrev(substr(base64_encode($tmp), 2));
		$tmp = strtr(
			$str,
			'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/',
			'./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'
		);
		return sprintf('%s$%s$%s', self::HASH_PREFIX, $salt, $tmp);
	}

	/**
	 * Verify if for given hash given password is valid.
	 *
	 * @param string $password password to check
	 * @param string $hash hash to check
	 * @return boolean true if password and hash are match, otherwise false
	 */
	public function verify($password, $hash) {
		$valid = false;
		$parts = explode('$', $hash, 4);
		if (count($parts) === 4) {
			$salt = $parts[2];
			$hash2 = $this->crypt($password, $salt);
			$valid = ($hash === $hash2);
		}
		return $valid;
	}
}
?>
