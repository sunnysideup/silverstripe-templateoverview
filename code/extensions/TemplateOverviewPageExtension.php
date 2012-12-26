<?php

/**
 *@author nicolaas[at]sunnysideup.co.nz
 *@description: adds functionality to controller for dev purposes only
 *
 **/


class TemplateOverviewPageExtension extends Extension {

	protected $templateList = null;

	public function TemplateOverviewPage() {
		return DataObject::get_one("TemplateOverviewPage");
	}
	public function BugManagementLink() {
		return TemplateOverviewDescriptionModelAdmin::get_full_url_segment();
	}

	public function IncludeTemplateOverviewDevelopmentFooter() {
		if(Director::isDev()) {
			Requirements::javascript($this->owner->project()."/javascript/TemplateOverviewOverlay.js");
			Requirements::javascript("templateoverview/javascript/TemplateOverviewExtension.js");
			Requirements::themedCSS("TemplateOverviewExtension", "templateoverview");
			return true;
		}
		return false;
	}

	function NextTemplateOverviewPage() {
		$list = $this->TemplateList();
		$doIt = false;
		if($list) {
			foreach($list as $page) {
				if($doIt) {
					return $page;
				}
				if($page->ClassName == $this->owner->ClassName) {
					$doIt = true;
				}
			}
		}
	}

	function PrevTemplateOverviewPage() {
		$list = $this->TemplateList();
		$doIt = false;
		if($list) {
			foreach($list as $page) {
				if($page->ClassName == $this->owner->ClassName) {
					$doIt = true;
				}
				if($doIt && isset($previousPage)) {
					return $previousPage;
				}
				$previousPage = $page;
			}
		}
	}

	function TemplateList() {
		if(!$this->templateList) {
			$page = DataObject::get_one("TemplateOverviewPage");
			if($page) {
				$this->templateList = $page->ListOfAllClasses();
			}
		}
		return $this->templateList;
	}

	function TemplateOverviewBugs() {
		$templateID = 0;
		if($templateOverviewDescription = DataObject::get_one("TemplateOverviewDescription", "\"ClassNameLink\" = '".$this->owner->ClassName."'")) {
			$templateID = $templateOverviewDescription->ID;
		}
		return DataObject::get(
			"TemplateOverviewBug",
			"\"Fixed\" <> 1 AND (((\"TemplateID\" = 0 AND \"PageID\" = 0 ) OR \"TemplateID\" = ".$templateID.") OR ((\"PageID\" = 0 AND \"TemplateID\")  OR \"PageID\" = ".$this->owner->ID.")  )",
			"PageID DESC, TemplateID DESC"
		);
	}

	function TemplateDescriptionForThisClass(){
		return DataObject::get_one("TemplateOverviewDescription", "ClassNameLink = '".$this->owner->ClassName."'");
	}

}
