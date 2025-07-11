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
 * Cryptographic keys management.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class CryptoKeys extends ShellCommandModule
{
	/**
	 * File prefix for temporary file with signature.
	 */
	private const SIGNATURE_FILE_PREFIX = 'sign_';

	/**
	 * File prefix for temporary file with key.
	 */
	private const PUBKEY_FILE_PREFIX = 'pubkey_';

	/**
	 * File prefix for temporary file with data.
	 */
	private const DATA_FILE_PREFIX = 'ckdata_';

	/**
	 * Create private key.
	 *
	 * @param string $key_type key type (rsa, ecdsa...)
	 * @param string $privkey_file private key file path
	 * @param array $cmd_params create key command properties (use_sudo ...)
	 * @return array command result
	 */
	public function createPrivateKey(string $key_type, string $privkey_file, array $cmd_params = []): array
	{
		$mod = self::getKeyModule($key_type);
		$cmd = $mod::getPreparePrivateKeyCommand($privkey_file, null, $cmd_params);
		$ret = $this->execCommand($cmd, $cmd_params);
		$state = ($ret['error'] == 0);
		if (!$state) {
			// Error while creating key
			$emsg = "Error while creating private key.";
			$output = implode(PHP_EOL, $ret['output']);
			$lmsg = $emsg . " ExitCode: {$ret['exitcode']}, Error: {$output}.";
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				$lmsg
			);
		}
		return $ret;
	}

	/**
	 * Get public key from private key.
	 *
	 * @param string $key_type key type (rsa, ecdsa...)
	 * @param string $privkey_file private key file path
	 * @param string $cmd_params key command parameters
	 * @return string command result with public key in output
	 */
	public function getPublicKey(string $key_type, string $privkey_file, array $cmd_params): array
	{
		$mod = self::getKeyModule($key_type);
		$cmd = $mod::getPreparePublicKeyCommand($privkey_file, '', $cmd_params);
		$ret = $this->execCommand($cmd, $cmd_params);
		$state = ($ret['error'] == 0);
		if (!$state) {
			// Error while creating key
			$emsg = "Error while getting public key.";
			$output = implode(PHP_EOL, $ret['output']);
			$lmsg = $emsg . " ExitCode: {$ret['exitcode']}, Error: {$output}.";
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				$lmsg
			);
		} else {
			$ret['output'] = self::getOutput($ret['output']);
		}
		return $ret;
	}

	/**
	 * Verify data signature.
	 *
	 * @param string $pubkey_file public key file path
	 * @param string $signature_file data signature file path to verify
	 * @param string $data_file data to verify signature
	 * @param array $cmd_params signature command paramters
	 * @return true if signature is verified successfully, false otherwise
	 */
	public function verifySignature(string $pubkey_file, string $signature_file, string $data_file, array $cmd_params = []): bool
	{
		$command = self::getVerifySignatureCommand($pubkey_file, $signature_file, $data_file, $cmd_params);
		$cmd = implode(' ', $command);
		exec($cmd, $output, $error);
		$state = ($error == 0);
		if (!$state) {
			// Error while creating key
			$emsg = "Signature verification error.";
			$out = implode(PHP_EOL, $output);
			$lmsg = $emsg . " ExitCode: {$error}, Error: {$out}.";
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				$lmsg
			);
		}
		return $state;
	}

	/**
	 * Verify data signature string.
	 *
	 * @param string $pubkey public key string
	 * @param string $signature data signature to verify
	 * @param string $data data to verify signature
	 * @param string $tmpdir temporary directory for storing files
	 * @param array $cmd_params signature command paramters
	 * @return true if signature is verified successfully, false otherwise
	 */
	public function verifySignatureString(string $pubkey, string $signature, string $data, string $tmpdir, array $cmd_params = []): bool
	{
		$pubkey_file = tempnam($tmpdir, self::PUBKEY_FILE_PREFIX);
		if (file_put_contents($pubkey_file, $pubkey, LOCK_EX) === false) {
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				"Error while writing public key file {$pubkey_file}"
			);
		}

		$signature_file = tempnam($tmpdir, self::SIGNATURE_FILE_PREFIX);
		if (file_put_contents($signature_file, $signature, LOCK_EX) === false) {
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				"Error while writing signature file {$signature_file}"
			);
		}

		$data_file = tempnam($tmpdir, self::DATA_FILE_PREFIX);
		if (file_put_contents($data_file, $data, LOCK_EX) === false) {
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				"Error while writing data file {$data_file}"
			);
		}

		$state = $this->verifySignature($pubkey_file, $signature_file, $data_file, $cmd_params);

		if (!unlink($pubkey_file)) {
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				"Error while removing public key file {$pubkey_file}"
			);
		}
		if (!unlink($signature_file)) {
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				"Error while removing signature file {$signature_file}"
			);
		}
		if (!unlink($data_file)) {
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				"Error while removing data file {$data_file}"
			);
		}

		return $state;
	}

	/**
	 * Get JWK thumbprint.
	 *
	 * @param string $key_type key type (rsa, ecdsa...)
	 * @param string $privkey_file private key file path
	 * @param string $cmd_params key command parameters
	 * @return array command result with JWK thumbprint in output
	 */
	public function getJWKThumbprint(string $key_type, string $privkey_file, array $cmd_params): array
	{
		$ret = $this->getPublicKey($key_type, $privkey_file, $cmd_params);
		$state = ($ret['error'] == 0);
		if (!$state) {
			return $ret;
		}
		$pubkey = $ret['output'];

		$ret = $this->getPublicKeyJWKFormat($key_type, $pubkey, $cmd_params);
		$state = ($ret['error'] == 0);
		if (!$state) {
			return $ret;
		}
		$pubkey_props = $ret['output'];
		$key = [
			'e' => $pubkey_props['e'],
			'kty' => $pubkey_props['kty'],
			'n' => $pubkey_props['n']
		];
		$pubkey_json = json_encode($key);

		$mod = self::getKeyModule($key_type);
		$cmd = $mod::getPrepareJWKThumbprintCommand($pubkey_json, $cmd_params);
		$ret = $this->execCommand($cmd, $cmd_params);
		$state = ($ret['error'] == 0);
		if (!$state) {
			// Error
			$emsg = "Error while getting public key thumbprint.";
			$output = implode(PHP_EOL, $ret['output']);
			$lmsg = $emsg . " ExitCode: {$ret['exitcode']}, Error: {$output}.";
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				$lmsg
			);
		} else {
			// Everything fine
			$result = self::parseThumbprintOutput($ret['output']);
			$ret['output'] = Miscellaneous::encodeBase64URL($result, true);
		}
		return $ret;
	}

	/**
	 * Parse openssl thumbprint output to get thumbprint.
	 *
	 * @param array $out thumbprint output
	 * @return string thumbprint
	 */
	private static function parseThumbprintOutput(array $out)
	{
		$thumbprint = '';
		for ($i = 0; $i < count($out); $i++) {
			if (preg_match('/^(spawn\s|password:|\[sudo\]\s)/i', $out[$i]) === 1) {
				continue;
			}
			if (preg_match('/^(EXITCODE=\d+|\s*)$/i', $out[$i]) === 1) {
				break;
			}
			$thumbprint .= trim($out[$i]);
		}
		return $thumbprint;
	}

	/**
	 * Get public key in JWK form.
	 *
	 * @param string $key_type key type (rsa, ecdsa...)
	 * @param string $pubkey public key
	 * @param string $cmd_params key command parameters
	 * @return array command output with JWK public key in output
	 */
	public function getPublicKeyJWKFormat(string $key_type, string $pubkey, array $cmd_params): array
	{
		$mod = self::getKeyModule($key_type);
		$cmd = $mod::getPreparePublicKeyModulusExponentCommand($pubkey, $cmd_params);
		$ret = $this->execCommand($cmd, $cmd_params);
		$state = ($ret['error'] == 0);
		if (!$state) {
			// Error while creating key
			$emsg = "Error while getting public key modulus and exponent.";
			$output = implode(PHP_EOL, $ret['output']);
			$lmsg = $emsg . " ExitCode: {$ret['exitcode']}, Error: {$output}.";
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				$lmsg
			);
		} else {
			$me = self::parseModulusExponent($ret['output']);
			$ret['output'] = [
				'kty' => strtoupper($key_type),
				'n' => $me['modulus'],
				'e' => $me['exponent'],
				'alg' => JWT::getSignAlgorithm($key_type),
				'ext' => true,
				'kid' => md5($me['modulus']),
				'use' => 'sig'
			];
		}
		return $ret;
	}

	/**
	 * Modulus and exponent value parser.
	 * Output example:
	 *
	 * Public-Key: (2048 bit)
	 * Modulus:
	 *     00:a9:f2:b8:64:a8:09:24:68:42:96:2d:de:f3:3f:
	 *     9b:cb:8c:0c:28:b8:eb:7d:e8:7e:f7:ec:e4:51:ec:
	 *     4a:ca:f5:5b:52:ff:83:6a:52:2a:77:74:b7:be:89:
	 *     d9:bc:c9:e7:40:b8:91:e3:4f:46:80:8d:dd:b0:06:
	 *     79:9e:ca:d4:53:5e:ba:fb:8e:32:16:0c:80:0b:7a:
	 *     ce:7e:1f:2f:1a:09:75:8b:c7:0a:82:87:73:d4:a8:
	 *     9a:fc:ba:eb:7a:76:01:eb:c9:35:70:f0:ef:5f:6e:
	 *     80:9b:1b:c2:4b:11:22:d7:69:f8:e8:95:c8:fa:2b:
	 *     0f:26:e0:c6:e0:76:0d:b9:90:db:aa:bf:93:94:76:
	 *     c0:d7:a3:ab:e9:45:16:08:b9:6d:22:58:37:21:c1:
	 *     de:b0:ff:c7:1a:2a:8a:3c:43:1b:1a:ac:b4:b2:f2:
	 *     fb:19:b9:33:1e:ab:d4:c8:4f:ab:d6:06:fb:84:0f:
	 *     17:31:e9:13:64:7e:44:58:9a:d1:73:77:da:16:c1:
	 *     89:23:8e:da:78:8d:41:3d:3b:f6:57:81:62:f1:a3:
	 *     73:7b:d0:83:e7:a3:45:78:e5:b8:32:25:f0:18:16:
	 *     60:2f:f1:bd:e9:0c:cd:14:3c:45:f5:7d:c2:4c:99:
	 *     ea:50:47:73:46:94:bc:dc:65:7d:c6:15:cb:b9:71:
	 *     4b:cd
	 * Exponent: 65537 (0x10001)
	 *
	 * @param array $out openssl certificate command output
	 * @return array parsed modulus and exponent
	 */
	private static function parseModulusExponent(array $out): array
	{
		$section = $modulus = $exponent = '';
		for ($i = 0; $i < count($out); $i++) {
			if (preg_match('/^(?P<section>Modulus):/i', $out[$i], $match) === 1) {
				$section = strtolower($match['section']);
				continue;
			}
			if (preg_match('/^Exponent:\s+(?P<exponent>\d+)\s+/i', $out[$i], $match) === 1) {
				$exponent = $match['exponent'];
				break;
			}

			if ($section == 'modulus') {
				$modulus .= str_replace(':', '', trim($out[$i]));
			}
		}
		$modulus = preg_replace('/^00/', '', $modulus); // leading '00' is not part of modulus
		$exponent_hex = dechex($exponent);
		$modulus_bin = hex2bin($modulus);
		$modulus_b64url = Miscellaneous::encodeBase64URL($modulus_bin);
		$exponent_b64 = Miscellaneous::hexToBase64($exponent_hex);
		$exponent_b64url = Miscellaneous::encodeBase64URL($exponent_b64, true);
		return [
			'modulus' => $modulus_b64url,
			'exponent' => $exponent_b64url
		];
	}

	/**
	 * Get key module by type.
	 *
	 * @param string $type key type
	 * @return string key module
	 */
	public static function getKeyModule(string $type): string
	{
		$mod = '';
		if ($type === RSAKey::KEY_TYPE) {
			$mod = RSAKey::class;
		} elseif ($type === ECDSAKey::KEY_TYPE) {
			$mod = ECDSAKey::class;
		} else {
			// Default is used RSA key type
			$mod = RSAKey::class;
		}
		return $mod;
	}

	private static function getOutput(array $out): string
	{
		$output = [];
		for ($i = 0; $i < count($out); $i++) {
			$line = trim($out[$i]);
			if (preg_match('/^(spawn\s|password:|\[sudo\]\s|writing\sRSA\skey)/i', $line) === 1) {
				continue;
			}
			if (empty($line)) {
				break;
			}
			$output[] = $line;
		}
		return implode(PHP_EOL, $output);
	}

	/**
	 * Verify signature command using public key.
	 *
	 * @param string $pubkey_file public key file path
	 * @param string $signature_file data signature file to verify
	 * @param string $data_file data file to verify signature
	 * @param array $cmd_params signature command paramters
	 * @return array signature command
	 */
	public static function getVerifySignatureCommand(string $pubkey_file, string $signature_file, string $data_file, array $cmd_params = []): array
	{
		$ret = [
			'openssl',
			'dgst',
			'-sha256',
			'-verify',
			$pubkey_file,
			'-signature',
			$signature_file,
			$data_file
		];
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Convert DER key format to PEM format.
	 *
	 * @param string $der_data DER binary string data
	 * @param string $type key type to set begin and end markers
	 * @return string key in PEM format
	 */
	public function derToPem(string $der_data, string $type = 'PUBLIC KEY'): string
	{
		$key_b64 = base64_encode($der_data);
		$pem = chunk_split($key_b64, 64, "\n");
		$pem = sprintf(
			"-----BEGIN %s-----\n%s\n-----END %s-----",
			$type,
			trim($pem),
			$type
		);
		return $pem;
	}
}
