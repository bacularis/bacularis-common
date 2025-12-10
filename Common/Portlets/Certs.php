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

namespace Bacularis\Common\Portlets;

use DateTime;
use Bacularis\Common\Modules\AuditLog;
use Bacularis\Common\Modules\BinaryPackage;
use Bacularis\Common\Modules\ExecuteCommand;
use Bacularis\Common\Modules\LetsEncryptCert;
use Bacularis\Common\Modules\Logging;
use Bacularis\Common\Modules\Miscellaneous;
use Bacularis\Common\Modules\SelfSignedCert;
use Bacularis\Common\Modules\SSLCertificate;
use Bacularis\Common\Portlets\AdminAccess;
use Bacularis\Common\Portlets\PortletTemplate;
use Prado\TPropertyValue;

/**
 * SSL certificate management.
 * It enables to install, renew and uninstall SSL certificate.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Control
 */
class Certs extends PortletTemplate
{
	/**
	 * Additional operating systems which are supported to certificate settings.
	 */
	public const EXTRA_OS = [
		'Alpine Linux' => [
			'name' => 'Alpine Linux',
			'repository_type' => BinaryPackage::TYPE_APK
		]
	];

	private const UPDATE_HOST_CONFIG = 'UpdateHostConfig';

	/**
	 * Stores installed certificate properties.
	 *
	 * @var array
	 */
	public $cert_props = [];

	/**
	 * Stores raw output from OpenSSL binary with the installed certificate details.
	 *
	 * @var array
	 */
	public $cert_raw_output = [];

	/**
	 * Stores autodetected web server name.
	 *
	 * @var string
	 */
	public $web_server = '';

	public function onLoad($param)
	{
		parent::onLoad($param);
		if ($this->getPage()->IsCallBack) {
			return;
		}
		$this->setCertificateSetting();
		$this->loadWebServers();
	}

	/**
	 * Prepares page with SSL certificate and form fields.
	 */
	private function setCertificateSetting(): void
	{
		$props = [];
		if (SSLCertificate::certExists()) {
			// Get certificate info
			$result = SSLCertificate::getCertInfo();
			$state = ($result['error'] == 0);
			if ($state) {
				$this->cert_raw_output = $result['raw'];
				$props = $result['output'];
				$this->CertsInstallCert->Display = 'None';
				$this->CertsCertInstalled->Display = 'Dynamic';
			} else {
				$emsg = 'Unable to load the certificate info.';
				Logging::log(
					Logging::CATEGORY_APPLICATION,
					$emsg
				);
			}
		} else {
			$this->CertsInstallCert->Display = 'Dynamic';
		}
		$this->setCertProps($props);
	}

	/**
	 * Set fields with the certificate properties.
	 *
	 * @param array $props existing certificate properties
	 */
	private function setCertProps(array $props): void
	{
		$days_no = 365; // default 1 year
		if (isset($props['validity']['not_before']) && isset($props['validity']['not_after'])) {
			$days_no = SSLCertificate::getDaysInTimeScope(
				$props['validity']['not_before'],
				$props['validity']['not_after']
			);
		}
		$this->CertsSelfSignedValidityDays->Text = $days_no;
		$this->CertsSelfSignedCommonName->Text = $props['subject']['common_name'] ?? 'localhost';
		$this->CertsSelfSignedEmail->Text = $props['subject']['email'] ?? '';
		$this->CertsSelfSignedCountryCode->Text = $props['subject']['country_code'] ?? '';
		$this->CertsSelfSignedState->Text = $props['subject']['state'] ?? '';
		$this->CertsSelfSignedLocality->Text = $props['subject']['locality'] ?? '';
		$this->CertsSelfSignedOrganization->Text = $props['subject']['organization'] ?? '';
		$this->CertsSelfSignedOrganizationUnit->Text = $props['subject']['organization_unit'] ?? '';
		$this->CertsAction->SelectedValue = '';
		if (isset($props['issuer']['common_name']) && isset($props['subject']['common_name']) && $props['issuer']['common_name'] == $props['subject']['common_name']) {
			$this->CertsAction->SelectedValue = SelfSignedCert::CERT_TYPE;
		} elseif (isset($props['issuer']['organization']) && preg_match('/(Let\'s Encrypt)/i', $props['issuer']['organization']) === 1) {
			$this->CertsAction->SelectedValue = LetsEncryptCert::CERT_TYPE;
		} elseif (isset($props['issuer']['common_name']) && preg_match('/(Pebble)/i', $props['issuer']['common_name']) === 1) {
			$this->CertsAction->SelectedValue = LetsEncryptCert::CERT_TYPE;
		}
		$this->cert_props = $props;
	}

