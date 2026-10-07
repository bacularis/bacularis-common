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

namespace Bacularis\Common\Plugins;

use Bacularis\Common\Modules\BacularisCommonPluginBase;
use Bacularis\Common\Modules\DataEntropy;
use Bacularis\Common\Modules\FileType;
use Bacularis\Common\Modules\IBacularisVerificationCheckPlugin;
use Bacularis\Common\Modules\IBacularisVerificationConfigPlugin;
use Bacularis\Common\Modules\IBacularisVerificationDataPlugin;
use Bacularis\Common\Modules\RestoreDestinationCapability;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Detect files impacted by ransomware encryption.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Plugin
 */
class RansomwareDetectionCheck extends BacularisCommonPluginBase implements IBacularisVerificationCheckPlugin, IBacularisVerificationDataPlugin, IBacularisVerificationConfigPlugin
{
	/**
	 * Checker configuration categories
	 */
	private const CATEGORY_DETECTION_METHODS = 'Detection methods';
	private const CATEGORY_ANALYSIS_SETTINGS = 'Analysis settings';

	/**
	 * Data analyse methods
	 */
	private const DEFAULT_ENTROPY_ANALYSIS = true;
	private const DEFAULT_FILE_EXTENSION_MISMATCH = true;
	private const DEFAULT_FILE_TYPE_ANALYSIS = true;
	private const DEFAULT_MODIFICATION_TIME_ANALYSIS = true;
	private const DEFAULT_MODIFICATION_TIME_BURST_WINDOW = 600;
	private const DEFAULT_RANSOM_NOTE_DETECTION = true;

	/**
	 * High entropy threshold - default value.
	 */
	private const DEFAULT_HIGH_ENTROPY_THRESHOLD = 7.5;

	/**
	 * Default file types excluded by supported ransomware analysis methods.
	 * This is a common exclusion list shared by analysis methods.
	 */
	private const DEFAULT_EXCLUDED_FILE_TYPES = 'zip, gz, tgz, 7z, jpg, jpeg, mp4, mkv';

	/**
	 * Default ransom note file patterns.
	 */
	private const DEFAULT_RANSOM_NOTE_FILE_PATTERNS = 'HOW_TO_DECRYPT.txt, RECOVER_FILES.txt, DECRYPT_INSTRUCTIONS.*, README_DECRYPT.*';

	/**
	 * Default historical anomaly detection.
	 */
	private const DEFAULT_HISTORICAL_ANOMALY_DETECTION = true;

	/**
	 * Default warning treating as error.
	 */
	private const DEFAULT_TREAT_WARNING_AS_ERROR = true;

	/**
	 * Current analysis ratio thresholds.
	 */
	private const ENTROPY_HIGH_RATIO_VERY_LARGE_THRESHOLD = 0.60;
	private const ENTROPY_HIGH_RATIO_INDICATOR_THRESHOLD = 0.05;
	private const EXTENSION_MISMATCH_FILES_RATIO_INDICATOR_THRESHOLD = 0.01;
	private const EXTENSION_MISMATCH_FILES_RATIO_THRESHOLD = 0.03;
	private const EXTENSION_MISMATCH_FILES_RATIO_LARGE_THRESHOLD = 0.08;
	private const FILE_TYPE_GENERIC_RATIO_INDICATOR_THRESHOLD = 0.05;
	private const MODIFICATION_TIME_BURST_COUNT_INDICATOR_THRESHOLD = 20;
	private const MODIFICATION_TIME_BURST_RATIO_INDICATOR_THRESHOLD = 0.05;

	/**
	 * Ransom note diagnostic limits.
	 */
	private const RANSOM_NOTE_MATCHED_FILES_LIMIT = 20;

	/**
	 * Checker possible statuses.
	 */
	private const STATUS_PASS = 'pass';
	private const STATUS_WARNING = 'warning';
	private const STATUS_FAIL = 'fail';

	/**
	 * History version.
	 */
	private const HISTORY_VERSION = 1;

	/**
	 * Small history sample threshold based on ratio resolution, not an arbitrary file-count limit.
	 */
	private const HISTORY_SMALL_SAMPLE_RATIO_RESOLUTION = 0.01;

	/**
	 * Stores plugin configuration.
	 */
	private static $config = [];

	/**
	 * Stores previous checker execution historical results.
	 */
	private static $history = [];

	/**
	 * Stores current checker state.
	 */
	private static $state = [];

	/**
	 * Get plugin name displayed in web interface.
	 *
	 * @return string plugin name
	 */
	public static function getName(): string
	{
		return 'Ransomware detection';
	}

	/**
	 * Get plugin version.
	 *
	 * @return string plugin version
	 */
	public static function getVersion(): string
	{
		return '1.0.0';
	}

	/**
	 * Get plugin type.
	 *
	 * @return string plugin type
	 */
	public static function getType(): string
	{
		return 'verification';
	}

