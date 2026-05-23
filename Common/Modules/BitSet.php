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
 * Bitset module.
 * It stores simple integer indexes in bits.
 *
 * Indexes are stored in form:
 *
 *   [ 0][ 1][ 2][ 3][ 4][ 5][ 6][ 7] -> Byte 0
 *   [ 8][ 9][10][11][12][13][14][15] -> Byte 1
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class BitSet
{
	private string $data = '';

	public function __construct($data = null)
	{
		if ($data) {
			$this->data = $data;
		}
	}

	/**
	 * Set bit in bitset
	 *
	 * @param int $index index number
	 */
	public function set(int $index): void
	{
		// Make sure the size and eventually resize
		$this->ensureSize($index);

		// Get byte and bit for index
		$byte = intdiv($index, 8);
		$bit = $index % 8;

		// Set bit for index
		$this->data[$byte] = $this->data[$byte] | chr(1 << $bit);
	}

	/**
	 * Check if bit is set.
	 *
	 * @param int $index index number
	 * @return true if bit is set, false otherwise
	 */
	public function get(int $index): bool
	{
		// Get byte for index
		$byte = intdiv($index, 8);

		// Byte is out of rage not exists in bitset
		if ($byte >= strlen($this->data)) {
			return false;
		}

		// Get bit from index
		$bit = $index % 8;

		$result = ((ord($this->data[$byte]) >> $bit) & 1);
		$is_set = ($result === 1);
		return $is_set;
	}

	/**
	 * Count all set bit.
	 *
	 * @return int count number of indexes set in bits
	 */
	public function count(): int
	{
		// Lookup table
		static $lut = null;

		if ($lut === null) {
			$lut = [];
			for ($i = 0; $i < 256; $i++) {
				$lut[$i] = substr_count(decbin($i), '1');
			}
		}

		// Count total number of set bits
		$count = 0;
		$len = strlen($this->data);
		for ($i = 0; $i < $len; $i++) {
			$count += $lut[ord($this->data[$i])];
		}
		return $count;
	}

	/**
	 * Reserve bitset size.
	 *
	 * @param int $max_index reserve bitset to given index number
	 */
	public function reserve(int $max_index): void
	{
		$needed = intdiv($max_index, 8) + 1;

		if ($needed > strlen($this->data)) {
			$this->data = str_pad($this->data, $needed, "\0");
		}
	}

	/**
	 * Get current size of bitset.
	 *
	 * @return int size of bitset
	 */
	public function size(): int
	{
		return strlen($this->data);
	}

	/**
	 * Reset bitset data
	 */
	public function clear(): void
	{
		$this->data = '';
	}

	/**
	 * Get bitset data.
	 *
	 * @return string raw bitset bits
	 */
	public function getData(): string
	{
		return $this->data;
	}

	/**
	 * Expand size of bitset.
	 * This can be used dynamically.
	 *
	 * @param int $index index number
	 */
	private function ensureSize(int $index): void
	{
		$needed = intdiv($index, 8) + 1;
		$current = strlen($this->data);

		if ($needed > $current) {
			$this->data .= str_repeat("\0", $needed - $current);
		}
	}
}
