<?php

class TemplateOverviewTestItemEntry extends DataObject {

	static $db = array(
		"Completed" => "Boolean",
		"PositiveResult" => "Boolean"
	);

	static $has_one = array(
		"Member" => "Member",
		"TemplateOverviewTestItem" => "TemplateOverviewTestItem"
	);

	private static $searchable_fields = array(
		"TemplateOverviewTestItemID",
		"MemberID",
		"Completed",
		"PositiveResult"
	);
	private static $field_labels = array(
		"Completed" => "Test done",
		"PositiveResult" => "Test OK",
		"Member" => "Tested By",
	);

	private static $singular_name = "Test Item Entry";

	private static $plural_name = "Test Item Entries";
	//CRUD settings
	private static $default_sort = "LastEdited DESC, Created DESC";



	function onBeforeWrite() {
		parent::onBeforeWrite();
		$this->MemberID = Member::currentUserID();
	}

}