	/**
	 * Get plugin configuration parameters.
	 *
	 * return array plugin parameters
	 */
	public static function getParameters(): array
	{
		return [
			[
				'name' => 'entropy-analysis',
				'type' => 'boolean',
				'default' => self::DEFAULT_ENTROPY_ANALYSIS,
				'label' => 'Entropy analysis',
				'category' => [self::CATEGORY_DETECTION_METHODS]
			],
			[
				'name' => 'historical-anomaly-detection',
				'type' => 'boolean',
				'default' => self::DEFAULT_HISTORICAL_ANOMALY_DETECTION,
				'label' => 'Historical anomaly detection',
				'category' => [self::CATEGORY_DETECTION_METHODS]
			],
			[
				'name' => 'file-type-analysis',
				'type' => 'boolean',
				'default' => true,
				'label' => 'File type analysis',
				'category' => [self::CATEGORY_DETECTION_METHODS]
			],
			[
				'name' => 'file-extension-mismatch',
				'type' => 'boolean',
				'default' => self::DEFAULT_FILE_EXTENSION_MISMATCH,
				'label' => 'File extension mismatch',
				'category' => [self::CATEGORY_DETECTION_METHODS]
			],
			[
				'name' => 'modification-time-analysis',
				'type' => 'boolean',
				'default' => self::DEFAULT_MODIFICATION_TIME_ANALYSIS,
				'label' => 'Modification time analysis',
				'category' => [self::CATEGORY_DETECTION_METHODS]
			],
			[
				'name' => 'ransom-note-detection',
				'type' => 'boolean',
				'default' => self::DEFAULT_RANSOM_NOTE_DETECTION,
				'label' => 'Ransom note detection',
				'category' => [self::CATEGORY_DETECTION_METHODS]
			],
			[
				'name' => 'treat-warning-as-error',
				'type' => 'boolean',
				'default' => self::DEFAULT_TREAT_WARNING_AS_ERROR,
				'label' => 'Treat warning as error',
				'category' => [self::CATEGORY_ANALYSIS_SETTINGS]
			],
			[
				'name' => 'history-size',
				'type' => 'integer',
				'default' => 12,
				'placeholder' => 'ex: 10',
				'label' => 'History size',
				'category' => [self::CATEGORY_ANALYSIS_SETTINGS]
			],
			[
				'name' => 'modification-time-burst-window',
				'type' => 'integer',
				'default' => self::DEFAULT_MODIFICATION_TIME_BURST_WINDOW,
				'label' => 'Modification time burst window (seconds)',
				'category' => [self::CATEGORY_ANALYSIS_SETTINGS]
			],
			[
				'name' => 'entropy-threshold',
				'type' => 'string',
				'default' => self::DEFAULT_HIGH_ENTROPY_THRESHOLD,
				'placeholder' => 'ex: 7.2',
				'label' => 'Entropy threshold (0–8)',
				'category' => [self::CATEGORY_ANALYSIS_SETTINGS]
			],
			[
				'name' => 'entropy-sample-size',
				'type' => 'integer',
				'default' => 262144,
				'placeholder' => 'ex: 262144',
				'label' => 'Entropy sample size (bytes)',
				'category' => [self::CATEGORY_ANALYSIS_SETTINGS]
			],
			[
				'name' => 'entropy-samples',
				'type' => 'integer',
				'default' => 3,
				'placeholder' => 'ex: 3',
				'label' => 'Entropy samples',
				'category' => [self::CATEGORY_ANALYSIS_SETTINGS]
			],
			[
				'name' => 'excluded-file-types',
				'type' => 'string',
				'default' => self::DEFAULT_EXCLUDED_FILE_TYPES,
				'placeholder' => 'ex: zip, gz, 7z, jpg, mp4',
				'label' => 'Excluded file types',
				'category' => [self::CATEGORY_ANALYSIS_SETTINGS]
			],
			[
				'name' => 'ransom-note-file-patterns',
				'type' => 'string',
				'default' => self::DEFAULT_RANSOM_NOTE_FILE_PATTERNS,
				'placeholder' => 'ex: RECOVER_FILES.txt, DECRYPT_INSTRUCTIONS.*',
				'label' => 'Ransom note file patterns',
				'category' => [self::CATEGORY_ANALYSIS_SETTINGS]
			]
		];
	}

	/**
	 * Main check command.
	 * It checks if item is valid or not.
	 *
	 * @param string $operator check operator
	 * @param mixed $current_value item value to check
	 * @param mixed $expected_value expected value
	 * @return array current value, expected value and check result: true on succes, false otherwise
	 */
	public static function check(string $operator, $current_value, $expected_value): array
	{
		$ret = ['result' => false, 'current' => '', 'expected' => ''];

		if (!is_dir($current_value)) {
			return $ret;
		}
		$analysis = self::analyse($current_value);
		$evaluation = self::prepareResult($analysis);
		$twae = self::$config['treat-warning-as-error'] ?? self::DEFAULT_TREAT_WARNING_AS_ERROR;
		$history_enabled = self::$config['historical-anomaly-detection'] ?? self::DEFAULT_HISTORICAL_ANOMALY_DETECTION;
		$files_scanned = $analysis['stat_log']['files_scanned'];
		$is_small_sample = ($files_scanned > 0 && (1 / $files_scanned) >= self::HISTORY_SMALL_SAMPLE_RATIO_RESOLUTION);

		$ret['result'] = ($evaluation['status'] === self::STATUS_PASS || ($evaluation['status'] === self::STATUS_WARNING && !$twae));
		$reason_lines = $evaluation['errors'];
		foreach ($evaluation['reasons'] as $reason) {
			$reason_lines[] = sprintf('%s (+%d)', $reason['message'], $reason['score']);
		}
		$reasons = $reason_lines ? 'Reasons:' . PHP_EOL . PHP_EOL . ' - ' . implode(PHP_EOL . ' - ', $reason_lines) . PHP_EOL : '';
		$note = '';
		if ($history_enabled && $is_small_sample) {
			$note = PHP_EOL . implode(PHP_EOL, [
				'NOTE: Historical baseline analysis is being applied to a small file set.',
				'Changes to individual files may have a significant impact on percentage-based',
				'metrics and can make anomaly detection more sensitive. Consider disabling',
				'historical baseline analysis if this sensitivity is not desired.'
			]) . PHP_EOL;
		}
		$ret['current'] = sprintf(
			"%s (score: %d)\n%s%s",
			strtoupper($evaluation['status']),
			$evaluation['score'],
			$reasons,
			$note
		);
		$ret['expected'] = 'No ransomware indicators detected';

		// Set current checker execution state
		self::$state = $analysis['stat_log'];

		// Mark if state should be used for ransomware detection baseline
		self::$state['baseline']['eligible'] = ($evaluation['status'] === self::STATUS_PASS);

		return $ret;
	}

