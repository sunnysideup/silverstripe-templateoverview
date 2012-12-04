<?php
/**
 *@author nicolaas[at]sunnysideup.co.nz
 *@description model admin for template overview
 **/


class TemplateOverviewDescriptionModelAdmin extends ModelAdmin {


  public $showImportForm = false;

	public static $managed_models = array("TemplateOverviewBug", "TemplateOverviewDescription", "TemplateOverviewTestItem", "TemplateOverviewTestItemEntry");

	public static $url_segment = 'templates';
		static function get_url_segment() {return self::$url_segment;}
		static function get_full_url_segment() {return "/admin/".self::$url_segment;}

	public static $menu_title = 'Bugs';

}
