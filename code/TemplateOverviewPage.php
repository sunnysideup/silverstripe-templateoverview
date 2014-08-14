<?php
/**
 *@author: nicolaas [at] sunnysideup.co.nz
 *@description Add a page to your site that allows you to view all the html that can be used in the typography section - if applied correctly.
 */


class TemplateOverviewPage extends Page {

	//parents and children in sitetree
	private static $allowed_children = array("TemplateOverviewTestPage"); //can also be "none";

	private static $default_child = "TemplateOverviewTestPage";

	private static $can_be_root = true;

	private static $icon = "templateoverview/images/treeicons/TemplateOverviewPage";

	private static $description = "This page allows you to view all the html that can be used in the typography section";

		/**
	 * Standard SS variable.
	 */
	private static $singular_name = "Template Overview Page";
		function i18n_singular_name() { return _t("TemplateOverviewPage.SINGULARNAME", "Template Overview Page");}

	/**
	 * Standard SS variable.
	 */
	private static $plural_name = "Template Overview Pages";
		function i18n_plural_name() { return _t("TemplateOverviewPage.PLURALNAME", "Template Overview Pages");}

	private static $auto_include = false;

	private static $parent_url_segment = "admin-only";

	private static $classes_to_exclude = array("SiteTree", "TemplateOverviewPage","TemplateOverviewTestPage", "RedirectorPage", "VirtualPage");

	private static $defaults = array(
		"URLSegment" => "templates",
		"ShowInMenus" => 0,
		"ShowInSearch" => 0,
		"Title" => "Template overview (internal use only)",
		"MenuTitle" => "Template overview",
	);

	private static $has_many = array(
		"TemplateOverviewDescriptions" => "TemplateOverviewDescription"
	);

	public function canCreate($member = null) {
		return SiteTree::get()->filter(array("ClassName" => 'TemplateOverviewPage'))->count() ? false : true;
	}


	protected $counter = 0;

	protected $showAll = false;

	private static $list_of_all_classes = null;

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$gridFieldConfig = GridFieldConfig_RelationEditor::create();
		$gridfield = new GridField("TemplateOverviewDescriptions", "TemplateOverviewDescription", $this->TemplateOverviewDescriptions(), $gridFieldConfig);
		$fields->addFieldToTab('Root.Descriptions', $gridfield);
		return $fields;
	}

	public function requireDefaultRecords() {
		parent::requireDefaultRecords();
		if($this->config()->get("auto_include")) {
			$check = TemplateOverviewPage::get()->First();
			if(!$check) {
				$page = new TemplateOverviewPage();
				$page->ShowInMenus = 0;
				$page->ShowInSearch = 0;
				$page->Title = "Templates overview";
				$page->PageTitle = "Templates overview";
				$page->Sort = 99998;
				$page->URLSegment = "templates";
				$parent = Page::get()->filter(array("URLSegment" => $this->config()->get("parent_url_segment")))->First();
				if($parent) {
					$page->ParentID = $parent->ID;
				}
				$page->writeToStage('Stage');
				$page->publish('Stage', 'Live');
				$page->URLSegment = "templates";
				$page->writeToStage('Stage');
				$page->publish('Stage', 'Live');
				DB::alteration_message("TemplateOverviewPage","created");
			}
		}
	}


	public function ListOfAllClasses($checkCurrentClass = true) {
		if(!self::$list_of_all_classes)  {
			$ArrayOfAllClasses =  Array();
			$classes = ClassInfo::subclassesFor("SiteTree");
			foreach($classes as $className) {
				if(!in_array($className, $this->config()->get("classes_to_exclude"))) {
					if($this->showAll) {
						$objects = $className::get()
							->filter(array("ClassName" => $className))
							->sort("RAND() ASC")
							->limit(25);
						$count = 0;
						if($objects->count()) {
							foreach($objects as $obj) {
								$object = $this->createPageObject($obj, $count++, $className);
								$ArrayOfAllClasses[$object->indexNumber] = clone $object;
							}
						}
					}
					else {
						$obj = null;
						$obj = $className::get()
							->filter(array("ClassName" => $className))
							->sort("RAND() ASC")
							->limit(1)
							->first();
						if($obj) {
							$count = SiteTree::get()->filter(array("ClassName" => $obj->ClassName))->count();
						}
						else {
							$obj = $className::create();
							$count = 0;
						}
						$object = $this->createPageObject($obj, $count, $className);
						$object->TemplateOverviewDescription = $this->TemplateDetails($className);
						$ArrayOfAllClasses[$object->indexNumber] = clone $object;
					}
				}
			}
			ksort($ArrayOfAllClasses);
			self::$list_of_all_classes =  new ArrayList();
			$currentClassname = '';
			if($checkCurrentClass) {
				if($c = Controller::curr()) {
					if($d = $c->dataRecord) {
						$currentClassname = $d->ClassName;
					}
				}
			}
			if(count($ArrayOfAllClasses)) {
				foreach($ArrayOfAllClasses as $item) {
					if($item->ClassName == $currentClassname) {
						$item->LinkingMode = "current";
					}
					else {
						$item->LinkingMode = "link";
					}
					self::$list_of_all_classes->push($item);
				}
			}
		}
		return self::$list_of_all_classes;
	}

	function ShowAll () {
		$this->showAll = true;
		return array();
	}


	protected function TemplateDetails($className) {
		$obj = TemplateOverviewDescription::get()
			->filter(array("ClassNameLink" => $className))
			->First();
		if(!$obj) {
			$obj = new TemplateOverviewDescription();
			$obj->ClassNameLink = $className;
			$obj->ParentID = $this->ID;
			$obj->write();
		}
		DB::query("UPDATE TemplateOverviewDescription SET ParentID = ".$this->ID.";");
		return $obj;
	}

	public function TotalCount () {
		return count(ClassInfo::subclassesFor("SiteTree"))-1;
	}

	private function createPageObject($obj, $count, $className) {
		$this->counter++;
		$listArray = array();
		$indexNumber = (10000 * $count) + $this->counter;
		$listArray["indexNumber"] = $indexNumber;
		$listArray["ClassName"] = $className;
		$listArray["Count"] = $count;
		$listArray["ID"] = $obj->ID;
		$listArray["URLSegment"] = $obj->URLSegment;
		$listArray["TypoURLSegment"] = $this->Link();
		$listArray["Title"] = $obj->MenuTitle;
		$listArray["PreviewLink"] = $obj->PreviewLink();
		$listArray["CMSEditLink"] = $obj->CMSEditLink();
		$staticIcon = $obj->stat("icon", true);
		if(is_array($staticIcon)) {
			$iconArray = $obj->stat("icon");
			$icon = $iconArray[0];
		}
		else {
			$icon = $obj->stat("icon");
		}
		$iconFile = Director::baseFolder().'/'.$icon;
		if(!file_exists($iconFile)) {
			$icon = $icon."-file.gif";
		}
		$listArray["Icon"] = $icon;
		return new ArrayData($listArray);
	}

	//not used!
	function NoSubClasses($obj) {
		$array = ClassInfo::subclassesFor($obj->ClassName);
		if(count($array)) {
			foreach($array as $class) {
				if($class::get()->byID($obj->ID)) {
					return false;
				}
			}
		}
		return true;
	}

}

