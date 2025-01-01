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

namespace Bacularis\Common\Modules\Shell;

use Prado\Shell\TShellWriter;
use Bacularis\Common\Modules\Params;

/**
 * Bacularis shell plugin module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class BShellPluginApplication extends BShellApplication
{
	/**
	 * Stores info if greetings were printed.
	 */
	private $greetings = false;

	/**
	 * Initialize plugin actions.
	 */
	protected function initActions(): void
	{
		$this->addAction('Bacularis\\Common\\Modules\\Shell\\Actions\\PluginCommandAction');
		$this->addAction('Prado\\Shell\\Actions\\THelpAction');
	}

	/**
	 * Run the plugin shell application.
	 *
	 * @param null|array $args command line parameters
	 */
	public function run($args = null)
	{
		set_exception_handler([static::class, 'handleError']);
		parent::run($args);
	}

	/**
	 * Error handler method.
	 * It is required to print propser traces in the plugin command output
	 * if any error happen.
	 * NOTE: This method ends with exit();
	 *
	 * @param object $exception exception object
	 */
	public static function handleError($exception)
	{
		$msg = $exception->getMessage();
		$trace = $exception->getTraceAsString();
		fwrite(STDERR, $msg);
		fwrite(STDERR, $trace);
		exit(1);
	}

	/**
	 * Print greetings message.
	 */
	public function printGreeting(): void
	{
		if (!$this->greetings) {
			$msg = 'Bacularis command line tool for plugins v' . Params::BACULARIS_VERSION . '.';
			$this->output_writer->write($msg, TShellWriter::DARK_GRAY);
			$this->output_writer->writeLine();
			$this->output_writer->writeLine();
			$this->greetings = true;
		}
	}
}
