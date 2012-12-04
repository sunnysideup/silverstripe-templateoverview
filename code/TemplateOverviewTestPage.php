<?php
/**
 * allows you to open all pages to check if all of them can be opened.
 *
 *
 */
class TemplateOverviewTestPage extends Page {

	//parents and children in sitetree
	static $allowed_children = "none"; //can also be "none";
	static $can_be_root = false;

	//appearance
	static $icon = "templateoverview/images/treeicons/TemplateOverviewTestPage";

	function requireDefaultRecords(){
		parent::requireDefaultRecords();
		if(isset($_REQUEST["checkallpages"])) {
			$classObjects = DataObject::get("TemplateOverviewDescription");
			if($classObjects) {
				foreach($classObjects as $classObject) {
					$className = $classObject->ClassNameLink;
					if($className && class_exists($className) && $className != "TemplateOverviewTestPage") {
						$page = DataObject::get_one($className,"\"ClassName\" = '$className'");
						if($page) {
							$url1 = Director::absoluteURL($page->Link());
							$url2 = Director::absoluteURL("/admin/show/".$page->ID);
							$this->checkURL($url1);
							$this->checkURL($url2);
						}
					}
				}
			}
		}
	}

	/**
	 * to do: to be completed....
	 *
	 */
	protected function checkURL($url){
		print_r(get_headers($url, 1));
		return ;
		$handle = curl_init($url);
		curl_setopt($handle,  CURLOPT_RETURNTRANSFER, TRUE);
		/* Get the HTML or whatever is linked in $url. */
		$response = curl_exec($handle);
		/* Check for 404 (file not found). */
		$httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
		if($httpCode == 404) {
			DB::alteration_message("could not find ".$url);
		}
		elseif($httpCode == 500) {
			DB::alteration_message("Error in opening ".$url, "deleted");
		}
		else {
			DB::alteration_message("".$url." returned $httpCode", "deleted");
		}
		curl_close($handle);
	}

}

class TemplateOverviewTestPage_Controller extends Page_Controller {

	function createtest() {
		$tests = DataObject::get("TemplateOverviewTestItem");
		foreach($tests as $test) {
			$entry = new TemplateOverviewTestItemEntry();
			$entry->TemplateOverviewTestItemID = $test->ID;
			$member = Member::currentMemberID();
			$entry->write();
		}
		Director::redirect($this->Link("testscreated"));
	}

	function testscreated() {
		$message = "Test Entries Created";

	}

}
