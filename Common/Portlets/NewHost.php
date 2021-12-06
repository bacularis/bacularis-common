<?php
/*
 * Bacula(R) - The Network Backup Solution
 * Baculum   - Bacula web interface
 *
 * Copyright (C) 2013-2019 Kern Sibbald
 *
 * The main author of Baculum is Marcin Haba.
 * The original author of Bacula is Kern Sibbald, with contributions
 * from many others, a complete list can be found in the file AUTHORS.
 *
 * You may use this file and others of this release according to the
 * license defined in the LICENSE file, which includes the Affero General
 * Public License, v3.0 ("AGPLv3") and some additional permissions and
 * terms pursuant to its AGPLv3 Section 7.
 *
 * This notice must be preserved when any source code is
 * conveyed and/or propagated.
 *
 * Bacula(R) is a registered trademark of Kern Sibbald.
 */

Prado::using('System.Web.UI.ActiveControls.TActiveLabel');
Prado::using('System.Web.UI.ActiveControls.TActiveLinkButton');
Prado::using('System.Web.UI.ActiveControls.TActiveDropDownList');
Prado::using('System.Web.UI.ActiveControls.TActivePanel');
Prado::using('System.Web.UI.ActiveControls.TActiveTextBox');
Prado::using('System.Web.UI.TTemplateControl');
Prado::using('Application.Common.Class.OAuth2');
Prado::using('Application.Common.Portlets.PortletTemplate');

/**
 * New host control.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Control
 * @package Baculum Common
 */
class NewHost extends PortletTemplate {

	private $error = false;

	private $show_buttons = true;

	private $client_mode = false;

	private $force_host_name;

	private $api_required;

	public function onLoad($param) {
		$host_name = $this->getForceHostName();
		if (!empty($host_name)) {
			$this->APIHostName->Text = $host_name;
			$this->APIHostName->setReadOnly(true);
		}
	}

	public function connectionAPITest($sender, $param) {
		$host = $this->APIAddress->Text;
		if (empty($host)) {
			$host = false;
		}
		$host_params = array(
			'protocol' => $this->APIProtocol->SelectedValue,
			'address' => $this->APIAddress->Text,
			'port' => $this->APIPort->Text,
			'url_prefix' => ''
		);

		if ($this->AuthBasic->Checked) {
			$host_params['auth_type'] = 'basic';
			$host_params['login'] = $this->APIBasicLogin->Text;
			$host_params['password'] = $this->APIBasicPassword->Text;
		} elseif ($this->AuthOAuth2->Checked) {
			$host_params['auth_type'] = 'oauth2';
			$host_params['client_id'] = $this->APIOAuth2ClientId->Text;
			$host_params['client_secret'] = $this->APIOAuth2ClientSecret->Text;
			$host_params['redirect_uri'] = $this->APIOAuth2RedirectURI->Text;
			$host_params['scope'] = $this->APIOAuth2Scope->Text;
		}
		$api = $this->getModule('api');

		// Catalog test
		$api->setHostParams($host, $host_params);
		$catalog = $api->get(array('catalog'), $host, false);

		// Console test
		$api->setHostParams($host, $host_params);
		$director = null;
		if (array_key_exists('director', $_SESSION)) {
			// Current director can't be passed to new remote host.
			$director = $_SESSION['director'];
			unset($_SESSION['director']);
		}

		$console = $api->set(array('console'), array('version'), $host, false);
		if (!is_null($director)) {
			// Revert director setting if any
			$_SESSION['director'] = $director;
		}

		// Config test
		$api->setHostParams($host, $host_params);
		$config = $api->get(array('config'), $host, false);

		$is_catalog = (is_object($catalog) && $catalog->error === 0);
		$is_console = (is_object($console) && $console->error === 0);
		$is_config = (is_object($config) && $config->error === 0);

		$status_ok = true;
		if (in_array('catalog', $this->api_required)) {
			$status_ok = $is_catalog;
		}
		if ($status_ok && in_array('console', $this->api_required)) {
			$status_ok = $is_console;
		}
		if ($status_ok && in_array('config', $this->api_required)) {
			$status_ok = $is_config;
		}

		if (!$is_catalog) {
			$this->APITestResultErr->Text .= $catalog->output . '<br />';
		}
		if (!$is_console) {
			$this->APITestResultErr->Text .= $console->output . '<br />';
		}
		if (!$is_config) {
			$this->APITestResultErr->Text .= $config->output . '<br />';
		}

		$this->APITestResultOk->Display = ($status_ok === true) ? 'Dynamic' : 'None';
		$this->APITestResultErr->Display = ($status_ok === false) ? 'Dynamic' : 'None';
		$this->APICatalogSupportYes->Display = ($is_catalog === true) ? 'Dynamic' : 'None';
		$this->APICatalogSupportNo->Display = ($is_catalog === false) ? 'Dynamic' : 'None';
		$this->APIConsoleSupportYes->Display = ($is_console === true) ? 'Dynamic' : 'None';
		$this->APIConsoleSupportNo->Display = ($is_console === false) ? 'Dynamic' : 'None';
		$this->APIConfigSupportYes->Display = ($is_config === true) ? 'Dynamic' : 'None';
		$this->APIConfigSupportNo->Display = ($is_config === false) ? 'Dynamic' : 'None';
	}

