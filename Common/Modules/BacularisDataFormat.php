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
 * Bacularis data format module.
 * It provides tools that prepare data to store in Bacularis Data Format (BADF).
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class BacularisDataFormat
{
	/**
	 * Magic string that starts each data block.
	 */
	private const MAGIC = 'BADF';

	/**
	 * Current data format version.
	 */
	private const VERSION = 1;

	/**
	 * All supported data format versions.
	 */
	private const VERSION_1 = 1;

	/**
	 * Encode block metadata and data.
	 * After encoding the string is ready to send to storage daemon.
	 *
	 * @param array $metadata block meta-data
	 * @param string $data block data
	 * @return string encoded block
	 */
	public static function encode(array $metadata, string $data): string
	{
		$version = chr(self::VERSION);
		$header = json_encode($metadata);
		$header_len = pack('N', strlen($header));
		$data_len = pack('N', strlen($data));

		return self::MAGIC
			. $version
			. $header_len
			. $header
			. $data_len
			. $data;
	}

	/**
	 * Read block from given data stream.
	 *
	 * @param mixed $stream data stream handler
	 * @return array two element array where 0 - decoded block header, 1 - block data
	 */
	public static function readBlockFromStream($stream): array
	{
		$data = [null, null];
		// Read magic
		$magic = DataStream::readExact($stream, 4);
		if ($magic === null || strlen($magic) !== 4) {
			$emsg = 'End of data stream, zero bytes read or time out.';
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				$emsg
			);
			return $data;
		}
		if ($magic !== self::MAGIC) {
			$out = var_export($magic, true);
			$emsg = "Incorect data format magic or data stream at wrong possition. Magic: '{$out}'";
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				$emsg
			);
			return $data;
		}

		// Read format version
		$verno = DataStream::readExact($stream, 1);

		// Read parsed block data and metadata
		$version = ord($verno);
		switch ($version) {
			case self::VERSION_1: {
				$data = self::parseDataV1($stream);
				break;
			}
			default: {
				$emsg = "Invalid data version number '{$version}'.";
				Logging::log(
					Logging::CATEGORY_APPLICATION,
					$emsg
				);
				break;
			}
		}
		return $data;
	}

	/**
	 * Parser for data format version 1.
	 *
	 * @param mixed $stream data stream handler
	 * @return array parsed block data and metadata
	 */
	private static function parseDataV1($stream): array
	{
		$result = [null, null];

		// Header length
		$hlen = DataStream::readExact($stream, 4);
		$header_un = unpack('N', $hlen);
		$header_len = 0;
		if (is_array($header_un)) {
			$header_len = $header_un[1];
		} else {
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				"Unable to unpack header length from data: '{$hlen}'."
			);
			return $result; // END
		}

		// Header
		$hdr = DataStream::readExact($stream, $header_len);
		$header = json_decode($hdr, true);
		if ($hdr === null || strlen($hdr) !== $header_len || is_null($header)) {
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				"Unable to decode BADF header: '{$hdr}'."
			);
			return $result; // END
		}

		// Data length
		$dlen = DataStream::readExact($stream, 4);
		$dlen_un = unpack('N', $dlen);
		$data_len = 0;
		if (is_array($dlen_un)) {
			$data_len = $dlen_un[1];
		} else {
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				"Unable to unpack data length from data: '{$dlen}'."
			);
			return $result; // END
		}

		// Data
		$data = DataStream::readExact($stream, $data_len);
		if (is_null($data) || strlen($data) !== $data_len) {
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				"Data stream reading ended with error."
			);
			return $result; // END
		}

		return [$header, $data];
	}
}
