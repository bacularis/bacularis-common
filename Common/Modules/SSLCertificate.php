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
 * SSL certificate module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class SSLCertificate extends CommonModule
{
	/**
	 * Certificate file name.
	 *
	 * @var string
	 */
	private const CERT_FILE = 'bacularis.crt';

	/**
	 * Private key file name.
	 *
	 * @var string
	 */
	private const PRIV_KEY_FILE = 'bacularis.key';

	/**
	 * PEM file name with certificate and key.
	 * PEM file is used by the Lighttpd web server.
	 *
	 * @var string
	 */
	private const PEM_FILE = 'bacularis.pem';

	/**
	 * Sudo command.
	 *
	 * @var string
	 */
	private const SUDO = 'sudo -S';

	/**
	 * Set common command parameters.
	 *
	 * @param array $command command reference
	 * @param array $params command parameters
	 */
	private static function setCommandParameters(array &$command, array $params)
	{
		if (key_exists('use_shell', $params) && $params['use_shell']) {
			$command = array_map(
				fn ($item) => str_replace(['"'], ['\\\"'], $item),
				$command
			);
			array_unshift($command, 'sh', '-c', '"');
			array_push($command, '"');
		}
		if (key_exists('use_sudo', $params) && $params['use_sudo']) {
			array_unshift($command, self::SUDO);
		}
		array_unshift($command, 'LANG=C');
	}

	/**
	 * Get command to prepare SSL certificate for web server HTTPS connections.
	 * This command uses the OpenSSL binary.
	 *
	 * @param string $params certificate parameters
	 * @return array command to prepare certificate
	 */
	public static function getPrepareHTTPSCertCommand(array $params): array
	{
		// Default parameter values
		$def_days_no = '3650';
		$def_country_code = 'ZZ';
		$def_state = 'Bacularis';
		$def_locality = 'Bacularis';
		$def_organization = 'Bacularis web interface';
		$def_organization_unit = 'Bacularis';
		$def_common_name = 'localhost';
		$def_email = 'non-existing-email@localhost';

		$key_file = self::getKeyFilePath();
		$cert_file = self::getCertFilePath();
		$subj = [];
		$subj[] = 'C=' . ($params['country_code'] ?? $def_country_code);
		$subj[] = 'ST=' . ($params['state'] ?? $def_state);
		$subj[] = 'L=' . ($params['locality'] ?? $def_locality);
		$subj[] = 'O=' . ($params['organization'] ?? $def_organization);
		$subj[] = 'OU=' . ($params['organization_unit'] ?? $def_organization_unit);
		$subj[] = 'CN=' . ($params['common_name'] ?? $def_common_name);
		$subj[] = 'emailAddress=' . ($params['email'] ?? $def_email);
		$ret = [
			'openssl',
			'req',
			'-x509',
			'-nodes',
			'-days ' . ($params['days_no'] ?? $def_days_no),
			'-newkey',
			'rsa:2048',
			'-keyout',
			$key_file,
			'-out',
			$cert_file,
			'-subj',
			'"/' . implode('/', $subj) . '"'
		];
		self::setCommandParameters($ret, $params);
		return $ret;
	}

	/**
	 * Get command to remove SSL certificate and key.
	 *
	 * @param string $params certificate parameters
	 * @return array command to prepare certificate
	 */
	public static function getRemoveHTTPSCertCommand(array $params): array
	{
		$key_file = self::getKeyFilePath();
		$cert_file = self::getCertFilePath();
		$ret = [
			'{',
			'rm',
			'-f',
			$key_file,
			';',
			'rm',
			'-f',
			$cert_file,
			';',
			'}'
		];
		$params['use_shell'] = true; // force using shell
		self::setCommandParameters($ret, $params);
		return $ret;
	}

	/**
	 * Get command to prepare certificate PEM file for HTTPS connection.
	 *
	 * @param array $params command options
	 * @return array command to prepare certificate PEM file
	 */
	public static function getPrepareHTTPSPemCommand(array $params = []): array
	{
		$key_file = self::getKeyFilePath();
		$cert_file = self::getCertFilePath();
		$pem_file = self::getPEMFilePath();
		$ret = [
			'{',
			'cat',
			$key_file,
			$cert_file,
			'>',
			$pem_file,
			';',
			'chmod 600 ' . $pem_file,
			';',
			'}'
		];
		$params['use_shell'] = true; // force using shell
		self::setCommandParameters($ret, $params);
		return $ret;
	}

	/**
	 * Get command to remove certificate PEM file for HTTPS connection.
	 *
	 * @param array $params command options
	 * @return array command to prepare certificate PEM file
	 */
	public static function getRemoveHTTPSPemCommand(array $params = []): array
	{
		$pem_file = self::getPEMFilePath();
		$ret = [
			'rm',
			'-f',
			$pem_file
		];
		self::setCommandParameters($ret, $params);
		return $ret;
	}

	/**
	 * Get SSL certificate info.
	 * This command uses the OpenSSL binary.
	 *
	 * @return array certificate details
	 */
	public static function getCertDetailsCommand(): array
	{
		$cert_file = self::getCertFilePath();
		$ret = [
			'openssl',
			'x509',
			'-in ' . $cert_file,
			'-noout',
			'-text',
			'-nameopt multiline'
		];
		return $ret;
	}

	/**
	 * Parse certificate fields from openssl binary program output.
	 *
	 * parsed output:
	 *         Issuer:
	 *             countryName               = PL
	 *             stateOrProvinceName       = slaskie
	 *             localityName              = Bakowice
	 *             organizationName          = Bacularis
	 *             organizationalUnitName    = dev
	 *             commonName                = mymhost.domain
	 *             emailAddress              = my-non-exising@e-mail.lan
	 *         Validity
	 *             Not Before: Dec 28 14:38:39 2024 GMT
	 *             Not After : Dec 26 14:38:39 2034 GMT
	 *         Subject:
	 *             countryName               = PL
	 *             stateOrProvinceName       = slaskie
	 *             localityName              = Bakowice
	 *             organizationName          = Bacularis
	 *             organizationalUnitName    = dev
	 *             commonName                = otherhost.domain
	 *             emailAddress              = my-non-exising@e-mail.lan
	 *
	 * @param array $output openssl command output with certificate
	 * @return array parsed output
	 */
	public static function parseOpenSSLCert(array $output): array
	{
		$ret = [];
		$section = '';
		$sep = '';
		for ($i = 0; $i < count($output); $i++) {
			// section 1
			if (preg_match('/\s*(?P<section>Subject|Issuer):?$/i', $output[$i], $match) === 1) {
				$section = strtolower($match['section']);
				$ret[$section] = [];
				$sep = '=';
				continue;
			}

			// section 2
			if (preg_match('/\s*(?P<section>Validity)$/i', $output[$i], $match) === 1) {
				$section = strtolower($match['section']);
				$ret[$section] = [];
				$sep = ':';
				continue;
			}

			// properties (for section 1 and 2)
			if (preg_match('/\s*(?P<prop>[\w\s]+)\s*' . $sep . '\s*(?P<value>.*)$/i', $output[$i], $match) === 1 && $section) {
				$prop = self::getCertProp($match['prop']);
				$ret[$section][$prop] = $match['value'];
				continue;
			}
		}
		return $ret;
	}

	/**
	 * Check if the certificate file exists.
	 *
	 * @return bool true if cert file exists, false otherwise
	 */
	public static function certExists(): bool
	{
		$cert_file = self::getCertFilePath();
		return file_exists($cert_file);
	}

	/**
	 * Convert certificate specific properties into props used by Bacularis.
	 *
	 * @param string $prop certificate property
	 * @return string converted property
	 */
	private static function getCertProp(string $prop): string
	{
		$prop = trim($prop);
		$cprop = $prop;
		if ($prop == 'countryName') {
			$cprop = 'country_code';
		}
		if ($prop == 'stateOrProvinceName') {
			$cprop = 'state';
		}
		if ($prop == 'localityName') {
			$cprop = 'locality';
		}
		if ($prop == 'organizationName') {
			$cprop = 'organization';
		}
		if ($prop == 'organizationalUnitName') {
			$cprop = 'organization_unit';
		}
		if ($prop == 'commonName') {
			$cprop = 'common_name';
		}
		if ($prop == 'emailAddress') {
			$cprop = 'email';
		}
		if ($prop == 'Not Before') {
			$cprop = 'not_before';
		}
		if ($prop == 'Not After') {
			$cprop = 'not_after';
		}
		return $cprop;
	}

	/**
	 * Get base directory where are stored cert and key.
	 *
	 * @return string base directory path
	 */
	public static function getBasePath(): string
	{
		$path = DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, [
			'etc',
			'bacularis'
		]);
		return $path;
	}

	/**
	 * Get certificate file path.
	 *
	 * @return string certificate file path
	 */
	public static function getCertFilePath(): string
	{
		$path = self::getBasePath();
		$path = implode(DIRECTORY_SEPARATOR, [$path, self::CERT_FILE]);
		return $path;
	}

	/**
	 * Get private key file path.
	 *
	 * @return string private key file path
	 */
	public static function getKeyFilePath(): string
	{
		$path = self::getBasePath();
		$path = implode(DIRECTORY_SEPARATOR, [$path, self::PRIV_KEY_FILE]);
		return $path;
	}

	/**
	 * Get PEM file path.
	 * This PEM file stores cert and key together.
	 * It is used by the Lighttpd web server.
	 *
	 * @return string PEM file path
	 */
	public static function getPEMFilePath(): string
	{
		$path = self::getBasePath();
		$path = implode(DIRECTORY_SEPARATOR, [$path, self::PEM_FILE]);
		return $path;
	}
}
