<?php


/**
 *@author nicolaas [at] sunnysideup . co . nz
 *@description: a test item is something that needs to be checked everytime the site has a new release
 * - e.g. "home page opens when you are logged in"
 * - e.g. "blog entry can be added"
 *
 **/
class TemplateOverviewTestItem extends DataObject {

	static $db = array(
		"Title" => "Varchar(255)",
		"HowToTestThis" => "Text",
		"HowToCheckIfItWorked" => "Text",
		"Sort" => "Int"
	);


	static $has_one = array(
		"Screenshot1" => "Image",
		"Screenshot2" => "Image",
		"Screenshot3" => "Image"
	);

	static $has_many = array(
		"TemplateOverviewTestItemEntry" => "TemplateOverviewTestItemEntry"
	);

	static $many_many = array(
		"TemplateOverviewDescriptions" => "TemplateOverviewDescription"
	);


	public static $casting = array(); //adds computed fields that can also have a type (e.g.

	public static $searchable_fields = array(
		"Title" => "PartialMatchFilter",
		"HowToTestThis" => "PartialMatchFilter",
		"HowToCheckIfItWorked" => "PartialMatchFilter"
	);

	public static $field_labels = array(
		"Title" => "Name",
		"HowToTestThis" => "How to test this?",
		"HowToCheckIfItWorked" => "How to check if it worked?",
		"Sort" => "Sorting index number"
	);
	public static $summary_fields = array(
		"Title" => "Name"
	); //note no => for relational fields

	public static $singular_name = "Test Item";

	public static $plural_name = "Test Items";
	//CRUD settings

	public static $default_sort = "Sort ASC, Title ASC";

	public static $defaults = array(
		"Sort" => 100
	);//use fieldName => Default Value


	function getCMSFields() {
		$fields = parent::getCMSFields();
		$source = DataObject::get("TemplateOverviewDescription");
		$fields->removeFieldFromTab("Root", "TemplateOverviewDescriptions");
		$fields->removeFieldFromTab("Root", "TemplateOverviewTestItemEntry");
		$fields->addFieldToTab("Root.TestOnTheseTemplates", new MultiSelectField(
			"TemplateOverviewDescriptions",
			"Test",
			$source->toDropdownMap('ID','ClassNameLinkFancy')
		));
		return $fields;
	}

	public function populateDefaults() {
		parent::populateDefaults();
		$this->Sort = 100;
	}


}