	public function addNewHost($sender, $param) {
		$cfg_host = array(
			'auth_type' => '',
			'login' => '',
			'password' => '',
			'client_id' => '',
			'client_secret' => '',
			'redirect_uri' => '',
			'scope' => ''
		);
		$cfg_host['protocol'] = $this->APIProtocol->Text;
		$cfg_host['address'] = $this->APIAddress->Text;
		$cfg_host['port'] = $this->APIPort->Text;
		$cfg_host['url_prefix'] = '';
		$cfg_host['login'] = $this->APIBasicLogin->Text;
		$cfg_host['password'] = $this->APIBasicPassword->Text;
		if ($this->AuthBasic->Checked == true) {
			$cfg_host['auth_type'] = 'basic';
			$cfg_host['login'] = $this->APIBasicLogin->Text;
			$cfg_host['password'] = $this->APIBasicPassword->Text;
		} elseif($this->AuthOAuth2->Checked == true) {
			$cfg_host['auth_type'] = 'oauth2';
			$cfg_host['client_id'] = $this->APIOAuth2ClientId->Text;
			$cfg_host['client_secret'] = $this->APIOAuth2ClientSecret->Text;
			$cfg_host['redirect_uri'] = $this->APIOAuth2RedirectURI->Text;
			$cfg_host['scope'] = $this->APIOAuth2Scope->Text;
		}
		$config = $this->getModule('host_config')->getConfig();
		$this->NewHostAddOk->Display = 'None';
		$this->NewHostAddError->Display = 'None';
		$this->NewHostAddExists->Display = 'None';
		$host_name = trim($this->APIHostName->Text);
		if (empty($host_name)) {
			$host_name = $cfg_host['address'];
		}
		if (!array_key_exists($host_name, $config)) {
			$config[$host_name] = $cfg_host;
			$res = $this->getModule('host_config')->setConfig($config);
			if ($res === true) {
				$this->APIAddress->Text = '';
				$this->APIPort->Text = '9096';
				$this->APIBasicLogin->Text = '';
				$this->APIBasicPassword->Text = '';
				$this->APIHostName->Text = '';
				$this->NewHostAddOk->Display = 'Dynamic';
				$this->APITestResultOk->Display = 'None';
				$this->APITestResultErr->Display = 'None';
				$this->APICatalogSupportYes->Display = 'None';
				$this->APICatalogSupportNo->Display = 'None';
				$this->APIConsoleSupportYes->Display = 'None';
				$this->APIConsoleSupportNo->Display = 'None';
				$this->APIConfigSupportYes->Display = 'None';
				$this->APIConfigSupportNo->Display = 'None';
				$this->error = true;
			} else {
				$this->NewHostAddError->Display = 'Dynamic';
			}
		} else {
			$this->NewHostAddExists->Display = 'Dynamic';
		}
		$this->onCallback($param);
	}

	public function setForceHostName($host_name) {
		$this->force_host_name = $host_name;
	}

	public function getForceHostName() {
		return $this->force_host_name;
	}

	public function setShowButtons($show) {
		$show = TPropertyValue::ensureBoolean($show);
		$this->show_buttons = $show;
	}

	public function getShowButtons() {
		return $this->show_buttons;
	}

	public function bubbleEvent($sender, $param) {
		if ($param instanceof TCommandEventParameter) {
			if ($this->error === true) {
				$this->raiseBubbleEvent($this,$param);
			}
			return true;
		} else {
			return false;
		}
	}

	public function setAPIRequired($api_required) {
		$this->api_required = explode('|', $api_required);
	}

	public function getAPIRequired() {
		return $this->api_required;
	}

	public function setClientMode($client_mode) {
		$client_mode = TPropertyValue::ensureBoolean($client_mode);
		$this->client_mode = $client_mode;
	}

	public function getClientMode() {
		return $this->client_mode;
	}

	public function onCallback($param) {
		$this->raiseEvent('OnCallback', $this, $param);
	}

}
