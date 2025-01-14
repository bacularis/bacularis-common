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
 * ECDSA keys management.
 * It enables to create ECDSA key.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class ECDSAKey extends ShellCommandModule
{
	/**
	 * Key type.
	 */
	public const KEY_TYPE = 'ecdsa';


	/**
	 * Default key size (bits).
	 */
	private const DEFAULT_KEY_SIZE = 256;

	/**
	 * SUDO command.
	 */
	private const SUDO = 'sudo -S';

	/**
	 * Get command to prepare ECDSA private key.
	 * This command uses the OpenSSL binary.
	 *
	 * @param string $privkey_file private key file path
	 * @param null|integer $key_size key size in bits
	 * @param string $cmd_params key command parameters
	 * @return array command to prepare ECDSA key
	 */
	public static function getPreparePrivateKeyCommand(string $privkey_file, $key_size = null, array $cmd_params = []): array
	{
		$key_size = $key_size ?: self::DEFAULT_KEY_SIZE;
		$dest = [
			'-out',
			$privkey_file
		];
		$ret = [
			'openssl',
			'ecparam',
			'-name',
			'secp256k1',
			'-genkey',
			'-noout',
			...$dest,
			$key_size
		];
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Get command to prepare ECDSA public key.
	 * This command uses the OpenSSL binary.
	 *
	 * @param string $privkey_file private key file path
	 * @param string $dest_file destination file to write key
	 * @param string $cmd_params key command parameters
	 * @return array command to prepare ECDSA key
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
			'ec',
			'-in',
			$privkey_file,
			'-pubout',
			...$dest
		];
		static::setCommandParameters($ret, $params);
		return $ret;
	}

	/**
	 * Get prepare signature command using ECDSA key.
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
			$privkey_file,
			'2>&1'
		];
		array_unshift($ret, 'echo', '-n', $data, '|');
		array_push($ret, '|', 'openssl', 'enc', '-base64');
		$cmd_params['use_shell'] = true;
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}
}