	/**
	 * Autodetect web server and mark it selected.
	 */
	private function loadWebServers(): void
	{
		$misc = $this->getModule('misc');
		$this->web_server = $misc->detectWebServer();
		$this->CertsWebServer->SelectedValue = $this->web_server;
	}

	/**
	 * Load OS profile list.
	 *
	 * @param TCallback $sender sender object
	 * @param TCallbackEventParameter $param event parameters
	 */
	public function loadOSProfiles($sender, $param): void
	{
		$config = $this->getModule('osprofile_config')->getPreDefinedOSProfiles();
		$names = array_keys($config);

		// Add extran OSes
		$extra_names = array_keys(self::EXTRA_OS);
		$names = array_merge($names, $extra_names);

		sort($names, SORT_NATURAL | SORT_FLAG_CASE);
		array_unshift($names, '');
		$osps = array_combine($names, $names);

		// OS profile combobox
		$this->CertsOSProfile->DataSource = $osps;
		$this->CertsOSProfile->dataBind();
	}

	/**
	 * Create certificate.
	 *
	 * @param TCallback $sender sender object
	 * @param TCallbackEventParameter $param event parameters
	 * @param bool true on success, false otherwise
	 */
	public function createCert($sender, $param): bool
	{
		$state = false;

		// Hide previous error message (if any)
		$eid = 'certificate_action_error';
		$cb = $this->getPage()->getCallbackClient();
		$cb->hide($eid);

		$common_name = '';
		$action = $this->CertsAction->getSelectedValue();
		switch ($action) {
			case LetsEncryptCert::CERT_TYPE: {
				$common_name = $this->CertsLetsEncryptCommonName->Text;
				$state = $this->createLetsEncryptCert();
				break;
			}
			case SelfSignedCert::CERT_TYPE: {
				$common_name = $this->CertsSelfSignedCommonName->Text;
				$state = $this->createSelfSignedCert();
				break;
			}
		}
		if (!$state) {
			return $state;
		}

		// Update web server config
		$state = $this->enableHTTPSInWebServer();
		if (!$state) {
			return $state;
		}

		// Check if there is a need to create PEM file
		$web_server = $this->CertsWebServer->getSelectedValue();
		if ($web_server == Miscellaneous::WEB_SERVERS['lighttpd']['id']) {
			$state = $this->createCertKeyPemFile();
		}
		if (!$state) {
			return $state;
		}

		// Post install action
		$this->postSaveActions($common_name);

		// Reload web server
		$state = $this->reloadWebServerConfig(
			$this->CertsAdminAccessCreateCert
		);
		if (!$state) {
			return $state;
		}

		return $state;
	}

	/**
	 * Renew certificate.
	 *
	 * @param TCallback $sender sender object
	 * @param TCallbackEventParameter $param event parameters
	 * @param bool true on success, false otherwise
	 */
	public function renewCert($sender, $param): bool
	{
		$state = false;

		// Hide previous error message (if any)
		$eid = 'certificate_action_error';
		$cb = $this->getPage()->getCallbackClient();
		$cb->hide($eid);

		$action = $this->CertsAction->getSelectedValue();
		switch ($action) {
			case LetsEncryptCert::CERT_TYPE: {
				$state = $this->renewLetsEncryptCert();
				break;
			}
			case SelfSignedCert::CERT_TYPE: {
				$state = $this->renewSelfSignedCert();
				break;
			}
		}
		if (!$state) {
			return $state;
		}

		// Check if there is a need to create PEM file
		$web_server = $this->CertsWebServer->getSelectedValue();
		if ($web_server == Miscellaneous::WEB_SERVERS['lighttpd']['id']) {
			$state = $this->createCertKeyPemFile();
		}
		if (!$state) {
			return $state;
		}

		// Reload web server
		$state = $this->reloadWebServerConfig(
			$this->CertsAdminAccessRenewCert
		);
		if (!$state) {
			return $state;
		}
		return $state;
	}

