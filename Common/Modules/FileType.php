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
 * File type detection module.
 *
 * Native PHP Fileinfo is preferred. The external "file" command is used
 * as a fallback and when sudo execution is requested.
 */
class FileType extends ShellCommandModule
{
	/**
	 * Generic MIME types for unrecognized binary data.
	 */
	private const GENERIC_MIME_TYPES = [
		'application/octet-stream',
		'application/x-binary',
		'binary/octet-stream'
	];

	/**
	 * File binary.
	 */
	public const FILE_BINARY = 'file';

	/**
	 * Bacularis-maintained file type aliases mapped to MIME types.
	 */
	public const TYPES = [
		'7z' => ['application/x-7z-compressed'],
		'avi' => ['video/x-msvideo'],
		'bz2' => ['application/x-bzip2'],
		'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
		'gif' => ['image/gif'],
		'gz' => ['application/gzip', 'application/x-gzip'],
		'jpeg' => ['image/jpeg'],
		'jpg' => ['image/jpeg'],
		'mkv' => ['video/x-matroska'],
		'mov' => ['video/quicktime'],
		'mp3' => ['audio/mpeg'],
		'mp4' => ['video/mp4'],
		'odg' => ['application/vnd.oasis.opendocument.graphics'],
		'odp' => ['application/vnd.oasis.opendocument.presentation'],
		'ods' => ['application/vnd.oasis.opendocument.spreadsheet'],
		'odt' => ['application/vnd.oasis.opendocument.text'],
		'pdf' => ['application/pdf'],
		'png' => ['image/png'],
		'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
		'rar' => ['application/vnd.rar', 'application/x-rar', 'application/x-rar-compressed'],
		'tar' => ['application/x-tar'],
		'tgz' => ['application/gzip', 'application/x-gzip'],
		'tiff' => ['image/tiff'],
		'wav' => ['audio/x-wav', 'audio/wav'],
		'webm' => ['video/webm'],
		'webp' => ['image/webp'],
		'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
		'xz' => ['application/x-xz'],
		'zip' => ['application/zip', 'application/x-zip', 'application/x-zip-compressed'],
		'zst' => ['application/zstd', 'application/x-zstd']
	];

	/**
	 * File info object instance.
	 */
	private static $file_info;

	/**
	 * Get file MIME type.
	 *
	 * @param string $path file path
	 * @param array $cmd_params command parameters
	 */
	public static function getMimeType(string $path, array $cmd_params = []): ?string
	{
		$use_sudo = $cmd_params['use_sudo'] ?? false;
		$force_external = $cmd_params['force_external'] ?? false;

		$mime = null;
		if (!$use_sudo && !$force_external) {
			// main PHP method
			$mime = self::getMimeTypeNative($path);
		}
		if ($mime === null) {
			// fallback method
			$mime = self::getMimeTypeExternal($path, $cmd_params);
		}
		return $mime;
	}

	/**
	 * Check if file is given type.
	 *
	 * @param string $path file path
	 * @param string $type file type (ex. mp4 or jpg)
	 * @param array $cmd_params command parameters
	 * @return bool true if file is given type, otherwise false
	 */
	public static function isType(string $path, string $type, array $cmd_params = []): bool
	{
		$mime = self::getMimeType($path, $cmd_params);
		return $mime !== null && self::matchesMimeType($mime, $type);
	}

	/**
	 * Check if given MIME type matches to type.
	 *
	 * @param string $mime MIME type
	 * @param string $type file type
	 * @return bool true if MIME type matches, otherwise false
	 */
	public static function matchesMimeType(string $mime, string $type): bool
	{
		$type = self::normalizeType($type);
		$mime = strtolower(trim($mime));

		return isset(self::TYPES[$type]) && in_array($mime, self::TYPES[$type], true);
	}

	/**
	 * Check if given MIME type matches to at least one of types.
	 *
	 * @param string $mime MIME type
	 * @param array $types file types to check
	 * @return bool true if MIME type matches to at least one of types, otherwise false
	 */
	public static function matchesAnyMimeType(string $mime, array $types): bool
	{
		foreach ($types as $type) {
			if (self::matchesMimeType($mime, (string) $type)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if MIME type represents generic unrecognized binary data.
	 *
	 * @param null|string $mime MIME type
	 * @return bool true if MIME type is generic, otherwise false
	 */
	public static function isGenericMimeType(?string $mime): bool
	{
		if ($mime === null) {
			return false;
		}

		$mime = strtolower(trim($mime));
		return in_array($mime, self::GENERIC_MIME_TYPES, true);
	}

	/**
	 * Check if file type is supported.
	 *
	 * @param string $type file type
	 * @return bool true if type is supported, otherwise false
	 */
	public static function isSupportedType(string $type): bool
	{
		return isset(self::TYPES[self::normalizeType($type)]);
	}

	/**
	 * Get MIME type using native PHP method.
	 *
	 * @param string $path file path
	 * @return null|string MIME type or null on error
	 */
	private static function getMimeTypeNative(string $path): ?string
	{
		if (!class_exists('\\finfo')) {
			return null;
		}

		if (self::$file_info === null) {
			self::$file_info = new \finfo(FILEINFO_MIME_TYPE);
		}

		$mime = @self::$file_info->file($path);
		if (!is_string($mime) || $mime === '') {
			return null;
		}

		return strtolower(trim($mime));
	}

	/**
	 * Get MIME type using external file command.
	 *
	 * @param string $path file path
	 * @param array $cmd_params command parameters
	 * @return null|string MIME type or null on error
	 */
	private static function getMimeTypeExternal(string $path, array $cmd_params): ?string
	{
		$cmd = [
			self::FILE_BINARY,
			'--brief',
			'--mime-type',
			'--',
			escapeshellarg($path)
		];

		$result = static::execCommand($cmd, $cmd_params);
		if (($result['exitcode'] ?? 1) !== 0) {
			return null;
		}

		$output = $result['output'] ?? [];
		$mime = is_array($output) ? static::getOutput($output) : trim((string) $output);

		return $mime === '' ? null : strtolower(trim($mime));
	}

	/**
	 * Normalize file type.
	 *
	 * @param string $type file type
	 * @return string normalized file type
	 */
	private static function normalizeType(string $type): string
	{
		return strtolower(ltrim(trim($type), '.'));
	}
}
