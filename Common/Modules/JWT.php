<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2026 Marcin Haba
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

use Bacularis\Common\Modules\CommonModule;

/**
 * JWT - JSON Web Token module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class JWT extends CommonModule
{
	/**
	 * Supported signature algorithm types.
	 */
	public const ALG_RS256 = 'RS256';
	public const ALG_ES256 = 'ES256';

	/**
	 * Create data signature (sign data).
	 *
	 * @param array $props sign data command properties (key_path, use_sudo ...etc.)
	 * @param string $data data to sign
	 * @param array $cmd_params command paramters
	 * @return string signature string or empty signature on error
	 */
	public function getSignature(array $props, string $data, array $cmd_params = []): string
	{

		// sudo setting
		$use_sudo = false;
		if (key_exists('use_sudo', $cmd_params) && $cmd_params['use_sudo']) {
			$use_sudo = true;
		}

		// Credentials
		$user = $cmd_params['user'] ?? '';
		$password = $cmd_params['password'] ?? '';

		// private key file path
		$privkey_file = ($props['privkey_file'] ?? '');

		$mod = self::getKeyModule($props['key_type']);
		$cmd = $mod::getPrepareSignatureCommand($privkey_file, $data, $cmd_params);

		$su = $this->getModule('su');
		$params = [
			'command' => implode(' ', $cmd),
			'use_sudo' => $use_sudo
		];
		$result = $su->execCommand(
			$user,
			$password,
			$params
		);
		$state = ($result['exitcode'] == 0);
		if (!$state) {
			// Error
			$emsg = "Error while creating JWT signature.";
			$output = implode(PHP_EOL, $result['output']);
			$lmsg = $emsg . " ExitCode: {$result['exitcode']}, Error: {$output}.";
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				$lmsg
			);

		}
		$ret = '';
		if ($state) {
			$sig = self::getOutput($result['output']);
			$ret = Miscellaneous::encodeBase64URL($sig, true);
			$ret = trim($ret);
		}
		return $ret;
	}

	private static function getOutput(array $out): string
	{
		$output = '';
		for ($i = 0; $i < count($out); $i++) {
			if (preg_match('/^(spawn\s|password:|\[sudo\]\s)/i', $out[$i]) === 1) {
				continue;
			}
			if (preg_match('/^(EXITCODE=\d+|\s*)$/i', $out[$i]) === 1) {
				break;
			}
			$output .= trim($out[$i]);
		}
		return $output;
	}

	/**
	 * Get header.
	 *
	 * @param string $key_type key type (rsa, ecdsa...)
	 * @param array $key public key in JWK form
	 * @param string $url URL property
	 * @param string $nonce nonce value
	 * @param string $kid key identifier
	 * @return string encoded header
	 */
	public static function getHeader(string $key_type, array $key, string $url, string $nonce, string $kid): string
	{
		$alg = self::getSignAlgorithm($key_type);
		$header = [
			'alg' => $alg,
			'url' => $url,
			'nonce' => $nonce,
			'typ' => 'JWT'
		];
		if (!empty($kid)) {
			$header['kid'] = $kid;
		} elseif (!empty($key)) {
			$header['jwk'] = $key;
		}
		return self::encodePart($header);
	}

	/**
	 * Get token data.
	 *
	 * @param mixed $body token body
	 * @return string encoded data
	 */
	public static function getData($body): string
	{
		/**
		 * Empty string is for POST-as-GET requests.
		 * @see https://datatracker.ietf.org/doc/html/rfc8555#section-6.3
		 */
		$data = (is_array($body) && count($body) == 0) ? '' : self::encodePart($body);
		return $data;
	}

	/**
	 * Get token parts (header, data and paypload).
	 * Parts are prepared to construct token.
	 *
	 * @param array $props token properties
	 * @param mixed $data token data (payload)
	 * @param array $cmd_params command paramters
	 * @return array token parts
	 */
	public function getTokenParts(array $props, $data, array $cmd_params = []): array
	{
		$pubkey = $props['pubkey'] ?? [];
		$header = self::getHeader(
			$props['key_type'],
			$pubkey,
			$props['url'],
			$props['nonce'],
			$props['kid']
		);
		$data = self::getData($data);
		$sign = "${header}.${data}";
		$signature = $this->getSignature($props, $sign, $cmd_params);
		return [
			'header' => $header,
			'data' => $data,
			'signature' => $signature
		];
	}

	/**
	 * Encode token part.
	 *
	 * @param mixed $part token part to encode
	 * @return string encoded token part
	 */
	private static function encodePart($part): string
	{
		$json = json_encode($part);
		$b64url = Miscellaneous::encodeBase64URL($json);
		return $b64url;
	}

	/**
	 * Decode token.
	 *
	 * @param string $token JWT string
	 * @return array decoded token
	 */
	public static function decodeToken(string $token): array
	{
		$dtoken = [
			'header' => '',
			'body' => '',
			'signature' => ''
		];

		// Validate token string
		$pattern = '/^[A-Za-z0-9_-]{2,}(?:\.[A-Za-z0-9_-]{2,}){2}$/';
		if (preg_match($pattern, $token) !== 1) {
			// initial validation error
			$emsg = "Token validation error. Token: {$token}.";
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				$emsg
			);
			return $dtoken;
		}

		// Extract token parts
		[
			$header,
			$body,
			$signature
		] = self::extractTokenParts($token);

		// Decode parts
		$h = Miscellaneous::decodeBase64URL($header);
		$b = Miscellaneous::decodeBase64URL($body);
		$s = Miscellaneous::decodeBase64URL($signature);

		return [
			'header' => json_decode($h, true),
			'body' => json_decode($b, true),
			'signature' => $s
		];
	}

	/**
	 * Extract token parts into header, body and signature.
	 *
	 * @param string $token token value
	 * @return array token parts in order: [header, body, signature]
	 */
	public static function extractTokenParts(string $token): array
	{
		return explode('.', $token, 3);
	}

	/**
	 * Get signature algorithm.
	 *
	 * @param string $key_type key type
	 * @return string signature algorithm.
	 */
	public static function getSignAlgorithm(string $key_type): string
	{
		$alg = '';
		if ($key_type === RSAKey::KEY_TYPE) {
			$alg = self::ALG_RS256;
		} elseif ($key_type === ECDSAKey::KEY_TYPE) {
			$alg = self::ALG_ES256;
		} else {
			// Default is used RS256
			$alg = self::ALG_RS256;
		}
		return $alg;
	}

	public static function getKeyModule($type)
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
}
