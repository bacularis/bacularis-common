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
 * Web server related methods.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class WebServerConfig extends CommonModule
{
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
	 * Get command to enable HTTPS connection in the Nginx web server configuration file.
	 *
	 * @param string $package_type OS package type: rpm or deb
	 * @param array $params command options
	 * @return array command to enable HTTPS in Nginx config
	 */
	public static function getEnableHTTPSNginxCommand(string $package_type, array $params = []): array
	{
		$cfg_path = self::getNginxConfigFilePath($package_type);
		$key_file = SSLCertificate::getKeyFilePath();
		$cert_file = SSLCertificate::getCertFilePath();
		$disable_https_cmd = self::getDisableHTTPSNginxCommand($package_type);
		$ret = [
			'{',
			...$disable_https_cmd,
			';',
			'sed',
			'-i',
			'-e',
			'"s/listen 9097;$/listen 9097 ssl;/"',
			'-e',
			'"/index index.php;/a ssl_certificate_key ' . $key_file . ';"',
			'-e',
			'"/index index.php;/a ssl_certificate ' . $cert_file . ';"',
			$cfg_path,
			';',
			'}'
		];
		$params['use_shell'] = true; // force using shell
		self::setCommandParameters($ret, $params);
		return $ret;
	}

	/**
	 * Get command to disable HTTPS connection in the Nginx web server configuration file.
	 *
	 * @param string $package_type OS package type: rpm or deb
	 * @param array $params command options
	 * @return array command to disable HTTPS in Nginx config
	 */
	public static function getDisableHTTPSNginxCommand(string $package_type, array $params = []): array
	{
		$cfg_path = self::getNginxConfigFilePath($package_type);
		$ret = [
			'sed',
			'-i',
			'-e',
			'"/ssl_certificate/d"',
			'-e',
			'"s/listen 9097 ssl;$/listen 9097;/"',
			$cfg_path
		];
		self::setCommandParameters($ret, $params);
		return $ret;
	}

	/**
	 * Get command to enable HTTPS connection in the Lighttpd web server configuration file.
	 *
	 * @param array $params command options
	 * @return array command to enable HTTPS in Nginx config
	 */
	public static function getEnableHTTPSLighttpdCommand(array $params = []): array
	{
		$cfg_path = self::getLighttpdConfigFilePath();
		$pem_file = SSLCertificate::getPEMFilePath();
		$disable_https_cmd = self::getDisableHTTPSLighttpdCommand();
		$ret = [
			'{',
			...$disable_https_cmd,
			';',
			'echo',
			'\'\\\'\'
server.modules += ( "mod_openssl" )
ssl.engine = "enable"
ssl.pemfile = "' . $pem_file . '"
\'\\\'\'',
			'>>',
			$cfg_path,
			';',
			'}'
		];
		$params['use_shell'] = true; // force using shell
		self::setCommandParameters($ret, $params);
		return $ret;
	}

	/**
	 * Get command to disable HTTPS connection in the Lighttpd web server configuration file.
	 *
	 * @param array $params command options
	 * @return array command to disable HTTPS in Nginx config
	 */
	public static function getDisableHTTPSLighttpdCommand(array $params = []): array
	{
		$cfg_path = self::getLighttpdConfigFilePath();
		$ret = [
			'sed',
			'-i',
			'-e',
			'"/ssl./d"',
			'-e',
			'"/mod_openssl/d"',
			$cfg_path,
		];
		self::setCommandParameters($ret, $params);
		return $ret;
	}

	/**
	 * Get command to enable HTTPS connection in the Apache web server configuration file.
	 *
	 * @param string $package_type OS package type: rpm or deb
	 * @param array $params command options
	 * @return array command to enable HTTPS in Apache config
	 */
	public static function getEnableHTTPSApacheCommand(string $package_type, array $params = []): array
	{
		$cfg_path = self::getApacheConfigFilePath($package_type);
		$key_file = SSLCertificate::getKeyFilePath();
		$cert_file = SSLCertificate::getCertFilePath();
		$disable_https_cmd = self::getDisableHTTPSApacheCommand($package_type);
		$ret = [
			'{',
			...$disable_https_cmd,
			';',
			'sed',
			'-i',
			'-e',
			'"/SSLEngine on/d"',
			'-e',
			'"/SSLCertificate/d"',
			'-e',
			'"/ServerName/a SSLEngine on"',
			'-e',
			'"/ServerName/a SSLCertificateKeyFile ' . $key_file . '"',
			'-e',
			'"/ServerName/a SSLCertificateFile ' . $cert_file . '"',
			$cfg_path,
			';',
			'}'
		];
		$params['use_shell'] = true; // force using shell
		self::setCommandParameters($ret, $params);
		return $ret;
	}

	/**
	 * Get command to disable HTTPS connection in the Apache web server configuration file.
	 *
	 * @param string $package_type OS package type: rpm or deb
	 * @param array $params command options
	 * @return array command to disable HTTPS in Apache config
	 */
	public static function getDisableHTTPSApacheCommand(string $package_type, array $params = []): array
	{
		$cfg_path = self::getApacheConfigFilePath($package_type);
		$ret = [
			'sed',
			'-i',
			'-e',
			'"/SSLEngine on/d"',
			'-e',
			'"/SSLCertificate/d"',
			$cfg_path
		];
		self::setCommandParameters($ret, $params);
		return $ret;
	}

	/**
	 * Get Nginx web server configuration file path.
	 * NOTE: Paths are hardcoded.
	 *
	 * @param string $package_type OS package type: rpm or deb
	 * @return string Nginx web server config path
	 */
	private static function getNginxConfigFilePath(string $package_type): string
	{
		$cfg_path = '';
		if ($package_type == 'rpm') {
			$cfg_path = implode(
				DIRECTORY_SEPARATOR,
				['', 'etc', 'nginx', 'conf.d', 'bacularis.conf']
			);
		} elseif ($package_type == 'deb') {
			$cfg_path = implode(
				DIRECTORY_SEPARATOR,
				['', 'etc', 'nginx', 'sites-available', 'bacularis.conf']
			);
		}
		return $cfg_path;
	}

	/**
	 * Get Apache web server configuration file path.
	 * NOTE: Paths are hardcoded.
	 *
	 * @param string $package_type OS package type: rpm or deb
	 * @return string Apache web server config path
	 */
	private static function getApacheConfigFilePath(string $package_type): string
	{
		$cfg_path = '';
		if ($package_type == 'rpm') {
			$cfg_path = implode(
				DIRECTORY_SEPARATOR,
				['', 'etc', 'httpd', 'conf.d', 'bacularis.conf']
			);
		} elseif ($package_type == 'deb') {
			$cfg_path = implode(
				DIRECTORY_SEPARATOR,
				['', 'etc', 'apache2', 'sites-available', 'bacularis.conf']
			);
		}
		return $cfg_path;
	}

	/**
	 * Get Lighttpd web server configuration file path.
	 * NOTE: Paths are hardcoded.
	 *
	 * @return string Lighttpd web server config path
	 */
	private static function getLighttpdConfigFilePath(): string
	{
		$cfg_path = implode(
			DIRECTORY_SEPARATOR,
			['', 'etc', 'bacularis', 'bacularis-lighttpd.conf']
		);
		return $cfg_path;
	}

	/**
	 * Reload Apache web server configuration.
	 *
	 * @param string $package_type OS package type: rpm or deb
	 * @param array $params command options
	 * @return array Apache web server reload command
	 */
	public static function getApacheReloadCommand(string $package_type, array $params = []): array
	{
		$ret = [];
		if ($package_type == 'rpm') {
			$ret = [
				'systemctl',
				'reload',
				'httpd'
			];
		} elseif ($package_type == 'deb') {
			$ret = [
				'systemctl',
				'reload',
				'apache2'
			];
		}
		self::setCommandParameters($ret, $params);
		return $ret;
	}

	/**
	 * Reload Nginx web server configuration.
	 *
	 * @param array $params command options
	 * @return array Nginx web server reload command
	 */
	public static function getNginxReloadCommand(array $params = []): array
	{
		$ret = [
			'systemctl',
			'reload',
			'nginx'
		];
		self::setCommandParameters($ret, $params);
		return $ret;
	}

	/**
	 * Reload Lighttpd web server configuration.
	 *
	 * @param array $params command options
	 * @return array Lighttpd web server reload command
	 */
	public static function getLighttpdRestartCommand(array $params = []): array
	{
		$ret = [
			'systemctl',
			'restart',
			'bacularis-lighttpd'
		];
		self::setCommandParameters($ret, $params);
		return $ret;
	}
}