	/**
	 * Perform ransomware detection analyse for path.
	 *
	 * @param string $path directory path
	 * @return array analysis for each file and statistic summary
	 */
	private static function analyse(string $path): array
	{
		$dir = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
		$iterator = new RecursiveIteratorIterator($dir);
		$is_entropy_analysis = self::$config['entropy-analysis'] ?? self::DEFAULT_ENTROPY_ANALYSIS;
		$is_file_type_analysis = self::$config['file-type-analysis'] ?? self::DEFAULT_FILE_TYPE_ANALYSIS;
		$is_extension_mismatch = self::$config['file-extension-mismatch'] ?? self::DEFAULT_FILE_EXTENSION_MISMATCH;
		$is_modification_time_analysis = self::$config['modification-time-analysis'] ?? self::DEFAULT_MODIFICATION_TIME_ANALYSIS;
		$is_ransom_note_detection = self::$config['ransom-note-detection'] ?? self::DEFAULT_RANSOM_NOTE_DETECTION;
		$excluded_file_types = self::getExcludedFileTypes();
		$modification_time_burst_window = (int) (self::$config['modification-time-burst-window'] ?? self::DEFAULT_MODIFICATION_TIME_BURST_WINDOW);
		$ransom_note_file_patterns = self::getRansomNoteFilePatterns();

		$stat_log = [
			'files_scanned' => 0
		];

		if ($is_entropy_analysis) {
			$stat_log['entropy'] = [
				'enabled' => (int) $is_entropy_analysis,
				'eligible' => 0,
				'files_analyzed' => 0,
				'excluded' => 0,
				'errors' => 0,
				'average' => 0,
				'high_count' => 0,
				'high_ratio' => 0,
				'histogram' => [
					'0-4' => 0,
					'4-6' => 0,
					'6-7' => 0,
					'7-8' => 0
				]
			];
		}

		if ($is_file_type_analysis) {
			$stat_log['file_type'] = [
				'enabled' => (int) $is_file_type_analysis,
				'eligible' => 0,
				'files_analyzed' => 0,
				'excluded' => 0,
				'errors' => 0,
				'generic_count' => 0,
				'generic_ratio' => 0
			];
		}

		if ($is_extension_mismatch) {
			$stat_log['extension_mismatch'] = [
				'enabled' => (int) $is_extension_mismatch,
				'eligible' => 0,
				'files_analyzed' => 0,
				'excluded' => 0,
				'errors' => 0,
				'mismatch_count' => 0,
				'mismatch_ratio' => 0,
				'mismatch_files_ratio' => 0
			];
		}

		if ($is_modification_time_analysis) {
			$stat_log['modification_time'] = [
				'enabled' => (int) $is_modification_time_analysis,
				'eligible' => 0,
				'files_analyzed' => 0,
				'errors' => 0,
				'latest_mtime' => 0,
				'burst_window' => $modification_time_burst_window,
				'burst_count' => 0,
				'burst_ratio' => 0
			];
		}

		if ($is_ransom_note_detection) {
			$stat_log['ransom_note'] = [
				'enabled' => (int) $is_ransom_note_detection,
				'files_analyzed' => 0,
				'matches' => 0,
				'matched_files' => []
			];
		}

		// Entropy specific options
		$entropy_opts = [
			'exclusions' => $excluded_file_types,
			'high_threshold' => (float) (self::$config['entropy-threshold'] ?? self::DEFAULT_HIGH_ENTROPY_THRESHOLD),
			'sum' => 0.0
		];
		$file_type_opts = [
			'exclusions' => $excluded_file_types
		];
		$extension_mismatch_opts = [
			'exclusions' => $excluded_file_types
		];
		$modification_time_opts = [
			'mtimes' => []
		];
		$ransom_note_opts = [
			'patterns' => $ransom_note_file_patterns
		];

		// Decide which tests will need and use file type
		$need_file_type = ($is_file_type_analysis || $is_extension_mismatch || ($is_entropy_analysis && $entropy_opts['exclusions']));

		// Main file/dir iterator
		foreach ($iterator as $file) {
			if ($file->isDir()) {
				// it is directory, skip it
				continue;
			}
			if ($file->isLink()) {
				// it is symbolic link, skip it
				continue;
			}
			if (!$file->isFile()) {
				// it is not a regular file, skip it
				continue;
			}

			$stat_log['files_scanned']++;
			$path = $file->getPathname();

			// Get file MIME type
			$mime = null;
			if ($need_file_type) {
				$mime = FileType::getMimeType($path);
			}

			if ($is_entropy_analysis) {
				// Analyse entropy
				self::analyseEntropy(
					$path,
					$mime,
					$stat_log['entropy'],
					$entropy_opts
				);
			}

			if ($is_file_type_analysis) {
				// Analyse file type
				self::analyseFileType(
					$path,
					$mime,
					$stat_log['file_type'],
					$file_type_opts
				);
			}

			if ($is_extension_mismatch) {
				// Analyse file extension mismatch
				self::analyseExtensionMismatch(
					$path,
					$mime,
					$stat_log['extension_mismatch'],
					$extension_mismatch_opts
				);
			}

			if ($is_modification_time_analysis) {
				// Analyse file modification time
				self::analyseModificationTime(
					$path,
					$stat_log['modification_time'],
					$modification_time_opts
				);
			}

			if ($is_ransom_note_detection) {
				// Analyse file name for ransom note patterns
				self::analyseRansomNote(
					$path,
					$stat_log['ransom_note'],
					$ransom_note_opts
				);
			}
		}

		$post_props = [
			'entropy' => $entropy_opts,
			'file_type' => $file_type_opts,
			'extension_mismatch' => $extension_mismatch_opts,
			'modification_time' => $modification_time_opts
		];

		// Post actions
		self::postAnalyse('entropy', $stat_log, $post_props);
		self::postAnalyse('file_type', $stat_log, $post_props);
		self::postAnalyse('extension_mismatch', $stat_log, $post_props);
		self::postAnalyse('modification_time', $stat_log, $post_props);

		return ['stat_log' => $stat_log];
	}

	/**
	 * Analyse file entropy and update entropy statistics.
	 *
	 * @param string $path file path
	 * @param null|string $mime file MIME type
	 * @param array $stat_log entropy analysis statistics
	 * @param array $opts entropy analysis options
	 */
	private static function analyseEntropy(string $path, ?string $mime, array &$stat_log, array &$opts): void
	{
		$entropy_excluded = ($opts['exclusions'] && $mime !== null && FileType::matchesAnyMimeType($mime, $opts['exclusions']));
		if ($entropy_excluded) {
			// file is excluded because of its type - skip it
			$stat_log['excluded']++;
			return;
		}

		// file is eligible to check entropy
		$stat_log['eligible']++;

		// Check entropy factor
		$entropy = self::entropyAnalysis($path);
		if ($entropy === null) {
			// Unable to check entropy in file - error
			$stat_log['errors']++;
			return;
		}

		$stat_log['files_analyzed']++;
		$opts['sum'] += $entropy;

		if ($entropy <= 4) {
			$stat_log['histogram']['0-4']++;
		} elseif ($entropy <= 6) {
			$stat_log['histogram']['4-6']++;
		} elseif ($entropy <= 7) {
			$stat_log['histogram']['6-7']++;
		} elseif ($entropy <= 8) {
			$stat_log['histogram']['7-8']++;
		}
		if ($entropy >= $opts['high_threshold']) {
			$stat_log['high_count']++;
		}
	}

