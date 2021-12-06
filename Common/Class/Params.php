<?php
/*
 * Bacula(R) - The Network Backup Solution
 * Baculum   - Bacula web interface
 *
 * Copyright (C) 2013-2021 Kern Sibbald
 *
 * The main author of Baculum is Marcin Haba.
 * The original author of Bacula is Kern Sibbald, with contributions
 * from many others, a complete list can be found in the file AUTHORS.
 *
 * You may use this file and others of this release according to the
 * license defined in the LICENSE file, which includes the Affero General
 * Public License, v3.0 ("AGPLv3") and some additional permissions and
 * terms pursuant to its AGPLv3 Section 7.
 *
 * This notice must be preserved when any source code is
 * conveyed and/or propagated.
 *
 * Bacula(R) is a registered trademark of Kern Sibbald.
 */

Prado::using('Application.Common.Class.CommonModule');

/**
 * Params class.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 * @package Baculum Common
 */
class Params extends CommonModule {

	const BACULUM_VERSION = '11.0.5.7';

	public static $months = array(
		'jan' => 'January',
		'feb' => 'February',
		'mar' => 'March',
		'apr' => 'April',
		'may' => 'May',
		'jun' => 'June',
		'jul' => 'July',
		'aug' => 'August',
		'sep' => 'September',
		'oct' => 'October',
		'nov' => 'November',
		'dec' => 'December'
	);

	public static $weeks = array(
		'1st' => 'first',
		'2nd' => 'second',
		'3rd' => 'third',
		'4th' => 'fourth',
		'5th' => 'fifth',
		'6th' => 'sixth'
	);
	public static $wdays = array(
		'sun' => 'Sunday',
		'mon' => 'Monday',
		'tue' => 'Tuesday',
		'wed' => 'Wednesday',
		'thu' => 'Thursday',
		'fri' => 'Friday',
		'sat' => 'Saturday'
	);

	/**
	 * Get time value in config form.
	 * Possible is of three modes:
	 *  - hourly at specified minute
	 *  - hourly on every full hour
	 *  - daily at specified hour and minute
	 *
	 * @param string $hour time hour
	 * @param string $minute time minute
	 * @return string time config value
	 */
	public static function getTimeConfig(array $hour, $minute) {
		$t = '';
		$hour_len = count($hour);
		$is_hourly = ($hour_len == 24);
		$is_daily = ($hour_len == 1);
		if ($is_hourly && is_int($minute) && $minute > 0) {
			// hourly at minute
			$min = sprintf('%02d', $minute);
			$t = "hourly at 0:{$min}";
		} elseif ($is_daily && is_int($minute)) {
			// at specified hour and minute
			$min = sprintf('%02d', $minute);
			$t = "at {$hour[0]}:{$min}";
		} else {
			// hourly every full hour
			$t = 'hourly';
		}
		return $t;
	}
	/**
	 * Get months of the year value in config form.
	 *
	 * @param array $moys_cfg month array (ex. [0,1,2,3,4])
	 * @return string months of the year config value
	 */
	public static function getMonthsOfYearConfig(array $moys_cfg) {
		$month = '';
		$months = array_keys(Params::$months);
		$moys_len = count($moys_cfg);
		if ($moys_len < 12) {
			$moy_value_cfg = [];
			for ($i = 0; $i < $moys_len; $i++) {
				$moy_value_cfg[] = $months[$moys_cfg[$i]];
			}
			$month = implode(',', $moy_value_cfg);
		}
		return $month;
	}

	/**
	 * Get weeks of the month value in config form.
	 *
	 * @param array $woms_cfg week array (ex. [0,1,4])
	 * @return string weeks of the month config value
	 */
	public static function getWeeksOfMonthConfig(array $woms_cfg) {
		$week = '';
		$woms_len = count($woms_cfg);
		$weeks = array_keys(Params::$weeks);
		if ($woms_len < 6) {
			$wom_value_cfg = [];
			for ($i = 0; $i < $woms_len; $i++) {
				$wom_value_cfg[] = $weeks[$woms_cfg[$i]];
			}
			$week = implode(',', $wom_value_cfg);
		}
		return $week;
	}

	/**
	 * Get days of the week value in config form.
	 *
	 * @param array $dows_cfg day array (ex. [0,1,5])
	 * @return string days of the week config value
	 */
	public static function getDaysOfWeekConfig(array $dows_cfg) {
		$wday = '';
		$dows_len = count($dows_cfg);
		$wdays = array_keys(Params::$wdays);
		if ($dows_len < 7) {
			$dow_value_cfg = [];
			for ($i = 0; $i < $dows_len; $i++) {
				$dow_value_cfg[] = $wdays[$dows_cfg[$i]];
			}
			$wday = implode(',', $dow_value_cfg);
		}
		return $wday;
	}

	/**
	 * Get days of the month value in config form.
	 * Zero-length $doms_cfg means lastday of the month.
	 *
	 * @param array $doms_cfg day array (ex. [0,1,5,22,30])
	 * @return string days of the month config value
	 */
	public static function getDaysOfMonthConfig(array $doms_cfg) {
		$days = '';
		$doms_len = count($doms_cfg);
		if ($doms_len === 0) {
			$days = 'on lastday';
		} elseif ($doms_len < 31) {
			$doms_w = array_map(function($el) {
				return ++$el;
			}, $doms_cfg);
			$days = 'on ' . implode(',', $doms_w);
		}
		return $days;
	}

	/**
	 * Get weeks of year value in config form.
	 *
	 * @param array $woy_cfg week array (ex. array(0,1,2,3,4))
	 * @return string weeks of the year config value
	 */
	public static function getWeeksOfYearConfig(array $woys_cfg) {
		$weeks = '';
		$woys_len = count($woys_cfg);
		if ($woys_len < 54) {
			$woys_w = array_map(function($el) {
				return ('w' . sprintf('%02d', $el));
			}, $woys_cfg);
			$weeks = implode(',', $woys_w);
		}
		return $weeks;
	}


	/**
	 * Simple method to prepare day config value by day number.
	 *
	 * @static
	 * @return integer single day config value
	 */
	private static function getDayByNo($day_no) {
		return ++$day_no;
	}

	/**
	 * Get Bacula config boolean value.
	 *
	 * @param boolean $value value
	 * @return string bacula config boolean value
	 */
	public static function getBoolValue($value) {
		return ($value ? 'yes' : 'no');
	}

	/**
	 * Get component version basing on given output.
	 * The version string in output should be component status command compatible.
	 *
	 * @param array $output component status output
	 * @return array major, minor and release numbers.
	 */
	public static function getComponentVersion(array $output) {
		$version = array('major' => 0, 'minor' => 0, 'release' => 0);
		for ($i = 0; $i < count($output); $i++) {
			if (preg_match('/^[\w\d\s:.\-]+Version:\s+(?P<major>\d+)\.(?P<minor>\d+)\.(?P<release>\d+)\s+\(/', $output[$i], $match) === 1) {
				$version['major'] = intval($match['major']);
				$version['minor'] = intval($match['minor']);
				$version['release'] = intval($match['release']);
				break;
			}
		}
		return $version;
	}
}
?>