	/**
	 * Create Let's Encrypt account.
	 *
	 * @return array account information
	 */
	private function createLetsEncryptAccount(): array
	{
		$email = $this->CertsLetsEncryptEmail->Text;

		$user = $this->CertsAdminAccessCreateCert->getAdminUser();
		$password = $this->CertsAdminAccessCreateCert->getAdminPassword();
		$use_sudo = $this->CertsAdminAccessCreateCert->getAdminUseSudo();

		$cmd_params = [
			'user' => $user,
			'password' => $password,
			'use_sudo' => $use_sudo
		];
		$ssl_le_cert = $this->getModule('ssl_le_cert');
		$result = $ssl_le_cert->createAccount($email, $cmd_params);
		$state = ($result['error'] == 0);
		$emsg = '';
		if (!$state) {
			// Error
			$emsg = 'Error while creating account on ACME server.';
			$this->reportError($result, $emsg);
		}
		return $result;
	}

	/**
	 * Get existing Lets Encrypt account.
	 *
	 * @param AdminAccess $adm_access admin access control
	 * @return array account information
	 */
	private function getLetsEncryptAccount(AdminAccess $adm_access): array
	{
		$user = $adm_access->getAdminUser();
		$password = $adm_access->getAdminPassword();
		$use_sudo = $adm_access->getAdminUseSudo();

		$cmd_params = [
			'user' => $user,
			'password' => $password,
			'use_sudo' => $use_sudo
		];
		$ssl_le_cert = $this->getModule('ssl_le_cert');
		$result = $ssl_le_cert->getExistingAccount($cmd_params);
		$state = ($result['error'] == 0);
		if (!$state) {
			// Error
			$emsg = 'Error while getting existing account from ACME server.';
			$this->reportError($result, $emsg);
		}
		return $result;
	}

	/**
	 * Create Let's Encrypt certificate.
	 *
	 * @return bool creation status
	 */
	private function createLetsEncryptCert(): bool
	{
		// create let's encrypt account first
		$result = $this->createLetsEncryptAccount();
		$state = $result['error'] == 0;
		if (!$state) {
			return $state;
		}
		$params = $result;

		$email = $this->CertsLetsEncryptEmail->Text;
		$common_name = $this->CertsLetsEncryptCommonName->Text;

		$user = $this->CertsAdminAccessCreateCert->getAdminUser();
		$password = $this->CertsAdminAccessCreateCert->getAdminPassword();
		$use_sudo = $this->CertsAdminAccessCreateCert->getAdminUseSudo();

		$cmd_params = [
			'user' => $user,
			'password' => $password,
			'use_sudo' => $use_sudo
		];
		$ssl_le_cert = $this->getModule('ssl_le_cert');
		$result = $ssl_le_cert->createOrder(
			$common_name,
			$email,
			$params,
			$cmd_params
		);
		$state = ($result['error'] == 0);
		if (!$state) {
			// Error
			$emsg = 'Error while preparing certificate.';
			$this->reportError($result, $emsg);
		}
		return $state;
	}

	/**
	 * Renew Let's Encrypt certificate.
	 *
	 * @return bool creation status
	 */
	private function renewLetsEncryptCert(): bool
	{
		$user = $this->CertsAdminAccessRenewCert->getAdminUser();
		$password = $this->CertsAdminAccessRenewCert->getAdminPassword();
		$use_sudo = $this->CertsAdminAccessRenewCert->getAdminUseSudo();

		$cmd_params = [
			'user' => $user,
			'password' => $password,
			'use_sudo' => $use_sudo
		];
		$ssl_le_cert = $this->getModule('ssl_le_cert');
		$result = $ssl_le_cert->renewCert($cmd_params);
		$state = ($result['error'] == 0);
		if (!$state) {
			// Error
			$emsg = 'Error while renewing certificate.';
			$this->reportError($result, $emsg);
		}
		return $state;
	}

