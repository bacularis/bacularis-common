<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2024 Marcin Haba
 *
 * The main author of Bacularis is Marcin Haba, with contributors, whose
 * full list can be found in the AUTHORS file.
 *
 * You may use this file and others of this release according to the
 * license defined in the LICENSE file, which includes the Affero General
 * Public License, v3.0 ("AGPLv3") and some additional permissions and
 * terms pursuant to its AGPLv3 Section 7.
 */

namespace Bacularis\Common\Modules;

/**
 * Time-based one-time password module.
 * It is responsible for providing tools for using TOTP.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Totp extends CommonModule
{
	/**
	 * Time interval to regenerate new token.
	 */
	public const TIME_INTERVAL = 30;

	/**
	 * Length generated token.
	 */
	public const TOKEN_LENGTH = 6;

	/**
	 * Supported hash algorithms.
	 */
	public const ALG_SHA1 = 'sha1';
	public const ALG_SHA256 = 'sha256';
	public const ALG_SHA512 = 'sha512';

	/**
	 * Get counter.
	 * For TOTP the counter bases on current time.
	 *
	 * @return int counter
	 */
	public static function getCounter()
	{
		$ts = microtime(true) / self::TIME_INTERVAL;
		return floor($ts);
	}

	/**
	 * Get token.
	 *
	 * @param string $secret secret key
	 * @param int $counter time counter
	 * @param string $alg hash algorithm
	 */
	public function getToken($secret, $counter, $alg = self::ALG_SHA1)
	{
		$ret = '';
		if (strlen($secret) < 8) {
			// secret too short
			return $ret;
		}
		if (!$this->validateAlg($alg)) {
			// wrong algorighm
			return $ret;
		}
		$bin_counter = pack('J*', $counter);
		$hash = hash_hmac($alg, $bin_counter, $secret, true);
		$hmac = $this->getTokenFromHash($hash);
		$ret = str_pad($hmac, self::TOKEN_LENGTH, '0', STR_PAD_LEFT);
		return $ret;
	}

	/**
	 * Validate token using secret.
	 *
	 * @param string secret shared secret key
	 * @param int digit token
	 * @param mixed $secret
	 * @param mixed $token
	 * @return bool true if token is valid, otherwise false
	 */
	public function validateToken($secret, $token)
	{
		$counter = $this->getCounter();
		$token_gen = $this->getToken($secret, $counter);
		return (!empty($token) && $token === $token_gen);
	}

	/**
	 * Get all supported hash algorithms.
	 *
	 * @return array supported algorithms
	 */
	private function getHashAlgs()
	{
		return [
			self::ALG_SHA1,
			self::ALG_SHA256,
			self::ALG_SHA512
		];
	}

	/**
	 * Validate hash algorithm.
	 *
	 * @param string $alg algorithm name
	 * @return bool true if valid, otherwise false
	 */
	private function validateAlg($alg)
	{
		$algs = $this->getHashAlgs();
		return in_array($alg, $algs);
	}

	/**
	 * Get token from hash.
	 *
	 * @param string $hash token hash.
	 * @return int token value
	 */
	private function getTokenFromHash($hash)
	{
		$offset = ord($hash[strlen($hash) - 1]) & 0xF;

		$token = (
			((ord($hash[$offset]) & 0x7F) << 24) |
			((ord($hash[$offset + 1]) & 0xFF) << 16) |
			((ord($hash[$offset + 2]) & 0xFF) << 8) |
			 (ord($hash[$offset + 3]) & 0xFF)
		);
		$ret = $token % pow(10, self::TOKEN_LENGTH);
		return $ret;
	}
}
