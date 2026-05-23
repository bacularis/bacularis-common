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
 * Amazon EC2 wait command support.
 * It waits on a given resource become available or action finished.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Wait
{
	/**
	 * Wait on given EC2 resource, action or state.
	 *
	 * @param string $account AWS account name
	 * @param string $resource wait command parameter that decides about what to wait
	 * @param string $parameters rest command parameters
	 * @return bool true if waiting finished successfully, false otherwise (timeout)
	 */
	public static function waiting(string $account, string $resource, array $parameters = []): bool
	{
		// Prepare command parameters
		$params = [
			'ec2',
			'wait',
			$resource,
		];
		$params = array_merge($params, $parameters);

		$app = Prado::getApplication();
		$aws_cmd = $app->getModule('aws_command');
		$aws_cmd::addGlobalOptions($params);
		$result = $aws_cmd->execCommand($account, $params);
		return ($result['error'] == 0);
	}
}