class TemplateOverviewPage_Controller extends Page_Controller {


	private static $allowed_actions = array(
		"showmore" => "ADMIN",
		"clearalltemplatedescriptions" => "ADMIN"
	);

	function init() {
		parent::init();
		Requirements::javascript(THIRDPARTY_DIR."/jquery/jquery.js");
		Requirements::javascript('templateoverview/javascript/TemplateOverviewPage.js');
		Requirements::css("templateoverview/css/TemplateOverviewPage.css");
		if(class_exists("PrettyPhoto")) {
			PrettyPhoto::include_code();
		}
		else {
			user_error("It is recommended that you install the Sunny Side Up Pretty Photo Module", E_USER_NOTICE);
		}
	}

	function showmore($request) {
		$id = $request->param("ID");
		$obj = SiteTree::get()->byID(intval($id));
		if($obj) {
			$className = $obj->ClassName;
			$data = $className::get()
				->filter(array("ClassName" => $obj->ClassName))
				->limit(200);
			$array = array(
				"Results" => $data,
				"MoreDetail" => TemplateOverviewDescription::get()->filter(array("ClassNameLink" => $obj->ClassName))->First()
			);
		}
		else {
			$array = array();
		}
		return $this->customise($array)->renderWith("TemplateOverviewPageShowMoreList");
	}




	function ConfigurationDetails() {
		$m = Member::currentUser();
		if($m) {
			if($m->inGroup("ADMIN")) {
				$baseFolder = Director::baseFolder();
				$myFile = $baseFolder."/".$this->project()."/_config.php";
				$fh = fopen($myFile, 'r');
				$string = '';
				while(!feof($fh)) {
					$string .= fgets($fh, 1024);
				}
				fclose($fh);
				return $string;
			}
		}
	}

	function clearalltemplatedescriptions() {
		if($m = Member::currentUser()) {
			if($m->inGroup("ADMIN")) {
				DB::query("DELETE FROM TemplateOverviewDescription");
				die("all descriptions have been deleted");
			}
		}
	}

	function TestTaskLink(){
		return "/dev/tasks/CheckAllTemplates/";
	}
}