	/**
	 * Analyse file type and update file type statistics.
	 *
	 * @param string $path file path
	 * @param null|string $mime file MIME type
	 * @param array $stat_log file type analysis statistics
	 * @param array $opts file type analysis options
	 */
	private static function analyseFileType(string $path, ?string $mime, array &$stat_log, array $opts): void
	{
		if ($mime === null) {
			// File type could not be detected, so exclusion cannot be determined
			$stat_log['eligible']++;
			$stat_log['errors']++;
			return;
		}

		$file_type_excluded = ($opts['exclusions'] && FileType::matchesAnyMimeType($mime, $opts['exclusions']));
		if ($file_type_excluded) {
			// file is excluded because of its type - skip it
			$stat_log['excluded']++;
			return;
		}

		// file is eligible to check file type
		$stat_log['eligible']++;
		$stat_log['files_analyzed']++;

		if (FileType::isGenericMimeType($mime)) {
			$stat_log['generic_count']++;
		}
	}

	/**
	 * Analyse file extension consistency and update mismatch statistics.
	 *
	 * @param string $path file path
	 * @param null|string $mime file MIME type
	 * @param array $stat_log file extension mismatch statistics
	 * @param array $opts file extension mismatch options
	 */
	private static function analyseExtensionMismatch(string $path, ?string $mime, array &$stat_log, array $opts): void
	{
		if ($mime === null) {
			$extension = pathinfo($path, PATHINFO_EXTENSION);
			if (FileType::isSupportedType($extension)) {
				// Supported extension could not be compared because MIME detection failed
				$stat_log['eligible']++;
				$stat_log['errors']++;
			}
			return;
		}

		$extension_mismatch_excluded = ($opts['exclusions'] && FileType::matchesAnyMimeType($mime, $opts['exclusions']));
		if ($extension_mismatch_excluded) {
			// file is excluded because of its type - skip it
			$stat_log['excluded']++;
			return;
		}

		$extension_matches = FileType::matchesFileExtension($path, $mime);
		if ($extension_matches === null) {
			// File extension and MIME type cannot be meaningfully compared
			return;
		}

		// file is eligible to check extension and MIME type consistency
		$stat_log['eligible']++;
		$stat_log['files_analyzed']++;

		if (!$extension_matches) {
			$stat_log['mismatch_count']++;
		}
	}

	/**
	 * Analyse file modification time and update modification time statistics.
	 *
	 * @param string $path file path
	 * @param array $stat_log modification time analysis statistics
	 * @param array $opts modification time analysis options
	 */
	private static function analyseModificationTime(string $path, array &$stat_log, array &$opts): void
	{
		$stat_log['eligible']++;
		$mtime = @filemtime($path);
		if ($mtime === false) {
			$stat_log['errors']++;
			return;
		}

		$stat_log['files_analyzed']++;
		$opts['mtimes'][] = $mtime;
	}

	/**
	 * Analyse file name for ransom note patterns and update statistics.
	 *
	 * @param string $path file path
	 * @param array $stat_log ransom note detection statistics
	 * @param array $opts ransom note detection options
	 */
	private static function analyseRansomNote(string $path, array &$stat_log, array $opts): void
	{
		$stat_log['files_analyzed']++;
		$basename = basename($path);
		$basename = strtolower($basename);
		foreach ($opts['patterns'] as $pattern) {
			if (fnmatch($pattern, $basename)) {
				$stat_log['matches']++;
				if (count($stat_log['matched_files']) < self::RANSOM_NOTE_MATCHED_FILES_LIMIT) {
					$stat_log['matched_files'][] = $path;
				}
				break;
			}
		}
	}

	/**
	 * Run post-analysis actions for a ransomware detection method.
	 *
	 * @param string $method analysis method name
	 * @param array $stat_log analysis statistics
	 * @param array $props analysis method properties
	 */
	private static function postAnalyse(string $method, array &$stat_log, array $props): void
	{
		if (!key_exists($method, $stat_log) || !$stat_log[$method]['enabled']) {
			return;
		}

		switch ($method) {
			case 'entropy':
				self::postAnalyseEntropy($stat_log[$method], $props[$method]);
				break;
			case 'file_type':
				self::postAnalyseFileType($stat_log[$method]);
				break;
			case 'extension_mismatch':
				self::postAnalyseExtensionMismatch($stat_log[$method], $stat_log['files_scanned']);
				break;
			case 'modification_time':
				self::postAnalyseModificationTime($stat_log[$method], $props[$method]);
				break;
		}
	}

	/**
	 * Summarize entropy analysis results.
	 *
	 * @param array $stat_log entropy analysis statistics
	 * @param array $opts entropy analysis options
	 */
	private static function postAnalyseEntropy(array &$stat_log, array $opts): void
	{
		$entropy_files = $stat_log['files_analyzed'];
		if ($entropy_files > 0) {
			$entropy_sum = $opts['sum'];
			$stat_log['average'] = $entropy_sum / $entropy_files;
			$stat_log['high_ratio'] = $stat_log['high_count'] / $entropy_files;
		}
	}

	/**
	 * Summarize file type analysis results.
	 *
	 * @param array $stat_log file type analysis statistics
	 */
	private static function postAnalyseFileType(array &$stat_log): void
	{
		$file_type_files = $stat_log['files_analyzed'];
		if ($file_type_files > 0) {
			$stat_log['generic_ratio'] = $stat_log['generic_count'] / $file_type_files;
		}
	}

	/**
	 * Summarize file extension mismatch analysis results.
	 *
	 * @param array $stat_log file extension mismatch statistics
	 * @param int $files_scanned total number of scanned files
	 */
	private static function postAnalyseExtensionMismatch(array &$stat_log, int $files_scanned): void
	{
		// Compute proportion of all analyzed files that have an extension/type mismatch
		$extension_mismatch_files = $stat_log['files_analyzed'];
		if ($extension_mismatch_files > 0) {
			$stat_log['mismatch_ratio'] = $stat_log['mismatch_count'] / $extension_mismatch_files;
		}

		// Compute proportion of all scanned files that have an extension/type mismatch
		if ($files_scanned > 0) {
			$stat_log['mismatch_files_ratio'] = $stat_log['mismatch_count'] / $files_scanned;
		}
	}

