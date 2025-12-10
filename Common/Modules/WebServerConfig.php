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
class WebServerConfig extends ShellCommandModule
{
	/**
	 * Get command to enable HTTPS connection in the Nginx web server configuration file.
	 *
	 * @param string $package_type OS package type: rpm, deb or apk
	 * @param array $cmd_params command options
	 * @return array command to enable HTTPS in Nginx config
	 */
	public static function getEnableHTTPSNginxCommand(string $package_type, array $cmd_params = []): array
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
			'-E',
			'-i',
			'-e',
			'"s/listen (.*[0-9]+);$/listen \\\1 ssl;/"',
			'-e',
			'"/index index.php;/a ssl_certificate_key ' . $key_file . ';"',
			'-e',
			'"/index index.php;/a ssl_certificate ' . $cert_file . ';"',
			$cfg_path,
			';',
			'}'
		];
		$cmd_params['use_shell'] = true; // force using shell
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Get command to disable HTTPS connection in the Nginx web server configuration file.
	 *
	 * @param string $package_type OS package type: rpm, deb or apk
	 * @param array $cmd_params command options
	 * @return array command to disable HTTPS in Nginx config
	 */
	public static function getDisableHTTPSNginxCommand(string $package_type, array $cmd_params = []): array
	{
		$cfg_path = self::getNginxConfigFilePath($package_type);
		$ret = [
			'sed',
			'-E',
			'-i',
			'-e',
			'"/ssl_certificate/d"',
			'-e',
			'"s/listen (.*[0-9]+) ssl;$/listen \\\1;/"',
			$cfg_path
		];
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Get command to set listening port in the Nginx web server configuration file.
	 * NOTE: The sed first string occurence substitution works only with GNU sed version.
	 *
	 * @param string $package_type OS package type: rpm, deb or apk
	 * @param int $port port to set
	 * @param array $cmd_params command optionsa
	 * @return array command to set listening port in Nginx config
	 */
	public static function getChangeNginxPortCommand(string $package_type, int $port, array $cmd_params = []): array
	{
		$cfg_path = self::getNginxConfigFilePath($package_type);
		$ret = [
			'sed',
			'-E',
			'-i',
			'-e',
			'"0,/listen .*:?[0-9]+[^0-9]*;/{s/listen (.+:)?([0-9]+)([^0-9]*);$/listen \\\1XXPORTXX\\\3;/}"',
			'-e',
			'"s/XXPORTXX/' . $port . '/"',
			$cfg_path
		];
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Get command to enable HTTPS connection in the Lighttpd web server configuration file.
	 *
	 * @param array $cmd_params command options
	 * @return array command to enable HTTPS in Nginx config
	 */
	public static function getEnableHTTPSLighttpdCommand(array $cmd_params = []): array
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
		$cmd_params['use_shell'] = true; // force using shell
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Get command to disable HTTPS connection in the Lighttpd web server configuration file.
	 *
	 * @param array $cmd_params command options
	 * @return array command to disable HTTPS in Nginx config
	 */
	public static function getDisableHTTPSLighttpdCommand(array $cmd_params = []): array
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
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Get command to set listening port in the Lighttpd web server configuration file.
	 *
	 * @param int $port port to set
	 * @param array $cmd_params command optionsa
	 * @return array command to set listening port in Lighttpd config
	 */
	public static function getChangeLighttpdPortCommand(int $port, array $cmd_params = []): array
	{
		$cfg_path = self::getLighttpdConfigFilePath();
		$ret = [
			'sed',
			'-E',
			'-i',
			'-e',
			'"s/server.port\\\s*=\\\s*[0-9]+$/server.port = ' . $port . '/"',
			$cfg_path
		];
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Get command to enable HTTPS connection in the Apache web server configuration file.
	 *
	 * @param string $package_type OS package type: rpm, deb or apk
	 * @param array $cmd_params command options
	 * @return array command to enable HTTPS in Apache config
	 */
	public static function getEnableHTTPSApacheCommand(string $package_type, array $cmd_params = []): array
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
		$cmd_params['use_shell'] = true; // force using shell
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Get command to disable HTTPS connection in the Apache web server configuration file.
	 *
	 * @param string $package_type OS package type: rpm, deb or apk
	 * @param array $cmd_params command options
	 * @return array command to disable HTTPS in Apache config
	 */
	public static function getDisableHTTPSApacheCommand(string $package_type, array $cmd_params = []): array
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
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Get command to set listening port in the Apache web server configuration file.
	 * NOTE: The sed first string occurence substitution works only with GNU sed version.
	 *
	 * @param string $package_type OS package type: rpm, deb or apk
	 * @param int $port port to set
	 * @param array $cmd_params command optionsa
	 * @return array command to set listening port in Apache config
	 */
	public static function getChangeApachePortCommand(string $package_type, int $port, array $cmd_params = []): array
	{
		$cfg_path = self::getApacheConfigFilePath($package_type);
		$ret = [
			'sed',
			'-E',
			'-i',
			'-e',
			'"0,/Listen [A-Za-z0-9.]*:?[0-9]+$/{s/Listen ([A-Za-z0-9.]*:?)?([0-9]+)$/Listen \\\1XXPORTXX/}"',
			'-e',
			'"s/XXPORTXX/' . $port . '/"',
			'-e',
			'"0,/<VirtualHost [A-Za-z0-9._*]*:[0-9]+>/{s/<VirtualHost ([A-Za-z0-9._*]*):([0-9]+)>/<VirtualHost \\\1:' . $port . '>/}"',
			$cfg_path
		];
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Get Nginx web server configuration file path.
	 * NOTE: Paths are hardcoded.
	 *
	 * @param string $package_type OS package type: rpm, deb or apk
	 * @return string Nginx web server config path
	 */
	private static function getNginxConfigFilePath(string $package_type): string
	{
		$cfg_path = '';
		if ($package_type == BinaryPackage::TYPE_RPM) {
			$cfg_path = implode(
				DIRECTORY_SEPARATOR,
				['', 'etc', 'nginx', 'conf.d', 'bacularis.conf']
			);
		} elseif ($package_type == BinaryPackage::TYPE_DEB) {
			$cfg_path = implode(
				DIRECTORY_SEPARATOR,
				['', 'etc', 'nginx', 'sites-available', 'bacularis.conf']
			);
		} elseif ($package_type == BinaryPackage::TYPE_APK) {
			$cfg_path = implode(
				DIRECTORY_SEPARATOR,
				['', 'etc', 'nginx', 'http.d', 'bacularis.conf']
			);
		}
		return $cfg_path;
	}

	/**
	 * Get Apache web server configuration file path.
	 * NOTE: Paths are hardcoded.
	 *
	 * @param string $package_type OS package type: rpm, deb or apk
	 * @return string Apache web server config path
	 */
	private static function getApacheConfigFilePath(string $package_type): string
	{
		$cfg_path = '';
		if ($package_type == BinaryPackage::TYPE_RPM) {
			$cfg_path = implode(
				DIRECTORY_SEPARATOR,
				['', 'etc', 'httpd', 'conf.d', 'bacularis.conf']
			);
		} elseif ($package_type == BinaryPackage::TYPE_DEB) {
			$cfg_path = implode(
				DIRECTORY_SEPARATOR,
				['', 'etc', 'apache2', 'sites-available', 'bacularis.conf']
			);
		} elseif ($package_type == BinaryPackage::TYPE_APK) {
			$cfg_path = implode(
				DIRECTORY_SEPARATOR,
				['', 'etc', 'apache2', 'conf.d', 'bacularis.conf']
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
	 * @param string $package_type OS package type: rpm, deb or apk
	 * @param array $cmd_params command options
	 * @return array Apache web server reload command
	 */
	public static function getApacheReloadCommand(string $package_type, array $cmd_params = []): array
	{
		$ret = [];
		if ($package_type == BinaryPackage::TYPE_RPM) {
			$ret = [
				'systemctl',
				'reload',
				'httpd'
			];
		} elseif ($package_type == BinaryPackage::TYPE_DEB) {
			$ret = [
				'systemctl',
				'reload',
				'apache2'
			];
		} elseif ($package_type == BinaryPackage::TYPE_APK) {
			$ret = [
				'rc-service',
				'apache2',
				'reload'
			];
		}
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Reload Nginx web server configuration.
	 *
	 * @param string $package_type OS package type: rpm, deb or apk
	 * @param array $cmd_params command options
	 * @return array Nginx web server reload command
	 */
	public static function getNginxReloadCommand(string $package_type, array $cmd_params = []): array
	{
		$ret = [];
		if ($package_type == BinaryPackage::TYPE_RPM || $package_type == BinaryPackage::TYPE_DEB) {
			$ret = [
				'systemctl',
				'reload',
				'nginx'
			];
		} elseif ($package_type == BinaryPackage::TYPE_APK) {
			$ret = [
				'rc-service',
				'nginx',
				'reload'
			];
		}
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Reload Lighttpd web server configuration.
	 *
	 * @param string $package_type OS package type: rpm, deb or apk
	 * @param array $cmd_params command options
	 * @return array Lighttpd web server reload command
	 */
	public static function getLighttpdRestartCommand(string $package_type, array $cmd_params = []): array
	{
		$ret = [];
		if ($package_type == BinaryPackage::TYPE_RPM || $package_type == BinaryPackage::TYPE_DEB) {
			$ret = [
				'systemctl',
				'restart',
				'bacularis-lighttpd'
			];
		} elseif ($package_type == BinaryPackage::TYPE_APK) {
			/**
			 * NOTE: This bacularis-lighttpd service does not exists on Alpine.
			 * This service has to be prepared by user self until Bacularis starts providing it.
			 */
			$ret = [
				'rc-service',
				'bacularis-lighttpd',
				'restart'
			];
		}
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Enable HTTPS protocol in web server configuration file.
	 *
	 * @param string $repository_type system repository type (rpm, deb)
	 * @param string $web_server web server name (apache, nginx, lighttpd)
	 * @param array $cmd_params command parameters
	 * @return array command result details
	 */
	public function enableHTTPS(string $repository_type, string $web_server, array $cmd_params): array
	{
		$cmd = [];
		if ($web_server == Miscellaneous::WEB_SERVERS['nginx']['id']) {
			$cmd = self::getEnableHTTPSNginxCommand(
				$repository_type,
				$cmd_params
			);
		} elseif ($web_server == Miscellaneous::WEB_SERVERS['lighttpd']['id']) {
			$cmd = self::getEnableHTTPSLighttpdCommand(
				$cmd_params
			);
		} elseif ($web_server == Miscellaneous::WEB_SERVERS['apache']['id']) {
			$cmd = self::getEnableHTTPSApacheCommand(
				$repository_type,
				$cmd_params
			);
		}
		$result = $this->execCommand($cmd, $cmd_params);
		return $result;
	}

	/**
	 * Disable HTTPS protocol in web server configuration file.
	 *
	 * @param string $repository_type system repository type (rpm, deb)
	 * @param string $web_server web server name (apache, nginx, lighttpd)
	 * @param array $cmd_params command parameters
	 * @return array command result details
	 */
	public function disableHTTPS(string $repository_type, string $web_server, array $cmd_params): array
	{
		$cmd = [];
		if ($web_server == Miscellaneous::WEB_SERVERS['nginx']['id']) {
			$cmd = self::getDisableHTTPSNginxCommand(
				$repository_type,
				$cmd_params
			);
		} elseif ($web_server == Miscellaneous::WEB_SERVERS['lighttpd']['id']) {
			$cmd = self::getDisableHTTPSLighttpdCommand(
				$cmd_params
			);
		} elseif ($web_server == Miscellaneous::WEB_SERVERS['apache']['id']) {
			$cmd = self::getDisableHTTPSApacheCommand(
				$repository_type,
				$cmd_params
			);
		}
		$result = $this->execCommand($cmd, $cmd_params);
		return $result;
	}

	/**
	 * Reload web server configuration.
	 * In case Lighttpd there is called restart action, not reload.
	 *
	 * @param string $repository_type system repository type (rpm, deb)
	 * @param string $web_server web server name (apache, nginx, lighttpd)
	 * @param array $cmd_params command parameters
	 * @return array command result details
	 */
	public function reloadConfig(string $repository_type, string $web_server, array $cmd_params): array
	{
		$cmd = [];
		if ($web_server == Miscellaneous::WEB_SERVERS['nginx']['id']) {
			$cmd = self::getNginxReloadCommand(
				$repository_type,
				$cmd_params
			);
		} elseif ($web_server == Miscellaneous::WEB_SERVERS['lighttpd']['id']) {
			$cmd = self::getLighttpdRestartCommand(
				$repository_type,
				$cmd_params
			);
		} elseif ($web_server == Miscellaneous::WEB_SERVERS['apache']['id']) {
			$cmd = self::getApacheReloadCommand(
				$repository_type,
				$cmd_params
			);
		}
		$result = $this->execCommand($cmd, $cmd_params);
		return $result;
	}


	/**
	 * Set port in web server configuration.
	 *
	 * @param string $repository_type system repository type (rpm, deb)
	 * @param string $web_server web server name (apache, nginx, lighttpd)
	 * @param int $port port to set
	 * @param array $cmd_params command parameters
	 * @return array command result details
	 */
	public function setPort(string $repository_type, string $web_server, int $port, array $cmd_params): array
	{
		$cmd = [];
		if ($web_server == Miscellaneous::WEB_SERVERS['nginx']['id']) {
			$cmd = self::getChangeNginxPortCommand(
				$repository_type,
				$port,
				$cmd_params
			);
		} elseif ($web_server == Miscellaneous::WEB_SERVERS['lighttpd']['id']) {
			$cmd = self::getChangeLighttpdPortCommand(
				$port,
				$cmd_params
			);
		} elseif ($web_server == Miscellaneous::WEB_SERVERS['apache']['id']) {
			$cmd = self::getChangeApachePortCommand(
				$repository_type,
				$port,
				$cmd_params
			);
		}
		$result = $this->execCommand($cmd, $cmd_params);
		return $result;
	}
}
