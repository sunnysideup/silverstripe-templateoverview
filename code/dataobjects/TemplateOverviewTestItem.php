<?php


/**
 *@author nicolaas [at] sunnysideup . co . nz
 *@description: a test item is something that needs to be checked everytime the site has a new release
 * - e.g. "home page opens when you are logged in"
 * - e.g. "blog entry can be added"
 *
 **/
class TemplateOverviewTestItem extends DataObject {

	private static $db = array(
		"Title" => "Varchar(255)",
		"HowToTestThis" => "Text",
		"HowToCheckIfItWorked" => "Text",
		"Sort" => "Int"
	);


	private static $has_one = array(
		"Screenshot1" => "Image",
		"Screenshot2" => "Image",
		"Screenshot3" => "Image"
	);

	private static $has_many = array(
		"TemplateOverviewTestItemEntry" => "TemplateOverviewTestItemEntry"
	);

	private static $many_many = array(
		"TemplateOverviewDescriptions" => "TemplateOverviewDescription"
	);


	private static $casting = array(); //adds computed fields that can also have a type (e.g.

	private static $searchable_fields = array(
		"Title" => "PartialMatchFilter",
		"HowToTestThis" => "PartialMatchFilter",
		"HowToCheckIfItWorked" => "PartialMatchFilter"
	);

	private static $field_labels = array(
		"Title" => "Name",
		"HowToTestThis" => "How to test this?",
		"HowToCheckIfItWorked" => "How to check if it worked?",
		"Sort" => "Sorting index number"
	);
	private static $summary_fields = array(
		"Title" => "Name"
	); //note no => for relational fields

	private static $singular_name = "Test Item";

	private static $plural_name = "Test Items";
	//CRUD settings

	private static $default_sort = "Sort ASC, Title ASC";

	private static $defaults = array(
		"Sort" => 100
	);//use fieldName => Default Value


	function getCMSFields() {
		$fields = parent::getCMSFields();
		$source = TemplateOverviewDescription::get();
		$fields->removeFieldFromTab("Root", "TemplateOverviewDescriptions");
		$fields->removeFieldFromTab("Root", "TemplateOverviewTestItemEntry");
		$fields->addFieldToTab("Root.TestOnTheseTemplates", new CheckboxSetField(
			"TemplateOverviewDescriptions",
			"Test",
			$source->map('ID','ClassNameLinkFancy')
		));
		return $fields;
	}

	public function populateDefaults() {
		parent::populateDefaults();
		$this->Sort = 100;
	}


}