	/**
	 * Summarize modification time analysis results.
	 *
	 * @param array $stat_log modification time analysis statistics
	 * @param array $opts modification time analysis options
	 */
	private static function postAnalyseModificationTime(array &$stat_log, array $opts): void
	{
		$modification_time_files = $stat_log['files_analyzed'];
		if ($modification_time_files === 0) {
			return;
		}

		$mtimes = $opts['mtimes'];
		$latest_mtime = max($mtimes);
		$window_start = $latest_mtime - $stat_log['burst_window'];
		$burst_count = 0;
		foreach ($mtimes as $mtime) {
			if ($mtime >= $window_start) {
				$burst_count++;
			}
		}

		$stat_log['latest_mtime'] = $latest_mtime;
		$stat_log['burst_count'] = $burst_count;
		$stat_log['burst_ratio'] = $burst_count / $modification_time_files;
	}

	/**
	 * Analyse entropy for given file.
	 *
	 * @param string $path file path
	 * @return null|float entropy value or null on error
	 */
	private static function entropyAnalysis(string $path): ?float
	{
		$sample_size = self::$config['entropy-sample-size'] ?? DataEntropy::DEFAULT_SAMPLE_SIZE;
		$samples = self::$config['entropy-samples'] ?? DataEntropy::DEFAULT_SAMPLES;

		// Check file entropy
		$entropy = DataEntropy::calculateFile($path, $sample_size, $samples);

		if ($entropy === null) {
			// Unable to analyze file.
		}
		return $entropy;
	}

	/**
	 * Get normalized file types excluded from supported analysis methods.
	 *
	 * @return array unique supported file types excluded from analysis
	 */
	private static function getExcludedFileTypes(): array
	{
		$file_type_func = static function ($type) {
			$type = trim($type);
			$type = ltrim($type, '.');
			return strtolower($type);
		};
		$types = self::$config['excluded-file-types'] ?? self::DEFAULT_EXCLUDED_FILE_TYPES;
		$type_list = explode(',', $types);
		$excluded_file_types = array_map(
			$file_type_func,
			$type_list
		);

		$excluded_file_types = array_filter(
			$excluded_file_types,
			static fn ($type) => FileType::isSupportedType($type)
		);
		$excluded_file_types = array_values(
			array_unique($excluded_file_types)
		);
		return $excluded_file_types;
	}

	/**
	 * Get normalized ransom note file patterns.
	 *
	 * @return array normalized ransom note file patterns
	 */
	private static function getRansomNoteFilePatterns(): array
	{
		$pattern_func = static function ($pattern) {
			$pattern = trim($pattern);
			return strtolower($pattern);
		};
		$patterns = self::$config['ransom-note-file-patterns'] ?? self::DEFAULT_RANSOM_NOTE_FILE_PATTERNS;
		$pattern_list = explode(',', $patterns);
		$ransom_note_file_patterns = array_map(
			$pattern_func,
			$pattern_list
		);
		$ransom_note_file_patterns = array_filter(
			$ransom_note_file_patterns,
			static fn ($pattern) => $pattern !== ''
		);
		$ransom_note_file_patterns = array_values(
			array_unique($ransom_note_file_patterns)
		);
		return $ransom_note_file_patterns;
	}

	/**
	 * Prepare checker result.
	 *
	 * @param array $analysis analysis results
	 * @return array checker evaluation result
	 */
	private static function prepareResult(array $analysis)
	{
		$ret = [
			'status' => '',
			'score' => 0,
			'reasons' => [],
			'errors' => []
		];
		$history_enabled = self::$config['historical-anomaly-detection'] ?? self::DEFAULT_HISTORICAL_ANOMALY_DETECTION;
		$history = [];
		if ($history_enabled) {
			$history = self::getHistoryResults();
		}
		$entropy_score = 0;
		$entropy_indicator = false;
		$file_type_score = 0;
		$file_type_indicator = false;
		$extension_mismatch_score = 0;
		$extension_mismatch_indicator = false;
		$modification_time_score = 0;
		$modification_time_indicator = false;
		$ransom_note_score = 0;
		$ransom_note_indicator = false;

		if (key_exists('entropy', $analysis['stat_log'])) {
			$entropy = $analysis['stat_log']['entropy'];
			if ($entropy['enabled']) {
				// Analyse entropy
				if ($entropy['eligible'] > 0 && $entropy['files_analyzed'] === 0) {
					$ret['errors'][] = 'Unable to analyze file entropy';
					$ret['status'] = self::STATUS_FAIL;
				} else {
					$entropy_history = $history['entropy'] ?? [];
					if ($entropy_history) {
						// Use history to analyse entropy
						$evaluation = self::evaluateHistoricalEntropy($entropy, $entropy_history, $ret);
					} else {
						// Use static thresholds to analyse entropy
						$evaluation = self::evaluateCurrentEntropy($entropy, $ret);
					}
					$entropy_score = $evaluation['score'];
					$entropy_indicator = $evaluation['indicator'];
					$ret['score'] += $entropy_score;
				}
			}
		}

		if (key_exists('file_type', $analysis['stat_log'])) {
			$file_type = $analysis['stat_log']['file_type'];
			if ($file_type['enabled']) {
				// Analyse file type
				if ($file_type['eligible'] > 0 && $file_type['files_analyzed'] === 0) {
					$ret['errors'][] = 'Unable to analyze file types';
					$ret['status'] = self::STATUS_FAIL;
				} else {
					$file_type_history = $history['file_type'] ?? [];
					if ($file_type_history) {
						// Use history to analyse file types
						$evaluation = self::evaluateHistoricalFileType($file_type, $file_type_history, $ret);
					} else {
						// Use static thresholds to analyse file types
						$evaluation = self::evaluateCurrentFileType($file_type);
					}
					$file_type_score = $evaluation['score'];
					$file_type_indicator = $evaluation['indicator'];
					$ret['score'] += $file_type_score;
				}
			}
		}

		if (key_exists('extension_mismatch', $analysis['stat_log'])) {
			$extension_mismatch = $analysis['stat_log']['extension_mismatch'];
			if ($extension_mismatch['enabled']) {
				// Analyse file extension mismatch
				if ($extension_mismatch['eligible'] > 0 && $extension_mismatch['files_analyzed'] === 0) {
					$ret['errors'][] = 'Unable to analyze file extension/type mismatches';
					$ret['status'] = self::STATUS_FAIL;
				} else {
					$extension_mismatch_history = $history['extension_mismatch'] ?? [];
					if ($extension_mismatch_history) {
						// Use history to analyse file extension mismatches
						$evaluation = self::evaluateHistoricalExtensionMismatch($extension_mismatch, $extension_mismatch_history, $ret);
					} else {
						// Use static thresholds to analyse file extension mismatches
						$evaluation = self::evaluateCurrentExtensionMismatch($extension_mismatch, $ret);
					}
					$extension_mismatch_score = $evaluation['score'];
					$extension_mismatch_indicator = $evaluation['indicator'];
					$ret['score'] += $extension_mismatch_score;
				}
			}
		}

		if (key_exists('modification_time', $analysis['stat_log'])) {
			$modification_time = $analysis['stat_log']['modification_time'];
			if ($modification_time['enabled']) {
				// Analyse file modification time
				$evaluation = self::evaluateCurrentModificationTime($modification_time, $ret);
				$modification_time_score = $evaluation['score'];
				$modification_time_indicator = $evaluation['indicator'];
				$ret['score'] += $modification_time_score;
			}
		}

		if (key_exists('ransom_note', $analysis['stat_log'])) {
			$ransom_note = $analysis['stat_log']['ransom_note'];
			if ($ransom_note['enabled']) {
				// Analyse ransom note detection
				$evaluation = self::evaluateCurrentRansomNote($ransom_note, $ret);
				$ransom_note_score = $evaluation['score'];
				$ransom_note_indicator = $evaluation['indicator'];
				$ret['score'] += $ransom_note_score;
			}
		}

		$indicators = [];
		if ($entropy_indicator) {
			$indicators[] = 'high entropy ratio';
		}
		if ($file_type_indicator) {
			$indicators[] = 'generic/unrecognized file types';
		}
		if ($extension_mismatch_indicator) {
			$indicators[] = 'file extension/type mismatches';
		}
		if ($modification_time_indicator) {
			$indicators[] = 'mass file modification activity';
		}
		if ($ransom_note_indicator) {
			$indicators[] = 'ransom note detected';
		}

		$indicator_count = count($indicators);
		if ($indicator_count >= 2) {
			$score_add = ($indicator_count >= 3 ? 2 : 1);
			$indicator_names = implode(', ', $indicators);
			$ret['score'] += $score_add;
			$ret['reasons'][] = [
				'message' => 'Multiple anomaly indicators observed: ' . $indicator_names,
				'score' => $score_add
			];
		}

		// Prepare results
		if ($ret['status'] === '') {
			if ($ret['score'] >= 5) {
				$ret['status'] = self::STATUS_FAIL;
			} elseif ($ret['score'] >= 3) {
				$ret['status'] = self::STATUS_WARNING;
			} else {
				$ret['status'] = self::STATUS_PASS;
			}
		}

		return $ret;
	}

