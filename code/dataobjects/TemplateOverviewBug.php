<?php

class TemplateOverviewBug extends DataObject {

	protected static $error_email = "";
		static function set_error_email($s) {self::$error_email = $s;}
		static function get_error_email() {return self::$error_email;}

	static $db = array(
		"Title" => "Varchar(255)",
		"WhatWasExpected" => "Text",
		"WhatActuallyHappened" => "Text",
		"OtherInformation" => "Text",
		"QuestionsFromDeveloper" => "Text",
		"URL" => "Varchar(255)",
		"OutcomeNote" => "Text",
		"FixedBy" => "Varchar",
		"Fixed" => "Boolean",
		"NeedsMoreInformation" => "Boolean"
	);

	static $has_one = array(
		"Screenshot1" => "Image",
		"Screenshot2" => "Image",
		"Screenshot3" => "Image",
		"Screenshot4" => "Image",
		"Screenshot5" => "Image",
		"Screenshot6" => "Image",
		"Screenshot7" => "Image",
		"Member" => "Member",
		"Template" => "TemplateOverviewDescription",
		"Page" => "SiteTree"
	);

	public static $searchable_fields = array(
		"Title" => "PartialMatchFilter",
		"NeedsMoreInformation",
		"Fixed",
		"TemplateID"
	);

	public static $summary_fields = array(
		"Title",
		"NeedsMoreInformation",
		"Fixed",
		"Template.Title" => "Template"
	);

	public static $field_labels = array(
		"Title" => "Short Description (e.g. newsletter page does not open)",
		"Member" => "Reported by",
	);

	public static $singular_name = "Bug report";

	public static $plural_name = "Bug reports";
	//CRUD settings
	public static $default_sort = "Fixed, LastEdited DESC, Created DESC";



	function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->removeByName("Screenshot1ID");
		$fields->removeByName("Screenshot1ID");
		$fields->removeByName("Screenshot3ID");
		$fields->removeByName("Screenshot4ID");
		$fields->removeByName("Screenshot5ID");
		$fields->removeByName("Screenshot6ID");
		$fields->removeByName("Screenshot7ID");
		$fields->removeByName("PageID");
		$fields->removeByName("TemplateID");
		$fields->removeByName("MemberID");
		$fields->removeByName("Fixed");
		$fields->removeByName("FixedBy");
		$fields->removeByName("OutcomeNote");
		$fields->removeByName("NeedsMoreInformation");
		$fields->removeByName("QuestionsFromDeveloper");
		$fields->removeByName("URL");
		if(!$this->PageID) {
			$fields->addFieldToTab("Root.RelatesTo", new TextField("URL", "URL - e.g. http://www.mysite.com/mypage/with/abug/"));
		}
		else {
			if($page = $this->Page()) {
				$fields->addFieldToTab("Root.RelatesTo", new LiteralField("Link", "<p>Go to <a href=\"".$page->Link()."\">".$page->Title."</a>  page.</p>"));
			}
		}
		$fields->addFieldToTab("Root.RelatesTo", new TreeDropdownField("PageID", "Relevant page (if any)", "SiteTree"));
		$templates = DataObject::get("TemplateOverviewDescription");
		if($templates) {
			$fields->addFieldToTab("Root.RelatesTo", new DropdownField("TemplateID", "Relevant page type (if any)", $templates->toDropdownMap('ID','ClassNameLinkFancy', " --- please select ---")));
		}

		$fields->addFieldToTab("Root.ScreenShots", new LiteralField("HowToMakeAScreenShot", '<h3>Learn how to make <a target="_blank" href="http://www.google.com.au/search?&q=How+To+Make+ScreenShot">a screenshot</a></h3>'));
		$fields->addFieldToTab("Root.ScreenShots", new ImageField("Screenshot1", "Required First screenshot", $value = null, $form = null, $rightTitle = null, $folderName = "Bugs"));
		$fields->addFieldToTab("Root.ScreenShots", new ImageField("Screenshot2", "Second screenshot (optional)", $value = null, $form = null, $rightTitle = null, $folderName = "Bugs"));
		$fields->addFieldToTab("Root.ScreenShots", new ImageField("Screenshot3", "Third screenshot (optional)", $value = null, $form = null, $rightTitle = null, $folderName = "Bugs"));
		$fields->addFieldToTab("Root.ScreenShots", new ImageField("Screenshot4", "Fourth screenshot (optional)", $value = null, $form = null, $rightTitle = null, $folderName = "Bugs"));
		$fields->addFieldToTab("Root.ScreenShots", new ImageField("Screenshot5", "Fifth screenshot (optional)", $value = null, $form = null, $rightTitle = null, $folderName = "Bugs"));
		$fields->addFieldToTab("Root.ScreenShots", new ImageField("Screenshot6", "Sixth screenshot (optional)", $value = null, $form = null, $rightTitle = null, $folderName = "Bugs"));
		$fields->addFieldToTab("Root.ScreenShots", new ImageField("Screenshot7", "Seventh screenshot (optional)", $value = null, $form = null, $rightTitle = null, $folderName = "Bugs"));
		$fields->addFieldToTab("Root.Outcome", new CheckboxField("NeedsMoreInformation", "Developer needs more information from website owner"));
		$fields->addFieldToTab("Root.Outcome", new TextareaField("QuestionsFromDeveloper", "Questions from developer"));
		if(!$this->NeedsMoreInformation) {
			$fields->addFieldToTab("Root.Outcome", new CheckboxField("Fixed", "Fixed"));
			$fields->addFieldToTab("Root.Outcome", new TextField("FixedBy", "(To be) Fixed by ..."));
			$fields->addFieldToTab("Root.Outcome", new TextareaField("OutcomeNote", "Notes about fix..."));
		}
		return $fields;
	}

	function Link($action = "") {
		return TemplateOverviewDescriptionModelAdmin::get_full_url_segment()."TemplateOverviewBug/".$this->ID."/edit/";
	}



	function onBeforeWrite() {
		parent::onBeforeWrite();
		$this->MemberID = Member::currentUserID();
	}

	function onAfterWrite() {
		if(!$this->Fixed) {
			if($this->NeedsMoreInformation) {
				$email = new Email(self::get_error_email(), Email::$admin_email_address, $subject = "bug needs more information on ".Director::absoluteBaseURL(), $body = "see ".Director::absoluteBaseURL().TemplateOverviewDescriptionModelAdmin::get_full_url_segment());
			}
			else {
				$email = new Email(Email::$admin_email_address, self::get_error_email(), $subject = "new bug on ".Director::absoluteBaseURL(), $body = "see ".Director::absoluteBaseURL().TemplateOverviewDescriptionModelAdmin::get_full_url_segment());
			}
			$email->send();
		}
		if(!$this->Title) {
			$this->Title = "BUG #".$this->ID;
			$this->write();
		}
	}


}
