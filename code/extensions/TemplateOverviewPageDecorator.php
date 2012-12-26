<?php

/**
 *@author nicolaas[at]sunnysideup.co.nz
 *@description: adds functionality to controller for dev purposes only
 *
 **/


class TemplateOverviewPageDecorator extends SiteTreeExtension {


	function updateCMSFields(FieldList $fields) {
		if(method_exists($this->owner,'getHowToMakeThisTemplateWorkArray')) {
			$array = $this->owner->getHowToMakeThisTemplateWorkArray();
			if(is_array($array) && count($array)) {
				$fields->addFieldToTab("Root.Help", new LiteralField(
					"HowToMakeThisPageWork",
					'<h3 id="HowToMakeThisPageWorkHeader">'._t("TemplateOverviewPageDecorator.HOWTOMAKEITWORKHEADER", "How to make this page work").'</h3>'
						.'<ul id="HowToMakeThisPageWorkList"><li>'.implode("</li><li>",$array).'</li></ul>'
				));
			}
		}
		$obj = DataObject::get_one("TemplateOverviewDescription", "ClassNameLink = '".$this->owner->ClassName."'");
		if($obj) {
			if($obj->ToDoListHyperLink) {
				$fields->replaceField("ToDo", new LiteralField("ToDo", '<p><a href="'.$obj->ToDoListHyperLink.'" target="todo">review to do items...</a></p>'));
			}
			$fields->addFieldToTab("Root.Help", new LiteralField("MoreHelp", $obj->renderWith("TemplateOverviewPageCMSDetail")));
		}
	}

}