	/**
	 * Evaluate current entropy statistics using absolute thresholds.
	 *
	 * @param array $entropy entropy analysis statistics
	 * @param array $result checker evaluation result
	 * @return array score contribution and indicator state
	 */
	private static function evaluateCurrentEntropy(array $entropy, array &$result): array
	{
		$current_ratio = $entropy['high_ratio'];
		$indicator = ($current_ratio >= self::ENTROPY_HIGH_RATIO_INDICATOR_THRESHOLD);
		$score = 0;
		if ($current_ratio >= self::ENTROPY_HIGH_RATIO_VERY_LARGE_THRESHOLD) {
			$score_add = 1;
			$score += $score_add;
			$result['reasons'][] = [
				'message' => 'Very large high-entropy file ratio',
				'score' => $score_add
			];
		}
		return [
			'score' => $score,
			'indicator' => $indicator
		];
	}

	/**
	 * Evaluate current file type statistics for anomaly indication.
	 *
	 * @param array $file_type file type analysis statistics
	 * @return array score contribution and indicator state
	 */
	private static function evaluateCurrentFileType(array $file_type): array
	{
		$current_ratio = $file_type['generic_ratio'];
		$indicator = ($current_ratio >= self::FILE_TYPE_GENERIC_RATIO_INDICATOR_THRESHOLD);
		$score = 0;
		return [
			'score' => $score,
			'indicator' => $indicator
		];
	}

	/**
	 * Evaluate current file extension mismatch statistics using absolute thresholds.
	 *
	 * @param array $extension_mismatch file extension mismatch statistics
	 * @param array $result checker evaluation result
	 * @return array score contribution and indicator state
	 */
	private static function evaluateCurrentExtensionMismatch(array $extension_mismatch, array &$result): array
	{
		$current_ratio = $extension_mismatch['mismatch_files_ratio'];
		$indicator = ($current_ratio >= self::EXTENSION_MISMATCH_FILES_RATIO_INDICATOR_THRESHOLD);

		$score = 0;
		if ($current_ratio >= self::EXTENSION_MISMATCH_FILES_RATIO_LARGE_THRESHOLD) {
			$score_add = 2;
			$score += $score_add;
			$result['reasons'][] = [
				'message' => 'Large file extension/type mismatch ratio',
				'score' => $score_add
			];
		} elseif ($current_ratio >= self::EXTENSION_MISMATCH_FILES_RATIO_THRESHOLD) {
			$score_add = 1;
			$score += $score_add;
			$result['reasons'][] = [
				'message' => 'File extension/type mismatches detected',
				'score' => $score_add
			];
		}
		return [
			'score' => $score,
			'indicator' => $indicator
		];
	}

	/**
	 * Evaluate current modification time statistics using absolute thresholds.
	 *
	 * @param array $modification_time modification time analysis statistics
	 * @param array $result checker evaluation result
	 * @return array score contribution and indicator state
	 */
	private static function evaluateCurrentModificationTime(array $modification_time, array &$result): array
	{
		$indicator = (
			$modification_time['burst_count'] >= self::MODIFICATION_TIME_BURST_COUNT_INDICATOR_THRESHOLD
			&& $modification_time['burst_ratio'] >= self::MODIFICATION_TIME_BURST_RATIO_INDICATOR_THRESHOLD
		);
		return [
			'score' => 0,
			'indicator' => $indicator
		];
	}

	/**
	 * Evaluate current ransom note detection statistics.
	 *
	 * @param array $ransom_note ransom note detection statistics
	 * @param array $result checker evaluation result
	 * @return array score contribution and indicator state
	 */
	private static function evaluateCurrentRansomNote(array $ransom_note, array &$result): array
	{
		$indicator = ($ransom_note['matches'] > 0);
		$score = 0;
		if ($indicator) {
			$score = 3;
			$result['reasons'][] = [
				'message' => 'Ransom note detected',
				'score' => $score
			];
		}
		return [
			'score' => $score,
			'indicator' => $indicator
		];
	}

