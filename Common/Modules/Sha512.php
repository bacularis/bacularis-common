<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2025 Marcin Haba
 *
 * The main author of Bacularis is Marcin Haba, with contributors, whose
 * full list can be found in the AUTHORS file.
 *
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

namespace Bacularis\Common\Modules;

/**
 * Cryptographic SHA-512 hashing function module
 * Module is responsible for providing SHA-512 support.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Sha512 extends CommonModule
{
	// SHA-512 hash prefix
	public const HASH_PREFIX = '$6';

	// Salt length
	public const DEF_SALT_LEN = 16;

	public const SHA512_ROUNDS = 10000;

	/**
	 * Get hashed password using SHA-512 algorithm and salt.
	 *
	 * @param string $password plain text password
	 * @param string $salt cryptographic salt
	 * @return string hashed password
	 */
	public function crypt($password, $salt = null)
	{
		if (is_null($salt)) {
			// Salt string  - 16 characters for SHA-512
			$salt = $this->getModule('crypto')->getRandomString(self::DEF_SALT_LEN);
		}

		$salt_val = sprintf(
			'%s$rounds=%d$%s$',
			self::HASH_PREFIX,
			self::SHA512_ROUNDS,
			$salt
		);
		return crypt($password, $salt_val);
	}

	/**
	 * Verify if for given hash given password is valid.
	 *
	 * @param string $password password to check
	 * @param string $hash hash to check
	 * @return bool true if password and hash are match, otherwise false
	 */
	public function verify($password, $hash)
	{
		$valid = false;
		$parts = explode('$', $hash, 5);
		if (count($parts) === 5) {
			$salt = $parts[3];
			$hash2 = $this->crypt($password, $salt);
			$valid = ($hash === $hash2);
		}
		return $valid;
	}
}
