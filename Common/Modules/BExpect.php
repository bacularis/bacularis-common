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

namespace Bacularis\Common\Modules;

use Bacularis\Common\Modules\Logging;

/**
 * BExpect, simple expect module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class BExpect extends CommonModule
{
	/**
	 * Default timeout in seconds for particular bexpect command actions.
	 */
	public const DEFAULT_CMD_TIMEOUT = 3;

	/**
	 * Command to execute.
	 */
	private $command;

	/**
	 * proc_open() pipe list
	 */
	private $pipes = [];

	/**
	 * Current process resource.
	 */
	private $proc;

	/**
	 * Action list.
	 */
	private $actions = [];

	/**
	 * Set command to execute.
	 *
	 * @param string $cmd command
	 */
	public function setCommand($cmd)
	{
		$this->command = $cmd;
	}

	/**
	 * Add expected action to do on given output pattern.
	 *
	 * @param string $expected regex pattern to find requested output
	 * @param string $action action to do on requested output (string sent to stdin)
	 * @param int $timeout timeout in seconds
	 */
	public function addAction($expected, $action = null, $timeout = null)
	{
		$this->actions[] = [
			'expected' => $expected,
			'action' => $action,
			'timeout' => ($timeout ?: self::DEFAULT_CMD_TIMEOUT)
		];
	}

	/**
	 * Run command in BExpect.
	 * Main method to start "expecting".
	 *
	 * @param array $env environment variables to set
	 * @return array command output
	 */
	public function exec($env = [])
	{
		if (!$this->command) {
			return;
		}

		$output = [];
		$this->runCommand($env);
		$output = $this->doActions();
		$this->finishCommand();
		return $output;
	}

	/**
	 * Do single action.
	 * It is for sending string defined in action on stdin.
	 *
	 * @return array action command output
	 */
	private function doActions()
	{
		$start_time = time();
		$output = [];

		stream_set_blocking($this->pipes[0], false);
		stream_set_blocking($this->pipes[1], false);
		stream_set_blocking($this->pipes[2], false);

		if (count($this->actions) === 0) {
			return $output;
		}
		$action = array_shift($this->actions);

		$stdstr = '';
		while (true) {
			if ($action['timeout'] <= (time() - $start_time)) {
				// timeout occured, break
				Logging::log(
					Logging::CATEGORY_EXECUTE,
					'Process running in BExpect timed out.'
				);
				break;
			}
			if (feof($this->pipes[1])) {
				// EOF, break
				Logging::log(
					Logging::CATEGORY_EXECUTE,
					'Process running in BExpect unexpectedly finished.'
				);
				break;
			}
			if (preg_match('/' . $action['expected'] . '/', $stdstr) === 1) {
				// Output found, do action
				$this->writeToStdIn($action['action']);
				if (count($this->actions) > 0) {
					$action = array_shift($this->actions);
				}
			}
			$stderr = $this->readFromStdErr();
			$stdout = $this->readFromStdOut();
			$stdstr = $stderr . $stdout;
			if (!empty($stdstr)) {
				$output[] = $stdstr;
			}
			usleep(100000);
		}
		return $output;
	}

	/**
	 * Read from standard output (stdout).
	 *
	 * @return string value read from stdout
	 */
	private function readFromStdOut()
	{
		$output = stream_get_contents($this->pipes[1]);
		return rtrim($output);
	}

	/**
	 * Read from standard error (stderr).
	 *
	 * @return string value read from stderr
	 */
	private function readFromStdErr()
	{
		$output = stream_get_contents($this->pipes[2]);
		return rtrim($output);
	}

	/**
	 * Write to standard input (stdin).
	 *
	 * @param string $str value written to stdin
	 */
	private function writeToStdIn($str)
	{
		if (!empty($str)) {
			fwrite($this->pipes[0], "$str\n");
		}
	}

	/**
	 * Prepare and run command in BExpect.
	 *
	 * @param array $env environment variables to set
	 */
	private function runCommand($env = [])
	{
		$descriptor_spec = [
			['pipe', 'r'],
			['pipe', 'w'],
			['pipe', 'w']
		];
		$this->proc = proc_open(
			$this->command,
			$descriptor_spec,
			$this->pipes,
			null,
			array_merge(['LANG' => 'C'], $env)
		);
		if (!is_resource($this->proc)) {
			Logging::log(
				Logging::CATEGORY_EXECUTE,
				'Cannot create spawn process'
			);
		}
	}

	/**
	 * Finish and cleanup.
	 */
	private function finishCommand()
	{
		fclose($this->pipes[0]);
		fclose($this->pipes[1]);
		fclose($this->pipes[2]);
		proc_close($this->proc);
		$this->actions = [];
		$this->command = null;
	}
}
