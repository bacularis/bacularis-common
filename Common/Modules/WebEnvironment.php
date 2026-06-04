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
 * Tools to set up Bacularis web environment.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class WebEnvironment
{
	/**
	 * Check if system is ready for deployment.
	 *
	 * @param string $package_type package type ('deb', 'rpm', 'apk'...etc.)
	 * @param array $cmd_params command options
	 * @return array empty array on validation successfull, otherwise list with detected issues
	 */
	public static function checkSystemForDeployment(string $package_type, array $cmd_params = []): array
	{
		$issues = self::checkReadWriteForDeployment($package_type, $cmd_params);
		return $issues;
	}

	/**
	 * Check if read write access to file system for deployment.
	 *
	 * @param string $package_type package type ('deb', 'rpm', 'apk'...etc.)
	 * @param array $cmd_params command options
	 * @return array empty array on validation successfull, otherwise list with detected issues
	 */
	private static function checkReadWriteForDeployment(string $package_type, array $cmd_params = []): array
	{
		$units = [];
		switch ($package_type) {
			case BinaryPackage::TYPE_DEB: {
				// DEB-based system
				$unit_ver = sprintf(
					'php%d.%d-fpm.service',
					PHP_MAJOR_VERSION,
					PHP_MINOR_VERSION
				);
				$units = [$unit_ver];
				break;
			}
			case BinaryPackage::TYPE_RPM: {
				// RPM-based system
				$units = ['php-fpm.service'];
				break;
			}
		}
		$issues = [];

		// Check if ProtectSystem is enabled for PHP-FPM
		$options = ['ProtectSystem' => ['full', 'strict']];
		for ($i = 0; $i < count($units); $i++) {
			foreach ($options as $option => $values) {
				$value = Systemd::getUnitOption($units[$i], $option, $cmd_params);
				if (in_array($value, $values)) {
					$issues[] = [
						'type' => 'warning',
						'message' => "The systemd unit '{$units[$i]}' has the '{$option}' directive set to '{$value}'. This prevents installation using the selected method.\n\nPlease temporarily disable the '{$option}' directive, restart the '{$units[$i]}' service and then run the installation again.\n\nAfter the installation is complete, you can re-enable the '{$option}' directive.",
						'option' => $option,
						'value' => $value
					];
				}
			}
		}
		return $issues;
	}
}