	/**
	 * Actions after successful installing certificate.
	 *
	 * @param string $common_name common name address
	 */
	private function postSaveActions(string $common_name): void
	{
		$audit = $this->getModule('audit');
		if (is_object($audit)) {
			$audit->audit(
				AuditLog::TYPE_INFO,
				AuditLog::CATEGORY_APPLICATION,
				"New certificate has been created and configured for host: {$common_name}"
			);
		}

		$eid = 'certificate_action_error';
		$cb = $this->getPage()->getCallbackClient();
		$cb->hide($eid);

		// Update API host protocol
		$update_hcfg = $this->getUpdateHostProps();
		if ($update_hcfg) {
			$host_config = $this->getModule('host_config');
			$api_host = $this->User->getDefaultAPIHost();
			$hcfg = $host_config->getHostConfig($api_host);
			if (key_exists('address', $hcfg) && $hcfg['address'] == 'localhost') {
				$props = [
					'protocol' => 'https'
				];
				if ($_SERVER['SERVER_PORT'] == 80) {
					$props['port'] = 443;
				}
				$host_config->updateHostConfig(
					$api_host,
					$props
				);
			}
		}

		$iid = 'install_cert_info';
		$cb->show($iid);
	}

	/**
	 * Create self-signed certificate.
	 *
	 * @param AdminAccess $adm_access admin access control
	 * @return bool state true on success, false otherwise
	 */
	private function createSelfSignedCert(): bool
	{
		$days_no = $this->CertsSelfSignedValidityDays->Text;
		$common_name = $this->CertsSelfSignedCommonName->Text;
		$email = $this->CertsSelfSignedEmail->Text;
		$country_code = $this->CertsSelfSignedCountryCode->Text;
		$state = $this->CertsSelfSignedState->Text;
		$locality = $this->CertsSelfSignedLocality->Text;
		$organization = $this->CertsSelfSignedOrganization->Text;
		$organization_unit = $this->CertsSelfSignedOrganizationUnit->Text;

		$user = $this->CertsAdminAccessCreateCert->getAdminUser();
		$password = $this->CertsAdminAccessCreateCert->getAdminPassword();
		$use_sudo = $this->CertsAdminAccessCreateCert->getAdminUseSudo();

		$cmd_params = [
			'user' => $user,
			'password' => $password,
			'use_sudo' => $use_sudo
		];

		$cert_params = [
			'days_no' => $days_no,
			'common_name' => $common_name,
			'email' => $email,
			'country_code' => $country_code,
			'state' => $state,
			'locality' => $locality,
			'organization' => $organization,
			'organization_unit' => $organization_unit,
			'use_sudo' => $use_sudo
		];

		// Create self-signed certificate
		$ssl_ss_cert = $this->getModule('ssl_ss_cert');
		$result = $ssl_ss_cert->createCert($cert_params, $cmd_params);
		$state = ($result['error'] == 0);
		if (!$state) {
			// Error while creating key and certificate
			$emsg = "Error while creating self-signed certificate for host: {$common_name}.";
			$this->reportError($result, $emsg);
		}
		return $state;
	}

	/**
	 * Renew self-signed certificate.
	 *
	 * @return bool state true on success, false otherwise
	 */
	private function renewSelfSignedCert(): bool
	{
		$user = $this->CertsAdminAccessRenewCert->getAdminUser();
		$password = $this->CertsAdminAccessRenewCert->getAdminPassword();
		$use_sudo = $this->CertsAdminAccessRenewCert->getAdminUseSudo();

		$cmd_params = [
			'user' => $user,
			'password' => $password,
			'use_sudo' => $use_sudo
		];
		$ssl_ss_cert = $this->getModule('ssl_ss_cert');
		$result = $ssl_ss_cert->renewCert($cmd_params);
		$state = ($result['error'] == 0);
		if (!$state) {
			// Error while creating key and certificate
			$emsg = "Error while renewing self-signed certificate.";
			$this->reportError($result, $emsg);
		}
		return $state;
	}

	/**
	 * Create PEM file with certificate and key.
	 * It is used by Lighttpd web server.
	 *
	 * @return bool true on success, false otherwise
	 */
	private function createCertKeyPemFile(): bool
	{
		$user = $this->CertsAdminAccessCreateCert->getAdminUser();
		$password = $this->CertsAdminAccessCreateCert->getAdminPassword();
		$use_sudo = $this->CertsAdminAccessCreateCert->getAdminUseSudo();

		$cmd_params = [
			'user' => $user,
			'password' => $password,
			'use_sudo' => $use_sudo
		];

		// Get create PEM file command
		$ssl_cert = $this->getModule('ssl_cert');
		$result = $ssl_cert->createCertKeyPemFile($cmd_params);
		$state = ($result['error'] == 0);
		if (!$state) {
			// Error
			$emsg = "Error while creating PEM file with certificate and key.";
			$this->reportError($result, $emsg);
		}
		return $state;
	}

