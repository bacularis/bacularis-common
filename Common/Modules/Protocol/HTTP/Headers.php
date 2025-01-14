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

namespace Bacularis\Common\Modules\Protocol\HTTP;

use Bacularis\Common\Modules\CommonModule;

/**
 * HTTP header module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Headers extends CommonModule
{
	/**
	 * Parse headers.
	 * Note, returned header names are lower case.
	 *
	 * @param mixed $headers HTTP header list
	 */
	public static function parseAll($headers): array
	{
		$result = [];
		$heads = [];
		if (is_string($headers)) {
			$heads = explode("\r\n", $headers);
		} elseif (is_array($headers)) {
			$heads = $headers;
		}
		for ($i = 0; $i < count($heads); $i++) {
			$h = self::parse($heads[$i]);
			$result = array_merge($result, $h);
		}
		return $result;
	}

	/**
	 * Parse single header.
	 *
	 * @param string $head header
	 * @result array parsed header in [name => value] form.
	 */
	public static function parse(string $head): array
	{
		$result = [];
		if (preg_match('/^(?P<name>[^:]+):(?P<value>[\S\s]+)$/', $head, $match) === 1) {
			$name = strtolower($match['name']);
			$result[$name] = trim($match['value']);
		}
		return $result;
	}
}
