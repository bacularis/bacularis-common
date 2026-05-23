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

namespace Bacularis\Common\Modules;

/**
 * Interface for the Bacularis worker task.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
interface IBWorkerTask
{
	/**
	 * Set maximum task retry number.
	 *
	 * @param int $retries maximum task retry number
	 */
	public function setRetries(int $retries): void;

	/**
	 * Get maximum task retry number.
	 *
	 * @return int maximum task retry number
	 */
	public function getRetries(): int;

	/**
	 * Set wait time in queue to run the task.
	 *
	 * @param int $timestamp timestamp to run task
	 */
	public function setRetryAt(int $timestamp): void;

	/**
	 * Get wait time to run the task.
	 *
	 * @return int timestamp to run task
	 */
	public function getRetryAt(): int;

	/**
	 * Get maximum number of task retries.
	 *
	 * @return int maximum task retries
	 */
	public function getMaxRetries(): int;

	/**
	 * Check if maximum numer of retries for task has been reached.
	 *
	 * @return bool true if task retry limit is reached, false otherwise
	 */
	public function isMaxRetriesReached(): bool;

	/**
	 * Set task destination.
	 * Destination is a place where task result data will be setInstanceMetaData
	 *
	 * @param array $dest destination with type (index 0) and handler (index 1)
	 */
	public function setDestination(array $dest): void;

	/**
	 * Get task destination.
	 *
	 * @return mixed task destination
	 */
	public function getDestination();

	/**
	 * Get task data.
	 *
	 * @return array task data
	 */
	public function getData(): array;

	/**
	 * Set task data.
	 *
	 * @param array $data task data
	 */
	public function setData(array $data): void;

	/**
	 * Check if worker task is finished.
	 *
	 * @return bool true if is finished, otherwise false
	 */
	public function isFinished(): bool;

	/**
	 * Run task.
	 */
	public function run();

	/**
	 * Prepare task result.
	 *
	 * @param mixed $data worker task result
	 */
	public function prepareResult($data);
}
