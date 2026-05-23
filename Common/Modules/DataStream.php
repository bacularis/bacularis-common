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
 * Data stream module.
 * It provides tools to work with data streams.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class DataStream
{
	/**
	 * Read given data length from stream.
	 *
	 * @param resource $stream stread resource
	 * @param int $len data length to read
	 * @return null|string data from stream or null if an error happened
	 */
	public static function readExact($stream, int $len): ?string
	{
		$data = '';
		$retries = 0;
		while (strlen($data) < $len) {
			$chunk = fread($stream, $len - strlen($data));
			if ($chunk === false) {
				// Stream ended early - error
				$data = null;
				break;
			} elseif ($chunk === '') {
				// EOF, no data or timeout
				if (feof($stream)) {
					$data = null;
					break;
				}
				if (++$retries > 1000) {
					$data = null;
					break;
				}
				usleep(10000);
				continue;
			}
			$data .= $chunk;
		}
		return $data;
	}
}
