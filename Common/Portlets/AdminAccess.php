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

use Bacularis\Common\Portlets\PortletTemplate;

/**
 * Module to get admin access.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Control
 */
class AdminAccess extends PortletTemplate
{
	private const POST_EXECUTE_ACTION = 'PostExecuteAction';

	public function onSave($param)
	{
		$this->raiseEvent('OnSave', $this, $param);
	}

	/**
	 * Run command with admin privileges.
	 *
	 * @param TCallback $sender sender object
	 * @param TCallbackEventParameter $param event parameter
	 */
	public function execute($sender, $param)
	{
		$this->onSave(null);
	}

	/**
	 * Get admin username.
	 *
	 * @return string user name
	 */
	public function getAdminUser(): string
	{
		return $this->AdminAccessName->Text;
	}

	/**
	 * Get admin user password.
	 *
	 * @return string user password
	 */
	public function getAdminPassword(): string
	{
		return $this->AdminAccessPassword->Text;
	}

	/**
	 * Get use sudo.
	 *
	 * @return bool if true, user use sudo, false otherwise
	 */
	public function getAdminUseSudo(): bool
	{
		return $this->AdminAccessUseSudo->Checked;
	}

	/**
	 * Set post execute action.
	 *
	 * @param string $action post execute action
	 */
	public function setPostExecuteAction(string $action): void
	{
		$this->setViewState(self::POST_EXECUTE_ACTION, $action);
	}

	/**
	 * Get post execute action.
	 *
	 * @return string post execute action
	 */
	public function getPostExecuteAction(): string
	{
		return $this->getViewState(self::POST_EXECUTE_ACTION, 'false');
	}
}
