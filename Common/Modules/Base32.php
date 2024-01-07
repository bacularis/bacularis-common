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

/**
 * Base32 encoding/decoding module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Base32 extends CommonModule
{
	/**
	 * Base32 dictionary.
	 */
	private static $dictionary = [
		'A' => 0,
		'B' => 1,
		'C' => 2,
		'D' => 3,
		'E' => 4,
		'F' => 5,
		'G' => 6,
		'H' => 7,
		'I' => 8,
		'J' => 9,
		'K' => 10,
		'L' => 11,
		'M' => 12,
		'N' => 13,
		'O' => 14,
		'P' => 15,
		'Q' => 16,
		'R' => 17,
		'S' => 18,
		'T' => 19,
		'U' => 20,
		'V' => 21,
		'W' => 22,
		'X' => 23,
		'Y' => 24,
		'Z' => 25,
		'2' => 26,
		'3' => 27,
		'4' => 28,
		'5' => 29,
		'6' => 30,
		'7' => 31
	];

	/**
	 * Decode base32 string into binary string.
	 *
	 * @param string $base32 base32 string
	 * @return string binary string or empty string on validation error
	 */
	public function decode($base32)
	{
		$ret = '';
		$b32 = strtoupper($base32);
		$l = strlen($b32);
		$reg = $buf = 0;
		for ($i = 0; $i < $l; $i++) {
			if (!key_exists($b32[$i], self::$dictionary)) {
				$ret = '';
				break;
			}
			$buf = $buf << 5;
			$buf = $buf + self::$dictionary[$b32[$i]];
			$reg = $reg + 5;
			if ($reg >= 8) {
				$reg = $reg - 8;
				$ret .= chr(($buf & (0xFF << $reg)) >> $reg);
			}
		}

		return $ret;
	}

	/**
	 * Generate random base32 string with given length.
	 *
	 * @param int $length string length
	 * @return string random base32 string
	 */
	public function generateRandomString($length = 16)
	{
		$ret = '';
		for ($i = 0; $i < $length; $i++) {
			$ret .= array_rand(self::$dictionary);
		}
		return $ret;
	}
}
