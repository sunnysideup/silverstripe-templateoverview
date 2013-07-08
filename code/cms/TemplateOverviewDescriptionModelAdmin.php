<?php
/**
 *@author nicolaas[at]sunnysideup.co.nz
 *@description model admin for template overview
 **/

class TemplateOverviewDescriptionModelAdmin extends ModelAdmin {


	public $showImportForm = false;

	public static $managed_models = array("TemplateOverviewBug", "TemplateOverviewDescription", "TemplateOverviewTestItem", "TemplateOverviewTestItemEntry");

	public static $url_segment = 'templates';

	public static $menu_title = 'Bugs';

	public static function get_full_url_segment($action = null){
		$obj = singleton("TemplateOverviewDescriptionModelAdmin");
		return $obj->Link($action);
	}

}
