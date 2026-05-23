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
 * Bacularis HTTP worker class.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class HTTPBWorker extends BWorker implements IBHTTPWorker
{
	/**
	 * HTTP connection handler.
	 */
	private $handler;

	/**
	 * Run HTTP Bacularis worker.
	 *
	 * @param IBWorkerTask $task task object
	 * @return mixed worker identifier
	 */
	public function run(IBWorkerTask $task)
	{
		$http_whandler = $task->run();

		curl_multi_add_handle($this->handler, $http_whandler);

		$worker_id = (int) $http_whandler;
		return $worker_id;
	}

	/**
	 * Set HTTP connection handler to send request.
	 *
	 * @param mixed $handler HTTP request handler resource
	 */
	public function setHTTPHandler($handler): void
	{
		$this->handler = $handler;
	}

	/**
	 * Get HTTP handler to send request.
	 *
	 * @return mixed HTTP connection handler
	 */
	public function getHTTPHandler()
	{
		return $this->handler;
	}
}
