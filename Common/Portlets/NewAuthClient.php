<?php
/*
 * Bacula(R) - The Network Backup Solution
 * Baculum   - Bacula web interface
 *
 * Copyright (C) 2013-2021 Kern Sibbald
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

Prado::using('System.Web.UI.ActiveControls.TActiveHiddenField');
Prado::using('System.Web.UI.ActiveControls.TActiveLabel');
Prado::using('System.Web.UI.ActiveControls.TActiveLinkButton');
Prado::using('System.Web.UI.ActiveControls.TActiveButton');
Prado::using('System.Web.UI.ActiveControls.TActiveTextBox');
Prado::using('System.Web.UI.TTemplateControl');
Prado::using('Application.Common.Class.OAuth2');
Prado::using('Application.Common.Class.BasicUserConfig');
Prado::using('Application.Common.Portlets.PortletTemplate');

/**
 * New auth client control.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Control
 * @package Baculum Common
 */
class NewAuthClient extends PortletTemplate {

	private $show_buttons = true;

	private $auth_types = ['basic', 'oauth2'];

	private $auth_type;

	private $modes = ['add', 'edit'];

	// @TODO: Move it to a common class
	const AUTH_TYPE_BASIC = 'basic';
	const AUTH_TYPE_OAUTH2 = 'oauth2';

	const MODE_TYPE_ADD = 'add';
	const MODE_TYPE_EDIT = 'edit';

	const MODE = 'Mode';

	public function saveNewAuthClient($sender, $param) {
		$this->NewAuthClientError->Display = 'None';
		$this->NewAuthClientExists->Display = 'None';

		$result = false;
		$exists = false;
		if ($this->getAuthType() === self::AUTH_TYPE_BASIC) {
			$basic_config = $this->getModule('basic_config');
			$props = [
				'bconsole_cfg_path' => $this->APIBasicBconsoleCfgPath->Text
			];
			if ($this->Mode == self::MODE_TYPE_ADD) {
				$users = $this->getModule('basic_apiuser')->getUsers();
				if (!key_exists($this->APIBasicLogin->Text, $users)) {
					$result = $basic_config->addUser(
						$this->APIBasicLogin->Text,
						$this->APIBasicPassword->Text,
						$props
					);
				} else {
					$exists = true;
				}
			} elseif ($this->Mode === self::MODE_TYPE_EDIT) {
				$result = $basic_config->editUser(
					$this->APIBasicLoginHidden->Value,
					$this->APIBasicPassword->Text,
					$props
				);
			}
		} elseif ($this->getAuthType() === self::AUTH_TYPE_OAUTH2) {
			$oauth2_cfg = $this->getModule('oauth2_config')->getConfig();
			if ($this->Mode == self::MODE_TYPE_ADD) {
				if (!key_exists($this->APIOAuth2ClientId->Text, $oauth2_cfg)) {
					$oauth2_cfg[$this->APIOAuth2ClientId->Text] = [
						'client_id' => $this->APIOAuth2ClientId->Text,
						'client_secret' => $this->APIOAuth2ClientSecret->Text,
						'redirect_uri' => $this->APIOAuth2RedirectURI->Text,
						'scope' => $this->APIOAuth2Scope->Text,
						'bconsole_cfg_path' => $this->APIOAuth2BconsoleCfgPath->Text,
						'name' => $this->APIOAuth2Name->Text
					];
					$result = $this->getModule('oauth2_config')->setConfig($oauth2_cfg);
				} else {
					$exists = true;
				}
			} elseif ($this->Mode == self::MODE_TYPE_EDIT) {
				$oauth2_cfg[$this->APIOAuth2ClientIdHidden->Value] = [
					'client_id' => $this->APIOAuth2ClientIdHidden->Value,
					'client_secret' => $this->APIOAuth2ClientSecret->Text,
					'redirect_uri' => $this->APIOAuth2RedirectURI->Text,
					'scope' => $this->APIOAuth2Scope->Text,
					'bconsole_cfg_path' => $this->APIOAuth2BconsoleCfgPath->Text,
					'name' => $this->APIOAuth2Name->Text
				];
				$result = $this->getModule('oauth2_config')->setConfig($oauth2_cfg);
			}
		}

		$cb = true;
		if ($exists) {
			$this->NewAuthClientExists->Display = 'Dynamic';
			$cb = false;
		} elseif ($result !== true) {
			$this->NewAuthClientError->Display = 'Dynamic';
			$cb = false;
		}
		if ($cb) {
			$this->onSuccess($param);
			$this->clearForm();
		}
	}

	public function cancelNewAuthClient($sender, $param) {
		$this->onCancel($param);
	}

	public function clearForm() {
		$this->APIBasicLogin->Text = '';
		$this->APIBasicPassword->Text = '';
		$this->RetypeAPIBasicPassword->Text = '';
		$this->APIOAuth2ClientId->Text = '';
		$this->APIOAuth2ClientSecret->Text = '';
		$this->APIOAuth2RedirectURI->Text = '';
		$this->APIOAuth2Scope->Text = '';
		$this->APIOAuth2BconsoleCfgPath->Text = '';
		$this->APIOAuth2Name->Text = '';
	}

	public function setShowButtons($show) {
		$show = TPropertyValue::ensureBoolean($show);
		$this->show_buttons = $show;
	}

	public function getShowButtons() {
		return $this->show_buttons;
	}

	public function setAuthType($auth_type) {
		if (in_array($auth_type, $this->auth_types)) {
			$this->auth_type = $auth_type;
		}
	}

	public function getAuthType() {
		return $this->auth_type;
	}

	public function setMode($mode) {
		if (in_array($mode, $this->modes)) {
			$this->setViewState(self::MODE, $mode);
		}
	}

	public function getMode() {
		return $this->getViewState(self::MODE, $this->modes[0]);
	}

	public function onSuccess($param) {
		$this->raiseEvent('OnSuccess', $this, $param);
	}

	public function onCancel($param) {
		$this->clearForm();
		$this->raiseEvent('OnCancel', $this, $param);
	}

	public function bubbleEvent($sender, $param) {
		if ($param instanceof TCommandEventParameter) {
			$this->raiseBubbleEvent($this,$param);
			return true;
		} else {
			return false;
		}
	}
}
