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

Prado::using('System.Web.UI.WebControls.TDataBoundControl');
Prado::using('Application.Common.Portlets.BSimpleRepeaterItem');

/**
 * Baculum simple repeater control.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Control
 * @package Baculum Web
 */
class BSimpleRepeater extends TDataBoundControl {

	/**
	 * Stores item template object
	 */
	private $_itemTemplate;

	/**
	 * Get template for items.
	 *
	 * @return ITemplate template
	 */
	public function getItemTemplate() {
		return $this->_itemTemplate;
	}

	/**
	 * Set template for items.
	 *
	 * @param ITemplate $tpl template
	 * @return none
	 */
	public function setItemTemplate($tpl) {
		if ($tpl instanceof \Prado\Web\UI\ITemplate) {
			$this->_itemTemplate = $tpl;
		}
	}

	/**
	 * Data binding.
	 *
	 * @param $data data from data source
	 * @return none
	 */
	protected function performDataBinding($data) {
		for ($i = 0; $i < count($data); $i++) {
			$this->createItem($data[$i]);
		}
	}

	/**
	 * Create single repeater item.
	 *
	 * return BSimpleRepeaterItem repeater item
	 */
	private function createItem($data) {
		$item = new BSimpleRepeaterItem;
		if ($item instanceof \Prado\IDataRenderer) {
			$item->setData($data);
		}
		if ($this->_itemTemplate) {
			$this->_itemTemplate->instantiateIn($item, $this);
		}
		return $item;
	}

	public function render($writer) {
		$this->renderChildren($writer);
	}
}
?>
