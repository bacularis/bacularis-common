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
 * Module to read/write section config style.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class ConfigSection extends CommonModule implements IConfigFormat
{
	/**
	 * Section name.
	 */
	private $section = '';

	/**
	 * Whitespace character used as indent.
	 */
	private $indent_char = ' ';

	/**
	 * Single indent size.
	 */
	private $indent_size = 2;

	/**
	 * Set section name.
	 *
	 * @param string $section section name
	 */
	public function setSectionName($section)
	{
		$this->section = $section;
	}

	/**
	 * Get section name.
	 *
	 * @return string section name
	 */
	public function getSectionName()
	{
		return $this->section;
	}

	/**
	 * Set indent character.
	 *
	 * @param string $char indent character
	 */
	public function setIndentChar($char)
	{
		$this->indent_char = $char;
	}

	/**
	 * Get indent character.
	 *
	 * @return string indent character
	 */
	public function getIndentChar()
	{
		return $this->indent_char;
	}

	/**
	 * Set indent size.
	 *
	 * @param int $size indent size
	 */
	public function setIndentSize($size)
	{
		$this->indent_size = $size;
	}

	/**
	 * Get indent size.
	 *
	 * @return int indent size
	 */
	public function getIndentSize()
	{
		return $this->indent_size;
	}

	private function getIndent()
	{
		return str_repeat($this->indent_char, $this->indent_size);
	}

	/**
	 * Write config data to file in section format.
	 *
	 * @param string $source config file path
	 * @param array $config config data
	 * @return bool true if config written successfully, otherwise false
	 */
	public function write($source, $config)
	{
		$content = $this->prepareConfig($config);
		$orig_umask = umask(0);
		umask(0077);
		$result = file_put_contents($source, $content);
		umask($orig_umask);
		return is_int($result);
	}

	/**
	 * Read config data from file in section format.
	 *
	 * @param string $source config file path
	 * @return array config data or empty array if empty config
	 */
	public function read($source)
	{
		$content = [];
		$cfg = file($source, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if (is_array($cfg)) {
			$content = $this->parseConfig($cfg);
		}
		return $content;
	}

	/**
	 * Parse config content.
	 *
	 * @param content config with one line per array item
	 * @param array $content
	 * @return array parsed config
	 */
	private function parseConfig(array $content)
	{
		$config = [];
		$section = '';
		$pattern_sect = '/^' . $this->section . '\s(?P<section>\S+)$/';
		$pattern_opt = '/^' . $this->getIndent() . '(?P<option>\S+)\s=\s(?P<value>[\S\s]*)$/';
		for ($i = 0; $i < count($content); $i++) {
			if (preg_match($pattern_sect, $content[$i], $match) === 1) {
				// section
				$section = $match['section'];
				$config[$section] = [];
			} elseif (!empty($section) && preg_match($pattern_opt, $content[$i], $match) === 1) {
				// option and value
				$config[$section][$match['option']] = $match['value'];
			}
		}
		return $config;
	}

	/**
	 * Prepare config data to save in section format.
	 *
	 * @param array $config config data
	 * @return string config content
	 */
	public function prepareConfig($config)
	{
		$content = '';
		foreach ($config as $section => $options) {
			$content .= "{$this->section} $section\n";
			foreach ($options as $option => $value) {
				$value = $value;
				$content .= $this->getIndent() . "$option = $value\n";
			}
			$content .= "\n";
		}
		return $content;
	}

	public function isConfigValid($required_options, $config, $path)
	{
		// @TODO: Add validating section config
		return true;
	}
}
