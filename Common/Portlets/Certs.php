<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2024 Marcin Haba
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
use Bacularis\Common\Modules\ExecuteCommand;
use Bacularis\Common\Modules\Logging;
use Bacularis\Common\Modules\Miscellaneous;
use Bacularis\Common\Modules\SSLCertificate;
use Bacularis\Common\Modules\WebServerConfig;
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
	private const UPDATE_HOST_CONFIG = 'UpdateHostConfig';

	/**
	 * Supported certificate types.
	 */
	public const CERT_TYPE_LETS_ENCRYPT = 'lets-encrypt';
	public const CERT_TYPE_SELF_SIGNED = 'self-signed';
	public const CERT_TYPE_EXISTING = 'existing';

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
			$cmd = SSLCertificate::getCertDetailsCommand();
			$ret = ExecuteCommand::execCommand($cmd);
			if ($ret['error'] === 0) {
				$this->cert_raw_output = $ret['output'];
				$props = SSLCertificate::parseOpenSSLCert($ret['output']);
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
			$not_before = new DateTime(
				$props['validity']['not_before']
			);
			$not_after = new DateTime(
				$props['validity']['not_after']
			);
			$ddiff = $not_after->getTimestamp() - $not_before->getTimestamp();
			$days_no = (int) ($ddiff / 60 / 60 / 24);
		}
		$this->CertsSelfSignedValidityDays->Text = $days_no;
		$this->CertsSelfSignedCommonName->Text = $props['subject']['common_name'] ?? 'localhost';
		$this->CertsSelfSignedEmail->Text = $props['subject']['email'] ?? '';
		$this->CertsSelfSignedCountryCode->Text = $props['subject']['country_code'] ?? 'ZZ';
		$this->CertsSelfSignedState->Text = $props['subject']['state'] ?? '';
		$this->CertsSelfSignedLocality->Text = $props['subject']['locality'] ?? '';
		$this->CertsSelfSignedOrganization->Text = $props['subject']['organization'] ?? '';
		$this->CertsSelfSignedOrganizationUnit->Text = $props['subject']['organization_unit'] ?? '';
		$this->CertsAction->SelectedValue = '';
		if (isset($props['issuer']['common_name']) && isset($props['subject']['common_name']) && $props['issuer']['common_name'] == $props['subject']['common_name']) {
			$this->CertsAction->SelectedValue = self::CERT_TYPE_SELF_SIGNED;
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
		sort($names, SORT_NATURAL | SORT_FLAG_CASE);
		array_unshift($names, '');
		$osps = array_combine($names, $names);

		// OS profile combobox
		$this->CertsOSProfile->DataSource = $osps;
		$this->CertsOSProfile->dataBind();
	}

	/**
	 * Save certificate.
	 *
	 * @param TCallback $sender sender object
	 * @param TCallbackEventParameter $param event parameters
	 */
	public function saveCert($sender, $param): void
	{
		$action = $this->CertsAction->getSelectedValue();
		switch ($action) {
			case self::CERT_TYPE_LETS_ENCRYPT: {
				$this->saveLetsEncryptCert();
				break;
			}
			case self::CERT_TYPE_SELF_SIGNED: {
				$this->saveSelfSignedCert();
				break;
			}
			case self::CERT_TYPE_EXISTING: {
				$this->saveExistingCert();
				break;
			}
		}
	}

	/**
	 * Save Let's Encrypt certificate.
	 */
	private function saveLetsEncryptCert(): bool
	{
		//@TODO
		return true;
	}

	/**
	 * Save self-signed certificate.
	 */
	private function saveSelfSignedCert(): bool
	{
		// Create certificate
		$state = $this->createSelfSignedCertificate();
		if (!$state) {
			return $state;
		}

		// Update web server config
		$state = $this->enableHTTPSInWebServer();
		if (!$state) {
			return $state;
		}

		// Reload web server
		$state = $this->reloadWebServerConfig(
			$this->CertsAdminAccessCreateCert
		);
		if (!$state) {
			return $state;
		}

		// Everything fine - certificate created correctly
		$common_name = $this->CertsSelfSignedCommonName->Text;
		$this->getModule('audit')->audit(
			AuditLog::TYPE_INFO,
			AuditLog::CATEGORY_APPLICATION,
			"New self-signed certificate has been created and configured for host: {$common_name}"
		);

		$eid = 'certificate_action_error';
		$cb = $this->getPage()->getCallbackClient();
		$cb->hide($eid);

		// Update API host protocol
		$update_hcfg = $this->getUpdateHostProtocol();
		if ($update_hcfg) {
			$this->updateAPIHostProtocol('https');
		}

		$iid = 'install_cert_info';
		$cb->show($iid);
		// END
		return true;
	}

	/**
	 * Create self-signed certificate.
	 *
	 * @return bool state true on success, false otherwise
	 */
	private function createSelfSignedCertificate(): bool
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
		$cmd = SSLCertificate::getPrepareHTTPSCertCommand($cert_params);
		$su = $this->getModule('su');
		$params = [
			'command' => implode(' ', $cmd),
			'use_sudo' => $use_sudo
		];
		$ret = $su->execCommand(
			$user,
			$password,
			$params
		);
		$state = ($ret['exitcode'] == 0);
		if (!$state) {
			// Error while creating key and certificate
			$emsg = "Error while creating self-signed certificate for host: {$common_name}.";
			$this->getModule('audit')->audit(
				AuditLog::TYPE_ERROR,
				AuditLog::CATEGORY_APPLICATION,
				$emsg
			);
			$output = implode(PHP_EOL, $ret['output']);
			$lmsg = $emsg . " ExitCode: {$ret['exitcode']}, Error: {$output}.";
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				$lmsg
			);
			$eid = 'certificate_action_error';
			$eid_msg = 'certificate_action_error_msg';
			$cb = $this->getPage()->getCallbackClient();
			$cb->update($eid_msg, htmlentities($lmsg));
			$cb->show($eid);
		}

		// Check if there is a need to create PEM file
		$web_server = $this->CertsWebServer->getSelectedValue();
		if ($web_server == Miscellaneous::WEB_SERVERS['lighttpd']['id']) {
			$state = $this->createCertKeyPemFile();
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

		$params = [
			'use_sudo' => $use_sudo
		];

		// Get create PEM file command
		$cmd = SSLCertificate::getPrepareHTTPSPemCommand($params);
		$params = [
			'command' => implode(' ', $cmd),
			'use_sudo' => $use_sudo
		];
		$su = $this->getModule('su');
		$ret = $su->execCommand(
			$user,
			$password,
			$params
		);
		$state = ($ret['exitcode'] == 0);
		if (!$state) {
			// Error while creating PEM file with cert and key
			$emsg = "Error while creating PEM file with certificate and key.";
			$this->getModule('audit')->audit(
				AuditLog::TYPE_ERROR,
				AuditLog::CATEGORY_APPLICATION,
				$emsg
			);
			$output = implode(PHP_EOL, $ret['output']);
			$lmsg = $emsg . " ExitCode: {$ret['exitcode']}, Error: {$output}.";
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				$lmsg
			);
			$eid = 'certificate_action_error';
			$eid_msg = 'certificate_action_error_msg';
			$cb = $this->getPage()->getCallbackClient();
			$cb->update($eid_msg, htmlentities($lmsg));
			$cb->show($eid);
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

		$params = [
			'use_sudo' => $use_sudo
		];

		// Get create PEM file command
		$cmd = SSLCertificate::getRemoveHTTPSPemCommand($params);
		$params = [
			'command' => implode(' ', $cmd),
			'use_sudo' => $use_sudo
		];
		$su = $this->getModule('su');
		$ret = $su->execCommand(
			$user,
			$password,
			$params
		);
		$state = ($ret['exitcode'] == 0);
		if (!$state) {
			// Error while creating PEM file with cert and key
			$emsg = "Error while removing PEM file with certificate and key.";
			$this->getModule('audit')->audit(
				AuditLog::TYPE_ERROR,
				AuditLog::CATEGORY_APPLICATION,
				$emsg
			);
			$output = implode(PHP_EOL, $ret['output']);
			$lmsg = $emsg . " ExitCode: {$ret['exitcode']}, Error: {$output}.";
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				$lmsg
			);
			$eid = 'certificate_action_error';
			$eid_msg = 'certificate_action_error_msg';
			$cb = $this->getPage()->getCallbackClient();
			$cb->update($eid_msg, htmlentities($lmsg));
			$cb->show($eid);
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
		$osprofile_name = $this->CertsOSProfile->getSelectedValue();
		if (empty($osprofile_name)) {
			// for renew certificate the OS profile can be missing
			return true;
		}
		$osprofile_config = $this->getModule('osprofile_config');
		$osprofile = $osprofile_config->getOSProfileConfig($osprofile_name);

		$common_name = $this->CertsSelfSignedCommonName->Text;
		$web_server = $this->CertsWebServer->getSelectedValue();

		$user = $this->CertsAdminAccessCreateCert->getAdminUser();
		$password = $this->CertsAdminAccessCreateCert->getAdminPassword();
		$use_sudo = $this->CertsAdminAccessCreateCert->getAdminUseSudo();

		$params = [
			'use_sudo' => $use_sudo
		];

		$cmd = '';
		if ($web_server == Miscellaneous::WEB_SERVERS['nginx']['id']) {
			$cmd = WebServerConfig::getEnableHTTPSNginxCommand(
				$osprofile['repository_type'],
				$params
			);
		} elseif ($web_server == Miscellaneous::WEB_SERVERS['lighttpd']['id']) {
			$cmd = WebServerConfig::getEnableHTTPSLighttpdCommand(
				$params
			);
		} elseif ($web_server == Miscellaneous::WEB_SERVERS['apache']['id']) {
			$cmd = WebServerConfig::getEnableHTTPSApacheCommand(
				$osprofile['repository_type'],
				$params
			);
		}
		$params = [
			'command' => implode(' ', $cmd),
			'use_sudo' => $use_sudo
		];
		$su = $this->getModule('su');
		$ret = $su->execCommand(
			$user,
			$password,
			$params
		);
		$state = ($ret['exitcode'] == 0);
		if (!$state) {
			// Error while modifying web server configuration
			$emsg = "Error while configuring self-signed certificate for host: {$common_name}.";
			$this->getModule('audit')->audit(
				AuditLog::TYPE_ERROR,
				AuditLog::CATEGORY_APPLICATION,
				$emsg
			);
			$output = implode(PHP_EOL, $ret['output']);
			$lmsg = $emsg . " ExitCode: {$ret['exitcode']}, Error: {$output}.";
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				$lmsg
			);
			$eid = 'certificate_action_error';
			$eid_msg = 'certificate_action_error_msg';
			$cb = $this->getPage()->getCallbackClient();
			$cb->update($eid_msg, htmlentities($lmsg));
			$cb->show($eid);
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
		$osprofile_name = $this->CertsOSProfile->getSelectedValue();
		if (empty($osprofile_name)) {
			// for renew certificate the OS profile can be missing
			return true;
		}
		$osprofile_config = $this->getModule('osprofile_config');
		$osprofile = $osprofile_config->getOSProfileConfig($osprofile_name);

		$web_server = $this->CertsWebServer->getSelectedValue();

		$user = $adm_access->getAdminUser();
		$password = $adm_access->getAdminPassword();
		$use_sudo = $adm_access->getAdminUseSudo();

		$params = [
			'use_sudo' => $use_sudo
		];

		$cmd = '';
		if ($web_server == Miscellaneous::WEB_SERVERS['nginx']['id']) {
			$cmd = WebServerConfig::getNginxReloadCommand(
				$params
			);
		} elseif ($web_server == Miscellaneous::WEB_SERVERS['lighttpd']['id']) {
			$cmd = WebServerConfig::getLighttpdRestartCommand(
				$params
			);
		} elseif ($web_server == Miscellaneous::WEB_SERVERS['apache']['id']) {
			$cmd = WebServerConfig::getApacheReloadCommand(
				$osprofile['repository_type'],
				$params
			);
		}
		$params = [
			'command' => implode(' ', $cmd),
			'use_sudo' => $use_sudo
		];
		$su = $this->getModule('su');
		$ret = $su->execCommand(
			$user,
			$password,
			$params
		);
		$state = ($ret['exitcode'] == 0);
		if (!$state) {
			// Web server reload/restart error
			$emsg = "Error while reloading web server configuration.";
			$this->getModule('audit')->audit(
				AuditLog::TYPE_ERROR,
				AuditLog::CATEGORY_APPLICATION,
				$emsg
			);
			$output = implode(PHP_EOL, $ret['output']);
			$lmsg = $emsg . " ExitCode: {$ret['exitcode']}, Error: {$output}.";
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				$lmsg
			);
			$eid = 'certificate_action_error';
			$eid_msg = 'certificate_action_error_msg';
			$cb = $this->getPage()->getCallbackClient();
			$cb->update($eid_msg, htmlentities($lmsg));
			$cb->show($eid);
		}
		return $state;
	}

	/**
	 * Update protocol in current API host config.
	 * NOTE: The protocol is updated only in the default local API host connection.
	 * For all other API connections that use local API, user has to switch to
	 * the new protocol manually.
	 *
	 * @param string $protocol protocol (http|https) to set in default API host
	 * @return bool true on success, false otherwise
	 */
	private function updateAPIHostProtocol(string $protocol = 'https'): bool
	{
		$api_host = $this->User->getDefaultAPIHost();
		$host_config = $this->getModule('host_config');
		$config = $host_config->getHostConfig($api_host);
		$config['protocol'] = $protocol;
		return $host_config->setHostConfig($api_host, $config);
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
		$this->getModule('audit')->audit(
			AuditLog::TYPE_INFO,
			AuditLog::CATEGORY_APPLICATION,
			"SSL certificate has been uninstalled."
		);

		$eid = 'certificate_action_error';
		$cb = $this->getPage()->getCallbackClient();
		$cb->hide($eid);

		// Update API host protocol
		$update_hcfg = $this->getUpdateHostProtocol();
		if ($update_hcfg) {
			$this->updateAPIHostProtocol('http');
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
		$osprofile_name = $this->CertsOSProfile->getSelectedValue();
		if (empty($osprofile_name)) {
			// to remove certificate the OS profile cannot be missing
			return true;
		}
		$osprofile_config = $this->getModule('osprofile_config');
		$osprofile = $osprofile_config->getOSProfileConfig($osprofile_name);

		$web_server = $this->CertsWebServer->getSelectedValue();

		$user = $this->CertsAdminAccessUninstallCert->getAdminUser();
		$password = $this->CertsAdminAccessUninstallCert->getAdminPassword();
		$use_sudo = $this->CertsAdminAccessUninstallCert->getAdminUseSudo();

		$params = [
			'use_sudo' => $use_sudo
		];

		$cmd = '';
		if ($web_server == Miscellaneous::WEB_SERVERS['nginx']['id']) {
			$cmd = WebServerConfig::getDisableHTTPSNginxCommand(
				$osprofile['repository_type'],
				$params
			);
		} elseif ($web_server == Miscellaneous::WEB_SERVERS['lighttpd']['id']) {
			$cmd = WebServerConfig::getDisableHTTPSLighttpdCommand(
				$params
			);
		} elseif ($web_server == Miscellaneous::WEB_SERVERS['apache']['id']) {
			$cmd = WebServerConfig::getDisableHTTPSApacheCommand(
				$osprofile['repository_type'],
				$params
			);
		}
		$params = [
			'command' => implode(' ', $cmd),
			'use_sudo' => $use_sudo
		];
		$su = $this->getModule('su');
		$ret = $su->execCommand(
			$user,
			$password,
			$params
		);
		$state = ($ret['exitcode'] == 0);
		if (!$state) {
			// Web server reload/restart error
			$emsg = "Error while disabling HTTPS in web server configuration.";
			$this->getModule('audit')->audit(
				AuditLog::TYPE_ERROR,
				AuditLog::CATEGORY_APPLICATION,
				$emsg
			);
			$output = implode(PHP_EOL, $ret['output']);
			$lmsg = $emsg . " ExitCode: {$ret['exitcode']}, Error: {$output}.";
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				$lmsg
			);
			$eid = 'certificate_action_error';
			$eid_msg = 'certificate_action_error_msg';
			$cb = $this->getPage()->getCallbackClient();
			$cb->update($eid_msg, htmlentities($lmsg));
			$cb->show($eid);
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

		$params = [
			'use_sudo' => $use_sudo
		];

		$cmd = SSLCertificate::getRemoveHTTPSCertCommand($params);
		$params = [
			'command' => implode(' ', $cmd),
			'use_sudo' => $use_sudo
		];
		$su = $this->getModule('su');
		$ret = $su->execCommand(
			$user,
			$password,
			$params
		);
		$state = ($ret['exitcode'] == 0);
		if (!$state) {
			// Remove cert and key error
			$emsg = "Error while removing SSL certificate and key.";
			$this->getModule('audit')->audit(
				AuditLog::TYPE_ERROR,
				AuditLog::CATEGORY_APPLICATION,
				$emsg
			);
			$output = implode(PHP_EOL, $ret['output']);
			$lmsg = $emsg . " ExitCode: {$ret['exitcode']}, Error: {$output}.";
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				$lmsg
			);
			$eid = 'certificate_action_error';
			$eid_msg = 'certificate_action_error_msg';
			$cb = $this->getPage()->getCallbackClient();
			$cb->update($eid_msg, htmlentities($lmsg));
			$cb->show($eid);
		}

		// Check if there is a need to remove PEM file
		$web_server = $this->CertsWebServer->getSelectedValue();
		if ($web_server == Miscellaneous::WEB_SERVERS['lighttpd']['id']) {
			$state = $this->removeCertKeyPemFile();
		}
		return $state;
	}

	/**
	 * Update host protocol option setter.
	 * On the certificate save, the API host config needs to be switched to 'https'.
	 *
	 * @param string $state decides if host protocol will be updated
	 */
	public function setUpdateHostProtocol($state): void
	{
		$st = TPropertyValue::ensureBoolean($state);
		$this->setViewState(self::UPDATE_HOST_CONFIG, $st);
	}

	/**
	 * Update host protocol option getter.
	 *
	 * @return bool update host protocol option value
	 */
	public function getUpdateHostProtocol(): bool
	{
		return $this->getViewState(self::UPDATE_HOST_CONFIG, false);
	}
}