	/**
	 * Evaluate current entropy statistics against historical baseline.
	 *
	 * @param array $entropy entropy analysis statistics
	 * @param array $history historical entropy baseline
	 * @param array $result checker evaluation result
	 * @return array score contribution and indicator state
	 */
	private static function evaluateHistoricalEntropy(array $entropy, array $history, array &$result): array
	{
		$current_ratio = $entropy['high_ratio'];
		$baseline_ratio = $history['baseline_high_ratio'];
		$ratio_diff = $current_ratio - $baseline_ratio;
		$entropy_diff = $entropy['average'] - $history['baseline_average_entropy'];
		$score = 0;

		if ($baseline_ratio > 0) {
			$ratio_factor = $current_ratio / $baseline_ratio;
			if ($ratio_diff >= 0.10 || ($ratio_diff >= 0.05 && $ratio_factor >= 1.5)) {
				$score_add = 3;
				$score += $score_add;
				$result['reasons'][] = [
					'message' => 'Large increase in high-entropy file ratio',
					'score' => $score_add
				];
			} elseif ($ratio_diff >= 0.03 && $ratio_factor >= 1.2) {
				$score_add = 2;
				$score += $score_add;
				$result['reasons'][] = [
					'message' => 'Increase in high-entropy file ratio',
					'score' => $score_add
				];
			}
		} elseif ($current_ratio >= 0.05) {
			$score_add = 3;
			$score += $score_add;
			$result['reasons'][] = [
				'message' => 'Large high-entropy file ratio',
				'score' => $score_add
			];
		} elseif ($current_ratio >= 0.01) {
			$score_add = 2;
			$score += $score_add;
			$result['reasons'][] = [
				'message' => 'High-entropy file ratio detected',
				'score' => $score_add
			];
		}

		if ($entropy_diff >= 0.75) {
			$score_add = 2;
			$score += $score_add;
			$result['reasons'][] = [
				'message' => 'Large increase in average entropy',
				'score' => $score_add
			];
		} elseif ($entropy_diff >= 0.25) {
			$score_add = 1;
			$score += $score_add;
			$result['reasons'][] = [
				'message' => 'Increase in average entropy',
				'score' => $score_add
			];
		}
		return [
			'score' => $score,
			'indicator' => ($score > 0)
		];
	}

	/**
	 * Evaluate current file type statistics against historical baseline.
	 *
	 * @param array $file_type file type analysis statistics
	 * @param array $history historical file type baseline
	 * @param array $result checker evaluation result
	 * @return array score contribution and indicator state
	 */
	private static function evaluateHistoricalFileType(array $file_type, array $history, array &$result): array
	{
		$current_ratio = $file_type['generic_ratio'];
		$baseline_ratio = $history['baseline_generic_ratio'];
		$ratio_diff = $current_ratio - $baseline_ratio;
		$score = 0;

		if ($baseline_ratio > 0) {
			$ratio_factor = $current_ratio / $baseline_ratio;
			if ($ratio_diff >= 0.10 || ($ratio_diff >= 0.05 && $ratio_factor >= 1.5)) {
				$score_add = 3;
				$score += $score_add;
				$result['reasons'][] = [
					'message' => 'Large increase in generic/unrecognized file type ratio',
					'score' => $score_add
				];
			} elseif ($ratio_diff >= 0.03 && $ratio_factor >= 1.2) {
				$score_add = 2;
				$score += $score_add;
				$result['reasons'][] = [
					'message' => 'Increase in generic/unrecognized file type ratio',
					'score' => $score_add
				];
			}
		} elseif ($current_ratio >= 0.10) {
			$score_add = 3;
			$score += $score_add;
			$result['reasons'][] = [
				'message' => 'Large generic/unrecognized file type ratio',
				'score' => $score_add
			];
		} elseif ($current_ratio >= 0.03) {
			$score_add = 2;
			$score += $score_add;
			$result['reasons'][] = [
				'message' => 'Generic/unrecognized file types detected',
				'score' => $score_add
			];
		}
		return [
			'score' => $score,
			'indicator' => ($score > 0)
		];
	}

	/**
	 * Evaluate current file extension mismatch statistics against historical baseline.
	 *
	 * @param array $extension_mismatch file extension mismatch statistics
	 * @param array $history historical file extension mismatch baseline
	 * @param array $result checker evaluation result
	 * @return array score contribution and indicator state
	 */
	private static function evaluateHistoricalExtensionMismatch(array $extension_mismatch, array $history, array &$result): array
	{
		$current_ratio = $extension_mismatch['mismatch_files_ratio'];
		$baseline_ratio = $history['baseline_mismatch_files_ratio'];
		$ratio_diff = $current_ratio - $baseline_ratio;
		$score = 0;

		if ($baseline_ratio > 0) {
			$ratio_factor = $current_ratio / $baseline_ratio;
			if ($ratio_diff >= 0.10 || ($ratio_diff >= 0.05 && $ratio_factor >= 1.5)) {
				$score_add = 3;
				$score += $score_add;
				$result['reasons'][] = [
					'message' => 'Large increase in file extension/type mismatches',
					'score' => $score_add
				];
			} elseif ($ratio_diff >= 0.03 && $ratio_factor >= 1.2) {
				$score_add = 2;
				$score += $score_add;
				$result['reasons'][] = [
					'message' => 'Increase in file extension/type mismatches',
					'score' => $score_add
				];
			}
		} elseif ($current_ratio >= 0.10) {
			$score_add = 3;
			$score += $score_add;
			$result['reasons'][] = [
				'message' => 'Large increase in file extension/type mismatches',
				'score' => $score_add
			];
		} elseif ($current_ratio >= 0.03) {
			$score_add = 2;
			$score += $score_add;
			$result['reasons'][] = [
				'message' => 'Increase in file extension/type mismatches',
				'score' => $score_add
			];
		}
		return [
			'score' => $score,
			'indicator' => ($score > 0)
		];
	}

