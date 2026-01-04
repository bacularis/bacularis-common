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

namespace Bacularis\Common\Modules\Shell;

use Prado\Prado;
use Prado\IO\TOutputWriter;
use Prado\TApplication;
use Prado\Shell\TShellWriter;
use Bacularis\Common\Modules\BacularisCommonPluginBase;

/**
 * Generic Bacularis shell application module.
 * The Bacularis shell extends the PRADO application shell module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
abstract class BShellApplication extends TApplication
{
	/**
	 * Stores shell output writer object
	 */
	protected $output_writer;

	/**
	 * Stores command arguments.
	 */
	protected $arguments = [];

	/**
	 * Stores command options.
	 */
	protected $options = [];

	/**
	 * Stores command actions.
	 */
	protected $actions = [];

	/**
	 * Run application.
	 * @param array $args command line arguments
	 */
	public function run($args = null)
	{
		// shift the script name from arguments
		array_shift($args);

		// set arguments
		$this->arguments = $args;

		// initialize output writer
		$this->initOutputWriter();

		// initialize actions
		$this->initActions();

		// attach prepare arguments method
		//$this->attachEventHandler('onInitComplete', [$this, 'processArguments'], 20);

		// run application
		parent::run($args);
	}

	/**
	 * Main application method to start the service.
	 */
	public function runService()
	{
		$this->parseActions($this->arguments);
	}

	/**
	 * Action parser and executor.
	 *
	 * @param array $args command arguments
	 * @return bool if action finished successfully, otherwise false
	 */
	public function parseActions(array $args): bool
	{
		$success = false;
		foreach ($this->actions as $cls => $action) {
			if (($method = $action->isValidAction($args)) !== null) {
				$action->setWriter($this->output_writer);
				$this->parseActionParams($action);
				$method = 'action' . $method;
				if (method_exists($action, $method)) {
					$success = $action->{$method}();
					if (!$success) {
						$this->output_writer->write("Action '{$method}' finished with error.\n");
					}
					break;
				} else {
					$this->output_writer->write("Action '{$method}' is not valid action.\n");
				}
			}
		}
		if (!$success) {
			//$this->printHelp();
			$this->onEndRequest();
			exit(1);
		}
		return $success;
	}

	/**
	 * Action parameter parser.
	 * It sets the parameters for the corresponding action module.
	 *
	 * @param string $action action name
	 */
	protected function parseActionParams($action)
	{
		$params = BacularisCommonPluginBase::parseCommandParameters($this->arguments);
		foreach ($params as $param => $value) {
			$action->params[$param] = $value ?? '';
		}
	}

	/**
	 * Initialize output writer object.
	 */
	private function initOutputWriter(): void
	{
		$output_writer = new TOutputWriter();
		$this->output_writer = new TShellWriter($output_writer);
	}

	/**
	 * Register new action.
	 *
	 * @param string $cls action module name
	 */
	public function addAction($cls): void
	{
		$this->actions[$cls] = Prado::createComponent($cls);
	}

	/**
	 * Get all registered shell actions.
	 *
	 * @return array action list
	 */
	public function getShellActions()
	{
		return $this->actions;
	}

	/**
	 * Print help message.
	 */
	public function printHelp()
	{
		$this->printGreeting();
		$this->output_writer->writeLine("plugin type[/action] <parameter> [optional]", [TShellWriter::BLUE, TShellWriter::BOLD]);
		$this->output_writer->writeLine();
		$this->output_writer->writeLine("example: plugin command/list");
		$this->output_writer->writeLine("example: plugin help");
		$this->output_writer->writeLine();
		$this->output_writer->writeLine("There are available the following action types:");
		foreach ($this->actions as $action) {
			$action->setWriter($this->output_writer);
			$this->output_writer->writeLine($action->renderHelp());
		}
		$this->output_writer->writeLine("To see single action help, please run:");
		$this->output_writer->writeLine();
		$this->output_writer->writeLine("  plugin help <type-name>/<action-name>");
		$this->output_writer->writeLine();
		$this->output_writer->flush();
	}

	/**
	 * Flushes output to shell.
	 *
	 * @param bool $continue_buffering determines if buffering should be continued
	 */
	public function flushOutput($continue_buffering = true)
	{
		$this->output_writer->flush();
		if (!$continue_buffering) {
			$this->output_writer = null;
		}
	}

	/**
	 * Initialize actions.
	 */
	abstract protected function initActions(): void;
}
