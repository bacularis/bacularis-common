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
	 * Binary file content class.
	 */
	private const CONTENT_CLASS_BINARY = 'binary';

	/**
	 * Text file content class.
	 */
	private const CONTENT_CLASS_TEXT = 'text';

	/**
	 * Generic MIME types for binary data.
	 */
	private const GENERIC_BINARY_MIME_TYPES = [
		'application/octet-stream',
		'application/x-binary',
		'binary/octet-stream'
	];

	/**
	 * Text MIME types.
	 */
	private const TEXT_MIME_TYPES = [
		'application/javascript',
		'application/json',
		'application/xml',
		'application/x-httpd-php'
	];

	/**
	 * Empty file MIME types.
	 */
	private const EMPTY_MIME_TYPES = [
		'application/x-empty',
		'inode/x-empty'
	];

	/**
	 * File binary.
	 */
	public const FILE_BINARY = 'file';

	/**
	 * Bacularis-maintained file type definitions.
	 */
	public const TYPES = [
		'7z' => ['mime_types' => ['application/x-7z-compressed'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'avi' => ['mime_types' => ['video/x-msvideo'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'bz2' => ['mime_types' => ['application/x-bzip2'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'css' => ['mime_types' => ['text/css'], 'content_class' => self::CONTENT_CLASS_TEXT],
		'csv' => ['mime_types' => ['text/csv'], 'content_class' => self::CONTENT_CLASS_TEXT],
		'docx' => ['mime_types' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'gif' => ['mime_types' => ['image/gif'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'gz' => ['mime_types' => ['application/gzip', 'application/x-gzip'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'gzip' => ['mime_types' => ['application/gzip', 'application/x-gzip'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'htm' => ['mime_types' => ['text/html'], 'content_class' => self::CONTENT_CLASS_TEXT],
		'html' => ['mime_types' => ['text/html'], 'content_class' => self::CONTENT_CLASS_TEXT],
		'jpeg' => ['mime_types' => ['image/jpeg'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'jpg' => ['mime_types' => ['image/jpeg'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'js' => ['mime_types' => ['application/javascript', 'text/javascript'], 'content_class' => self::CONTENT_CLASS_TEXT],
		'json' => ['mime_types' => ['application/json'], 'content_class' => self::CONTENT_CLASS_TEXT],
		'md' => ['mime_types' => ['text/markdown'], 'content_class' => self::CONTENT_CLASS_TEXT],
		'mkv' => ['mime_types' => ['video/x-matroska'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'mov' => ['mime_types' => ['video/quicktime'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'mp3' => ['mime_types' => ['audio/mpeg'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'mp4' => ['mime_types' => ['video/mp4'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'odg' => ['mime_types' => ['application/vnd.oasis.opendocument.graphics'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'odp' => ['mime_types' => ['application/vnd.oasis.opendocument.presentation'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'ods' => ['mime_types' => ['application/vnd.oasis.opendocument.spreadsheet'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'odt' => ['mime_types' => ['application/vnd.oasis.opendocument.text'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'pdf' => ['mime_types' => ['application/pdf'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'php' => ['mime_types' => ['application/x-httpd-php', 'text/x-php'], 'content_class' => self::CONTENT_CLASS_TEXT],
		'png' => ['mime_types' => ['image/png'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'pptx' => ['mime_types' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'rar' => ['mime_types' => ['application/vnd.rar', 'application/x-rar', 'application/x-rar-compressed'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'svg' => ['mime_types' => ['image/svg+xml'], 'content_class' => self::CONTENT_CLASS_TEXT],
		'tar' => ['mime_types' => ['application/x-tar'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'tgz' => ['mime_types' => ['application/gzip', 'application/x-gzip'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'tif' => ['mime_types' => ['image/tiff'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'tiff' => ['mime_types' => ['image/tiff'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'txt' => ['mime_types' => ['text/plain'], 'content_class' => self::CONTENT_CLASS_TEXT],
		'wav' => ['mime_types' => ['audio/x-wav', 'audio/wav'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'webm' => ['mime_types' => ['video/webm'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'webp' => ['mime_types' => ['image/webp'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'xlsx' => ['mime_types' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'xml' => ['mime_types' => ['application/xml', 'text/xml'], 'content_class' => self::CONTENT_CLASS_TEXT],
		'xz' => ['mime_types' => ['application/x-xz'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'zip' => ['mime_types' => ['application/zip', 'application/x-zip', 'application/x-zip-compressed'], 'content_class' => self::CONTENT_CLASS_BINARY],
		'zst' => ['mime_types' => ['application/zstd', 'application/x-zstd'], 'content_class' => self::CONTENT_CLASS_BINARY]
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

		return isset(self::TYPES[$type]) && in_array($mime, self::TYPES[$type]['mime_types'], true);
	}

	/**
	 * Check if file extension matches given MIME type.
	 *
	 * @param string $path file path
	 * @param string $mime MIME type
	 * @return null|bool true if extension matches MIME type, false if it does not match, null if extension or MIME type cannot be evaluated
	 */
	public static function matchesFileExtension(string $path, string $mime): ?bool
	{
		$extension = pathinfo($path, PATHINFO_EXTENSION);
		$extension = self::normalizeType($extension);
		$mime = strtolower(trim($mime));

		if ($extension === '' || $mime === '' || !key_exists($extension, self::TYPES)) {
			return null;
		}

		if (in_array($mime, self::EMPTY_MIME_TYPES, true)) {
			return null;
		}

		if (self::matchesMimeType($mime, $extension)) {
			return true;
		}

		$type = self::TYPES[$extension];
		$content_class = $type['content_class'] ?? null;
		if ($content_class === self::CONTENT_CLASS_BINARY) {
			return in_array($mime, self::GENERIC_BINARY_MIME_TYPES, true);
		}
		if ($content_class === self::CONTENT_CLASS_TEXT) {
			return self::isTextMimeType($mime);
		}

		return false;
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
		return in_array($mime, self::GENERIC_BINARY_MIME_TYPES, true);
	}

	/**
	 * Check if MIME type represents text data.
	 *
	 * @param string $mime MIME type
	 * @return bool true if MIME type is text, otherwise false
	 */
	private static function isTextMimeType(string $mime): bool
	{
		if (strpos($mime, 'text/') === 0) {
			return true;
		}

		return in_array($mime, self::TEXT_MIME_TYPES, true);
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
