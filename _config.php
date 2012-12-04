<?php

/**
 * developed by www.sunnysideup.co.nz
 * author: Nicolaas - modules [at] sunnysideup.co.nz
 *
 **/

ErrorPage::$icon = "templateoverview/images/treeicons/ErrorPage";

//copy the lines between the START AND END line to your /mysite/_config.php file and choose the right settings
//===================---------------- START templateoverview MODULE ----------------===================
//MUST SET
//TemplateOverviewBug::set_error_email("a@b.com");
//if(Director::isDev()) {
  //Object::add_extension('Page_Controller', 'TemplateOverviewPageExtension');
	//MAY SET
  //Object::add_extension('SiteTree', 'TemplateOverviewPageDecorator');
  //Director::addRules(7, array('error/report' => 'ErrorNotifierController'));
//}
//TemplateOverviewPage::set_auto_include(true);
//LeftAndMain::require_css("templateoverview/css/TemplateOverviewCMSHelp.css");
//any file in the folder below which starts with the Exact ClassName - e.g. HomePage or HomePage1 or HomePage_MoreDetail
//will be added to the template description.
//TemplateOverviewDescription::set_image_source_folder("_dev/designs");

// --- help files ---
//CMSHelp::set_help_file_directory_name("_help");
//LeftAndMain::$help_link = "admin/help/";
//===================---------------- END templateoverview MODULE ----------------===================

