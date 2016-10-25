<?php
/**
 *@author nicolaas[at]sunnysideup.co.nz
 *@description model admin for template overview
 **/

class TemplateOverviewDescriptionModelAdmin extends ModelAdmin
{
    public $showImportForm = false;

    private static $managed_models = array("TemplateOverviewDescription");

    private static $url_segment = 'templates';

    private static $menu_title = 'Templates';

    public static function get_full_url_segment($action = null)
    {
        $obj = singleton("TemplateOverviewDescriptionModelAdmin");
        return $obj->Link($action);
    }
}
