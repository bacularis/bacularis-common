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

use DateTime;

/**
 * SSL certificate module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class SSLCertificate extends ShellCommandModule
{
	/**
	 * Certificate file name.
	 *
	 * @var string
	 */
	private const CERT_FILE = 'bacularis_cert.pem';

	/**
	 * Private key file name.
	 *
	 * @var string
	 */
	private const PRIV_KEY_FILE = 'bacularis_key.pem';

	/**
	 * PEM file name with certificate and key.
	 * This PEM file is used by the Lighttpd web server.
	 *
	 * @var string
	 */
	private const PEM_FILE = 'bacularis.pem';

	/**
	 * Default number of days before certificate expiration
	 * when the certifacte will be renewed.
	 * Value in days.
	 *
	 * @var string
	 */
	public const DEFAULT_REFRESH_CERT_DAYS = 30;

	/**
	 * Get command to prepare SSL certificate for web server HTTPS connections.
	 * This command uses the OpenSSL binary.
	 *
	 * @param string $params certificate parameters
	 * @param string $cmd_params command parameters (use_sudo, user, password...)
	 * @return array command to prepare certificate
	 */
	public static function getPrepareHTTPSCertCommand(array $params, array $cmd_params): array
	{
		// Default parameter values
		$def_days_no = '3650';

		$key_file = self::getKeyFilePath();
		$cert_file = self::getCertFilePath();
		$subj = [];
		if (key_exists('country_code', $params)) {
			$subj[] = 'C=' . $params['country_code'];
		}
		if (key_exists('state', $params)) {
			$subj[] = 'ST=' . $params['state'];
		}
		if (key_exists('locality', $params)) {
			$subj[] = 'L=' . $params['locality'];
		}
		if (key_exists('organization', $params)) {
			$subj[] = 'O=' . $params['organization'];
		}
		if (key_exists('organization_unit', $params)) {
			$subj[] = 'OU=' . $params['organization_unit'];
		}
		if (key_exists('common_name', $params)) {
			$subj[] = 'CN=' . $params['common_name'];
		}
		if (key_exists('email', $params)) {
			$subj[] = 'emailAddress=' . $params['email'];
		}
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
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Get command to prepare CSR (Certificate Signing Request).
	 * This command uses the OpenSSL binary.
	 *
	 * @param string $params certificate parameters
	 * @param string $cmd_params command parameters (use_sudo, user, password...)
	 * @return array command to prepare certificate
	 */
	public static function getPrepareCSRCommand(array $params, array $cmd_params = []): array
	{
		// Default parameter values
		$def_country_code = 'ZZ';
		$def_state = 'Bacularis';
		$def_locality = 'Bacularis';
		$def_organization = 'Bacularis web interface';
		$def_organization_unit = 'Bacularis';
		$def_common_name = 'localhost';

		$key_file = self::getKeyFilePath();
		$subj = [];
		$subj[] = 'C=' . ($params['country_code'] ?? $def_country_code);
		$subj[] = 'ST=' . ($params['state'] ?? $def_state);
		$subj[] = 'L=' . ($params['locality'] ?? $def_locality);
		$subj[] = 'O=' . ($params['organization'] ?? $def_organization);
		$subj[] = 'OU=' . ($params['organization_unit'] ?? $def_organization_unit);
		$subj[] = 'CN=' . ($params['common_name'] ?? $def_common_name);
		if (key_exists('email', $params) && !empty($params['email'])) {
			$subj[] = 'emailAddress=' . $params['email'];
		}
		$ret = [
			'openssl',
			'req',
			'-new',
			'-key',
			$key_file,
			'-subj',
			'"/' . implode('/', $subj) . '"',
			'-addext',
			'"subjectAltName = DNS:' . ($params['common_name'] ?? $def_common_name) . '"',
			'-outform',
			'DER'

		];
		array_push($ret, '|', 'openssl', 'enc', '-base64');
		$cmd_params['use_shell'] = true;
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Get command to create SSL certificate.
	 *
	 * @param string $certificate certificate in PEM form
	 * @param string $cmd_params certificate parameters
	 * @return array command to prepare certificate
	 */
	public static function getCreateHTTPSCertCommand(string $certificate, array $cmd_params): array
	{
		$cert_file = self::getCertFilePath();
		$ret = [
			'echo',
			'-n',
			'"' . $certificate . '"',
			'>',
			$cert_file
		];
		$cmd_params['use_shell'] = true; // force using shell
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Get command to remove SSL certificate and key.
	 *
	 * @param string $cmd_params command parameters
	 * @return array command to prepare certificate
	 */
	public static function getRemoveHTTPSCertKeyCommand(array $cmd_params): array
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
		$cmd_params['use_shell'] = true; // force using shell
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Get command to do backup SSL certificate and key.
	 *
	 * @param string $cmd_params command parameters
	 * @return array command to prepare certificate
	 */
	public static function getBackupHTTPSCertKeyCommand(array $cmd_params): array
	{
		$key_file = self::getKeyFilePath();
		$cert_file = self::getCertFilePath();
		$ret = [
			'{',
			'[',
			'-e',
			$key_file,
			'-a',
			'-e',
			$cert_file,
			']',
			'&&',
			'cp',
			'-f',
			$key_file,
			$key_file . '.old',
			'&&',
			'cp',
			'-f',
			$cert_file,
			$cert_file . '.old',
			'||',
			'exit 0',
			';',
			'}'
		];
		$cmd_params['use_shell'] = true; // force using shell
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Get command to do restore SSL certificate and key.
	 *
	 * @param string $cmd_params command parameters
	 * @return array command to prepare certificate
	 */
	public static function getRestoreHTTPSCertKeyCommand(array $cmd_params): array
	{
		$key_file = self::getKeyFilePath();
		$cert_file = self::getCertFilePath();
		$ret = [
			'{',
			'[',
			'!',
			'-e',
			$key_file,
			'-o',
			'!',
			'-e',
			$cert_file,
			']',
			'&&',
			'[',
			'-e',
			$key_file . '.old',
			'-a',
			'-e',
			$cert_file . '.old',
			']',
			'&&',
			'cp',
			'-f',
			$key_file . '.old',
			$key_file,
			'&&',
			'cp',
			'-f',
			$cert_file . '.old',
			$cert_file,
			'||',
			'exit 0',
			';',
			'}'
		];
		$cmd_params['use_shell'] = true; // force using shell
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Get command to prepare certificate PEM file for HTTPS connection.
	 *
	 * @param array $cmd_params command parameters
	 * @return array command to prepare certificate PEM file
	 */
	public static function getPrepareHTTPSPemCommand(array $cmd_params = []): array
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
		$cmd_params['use_shell'] = true; // force using shell
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Get command to remove certificate PEM file for HTTPS connection.
	 *
	 * @param array $cmd_params command options
	 * @return array command to prepare certificate PEM file
	 */
	public static function getRemoveHTTPSPemCommand(array $cmd_params = []): array
	{
		$pem_file = self::getPEMFilePath();
		$ret = [
			'rm',
			'-f',
			$pem_file
		];
		static::setCommandParameters($ret, $cmd_params);
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
	 * Get command to get public key from SSL certificate.
	 * This command uses the OpenSSL binary.
	 *
	 * @param string $certificate SSL certificate PEM string
	 * @param array $cmd_params command options
	 * @return array command to get public key from certificate
	 */
	public static function getPubKeyFromCertCommand(string $certificate): array
	{
		$ret = [
			'echo',
			'-n',
			'"' . $certificate . '"',
			'|',
			'openssl',
			'x509',
			'-pubkey',
			'-noout'
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
		$multival_sections = ['x509v3_ext'];
		$section = $subsection = '';
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

			// section 3
			if (preg_match('/\s*(?P<section>X509v3\s+extensions):?$/i', $output[$i], $match) === 1) {
				$section = 'x509v3_ext';
				$ret[$section] = [];
				$sep = ':';
				continue;
			}

			// section 4
			if (preg_match('/\s*(?P<section>Signature Algorithm):\s*(?P<value>.+)$/i', $output[$i], $match) === 1) {
				$section = 'sign_alg';
				$ret[$section] = [
					$match['section'] => $match['value']
				];
				$subsection = ''; // no more subsections
				continue;
			}

			// properties (for section 3)
			$value = trim($output[$i]);
			if (stripos($subsection, 'x509v3') === 0 && stripos($value, 'x509v3') !== 0) {
				$ret[$section][$subsection][] = $value;
				continue;
				// properties (for section 1 and 2)
			} elseif (preg_match('/\s*(?P<prop>[\w\s]+)\s*' . $sep . '\s*(?P<value>.*)$/i', $output[$i], $match) === 1 && $section) {
				$prop = self::getCertProp($match['prop']);
				if (in_array($section, $multival_sections)) {
					$subsection = $prop;
					$ret[$section][$subsection] = [];
				} else {
					$ret[$section][$prop] = $match['value'];
				}
				continue;
			}
		}
		if (isset($ret['validity']['not_before']) && isset($ret['validity']['not_after'])) {
			// add info for how long certificate is valid (days)
			$ret['days_no'] = self::getDaysInTimeScope(
				$ret['validity']['not_before'],
				$ret['validity']['not_after']
			) - 1;
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
	 * Create CSR.
	 *
	 * @param string $address common name address
	 * @param string $email email address
	 * @param array $cmd_params command properties (use_sudo, user, password ...)
	 * @return array CSR details
	 */
	public function createCSR(string $address, string $email, array $cmd_params = []): array
	{
		$csr_params = [
			'common_name' => $address,
			'email' => $email
		];

		$cmd = self::getPrepareCSRCommand($csr_params, $cmd_params);
		$result = $this->execCommand($cmd, $cmd_params);
		$state = ($result['error'] == 0);
		if (!$state) {
			$output = implode(PHP_EOL, $result['output']);
			$lmsg = "Error while creating CSR. ExitCode: {$result['exitcode']}, Error: {$output}.";
			Logging::log(
				Logging::CATEGORY_EXTERNAL,
				$lmsg
			);
		} else {
			$csr_str = self::parseCSROutput($result['output']);
			$csr = Miscellaneous::encodeBase64URL($csr_str, true);
			$result['output'] = $csr;
			Logging::log(
				Logging::CATEGORY_EXTERNAL,
				'CSR => ' . $csr_str
			);
		}
		$result['error'] = $result['exitcode'];
		return $result;
	}

	/**
	 * Parse CSR output.
	 *
	 * @param array $output CSR output
	 * @result string CSR value
	 */
	private static function parseCSROutput(array $output): string
	{
		$out = '';
		for ($i = 0; $i < count($output); $i++) {
			if (preg_match('/^(spawn\s|password:|\[sudo\]\s)/i', $output[$i]) === 1) {
				continue;
			}
			if (preg_match('/^(EXITCODE=\d+|\s*)$/i', $output[$i]) === 1) {
				break;
			}
			$out .= trim($output[$i]);
		}
		return $out;
	}

	/**
	 * Get certificate detailed information.
	 *
	 * @return array output raw and parsed
	 */
	public static function getCertInfo()
	{
		$cmd = self::getCertDetailsCommand();
		$ret = ExecuteCommand::execCommand($cmd);
		$state = ($ret['error'] == 0);
		if ($state) {
			$ret['raw'] = $ret['output'];
			$ret['output'] = self::parseOpenSSLCert($ret['output']);
		}
		return $ret;
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
		if ($prop == 'X509v3 Key Usage') {
			$cprop = 'x509v3_key_usage';
		}
		if ($prop == 'X509v3 Extended Key Usage') {
			$cprop = 'x509v3_ext_key_usage';
		}
		if ($prop == 'X509v3 Basic Constraints') {
			$cprop = 'x509v3_basic_constr';
		}
		if ($prop == ' X509v3 Authority Key Identifier') {
			$cprop = 'x509v3_alt_key_id';
		}
		if ($prop == 'X509v3 Subject Alternative Name') {
			$cprop = 'x509v3_subj_alt_name';
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

	/**
	 * Create certificate file with given content.
	 *
	 * @param string $cert certificate
	 * @param array $cmd_params command parameters
	 * @return array command result details
	 */
	public function createCertFile(string $cert, array $cmd_params)
	{
		$cmd = self::getCreateHTTPSCertCommand($cert, $cmd_params);
		$result = $this->execCommand($cmd, $cmd_params);
		return $result;
	}

	/**
	 * Create PEM file with certificate and key.
	 * It is used by Lighttpd web server.
	 *
	 * @param array $cmd_params command parameters
	 * @return array command result details
	 */
	public function createCertKeyPemFile(array $cmd_params): array
	{
		$cmd = self::getPrepareHTTPSPemCommand($cmd_params);
		$result = $this->execCommand($cmd, $cmd_params);
		return $result;
	}

	/**
	 * Remove PEM file that contains certificate and key.
	 * It is used by Lighttpd web server.
	 *
	 * @param array $cmd_params command parameters
	 * @return array command result details
	 */
	public function removeCertKeyPemFile(array $cmd_params): array
	{
		$cmd = self::getRemoveHTTPSPemCommand($cmd_params);
		$result = $this->execCommand($cmd, $cmd_params);
		return $result;
	}

	/**
	 * Remove ertificate and key files.
	 *
	 * @param array $cmd_params command parameters
	 * @return array command result details
	 */
	public function removeCertAndKeyFiles(array $cmd_params): array
	{
		$cmd = self::getRemoveHTTPSCertKeyCommand($cmd_params);
		$result = $this->execCommand($cmd, $cmd_params);
		return $result;
	}

	/**
	 * Do backup certificate and key files.
	 *
	 * @param array $cmd_params command parameters
	 * @return array command result details
	 */
	public function backupCertAndKeyFiles(array $cmd_params): array
	{
		$cmd = self::getBackupHTTPSCertKeyCommand($cmd_params);
		$result = $this->execCommand($cmd, $cmd_params);
		return $result;
	}

	/**
	 * Do restore certificate and key files.
	 *
	 * @param array $cmd_params command parameters
	 * @return array command result details
	 */
	public function restoreCertAndKeyFiles(array $cmd_params): array
	{
		$cmd = self::getRestoreHTTPSCertKeyCommand($cmd_params);
		$result = $this->execCommand($cmd, $cmd_params);
		return $result;
	}

	/**
	 * Get public key string from SSL certificate.
	 *
	 * @param string $certificate SSL certificate PEM string
	 * @return array command result details
	 */
	public function getPublicKeyFromCert(string $certificate): array
	{
		$cmd = self::getPubKeyFromCertCommand($certificate);
		$result = ExecuteCommand::execCommand($cmd);
		if ($result['error'] !== 0) {
			$errmsg = var_export($result['output'], true);
			$emsg = "Unable to get public key from certificate. Error: {$result['error']}, Msg: {$errmsg}.";
			Logging::log(
				Logging::CATEGORY_EXTERNAL,
				$emsg
			);
		}
		return $result;
	}

	/**
	 * Get days left to expiry certificate.
	 *
	 * @return int days left to expiry (0 means expired certificate)
	 */
	public static function getCertValidityDaysLeft()
	{
		$result = self::getCertInfo();
		$days_no = 0;
		if ($result['error'] == 0) {
			$now = new DateTime();
			$start = $now->format(DateTime::RFC822);
			$cert = $result['output'];
			$end = $cert['validity']['not_after'];
			$days_no = self::getDaysInTimeScope($start, $end);
			if ($days_no < 0) {
				$days_no = 0;
			}
		}
		return $days_no;
	}

	/**
	 * Get days in a given time scope.
	 * This method is for calculating certificate validity time purposes.
	 *
	 * @param string $start start date (in certificate - not_before)
	 * @param string $end end date (in certificate - not_after)
	 * @return int days in time scope
	 */
	public static function getDaysInTimeScope(string $start, string $end): int
	{
		$nb = new DateTime($start);
		$na = new DateTime($end);
		$ddiff = $na->getTimestamp() - $nb->getTimestamp();
		$days_no = (int) ($ddiff / 60 / 60 / 24);
		$days_no += 1;
		return $days_no;
	}

	/**
	 * Add begin and end markers to certificate.
	 *
	 * @param string $cert certificate PEM string without markers
	 * @return string certificate PEM string with markers
	 */
	public static function addCertBeginEnd($cert): string
	{
		$cert = trim($cert);
		$ret = sprintf(
			"-----BEGIN CERTIFICATE-----
%s
-----END CERTIFICATE-----",
			$cert
		);
		return $ret;
	}
}
