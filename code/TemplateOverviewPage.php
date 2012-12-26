<?php
/**
 *@author: nicolaas [at] sunnysideup.co.nz
 *@description Add a page to your site that allows you to view all the html that can be used in the typography section - if applied correctly.
 */


class TemplateOverviewPage extends Page {

	//parents and children in sitetree
	static $allowed_children = array("TemplateOverviewTestPage"); //can also be "none";
	static $default_child = "TemplateOverviewTestPage";
	static $can_be_root = true;

	static $icon = "templateoverview/images/treeicons/TemplateOverviewPage";

	protected static $auto_include = false;
		static function set_auto_include($value) {self::$auto_include = $value;}
		static function get_auto_include() {return self::$auto_include;}

	protected static $parent_url_segment = "admin-only";
		static function set_parent_url_segment($value) {self::$parent_url_segment = $value;}
		static function get_parent_url_segment() {return self::$parent_url_segment;}

	protected static $classes_to_exclude = array("SiteTree", "TemplateOverviewPage","TemplateOverviewTestPage", "RedirectorPage", "VirtualPage");
		static function set_classes_to_exclude($array) {self::$classes_to_exclude = $array;}
		static function get_classes_to_exclude() {return self::$classes_to_exclude;}


	static $defaults = array(
		"URLSegment" => "templates",
		"ShowInMenus" => 0,
		"ShowInSearch" => 0,
		"Title" => "Template overview (internal use only)",
		"MenuTitle" => "Template overview",
	);

	static $has_many = array(
		"TemplateOverviewDescriptions" => "TemplateOverviewDescription"
	);

	public function canCreate($member = null) {
		$bt = defined('DB::USE_ANSI_SQL') ? "\"" : "`";
		return !DataObject::get("SiteTree", "{$bt}ClassName{$bt} = 'TemplateOverviewPage'");
	}


	protected $counter = 0;

	protected $showAll = false;

	protected static $list_of_all_classes = null;

	public function getCMSFields() {
		$bt = defined('DB::USE_ANSI_SQL') ? "\"" : "`";
		$fields = parent::getCMSFields();
		$tablefield = new HasManyComplexTableField(
			$controller = $this,
			$name = 'TemplateOverviewDescriptions',
			$sourceClass = 'TemplateOverviewDescription',
			null,
			$detailFormFields = 'getCMSFields_forPopup',
			$sourceFilter = "{$bt}ParentID{$bt} = ".$this->ID,
			$sourceSort = "{$bt}ClassNameLink{$bt} ASC"
			//$sourceJoin = null
		);
		$fields->addFieldToTab('Root.Content.Descriptions', $tablefield);
		return $fields;
	}

	public function requireDefaultRecords() {
		parent::requireDefaultRecords();
		$bt = defined('DB::USE_ANSI_SQL') ? "\"" : "`";
		if(self::$auto_include) {
			$check = DataObject::get_one("TemplateOverviewPage");
			if(!$check) {
				$page = new TemplateOverviewPage();
				$page->ShowInMenus = 0;
				$page->ShowInSearch = 0;
				$page->Title = "Templates overview";
				$page->MetaTitle = "Templates overview";
				$page->PageTitle = "Templates overview";
				$page->Sort = 99998;
				$page->URLSegment = "templates";
				$parent = DataObject::get_one("Page", "{$bt}URLSegment{$bt} = '".self::$parent_url_segment."'");
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


	public function ListOfAllClasses() {
		$bt = defined('DB::USE_ANSI_SQL') ? "\"" : "`";
		if(!self::$list_of_all_classes)  {
			$ArrayOfAllClasses =  Array();
			$classes = ClassInfo::subclassesFor("SiteTree");
			foreach($classes as $className) {
				if(!in_array($className, self::$classes_to_exclude)) {
					if($this->showAll) {
						$objects = DataObject::get($className, 'ClassName = "'.$className.'"', "RAND() ASC", null, "0, 25");
						$count = 0;
						if(is_object($objects) && $objects->count()) {
							foreach($objects as $obj) {
								$object = $this->createPageObject($obj, $count++, $className);
								$ArrayOfAllClasses[$object->indexNumber] = clone $object;
							}
						}
					}
					else {
						$obj = null;
						$objects = DataObject::get($className, "ClassName = '".$className."'", 'RAND() ASC', '', 1);
						if(is_object($objects) && $objects->count()) {
							$obj = $objects->First();
							$extension = '';
							if(Versioned::current_stage() == "Live") {
								$extension = "_Live";
							}
							$count = DB::query("Select COUNT(*) from {$bt}SiteTree{$extension}{$bt} where {$bt}ClassName{$bt} = '".$obj->ClassName."'")->value();
						}
						else {
							$obj = singleton($className);
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
			if($c = Controller::curr()) {
				if($d = $c->dataRecord) {
					$currentClassname = $d->ClassName;
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
		$bt = defined('DB::USE_ANSI_SQL') ? "\"" : "`";
		$obj = DataObject::get_one("TemplateOverviewDescription", "ClassNameLink = '".$className."'");
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
		$listArray["TypoURLSegment"] = $this->URLSegment;
		$listArray["Title"] = $obj->Title;
		$listArray["FullLink"] = Director::absoluteURL($obj->Link());
		$listArray["URLSegment"] = $obj->URLSegment;
		$staticIcon = $obj->stat("icon", true);
		if(is_array($staticIcon)) {
			$iconArray = $obj->stat("icon");
			$icon = $iconArray[0];
		}
		else {
			$icon = $obj->stat("icon");
		}
		$listArray["Icon"] = $icon."-file.gif";
		$object = new ArrayData($listArray);
		return $object;
	}

	//not used!
	function NoSubClasses($obj) {
		$array = ClassInfo::subclassesFor($obj->ClassName);
		if(count($array)) {
			foreach($array as $class) {
				if(DataObject::get_by_id($class, $obj->ID)) {
					return false;
				}
			}
		}
		return true;
	}

}

class TemplateOverviewPage_Controller extends Page_Controller {


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
		$bt = defined('DB::USE_ANSI_SQL') ? "\"" : "`";
		$id = $request->param("ID");
		$obj = DataObject::get_by_id("SiteTree", intval($id));
		if($obj) {
			$data = DataObject::get($obj->ClassName, $where = "{$bt}ClassName{$bt} = '".$obj->ClassName."'", $orderBy = "", $join = "", $limit = 500);
			$array = array(
				"Results" => $data,
				"MoreDetail" => DataObject::get("TemplateOverviewDescription", "ClassNameLink = '".$obj->ClassName."'")
			);
		}
		else {
			$array = array();
		}
		return $this->customise($array)->renderWith("TemplateOverviewPageShowMoreList");
	}




	function ConfigurationDetails() {
		$m = Member::CurrentMember();
		if($m) {
			if($m->IsAdmin()) {
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
		if($m =$this->CurrentMember()) {
			if($m->IsAdmin()) {
				DB::query("DELETE FROM TemplateOverviewDescription");
				die("all descriptions have been deleted");
			}
		}

	}

}