	/**
	 * Remove PEM file with certificate and key.
	 * It is used by Lighttpd web server.
	 *
	 * @return bool true on success, false otherwise
	 */
	private function removeCertKeyPemFile(): bool
	{
		$user = $this->CertsAdminAccessUninstallCert->getAdminUser();
		$password = $this->CertsAdminAccessUninstallCert->getAdminPassword();
		$use_sudo = $this->CertsAdminAccessUninstallCert->getAdminUseSudo();

		$cmd_params = [
			'user' => $user,
			'password' => $password,
			'use_sudo' => $use_sudo
		];

		// Get create PEM file command
		$ssl_cert = $this->getModule('ssl_cert');
		$result = $ssl_cert->removeCertKeyPemFile($cmd_params);
		$state = ($result['error'] == 0);
		if (!$state) {
			// Error
			$emsg = "Error while removing PEM file with certificate and key.";
			$this->reportError($result, $emsg);
		}
		return $state;
	}

	/**
	 * Enable HTTPS protocol in web server configuration file.
	 *
	 * @return bool true on success, false otherwise
	 */
	private function enableHTTPSInWebServer(): bool
	{
		$os_name = $this->CertsOSProfile->getSelectedValue();
		if (empty($os_name)) {
			// for renew certificate the OS profile can be missing
			return true;
		}
		$osprofile_config = $this->getModule('osprofile_config');
		$osprofile = $osprofile_config->getOSProfileConfig($os_name);
		$repository_type = '';
		if (count($osprofile) > 0) {
			$repository_type = $osprofile['repository_type'];
		} elseif (key_exists($os_name, self::EXTRA_OS)) {
			$repository_type = self::EXTRA_OS[$os_name]['repository_type'];
		}

		$common_name = $this->CertsSelfSignedCommonName->Text;

		$web_server = $this->CertsWebServer->getSelectedValue();

		$user = $this->CertsAdminAccessCreateCert->getAdminUser();
		$password = $this->CertsAdminAccessCreateCert->getAdminPassword();
		$use_sudo = $this->CertsAdminAccessCreateCert->getAdminUseSudo();

		$cmd_params = [
			'user' => $user,
			'password' => $password,
			'use_sudo' => $use_sudo
		];

		$ws_config = $this->getModule('ws_config');
		$result = $ws_config->enableHTTPS(
			$repository_type,
			$web_server,
			$cmd_params
		);
		$state = ($result['error'] == 0);
		if (!$state) {
			// Error
			$emsg = "Error while configuring self-signed certificate for host: {$common_name}.";
			$this->reportError($result, $emsg);
		}

		// Switch port if needed
		if ($state && $_SERVER['SERVER_PORT'] == 80) {
			$state = $this->setWebServerPort(
				443,
				$this->CertsAdminAccessCreateCert
			);
		}
		return $state;
	}

	/**
	 * Reload web server configuration.
	 * NOTE: in case Lighttpd the web server is not reloaded, but restarted.
	 *
	 * @param AdminAccess $adm_access admin access control instance
	 * @return bool true on success, false otherwise
	 */
	private function reloadWebServerConfig(AdminAccess $adm_access): bool
	{
		$os_name = $this->CertsOSProfile->getSelectedValue();
		if (empty($os_name)) {
			// for renew certificate the OS profile can be missing
			return true;
		}
		$osprofile_config = $this->getModule('osprofile_config');
		$osprofile = $osprofile_config->getOSProfileConfig($os_name);
		$repository_type = '';
		if (count($osprofile) > 0) {
			$repository_type = $osprofile['repository_type'];
		} elseif (key_exists($os_name, self::EXTRA_OS)) {
			$repository_type = self::EXTRA_OS[$os_name]['repository_type'];
		}

		$web_server = $this->CertsWebServer->getSelectedValue();

		$user = $adm_access->getAdminUser();
		$password = $adm_access->getAdminPassword();
		$use_sudo = $adm_access->getAdminUseSudo();

		$cmd_params = [
			'user' => $user,
			'password' => $password,
			'use_sudo' => $use_sudo
		];

		$ws_config = $this->getModule('ws_config');
		$result = $ws_config->reloadConfig(
			$repository_type,
			$web_server,
			$cmd_params
		);
		$state = ($result['error'] == 0);
		if (!$state) {
			// Web server reload/restart error
			$emsg = "Error while reloading web server configuration.";
			$this->reportError($result, $emsg);
		}
		return $state;
	}

