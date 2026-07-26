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

namespace Bacularis\Common\Modules\Shell\Actions;

use Bacularis\Common\Modules\LetsEncryptCert;
use Bacularis\Common\Modules\SSLCertificate;
use Bacularis\Common\Modules\SelfSignedCert;
use Bacularis\Common\Modules\Shell\BShellAction;
use Prado\Shell\TShellWriter;

/**
 * Bacularis tasks for SSL certificate command action.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class TaskCertAction extends BShellAction
{
	protected $action = 'cert';
	protected $methods = ['renew'];
	protected $parameters = [
		[
			'type' => 'certificate-type'
		]
	];
	protected $optional = [
		[
			'days' => 'number'
		]
	];
	protected $description = [
		'Create SSL certificate commands',
		'Renew web server SSL certificate before expiry time (default: 10 days). Supported certificate types: self-signed and lets-encrypt.'
	];
	public $params = [];

	/**
	 * Renew SSL certificate action.
	 *
	 * @param array $args command parameters
	 * @return bool true it is always valid
	 */
	public function actionRenew($args)
	{
		$state = false;
		$refresh_days = (int) ($this->params['days'] ?? 0);
		$days_left = SSLCertificate::getCertValidityDaysLeft();
		$mod = null;
		switch ($this->params['type']) {
			case LetsEncryptCert::CERT_TYPE: {
				$mod = $this->Application->getModule('ssl_le_cert');
				break;
			}
			case SelfSignedCert::CERT_TYPE: {
				$mod = $this->Application->getModule('ssl_ss_cert');
				break;
			}
		}
		if ($mod) {
			if ($refresh_days >= $days_left || $refresh_days === 0) {
				$result = $mod->renewCert();
				$state = ($result['error'] == 0);
			} else {
				$state = true;
			}
		}
		return $state;
	}

	/**
	 * Help command renderer.
	 *
	 * @param string $cmd command
	 */
	public function renderHelpCommand($cmd)
	{
		$this->showHelpCommand(
			$this->action,
			$this->methods,
			$this->parameters,
			$this->optional,
			$this->description,
			$cmd
		);
	}
}
