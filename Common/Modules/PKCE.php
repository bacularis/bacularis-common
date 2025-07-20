<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2025 Marcin Haba
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
 * Proof Key for Code Exchange (PKCE) module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class PKCE extends CommonModule
{
	/**
	 * Supported PKCE methods.
	 */
	public const CODE_CHALLENGE_METHOD_PLAIN = 'plain';
	public const CODE_CHALLENGE_METHOD_S256 = 'S256';

	/**
	 * Default random key size in bytes.
	 */
	public const DEF_KEY_LENGTH = 32;

	/**
	 * Generate random key.
	 *
	 * @param int $length key size
	 * @return string hexadecimal representation of key
	 */
	public static function generateRandomKey(int $length = self::DEF_KEY_LENGTH): string
	{
		$random = random_bytes($length);
		return bin2hex($random);
	}

	/**
	 * Get PKCE keys.
	 *
	 * @param string $method PKCE method
	 */
	public static function getKeys(string $method = self::CODE_CHALLENGE_METHOD_S256)
	{
		$key = self::generateRandomKey();
		$verifier = self::getCodeVerifier($key);
		$challenge = self::getCodeChallenge($verifier, $method);
		$keys = [
			'code_challenge' => $challenge,
			'code_verifier' => $verifier
		];
		return $keys;
	}

	/**
	 * Get code challenge key.
	 *
	 * @param string $vkey verifier PKCE key
	 * @param string $method PKCE method
	 * @return string base64url encoded code challenge key
	 */
	private static function getCodeChallenge(string $vkey, string $method): string
	{
		$challenge = $vkey;
		if (strtoupper($method) === self::CODE_CHALLENGE_METHOD_S256) {
			$vkey = hash('sha256', $vkey);
			$bin = pack('H*', $vkey);
			$challenge = Miscellaneous::encodeBase64URL($bin);
		}
		return $challenge;
	}

	/**
	 * Get code verifier key.
	 *
	 * @param string $key main PKCE key
	 * @return string base64url encoded code verifier key
	 */
	private static function getCodeVerifier(string $key): string
	{
		$bin = pack('H*', $key);
		$verifier = Miscellaneous::encodeBase64URL($bin);
		return $verifier;
	}
}
