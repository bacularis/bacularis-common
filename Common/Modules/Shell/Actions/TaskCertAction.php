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
	protected $parameters = [['type']];
	protected $optional = [['days']];
	protected $description = [
		'Task commands',
		'Renew web server SSL certificate 10 days before expiry time.'
	];
	public $params = [];

	/**
	 * Renew SSL certificate action.
	 *
	 * @return bool true it is always valid
	 */
	public function actionRenew()
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
		$this->output_writer->write("\nUsage: ");
		$this->output_writer->writeLine("task {$this->action}/<action>", [TShellWriter::BLUE, TShellWriter::BOLD]);
		$this->output_writer->writeLine("\nexample: task {$this->action}/{$this->methods[0]}\n");
		$this->output_writer->writeLine("The following actions are available:");
		$this->output_writer->writeLine();
		foreach ($this->methods as $i => $method) {
			$params = [];
			if ($this->parameters[$i]) {
				$parameters = is_array($this->parameters[$i]) ? $this->parameters[$i] : [$this->parameters[$i]];
				foreach ($parameters as $v) {
					$params[] = '<' . $v . '>';
				}
			}
			$parameters = implode(' ', $params);
			$options = [];
			if ($this->optional[$i]) {
				$optional = is_array($this->optional[$i]) ? $this->optional[$i] : [$this->optional[$i]];
				foreach ($optional as $v) {
					$options[] = '[' . $v . ']';
				}
			}
			$optional = (strlen($parameters) ? ' ' : '') . implode(' ', $options);

			$description = $this->getWriter()->wrapText($this->description[$i + 1], 10);
			$parameters = $this->getWriter()->format($parameters, [TShellWriter::BLUE, TShellWriter::BOLD]);
			$optional = $this->getWriter()->format($optional, [TShellWriter::BLUE]);
			$description = $this->getWriter()->format($description, TShellWriter::DARK_GRAY);

			$this->output_writer->write('  ');
			$this->output_writer->writeLine($this->action . '/' . $method . ' ' . $parameters . $optional, [TShellWriter::BLUE, TShellWriter::BOLD]);
			$this->output_writer->writeLine('         ' . $description);
			$this->output_writer->writeLine();
		}
	}
}
