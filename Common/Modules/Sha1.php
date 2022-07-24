<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2022 Marcin Haba
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

use Bacularis\Common\Modules\CommonModule;

/**
 * Cryptographic SHA-1 hashing function module
 * Module is responsible for providing SHA-1 support.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Sha1 extends CommonModule
{
	// SHA-1 hash prefix
	public const HASH_PREFIX = '{SHA}';

	/**
	 * Get hashed password using SHA-1 algorithm.
	 *
	 * @param string $password plain text password
	 * @return string hashed password
	 */
	public function crypt($password)
	{
		$hash = sha1($password, true);
		$bh = base64_encode($hash);
		$ret = self::HASH_PREFIX . $bh;
		return $ret;
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
		$hash2 = $this->crypt($password);
		return ($hash === $hash2);
	}
}
