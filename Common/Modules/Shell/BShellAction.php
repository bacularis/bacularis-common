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

use Prado\Shell\TShellAction;
use Prado\Shell\TShellWriter;

/**
 * Bacularis shell action module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class BShellAction extends TShellAction
{
	/**
	 * Stores shell writer object.
	 */
	protected $output_writer;

	/**
	 * Get shell writer object.
	 *
	 * @return TShellWriter output writer object
	 */
	public function getWriter(): TShellWriter
	{
		return $this->output_writer;
	}

	/**
	 * Set shell writer object.
	 *
	 * @param TShellWriter $writer output writer object
	 */
	public function setWriter(TShellWriter $writer)
	{
		$this->output_writer = $writer;
	}

	/**
	 * Get module.
	 *
	 * @param string $id module identifier.
	 * @return object module instance
	 */
	protected function getModule($id)
	{
		return $this->getApplication()->getModule($id);
	}

	/**
	 * Show help command.
	 *
	 * @param string $action action name
	 * @param array $methods action methods
	 * @param array $parameters required method parameters
	 * @param array $optional optional parameters
	 * @param array $description parameter descriptiona
	 * @param string $cmd typed command
	 */
	protected function showHelpCommand(string $action, array $methods, array $parameters, array $optional, array $description, $cmd = ''): void
	{
		// Script name
		$script = basename($_SERVER['argv'][0] ?? 'script');

		// Prepare help header
		$this->output_writer->write("\nUsage: ");
		$this->output_writer->writeLine("$script {$action}/<action>", [TShellWriter::BLUE, TShellWriter::BOLD]);
		$this->output_writer->writeLine("\nexample: $script {$action}/{$methods[0]}\n");
		$this->output_writer->writeLine("The following actions are available:");
		$this->output_writer->writeLine();

		foreach ($methods as $i => $method) {
			$action_method = $action . '/' . $method;
			if (strpos($cmd, '/') !== false && $cmd != $action_method) {
				// command provided, skip all not matching commands
				continue;
			}
			// Required parameters
			$req = [];
			if ($parameters[$i]) {
				$params_req = is_array($parameters[$i]) ? $parameters[$i] : [$parameters[$i]];
				foreach ($params_req as $k => $v) {
					$req[] = '--' . $k . ($v ? '=' . $v : '');
				}
			}
			$required_str = implode(' ', $req);

			// Optional parameters
			$opt = [];
			if ($optional[$i]) {
				$params_opt = is_array($optional[$i]) ? $optional[$i] : [$optional[$i]];
				foreach ($params_opt as $k => $v) {
					$opt[] = '[' . '--' . $k . ($v ? '=' . $v : '') . ']';
				}
			}
			$optional_str = (strlen($required_str) ? ' ' : '') . implode(' ', $opt);

			// Parameter description
			$description_str = $this->getWriter()->wrapText($description[$i + 1], 10);

			// Prepare output
			$params_required = $this->getWriter()->format(
				$required_str,
				[TShellWriter::BLUE, TShellWriter::BOLD]
			);
			$params_optional = $this->getWriter()->format(
				$optional_str,
				[TShellWriter::BLUE]
			);
			$params_description = $this->getWriter()->format(
				$description_str,
				TShellWriter::DARK_GRAY
			);

			// Print help message
			$this->output_writer->write('  ');
			$this->output_writer->writeLine(
				$action_method . ' ' . $params_required . $params_optional,
				[TShellWriter::BLUE, TShellWriter::BOLD]
			);
			$this->output_writer->writeLine('         ' . $params_description);
			$this->output_writer->writeLine();
		}
	}
}
