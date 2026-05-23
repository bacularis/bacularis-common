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

namespace Bacularis\Common\Modules\Cloud\Amazon\EC2;

use Prado\Prado;

/**
 * Amazon tags module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Tag
{
	/**
	 * Get tag specifications from key/val string.
	 * String tag form is used in plugin tag parameters.
	 *
	 * @param string $resource_type Amazon resource type ex: volume, snapshot, vpc ...etc.
	 * @param string $tag_str comma separated tags in form 'key1=val1,key2=val2...'
	 * @return string tag specifications ready to use in command
	 */
	public static function getTagSpecificationsFromString(string $resource_type, string $tag_str): string
	{
		$tags = explode(',', $tag_str);
		$tag_list = [];
		for ($i = 0; $i < count($tags); $i++) {
			if (substr_count($tags[$i], '=') != 1) {
				continue;
			}
			[$name, $value] = explode('=', $tags[$i], 2);
			$tag_list[] = [
				'Key' => trim($name),
				'Value' => trim($value)
			];
		};
		$ret = self::getTagSpecifications($resource_type, $tag_list);
		return $ret;
	}

	/**
	 * Get tag specifications.
	 *
	 * @param string $resource_type Amazon resource type ex: volume, snapshot, vpc ...etc.
	 * @param array $tags comma separated tags
	 * @return string tag specifications ready to use in command
	 */
	public static function getTagSpecifications(string $resource_type, array $tags): string
	{
		$tag_list = [];
		for ($i = 0; $i < count($tags); $i++) {
			$tag = array_change_key_case($tags[$i], CASE_LOWER);
			$tag_list[] = [
				'Key' => $tag['key'],
				'Value' => $tag['value']
			];
		}

		$ret = '';
		if ($tag_list) {
			$tag_specs = [[
				'ResourceType' => $resource_type,
				'Tags' => $tag_list
			]];
			$ret = json_encode($tag_specs);
		}
		return $ret;
	}

	/**
	 * Create tags on AWS resources.
	 *
	 * @param string $account AWS account name
	 * @param array $resources AWS resource ID list
	 * @param array $tags tag list
	 * @return bool true on success, false otherwise
	 */
	public static function createTags(string $account, array $resources, array $tags): bool
	{
		$resource_list = implode(' ', $resources);
		$tag_list = json_encode($tags);

		$command = [
			'ec2',
			'create-tags',
			"--resources '{$resource_list}'",
			"--tags '{$tag_list}'"
		];
		$app = Prado::getApplication();
		$aws_cmd = $app->getModule('aws_command');
		$result = $aws_cmd->execCommand($account, $command);

		$ret = ($result['error'] == 0);
		return $ret;
	}
}