	/**
	 * Get historical analysis factors for baseline.
	 *
	 * @return array historical analysis factors or empty array if history is empty
	 */
	private static function getHistoryResults(): array
	{
		$baseline_high_ratio = 0.0;
		$baseline_average_entropy = 0.0;
		$baseline_generic_ratio = 0.0;
		$baseline_mismatch_files_ratio = 0.0;
		$entropy_count = 0;
		$file_type_count = 0;
		$extension_mismatch_count = 0;
		$ret = [];

		if (!self::$history) {
			return [];
		}

		foreach (self::$history as $state) {
			if (($state['baseline']['eligible'] ?? false) !== true) {
				// State is not suitable for ransomware detection baseline
				continue;
			}

			if (key_exists('entropy', $state) && is_array($state['entropy']) && ($state['entropy']['files_analyzed'] ?? 0) > 0) {
				$baseline_high_ratio += $state['entropy']['high_ratio'];
				$baseline_average_entropy += $state['entropy']['average'];
				$entropy_count++;
			}

			if (
				key_exists('file_type', $state)
				&& is_array($state['file_type'])
				&& ($state['file_type']['files_analyzed'] ?? 0) > 0
				&& key_exists('generic_ratio', $state['file_type'])
				&& is_numeric($state['file_type']['generic_ratio'])
			) {
				$baseline_generic_ratio += $state['file_type']['generic_ratio'];
				$file_type_count++;
			}

			if (
				key_exists('extension_mismatch', $state)
				&& is_array($state['extension_mismatch'])
				&& ($state['extension_mismatch']['files_analyzed'] ?? 0) > 0
				&& key_exists('mismatch_files_ratio', $state['extension_mismatch'])
				&& is_numeric($state['extension_mismatch']['mismatch_files_ratio'])
			) {
				$baseline_mismatch_files_ratio += $state['extension_mismatch']['mismatch_files_ratio'];
				$extension_mismatch_count++;
			}
		}

		if ($entropy_count > 0) {
			$ret['entropy'] = [
				'baseline_high_ratio' => $baseline_high_ratio / $entropy_count,
				'baseline_average_entropy' => $baseline_average_entropy / $entropy_count
			];
		}

		if ($file_type_count > 0) {
			$ret['file_type'] = [
				'baseline_generic_ratio' => $baseline_generic_ratio / $file_type_count
			];
		}

		if ($extension_mismatch_count > 0) {
			$ret['extension_mismatch'] = [
				'baseline_mismatch_files_ratio' =>
					$baseline_mismatch_files_ratio / $extension_mismatch_count
			];
		}
		return $ret;
	}

	/**
	 * Get main plugin attribute.
	 * Main attribute answers on question what the attribute is used
	 * in the check action.
	 * This is the first parameter defined in the verification rules.
	 *
	 * @return string main attribute
	 */
	public static function getAttribute(): string
	{
		return 'Ransomware detection';
	}

	/**
	 * Get all supported operators by plugin.
	 *
	 * @return array operator list
	 */
	public static function getOperators(): array
	{
		return [];
	}

	/**
	 * Get possible values to select.
	 *
	 * @return array values to select or type
	 */
	public static function getValues(): array
	{
		return [];
	}

	/**
	 * Get checker capabilities.
	 * Capabilities define what data types is able to check and where
	 * it can be used.
	 *
	 * @return array check capabilities
	 */
	public static function getCapabilities(): array
	{
		return [
			RestoreDestinationCapability::FILE_CHECK
		];
	}

	/**
	 * Get checker requirements.
	 * Requirements define what this checker requires to correct working.
	 *
	 * @return array check requirements
	 */
	public static function getRequirements(): array
	{
		return [];
	}

	/**
	 * Set checker plugin configuration.
	 *
	 * @param array $config checker plugin configuration
	 */
	public static function setCheckerConfig(array $config): void
	{
		if (isset($config['parameters']) && is_array($config['parameters'])) {
			self::$config = $config['parameters'];
		} else {
			self::$config = $config;
		}
	}

	/**
	 * Set previous checker result data.
	 * This is for analyse and compare current results with historical data.
	 *
	 * @param array $history previous checker result data
	 */
	public static function setHistory(array $history): void
	{
		self::$history = $history;
	}

	/**
	 * Get current checker result data.
	 *
	 * @param array $result current checker result data
	 */
	public static function getState(array $result): array
	{
		$history_enabled = self::$config['historical-anomaly-detection'] ?? self::DEFAULT_HISTORICAL_ANOMALY_DETECTION;
		return $history_enabled ? self::$state : [];
	}

	/**
	 * Get maximum history size of the checker result data.
	 *
	 * @return int maximum historical data items
	 */
	public static function getHistorySize(): int
	{
		return max(
			1,
			(int) (self::$config['history-size'] ?? 12)
		);
	}

	/**
	 * Get checker configuration properties that have meaning
	 * for keeping history.
	 *
	 * @return array checker configuration properties that impact on history
	 */
	public static function getHistoryConfig(): array
	{
		return [
			'history-version' => self::HISTORY_VERSION,
			'entropy-analysis' => self::$config['entropy-analysis'] ?? self::DEFAULT_ENTROPY_ANALYSIS,
			'entropy-threshold' => self::$config['entropy-threshold'] ?? self::DEFAULT_HIGH_ENTROPY_THRESHOLD,
			'entropy-sample-size' => self::$config['entropy-sample-size'] ?? DataEntropy::DEFAULT_SAMPLE_SIZE,
			'entropy-samples' => self::$config['entropy-samples'] ?? DataEntropy::DEFAULT_SAMPLES,
			'excluded-file-types' => self::$config['excluded-file-types'] ?? self::DEFAULT_EXCLUDED_FILE_TYPES,
			'file-type-analysis' => self::$config['file-type-analysis'] ?? self::DEFAULT_FILE_TYPE_ANALYSIS,
			'file-extension-mismatch' => self::$config['file-extension-mismatch'] ?? self::DEFAULT_FILE_EXTENSION_MISMATCH,
			'modification-time-analysis' => self::$config['modification-time-analysis'] ?? self::DEFAULT_MODIFICATION_TIME_ANALYSIS,
			'modification-time-burst-window' => self::$config['modification-time-burst-window'] ?? self::DEFAULT_MODIFICATION_TIME_BURST_WINDOW,
			'ransom-note-detection' => self::$config['ransom-note-detection'] ?? self::DEFAULT_RANSOM_NOTE_DETECTION,
			'ransom-note-file-patterns' => self::$config['ransom-note-file-patterns'] ?? self::DEFAULT_RANSOM_NOTE_FILE_PATTERNS
		];
	}
}
