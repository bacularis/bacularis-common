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
 * Cryptographic SHA-1 hashing function module.
 * Module is responsible for providing SHA-1 support with
 * using salt.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 * @package Baculum Common
 */
class Ssha1 extends CommonModule {

	// Salted SHA-1 hash prefix
	const HASH_PREFIX = '{SSHA}';

	// Salt length
	const DEF_SALT_LEN = 4;

	/**
	 * Get hashed password using SHA-1 algorithm and salt.
	 *
	 * @param string $password plain text password
	 * @param string $salt cryptographic salt
	 * @return string hashed password
	 */
	public function crypt($password, $salt = null) {
		if (is_null($salt)) {
			$salt = $this->getModule('crypto')->getRandomString(self::DEF_SALT_LEN);
		}
		$hash = sha1($password . $salt, true);
		$bh = base64_encode($hash . $salt);
		$ret = self::HASH_PREFIX . $bh;
		return $ret;
	}

	/**
	 * Verify if for given hash given password is valid.
	 *
	 * @param string $password password to check
	 * @param string $hash hash to check
	 * @return boolean true if password and hash are match, otherwise false
	 */
	public function verify($password, $hash) {
		$pos = strlen(self::HASH_PREFIX) - 1;
		$bh = substr($hash, $pos);
		$h = base64_decode($bh);
		$salt = substr($h, -(self::DEF_SALT_LEN));
		$hash2 = $this->crypt($password, $salt);
		return ($hash === $hash2);
	}
}
?>
