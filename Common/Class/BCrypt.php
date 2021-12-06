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
 * Cryptographic BCrypt hashing function module
 * Module is responsible for providing BCrypt support.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 * @package Baculum Common
 */
class BCrypt extends CommonModule {

	// BCrypt hash prefix
	const HASH_PREFIX = '$2y';

	// Salt length
	const DEF_SALT_LEN = 22;

	// bcrypt uses not standard base64 alphabet
	const BCRYPT_BASE64_CODE = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

	// bcrypt cost parameter - number of iterations during hashing
	const BCRYPT_COST = 10;

	/**
	 * Get hashed password using BCrypt algorithm and salt.
	 *
	 * @param string $password plain text password
	 * @param string $salt cryptographic salt
	 * @return string hashed password
	 */
	public function crypt($password, $salt = null) {
		if (is_null($salt)) {
			// Suffle string
			$rand_string = str_shuffle(self::BCRYPT_BASE64_CODE);

			// BCrypt salt
			$salt = substr($rand_string, 0, self::DEF_SALT_LEN);
		}

		$salt_val = sprintf(
			'%s$%d$%s$',
			self::HASH_PREFIX,
			self::BCRYPT_COST,
			$salt
		);
		return crypt($password, $salt_val);
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
			$salt = substr($parts[3], 0, self::DEF_SALT_LEN);
			$hash2 = $this->crypt($password, $salt);
			$valid = ($hash === $hash2);
		}
		return $valid;
	}
}
?>
