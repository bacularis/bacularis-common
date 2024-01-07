<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2024 Marcin Haba
 *
 * The main author of Bacularis is Marcin Haba, with contributors, whose
 * full list can be found in the AUTHORS file.
 *
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

namespace Bacularis\Common\Modules;

use Prado\Prado;
use Prado\Exceptions\THttpException;
use Prado\Web\TUrlMapping;

/**
 * Baculum URL mapping class.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category URL
 */
class BaculumUrlMapping extends TUrlMapping
{
	public const DEFAULT_SERVICE_ID = 'web';

	private $services = [
		'web' => [
			'url_manager' => 'Bacularis.Web.Modules.WebUrlMapping',
			'url_pattern' => '!^(/index\.php)?/web([/,].*)?$!',
			'endpoints' => 'Bacularis.Web.endpoints'
		],
		'api' => [
			'url_manager' => 'Bacularis.API.Modules.APIUrlMapping',
			'url_pattern' => '!^(/index\.php)?/api([/,].*)?$!',
			'endpoints' => 'Bacularis.API.Pages.API.endpoints'
		],
		'oauth' => [
			'url_manager' => 'Bacularis.API.Modules.OAuthUrlMapping',
			'url_pattern' => '!^(/index\.php)?/oauth([/,].*)?$!',
			'endpoints' => 'Bacularis.API.Pages.OAuth2.endpoints'
		],
		'panel' => [
			'url_manager' => 'Bacularis.API.Modules.PanelUrlMapping',
			'url_pattern' => '!^(/index\.php)?/panel([/,].*)?$!',
			'endpoints' => 'Bacularis.API.Pages.Panel.endpoints'
		]
	];

	public function __construct()
	{
		parent::__construct();
		$this->setServiceUrlManager();
	}

	/**
	 * Get all pages for current service.
	 * Pages are taken directly from configuration file.
	 *
	 * @return array all pages for service.
	 */
	public function getPages()
	{
		$pages = [];
		foreach ($this->_patterns as $pattern) {
			$pages[] = $pattern->getServiceParameter();
		}
		return $pages;
	}

	private function getServiceID()
	{
		$service_id = null;
		$url = $this->getRequestedUrl();
		foreach ($this->services as $id => $params) {
			if (preg_match($params['url_pattern'], $url) === 1) {
				$service_id = $id;
				break;
			}
		}
		return $service_id;
	}

	private function setServiceUrlManager()
	{
		$service_id = $this->getServiceID() ?: self::DEFAULT_SERVICE_ID;
		$url = $this->getRequestedUrl();
		if (array_key_exists($service_id, $this->services)) {
			$service = $this->services[$service_id];
			$path = Prado::getPathOfNamespace($service['url_manager'], Prado::CLASS_FILE_EXT);
			if (file_exists($path)) {
				$this->setDefaultMappingClass($service['url_manager']);
				$this->setConfigFile($service['endpoints']);
			}
		} elseif (!empty($url)) {
			throw new THttpException(404, 'pageservice_page_unknown', $url);
		}
	}

	private function getRequestedUrl()
	{
		$path_info = $this->getRequest()->getPathInfo();
		if ($path_info == '/') {
			$path_info = '';
		}
		return $path_info;
	}
}
