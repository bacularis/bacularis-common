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
 * Module to read/write INI-style config in AWS CLI config-like form.
 * For Bacularis purposes We named this format NINI (Nested-Ini).
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class ConfigNIni extends ConfigIni
{
	/**
	 * Parse configuration file data in NIni format.
	 *
	 * @param string $content raw configuration text
	 * @return array parsed configuration
	 */
	protected function parseConfig(string $content): array
	{
		$result = [];
		$section = $subsection = '';
		$config = explode(PHP_EOL, $content);
		for ($i = 0; $i < count($config); $i++) {
			$line = rtrim($config[$i]);
			if (preg_match('/^\[(?P<section>[^\]]+)\]$/', $line, $match) === 1) {
				// section line
				$section = $match['section'];
				$result[$section] = [];
				$subsection = '';
				continue;
			}
			if (!$line || !$section) {
				// empty line
				continue;
			}
			if (preg_match('/^(?P<key>\w+)\s*=\s*(?P<value>[\s\S]+)$/', $line, $match) === 1) {
				// regular line
				if ($section) {
					$result[$section][$match['key']] = $match['value'];
				}
				$subsection = '';
				continue;
			} elseif (preg_match('/^\s+(?P<subsection>\w+)\s*=\s*$/', $line, $match) === 1) {
				// subsection line
				$subsection = $match['subsection'];
				$result[$section][$subsection] = [];
				continue;
			} elseif (preg_match('/^\s+(?P<key>\w+)\s*=\s*(?P<value>[\s\S]+)$/', $line, $match) === 1) {
				// subsection value line
				if ($section) {
					if ($subsection) {
						// add value to subsection
						$result[$section][$subsection][$match['key']] = $match['value'];
					} else {
						// add value to section (because there is no subsection)
						$result[$section][$match['key']] = $match['value'];
					}
					continue;
				}
			}
		}
		return $result;
	}

	/**
	 * Prepare config data to save in NINI format.
	 *
	 * @access public
	 * @param array $config config data
	 * @return string config content
	 */
	public function prepareConfig($config)
	{
		$content = '';
		foreach ($config as $section => $options) {
			$content .= "[$section]\n";
			foreach ($options as $option => $value) {
				if (is_array($value)) {
					$content .= "{$option} =\n";
					foreach ($value as $k => $v) {
						$content .= "  $k = $v\n";
					}
				} else {
					$content .= "$option = $value\n";
				}
			}
			$content .= "\n";
		}
		return $content;
	}
}