	/**
	 * Save existing certificate.
	 */
	private function saveExistingCert(): bool
	{
		//@TODO
		return true;
	}

	/**
	 * Uninstall and remove the certificate and key.
	 *
	 * @param TCallback $sender sender object
	 * @param TCallbackEventParameter $param event parameters
	 */
	public function uninstallCert($sender, $param): bool
	{
		// Hide previous error message (if any)
		$eid = 'certificate_action_error';
		$cb = $this->getPage()->getCallbackClient();
		$cb->hide($eid);

		// Disable SSL certificate in web server config
		$state = $this->disableHTTPSInWebServer();
		if (!$state) {
			return $state;
		}

		// Remove SSL certificate and key
		$state = $this->removeCertAndKey();
		if (!$state) {
			return $state;
		}

		// Reload web server
		$state = $this->reloadWebServerConfig(
			$this->CertsAdminAccessUninstallCert
		);
		if (!$state) {
			return $state;
		}

		// Everything fine - certificate uninstalled correctly
		$audit = $this->getModule('audit');
		if (is_object($audit)) {
			$audit->audit(
				AuditLog::TYPE_INFO,
				AuditLog::CATEGORY_APPLICATION,
				"SSL certificate has been uninstalled."
			);
		}

		// Update API host protocol
		$update_hcfg = $this->getUpdateHostProps();
		if ($update_hcfg) {
			$host_config = $this->getModule('host_config');
			$api_host = $this->User->getDefaultAPIHost();
			$hcfg = $host_config->getHostConfig($api_host);
			if (key_exists('address', $hcfg) && $hcfg['address'] == 'localhost') {
				$props = [
					'protocol' => 'http'
				];
				if ($_SERVER['SERVER_PORT'] == 443) {
					$props['port'] = 80;
				}
				$host_config->updateHostConfig(
					$api_host,
					$props
				);
			}
		}

		$iid = 'remove_cert_info';
		$cb->show($iid);
		// END
		return true;
	}

	/**
	 * Disable HTTPS protocol in web server configuration.
	 *
	 * @return bool true on success, false otherwise
	 */
	public function disableHTTPSInWebServer(): bool
	{
		$os_name = $this->CertsOSProfile->getSelectedValue();
		if (empty($os_name)) {
			// to remove certificate the OS profile cannot be missing
			return true;
		}
		$osprofile_config = $this->getModule('osprofile_config');
		$osprofile = $osprofile_config->getOSProfileConfig($os_name);
		$repository_type = '';
		if (count($osprofile) > 0) {
			$repository_type = $osprofile['repository_type'];
		} elseif (key_exists($os_name, self::EXTRA_OS)) {
			$repository_type = self::EXTRA_OS[$os_name]['repository_type'];
		}

		$web_server = $this->CertsWebServer->getSelectedValue();

		$user = $this->CertsAdminAccessUninstallCert->getAdminUser();
		$password = $this->CertsAdminAccessUninstallCert->getAdminPassword();
		$use_sudo = $this->CertsAdminAccessUninstallCert->getAdminUseSudo();

		$cmd_params = [
			'user' => $user,
			'password' => $password,
			'use_sudo' => $use_sudo
		];

		$ws_config = $this->getModule('ws_config');
		$result = $ws_config->disableHTTPS(
			$repository_type,
			$web_server,
			$cmd_params
		);
		$state = ($result['error'] == 0);
		if (!$state) {
			// Error
			$emsg = "Error while disabling HTTPS in web server configuration.";
			$this->reportError($result, $emsg);
		}

		// Switch port if needed
		if ($state && $_SERVER['SERVER_PORT'] == 443) {
			$state = $this->setWebServerPort(
				80,
				$this->CertsAdminAccessUninstallCert
			);
		}
		return $state;
	}

