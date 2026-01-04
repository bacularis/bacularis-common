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

namespace Bacularis\Common\Portlets;

use Bacularis\Common\Modules\AuditLog;
use Bacularis\Common\Modules\BinaryPackage;
use Bacularis\Common\Modules\Logging;
use Bacularis\Common\Modules\Miscellaneous;
use Bacularis\Common\Modules\WebServerConfig;
use Bacularis\Common\Portlets\PortletTemplate;

/**
 * Web server settings management.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Control
 */
class WebServerSettings extends PortletTemplate
{
	/**
	 * Additional operating systems which are supported to web server settings.
	 */
	public const EXTRA_OS = [
		'Alpine Linux' => [
			'name' => 'Alpine Linux',
			'repository_type' => BinaryPackage::TYPE_APK
		]
	];

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
		$this->setPort();
		$this->loadWebServers();
	}

	/**
	 * Autodetect web server and mark it selected.
	 */
	private function loadWebServers(): void
	{
		$misc = $this->getModule('misc');
		$this->web_server = $misc->detectWebServer();
		$this->WebServerList->SelectedValue = $this->web_server;
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
		$this->WebServerOSProfile->DataSource = $osps;
		$this->WebServerOSProfile->dataBind();
	}

	/**
	 * Set port in field.
	 */
	public function setPort(): void
	{
		$this->WebServerPort->Text = $_SERVER['SERVER_PORT'];
	}

	/**
	 * Save web server settings.
	 *
	 * @param TCallback $sender sender object
	 * @param TCallbackEventParameter $param event parameters
	 */
	public function saveSettings($sender, $param)
	{
		$port = (int) $this->WebServerPort->Text;

		// Update port
		$state = $this->setWebServerPort($port);
		if (!$state) {
			return $state;
		}

		// Reload/restart web server
		$state = $this->reloadWebServerConfig(
			$this->WebServerAdminAccessPort
		);
		if (!$state) {
			return $state;
		}

		// Update API host config
		if ($state) {
			$host_config = $this->getModule('host_config');
			$api_host = $this->User->getDefaultAPIHost();
			$hcfg = $host_config->getHostConfig($api_host);
			if (key_exists('address', $hcfg) && $hcfg['address'] == 'localhost') {
				$host_config->updateHostConfig(
					$api_host,
					['port' => $port]
				);
			}
			$iid = 'web_server_port_info';
			$cb = $this->getPage()->getCallbackClient();
			$cb->show($iid);
		}
	}

	/**
	 * Set port in web server configuration.
	 *
	 * @param int $port port to set
	 * @return bool true on success, false otherwise
	 */
	public function setWebServerPort(int $port): bool
	{
		$os_name = $this->WebServerOSProfile->getSelectedValue();
		if (empty($os_name)) {
			// to set port the OS profile cannot be missing
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

		$web_server = $this->WebServerList->getSelectedValue();

		$user = $this->WebServerAdminAccessPort->getAdminUser();
		$password = $this->WebServerAdminAccessPort->getAdminPassword();
		$use_sudo = $this->WebServerAdminAccessPort->getAdminUseSudo();

		$cmd_params = [
			'user' => $user,
			'password' => $password,
			'use_sudo' => $use_sudo
		];

		$cmd = [];
		if ($web_server == Miscellaneous::WEB_SERVERS['nginx']['id']) {
			$cmd = WebServerConfig::getChangeNginxPortCommand(
				$repository_type,
				$port,
				$cmd_params
			);
		} elseif ($web_server == Miscellaneous::WEB_SERVERS['lighttpd']['id']) {
			$cmd = WebServerConfig::getChangeLighttpdPortCommand(
				$port,
				$cmd_params
			);
		} elseif ($web_server == Miscellaneous::WEB_SERVERS['apache']['id']) {
			$cmd = WebServerConfig::getChangeApachePortCommand(
				$repository_type,
				$port,
				$cmd_params
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
			$emsg = "Error while setting port in web server configuration.";
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
			$eid = 'web_server_action_error';
			$eid_msg = 'web_server_action_error_msg';
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
		$os_name = $this->WebServerOSProfile->getSelectedValue();
		if (empty($os_name)) {
			// the OS profile can be missing
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

		$web_server = $this->WebServerList->getSelectedValue();

		$user = $adm_access->getAdminUser();
		$password = $adm_access->getAdminPassword();
		$use_sudo = $adm_access->getAdminUseSudo();

		$cmd_params = [
			'user' => $user,
			'password' => $password,
			'use_sudo' => $use_sudo
		];

		$cmd = [];
		if ($web_server == Miscellaneous::WEB_SERVERS['nginx']['id']) {
			$cmd = WebServerConfig::getNginxReloadCommand(
				$repository_type,
				$cmd_params
			);
		} elseif ($web_server == Miscellaneous::WEB_SERVERS['lighttpd']['id']) {
			$cmd = WebServerConfig::getLighttpdRestartCommand(
				$repository_type,
				$cmd_params
			);
		} elseif ($web_server == Miscellaneous::WEB_SERVERS['apache']['id']) {
			$cmd = WebServerConfig::getApacheReloadCommand(
				$repository_type,
				$cmd_params
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
			$eid = 'web_server_action_error';
			$eid_msg = 'web_server_action_error_msg';
			$cb = $this->getPage()->getCallbackClient();
			$cb->update($eid_msg, htmlentities($lmsg));
			$cb->show($eid);
		}
		return $state;
	}
}
