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

use Bacularis\Common\Modules\Protocol\ACME\ChallengeHTTP01;

/**
 * Let's Encrypt certificate management.
 * It uses ACME protocol.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class LetsEncryptCert extends CommonModule
{
	/**
	 * Certificate type.
	 */
	public const CERT_TYPE = 'lets-encrypt';

	/**
	 * ACME account private key file name.
	 *
	 * @var string
	 */
	private const PRIV_KEY_FILE = 'bacularis_letsencrypt_key.pem';

	/**
	 * ACME directory URL.
	 *
	 * @var string
	 */
	private const DIRECTORY_URL = 'https://acme-v02.api.letsencrypt.org/directory';
	// private const DIRECTORY_URL = 'https://acme-staging-v02.api.letsencrypt.org/directory';

	/**
	 * Create new Let's Encrypt account.
	 *
	 * @param string $email account email address
	 * @param array $cmd_params command parameters
	 * @return array account details
	 */
	public function createAccount(string $email, array $cmd_params): array
	{
		$props = [
			'directory_url' => self::DIRECTORY_URL,
			'email' => $email,
			'tos_agreed' => true,
			'privkey_file' => self::getKeyFilePath()
		];
		$acme_account = $this->getModule('acme_account');
		return $acme_account->createAccount($props, $cmd_params);
	}

	/**
	 * Get existing Let's Encrypt account.
	 *
	 * @param array $cmd_params command parameters
	 * @return array account details
	 */
	public function getExistingAccount(array $cmd_params): array
	{
		$props = [
			'directory_url' => self::DIRECTORY_URL,
			'privkey_file' => self::getKeyFilePath()
		];
		$acme_account = $this->getModule('acme_account');
		return $acme_account->getExistingAccount($props, $cmd_params);
	}

	/**
	 * Order new Let's Encrypt certificate issue.
	 *
	 * @param string $common_name common name address
	 * @param string $email email address
	 * @param array $params order request parameters
	 * @param array $cmd_params command parameters
	 * @return array order details
	 */
	public function createOrder(string $common_name, string $email, array $params, array $cmd_params): array
	{
		$ssl_cert = $this->getModule('ssl_cert');
		// [STEP 1] Prepare order
		$props = [
			'directory_url' => self::DIRECTORY_URL,
			'common_name' => $common_name,
			'kid' => ($params['kid'] ?? ''),
			'nonce' => ($params['nonce'] ?? ''),
			'privkey_file' => self::getKeyFilePath()
		];
		$acme_order = $this->getModule('acme_order');
		$result = $acme_order->createOrder($props, $cmd_params);
		$state = ($result['error'] == 0);
		if (!$state) {
			// Error, END.
			return $result;
		}
		$order_url = $result['kid'] ?? '';

		// [STEP 2] Authorize
		$acme_authz = $this->getModule('acme_authz');
		$authz_url = $result['output']['authorizations'][0] ?? '';
		$props = [
			'kid' => ($params['kid'] ?? ''),
			'nonce' => ($result['nonce'] ?? ''),
			'url' => $authz_url,
			'privkey_file' => self::getKeyFilePath()
		];
		$finalize_url = $result['output']['finalize'] ?? '';
		$result = $acme_authz->authorize($props, $cmd_params);
		$state = ($result['error'] == 0);
		if (!$state) {
			// Error, END.
			return $result;
		}

		// [STEP 3] Do http-01 challenge
		$chall = [];
		$challenges = $result['output']['challenges'] ?? [];
		for ($i = 0; $i < count($challenges); $i++) {
			if ($challenges[$i]['type'] == ChallengeHTTP01::CHALLENGE_NAME) {
				$chall = $challenges[$i];
				break;
			}
		}
		$props = [
			'kid' => ($params['kid'] ?? ''),
			'nonce' => ($result['nonce'] ?? ''),
			'url' => ($chall['url'] ?? ''),
			'token' => ($chall['token'] ?? ''),
			'privkey_file' => self::getKeyFilePath()
		];
		$acme_challenge_http01 = $this->getModule('acme_challenge_http01');
		$result = $acme_challenge_http01->challenge($props, $cmd_params);
		$state = ($result['error'] == 0);
		if (!$state) {
			// Error, END.
			return $result;
		}

		// [STEP 4] Finalize
		$props = [
			'kid' => ($params['kid'] ?? ''),
			'nonce' => ($result['nonce'] ?? ''),
			'url' => $finalize_url,
			'common_name' => $common_name,
			'email' => $email,
			'privkey_file' => self::getKeyFilePath(),
			'authz_url' => $authz_url,
			'order_url' => $order_url
		];
		$acme_finalize = $this->getModule('acme_finalize');
		$result = $acme_finalize->finalization($props, $cmd_params);
		$state = ($result['error'] == 0);
		if (!$state) {
			// Error, END.
			return $result;
		}

		// [STEP 5] Check certificate order
		$props = [
			'kid' => ($params['kid'] ?? ''),
			'nonce' => ($result['nonce'] ?? ''),
			'privkey_file' => self::getKeyFilePath(),
			'url' => $result['kid']
		];
		$result = $acme_order->checkOrder($props, $cmd_params);
		if (!$state) {
			// Error, END.
			$ssl_cert->restoreCertAndKeyFiles($cmd_params);
			return $result;
		}

		// [STEP 6] Download certificate
		$props = [
			'kid' => ($params['kid'] ?? ''),
			'nonce' => ($result['nonce'] ?? ''),
			'privkey_file' => self::getKeyFilePath(),
			'url' => ($result['output']['certificate'] ?? '')
		];
		$acme_download = $this->getModule('acme_download');
		$result = $acme_download->downloadCertificate($props, $cmd_params);

		$state = ($result['error'] == 0);
		if (!$state) {
			$ssl_cert->restoreCertAndKeyFiles($cmd_params);
			return $result;
		}

		// [STEP 7] Write cert to file
		$ssl_cert = $this->getModule('ssl_cert');
		$result = $ssl_cert->createCertFile($result['raw'], $cmd_params);
		$state = ($result['error'] == 0);
		if (!$state) {
			$ssl_cert->restoreCertAndKeyFiles($cmd_params);
			return $result;
		}
		return $result;
	}

	/**
	 * Renew Let's encrypt certificate.
	 *
	 * @param array $cmd_params command parameters
	 * @return array response details
	 */
	public function renewCert(array $cmd_params = []): array
	{
		// Get certificate information
		$result = SSLCertificate::getCertInfo();
		$state = ($result['error'] == 0);
		if (!$state) {
			return $result;
		}

		// Get common name from existing certificate
		$san = $result['output']['x509v3_ext']['x509v3_subj_alt_name'] ?? [];
		$common_name = '';
		if (count($san) == 1) {
			[, $cn] = explode(':', $san[0], 2);
			$common_name = trim($cn);
		}

		// Get existing Let's Encrypt account information
		$result = $this->getExistingAccount($cmd_params);
		$state = ($result['error'] == 0);
		if (!$state) {
			return $result;
		}

		// Renew the certificate
		$result = $this->createOrder(
			$common_name,
			'',
			$result,
			$cmd_params
		);
		return $result;
	}

	/**
	 * Get private key file path.
	 *
	 * @return string private key file path
	 */
	public static function getKeyFilePath(): string
	{
		$path = SSLCertificate::getBasePath();
		$path = implode(DIRECTORY_SEPARATOR, [$path, self::PRIV_KEY_FILE]);
		return $path;
	}
}
