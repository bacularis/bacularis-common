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
 * Cryptographic tools.
 * Module is responsible for providing basic cryptograhic tool set.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 * @package Baculum Common
 */
class Crypto extends CommonModule {

	/**
	 * Supported hash algorithms.
	 */
	const HASH_ALG_APR1_MD5 = 'apr-md5';
	const HASH_ALG_SHA1 = 'sha1';
	const HASH_ALG_SSHA1 = 'ssha1';
	const HASH_ALG_SHA256 = 'sha256';
	const HASH_ALG_SHA512 = 'sha512';
	const HASH_ALG_BCRYPT = 'bcrypt';

	/**
	 * Get (pseudo)random string.
	 *
	 * Useful for log out user from HTTP Basic auth by providing random password.
	 *
	 * @access public
	 * @return string random string from range [a-zA-Z0-9]
	 */
	public function getRandomString($length = null) {
		$characters = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$rand_string = str_shuffle($characters);
		if (is_int($length) && $length <= 62) {
			$rand_string = substr($rand_string, 0, $length);
		}
		return $rand_string;
	}

	/**
	 * Get hash algorithm module instance by hash algorithm name.
	 *
	 * @param string $hash_alg hash algorithm
	 * @return object hash algorithm module instance
	 */
	private function getModuleByHashAlg($hash_alg) {
		$mod = '';
		switch ($hash_alg) {
			case self::HASH_ALG_BCRYPT: {
				$mod = 'bcrypt';
				break;
			}
			case self::HASH_ALG_APR1_MD5: {
				$mod = 'apr1md5';
				break;
			}
			case self::HASH_ALG_SHA1: {
				$mod = 'sha1';
				break;
			}
			case self::HASH_ALG_SSHA1: {
				$mod = 'ssha1';
				break;
			}
			case self::HASH_ALG_SHA256: {
				$mod = 'sha256';
				break;
			}
			case self::HASH_ALG_SHA512: {
				$mod = 'sha512';
				break;
			}
			default: {
				$mod = 'apr1md5';
			}
		}
		return $this->getModule($mod);
	}

	/**
	 * Get hashed password to use in web server auth.
	 * If no hash algorithm given, use APR1-MD5.
	 *
	 * @access public
	 * @param string $password plain text password
	 * @param string $hash_alg hash algorithm
	 * @return string hashed password
	 */
	public function getHashedPassword($password, $hash_alg = null) {
		if (is_null($hash_alg)) {
			$hash_alg = self::HASH_ALG_APR1_MD5;
		}
		return $this->getModuleByHashAlg($hash_alg)->crypt($password);
	}

	/*
	 * Get all supported hash algorithms.
	 * It bases on HASH_ALG_ constants definition.
	 *
	 * @return array supported hash algorithms
	 */
	private function getSupportedHashAlgs() {
		$hash_algs = [];
		$ocls = new ReflectionClass(__CLASS__);
		foreach ($ocls->getConstants() as $const => $hash_alg) {
			if (strpos($const, 'HASH_ALG_') !== 0) {
				continue;
			}
			$hash_algs[$const] = $hash_alg;
		}
		return $hash_algs;
	}

	/**
	 * Get module corresponding a hash string.
	 *
	 * @param string $hash hash string to check
	 * @return object|null module object on true, false if hash algorithm not recognized
	 */
	public function getModuleByHash($hash) {
		$module = null;
		foreach ($this->getSupportedHashAlgs() as $const => $hash_alg) {
			$mod = $this->getModuleByHashAlg($hash_alg);
			if (strpos($hash, $mod::HASH_PREFIX) === 0) {
				$module = $mod;
				break;
			}
		}
		return $module;
	}
}
?>
