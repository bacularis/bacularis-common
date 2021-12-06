<?php
/*
 * Bacula(R) - The Network Backup Solution
 * Baculum   - Bacula web interface
 *
 * Copyright (C) 2013-2019 Kern Sibbald
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

Prado::using('Application.Common.Class.Interfaces');
Prado::using('Application.Common.Class.CommonModule');

/**
 * Module to read/write Bacula-style config.
 * Note: Only write config implemented. More info in read() method.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 * @package Baculum Common
 */
class ConfigBacula extends CommonModule implements ConfigFormat {

	/**
	 * Whitespace character used as indent.
	 */
	const INDENT_CHAR = ' ';

	/**
	 * Single indent size.
	 */
	const INDENT_SIZE = 2;

	/**
	 * Write config data to file in Bacula format.
	 *
	 * @access public
	 * @param string $source config file path
	 * @param array $config config data
	 * @return boolean true if config written successfully, otherwise false
	 */
	public function write($source, $config) {
		$content = $this->prepareConfig($config);
		$orig_umask = umask(0);
		umask(0077);
		$result = file_put_contents($source, $content);
		umask($orig_umask);
		return is_int($result);
	}

	/**
	 * Read config data from file in Bacula format.
	 *
	 * @access public
	 * @param string $source config file path
	 * @return none
	 */
	public function read($source) {
		// reading Bacula config files is done by Bacula JSON tools.
	}

	/**
	 * Prepare config data to save in Bacula format.
	 *
	 * @access public
	 * @param array $config config data
	 * @return string config content
	 */
	public function prepareConfig($config) {
		$content = '';
		for ($i = 0; $i < count($config); $i++) {
			$content .= $this->prepareResource($config[$i]);
		}
		return $content;
	}

	/**
	 * Prepare single resource content in Bacula format.
	 *
	 * @access private
	 * @param array $resource resource data
	 * @param string $nesting_number current (sub)resource number
	 * @return string resource content
	 */
	private function prepareResource($resource, $nesting_number = 0) {
		$content = '';
		foreach($resource as $name => $value) {
			$indent = $this->getIndent($nesting_number);
			$content .= $indent . "$name {\n";
			$nesting_number++;
			$content .= $this->prepareSubResource($value, null, $nesting_number);
			$content .= "}\n";
			$nesting_number--;
		}
		return $content;
	}

	/**
	 * Prepare single sub-resource content (nested resource).
	 *
	 * @access private
	 * @param array $subresource subresource data
	 * @param string $rname resource name
	 * @param integer $nesting_number current (sub)resource number
	 * @return string subresource content
	 */
	private function prepareSubResource($subresource, $rname, $nesting_number) {
		$content = '';
		if (is_string($rname)) {
			$indent = $this->getIndent($nesting_number);
			$content .= $indent . "$rname {\n";
			$nesting_number++;
		}
		foreach ($subresource as $name => $value) {
			if (is_array($value)) {
				$keys = array_keys($value);
				$vals = array_filter($keys, 'is_string');
				if (count($vals) > 0) {
					// associative array
					$content .= $this->prepareSubResource($value, $name, $nesting_number);
				} else {
					// numeric array
					for ($i = 0; $i < count($value); $i++) {
						if (is_array($value[$i])) {
							$content .= $this->prepareSubResource($value[$i], $name, $nesting_number);
						} else {
							$val = $this->prepareValue($value[$i]);
							$indent = $this->getIndent($nesting_number);
							$content .= $indent . "$name = $val\n";
						}
					}
				}
			} else {
				$val = $this->prepareValue($value);
				$indent = $this->getIndent($nesting_number);
				$content .= $indent . "$name = $val\n";
			}
		}
		if (is_string($rname)) {
			$nesting_number--;
			$indent = $this->getIndent($nesting_number);
			$content .= $indent . "}\n";
		}
		return $content;
	}


	/**
	 * Prepare value written to Bacula-style config.
	 *
	 * @access private
	 * @param string $value text to prepare
	 * @return string value ready to write
	 */
	private function prepareValue($value) {
		if (is_array($value)) {
			$value = implode(',', $value);
		}
		return $value;
	}

	/**
	 * Get indent value.
	 *
	 * @access private
	 * @param integer $multiplier number of single indents
	 * @return string indent value
	 */
	private function getIndent($multiplier) {
		$indent = '';
		if ($multiplier > 0) {
			$size = self::INDENT_SIZE * $multiplier;
			$indent = str_repeat(self::INDENT_CHAR, $size);
		}
		return $indent;
	}
}
