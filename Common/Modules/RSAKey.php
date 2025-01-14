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
 * RSA keys management.
 * It enables to create RSA key.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class RSAKey extends ShellCommandModule
{
	/**
	 * Key type.
	 */
	public const KEY_TYPE = 'rsa';


	/**
	 * Default key size (bits).
	 */
	private const DEFAULT_KEY_SIZE = 2048;

	/**
	 * SUDO command.
	 */
	private const SUDO = 'sudo -S';

	/**
	 * Get command to prepare RSA private key.
	 * This command uses the OpenSSL binary.
	 *
	 * @param string $privkey_file private key file path
	 * @param int $key_size key size in bits
	 * @param string $cmd_params key command parameters
	 * @return array command to prepare RSA key
	 */
	public static function getPreparePrivateKeyCommand(string $privkey_file, $key_size = null, array $cmd_params = []): array
	{
		$key_size = $key_size ?: self::DEFAULT_KEY_SIZE;
		$ret = [
			'openssl',
			'genrsa',
			'-out',
			$privkey_file,
			$key_size
		];
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Get command to prepare RSA public key.
	 * This command uses the OpenSSL binary.
	 *
	 * @param string $privkey_file private key file path
	 * @param string $dest_file destination file to write key
	 * @param string $cmd_params key command parameters
	 * @return array command to prepare RSA key
	 */
	public static function getPreparePublicKeyCommand(string $privkey_file, string $dest_file = '', array $cmd_params = []): array
	{
		$dest = [];
		if ($dest_file) {
			$dest = [
				'-out',
				$dest_file
			];
		}
		$ret = [
			'openssl',
			'rsa',
			'-in',
			$privkey_file,
			'-pubout',
			...$dest
		];
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Get command to extract modulus and exponent from the RSA public key.
	 * This command uses the OpenSSL binary.
	 *
	 * @param string $pubkey public key
	 * @param string $cmd_params key command parameters
	 * @return array command to prepare RSA key
	 */
	public static function getPreparePublicKeyModulusExponentCommand(string $pubkey, array $cmd_params = []): array
	{
		$ret = [
			'echo',
			'"' . $pubkey . '"',
			'|',
			'openssl',
			'rsa',
			'-pubin',
			'-inform',
			'PEM',
			'-text',
			'-noout'
		];
		$cmd_params['use_shell'] = true;
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Get prepare signature command using RSA key.
	 *
	 * @param string $privkey_file private key file path
	 * @param string $data data to get signature
	 * @param array $cmd_params signature command paramters
	 * @return array signature command
	 */
	public static function getPrepareSignatureCommand(string $privkey_file, string $data, array $cmd_params = []): array
	{
		$ret = [
			'openssl',
			'dgst',
			'-sha256',
			'-binary',
			'-sign',
			$privkey_file
		];
		array_unshift($ret, 'echo', '-n', $data, '|');
		array_push($ret, '|', 'openssl', 'enc', '-base64');
		$cmd_params['use_shell'] = true;
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Get prepare public key thumbprint value.
	 *
	 * @param string $key_json key string
	 * @param array $cmd_params command paramters
	 * @return array thumbprint command
	 */
	public static function getPrepareJWKThumbprintCommand(string $key_json, array $cmd_params = []): array
	{
		$ret = [
			'echo',
			'-n',
			'\'\\\'\'' . $key_json . '\'\\\'\'',
			'|',
			'openssl',
			'sha256',
			'-binary'
		];
		array_push($ret, '|', 'openssl', 'enc', '-base64');
		$cmd_params['use_shell'] = true;
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}
}
