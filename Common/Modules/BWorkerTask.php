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
 * Worker task class.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
abstract class BWorkerTask implements IBWorkerTask
{
	/**
	 * Worker task ID prefix.
	 */
	private const WORKER_TASK_PREFIX = 'TASK-';

	/**
	 * Maximum number of re-tries for failed tasks.
	 */
	private const MAX_TASK_RETRIES = 8;

	/**
	 * Worker task identifier.
	 */
	private $id = '';

	/**
	 * Number of task retries.
	 */
	private $retries = 0;

	/**
	 * Maximum time to retry the task.
	 * After this time, task is expired.
	 */
	private $retry_at;

	/**
	 * Task destination.
	 * It determines where to send the result task data
	 */
	private $destination;

	/**
	 * Task data.
	 */
	private $data = [];

	/**
	 * Decides if task is finished.
	 */
	private $finished = false;

	public function __construct($params)
	{
		$this->setID();
		$this->setData($params);
	}

	/**
	 * Get task object identifier.
	 *
	 * @return string task object identifier
	 */
	public function getID(): string
	{
		return $this->id;
	}

	/**
	 * Set task object identifier.
	 */
	public function setID(): void
	{
		$this->id = self::WORKER_TASK_PREFIX . spl_object_id($this);
	}

	public function __toString()
	{
		return $this->getID();
	}

	/**
	 * Set maximum task retry number.
	 *
	 * @param int $retries maximum task retry number
	 */
	public function setRetries(int $retries): void
	{
		$this->retries = $retries;
	}

	/**
	 * Get maximum task retry number.
	 *
	 * @return int maximum task retry number
	 */
	public function getRetries(): int
	{
		return $this->retries;
	}

	/**
	 * Set wait time in queue to run the task.
	 *
	 * @param int $timestamp timestamp to run task
	 */
	public function setRetryAt(int $timestamp): void
	{
		$this->retry_at = $timestamp;
	}

	/**
	 * Get wait time to run the task.
	 *
	 * @return int timestamp to run task
	 */
	public function getRetryAt(): int
	{
		return $this->retry_at;
	}

	/**
	 * Get maximum number of task retries.
	 *
	 * @return int maximum task retries
	 */
	public function getMaxRetries(): int
	{
		return self::MAX_TASK_RETRIES;
	}

	/**
	 * Check if maximum numer of retries for task has been reached.
	 *
	 * @return bool true if task retry limit is reached, false otherwise
	 */
	public function isMaxRetriesReached(): bool
	{
		return ($this->getRetries() >= $this->getMaxRetries());
	}

	/**
	 * Set task destination.
	 * Destination is a place where task result data will be setInstanceMetaData
	 *
	 * @param array $dest destination with type (index 0) and handler (index 1)
	 */
	public function setDestination(array $dest): void
	{
		[, $handler] = $dest;
		$this->destination = $handler;
	}

	/**
	 * Get task destination.
	 *
	 * @return mixed task destination
	 */
	public function getDestination()
	{
		return $this->destination;
	}

	/**
	 * Get task data.
	 *
	 * @return array task data
	 */
	public function getData(): array
	{
		return $this->data;
	}

	/**
	 * Set task data.
	 *
	 * @param array $data task data
	 */
	public function setData(array $data): void
	{
		$this->data = $data;
	}

	/**
	 * Check if worker task is finished.
	 *
	 * @return bool true if is finished, otherwise false
	 */
	public function isFinished(): bool
	{
		return ($this->finished === true);
	}

	/**
	 * Run task.
	 */
	abstract public function run();

	public function finish($data)
	{
		$this->prepareResult($data);
		$this->finished = true;
	}

	/**
	 * Prepare task result.
	 *
	 * @param mixed $data worker task result
	 */
	abstract public function prepareResult($data);
}