	/**
	 * Remove certificate and key.
	 * For Lighttpd web server there is removed also PEM file with certificate and key.
	 *
	 * @return bool true on success, false otherwise
	 */
	public function removeCertAndKey(): bool
	{
		$user = $this->CertsAdminAccessUninstallCert->getAdminUser();
		$password = $this->CertsAdminAccessUninstallCert->getAdminPassword();
		$use_sudo = $this->CertsAdminAccessUninstallCert->getAdminUseSudo();

		$cmd_params = [
			'user' => $user,
			'password' => $password,
			'use_sudo' => $use_sudo
		];

		$ssl_cert = $this->getModule('ssl_cert');
		$result = $ssl_cert->removeCertAndKeyFiles($cmd_params);
		$state = ($result['error'] == 0);
		if (!$state) {
			// Remove cert and key error
			$emsg = "Error while removing SSL certificate and key.";
			$this->reportError($result, $emsg);
		}

		// Check if there is a need to remove PEM file
		$web_server = $this->CertsWebServer->getSelectedValue();
		if ($web_server == Miscellaneous::WEB_SERVERS['lighttpd']['id']) {
			$state = $this->removeCertKeyPemFile();
		}
		return $state;
	}

	/**
	 * Set port in web server configuration.
	 *
	 * @param int $port port to set
	 * @param AdminAccess $admin_access administrator access control instance
	 * @return bool true on success, false otherwise
	 */
	public function setWebServerPort(int $port, AdminAccess $admin_access): bool
	{
		$os_name = $this->CertsOSProfile->getSelectedValue();
		if (empty($os_name)) {
			// for renew certificate the OS profile can be missing
			return true;
		}
		$osprofile_config = $this->getModule('osprofile_config');
		$osprofile = $osprofile_config->getOSProfileConfig($os_name);
		$repository_type = '';
		if (count($osprofile) > 0) {
			$repository_type = $osprofile['repository_type'];
		} elseif (key_exists($os_name, self::EXTRA_OS)) {
			$repository_type = self::EXTRA_OS[$os_name]['repository_type'];
		}

		$web_server = $this->CertsWebServer->getSelectedValue();

		$user = $admin_access->getAdminUser();
		$password = $admin_access->getAdminPassword();
		$use_sudo = $admin_access->getAdminUseSudo();

		$cmd_params = [
			'user' => $user,
			'password' => $password,
			'use_sudo' => $use_sudo
		];
		$ws_config = $this->getModule('ws_config');
		$result = $ws_config->setPort(
			$repository_type,
			$web_server,
			$port,
			$cmd_params
		);
		$state = ($result['error'] == 0);
		if (!$state) {
			// Error
			$emsg = "Error while setting port in web server configuration.";
			$this->reportError($result, $emsg);
		}
		return $state;
	}

	/**
	 * Report errors.
	 *
	 * @param array $result result from command
	 * @param string $emsg error message to display
	 */
	private function reportError(array $result, string $emsg): void
	{
		$output = '';
		if (key_exists('raw', $result)) {
			// responses from ACME server
			$output = $result['raw'];
		} elseif (key_exists('output', $result)) {
			// all other errors
			$output = implode(PHP_EOL, $result['output']);
		}
		$emsg .= " Error: {$result['error']}, Output: {$output}.";
		$audit = $this->getModule('audit');
		if (is_object($audit)) {
			$audit->audit(
				AuditLog::TYPE_ERROR,
				AuditLog::CATEGORY_APPLICATION,
				$emsg
			);
		}
		Logging::log(
			Logging::CATEGORY_APPLICATION,
			$emsg
		);
		$eid = 'certificate_action_error';
		$eid_msg = 'certificate_action_error_msg';
		$cb = $this->getPage()->getCallbackClient();
		$cb->update($eid_msg, htmlentities($emsg));
		$cb->show($eid);
	}


	/**
	 * Update host properties option setter.
	 * On the certificate save, the API host config needs to be switched to 'https'.
	 *
	 * @param string $state decides if host protocol will be updated
	 */
	public function setUpdateHostProps($state): void
	{
		$st = TPropertyValue::ensureBoolean($state);
		$this->setViewState(self::UPDATE_HOST_CONFIG, $st);
	}

	/**
	 * Update host properties option getter.
	 *
	 * @return bool update host protocol option value
	 */
	public function getUpdateHostProps(): bool
	{
		return $this->getViewState(self::UPDATE_HOST_CONFIG, false);
	}
}
