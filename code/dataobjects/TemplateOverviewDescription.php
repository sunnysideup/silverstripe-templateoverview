<?php

/**
 *@author nicolaas[at]sunnysideup.co.nz
 *@description: more details per template. Records are auto-added
 *@link: http://localhost/www.mysite.co.nz/admin/templates/TemplateOverviewDescription/13/edit
 **/


class TemplateOverviewDescription extends DataObject {

	static $db = array(
		"Description" => "Text",
		"ToDoListHyperLink" => "Varchar(255)",
		"ClassNameLink" => "Varchar(120)"
	);

	static $has_one = array(
		"Parent" => "TemplateOverviewPage",
		"Image1" => "Image",
		"Image2" => "Image",
		"Image3" => "Image",
		"Image4" => "Image",
		"Image5" => "Image",
		"Image6" => "Image",
		"Image7" => "Image"
	);

	static $belongs_many_many = array(
		"TemplateOverviewTestItems" => "TemplateOverviewTestItem"
	);

	public static $searchable_fields = array(
		"ClassNameLink" => "PartialMatchFilter"
	);

	public static $summary_fields = array(
		"ClassNameLink",
		"ToDoListHyperLink"
	);

	public static $field_labels = array(
		"ClassNameLink" => "Page Type Name",
		"ToDoListHyperLink" => "Link to To Do List (e.g. http://www.my-project-management-tool.com/mypage/)",
	);

	static $singular_name = 'Template Description';

	static $plural_name = 'Template Descriptions';

	static $default_sort = 'ClassNameLink ASC';

	static $indexes = array(
		"ClassNameLink" => true,
	);

	static $casting = array(
		"Title" => "Varchar",
	);

	/**
	 * Location where we keep the template overview designs.
	 * @var String
	 */
	protected static $image_folder_name = "templateoverview/designz";
		static function get_image_folder_name() {return self::$image_folder_name; }
		static function set_image_folder_name($s) {self::$image_folder_name = $s; }

	/**
	 * Location where we keep the template overview designs.
	 * @var String
	 */
	protected static $image_source_folder = "";
		static function get_image_source_folder() {return self::$image_source_folder; }
		static function set_image_source_folder($s) {self::$image_source_folder = $s; }

	function canAdd() {
		return false;
	}

	function canDelete() {
		return true;
	}

	function ClassNameLinkFancy() {
		return implode(" ", preg_split('/(?<=\\w)(?=[A-Z])/', $this->ClassNameLink));
		return preg_replace("/(?<=[^A-Z])([A-Z])/", "$1", $this->ClassNameLink);
	}

	function Title() {return $this->getTitle();}
	function getTitle() {
		return $this->ClassNameLinkFancy();
	}

	function ModelAdminLink() {
		return TemplateOverviewDescriptionModelAdmin::get_full_url_segment()."/".$this->ClassName."/".$this->ID."/edit/";
	}

	function getCMSFields() {
		$fields = parent::getCMSFields();
		$page = DataObject::get_one("TemplateOverviewPage");
		if(!$page) {
			user_error("Please make sure to create a TemplateOverviewPage to make use of this module.", E_USER_WARNING);
		}
		$fields->removeByName("ClassNameLink");
		$fields->removeByName("Image1");
		$fields->removeByName("Image2");
		$fields->removeByName("Image3");
		$fields->removeByName("Image4");
		$fields->removeByName("Image5");
		$fields->addFieldToTab("Root.Design", new ImageField("Image1", "Design One", $value = null, $form = null, $rightTitle = null, self::get_image_folder_name()));
		$fields->addFieldToTab("Root.Design", new ImageField("Image2", "Design Two", $value = null, $form = null, $rightTitle = null, self::get_image_folder_name()));
		$fields->addFieldToTab("Root.Design", new ImageField("Image6", "Design Three", $value = null, $form = null, $rightTitle = null, self::get_image_folder_name()));
		$fields->addFieldToTab("Root.Instructions", new ImageField("Image3", "Instructions One", $value = null, $form = null, $rightTitle = null, self::get_image_folder_name()));
		$fields->addFieldToTab("Root.Instructions", new ImageField("Image4", "Instructions Two", $value = null, $form = null, $rightTitle = null, self::get_image_folder_name()));
		$fields->addFieldToTab("Root.Instructions", new ImageField("Image5", "Instructions Three", $value = null, $form = null, $rightTitle = null, self::get_image_folder_name()));
		$fields->addFieldToTab("Root.Instructions", new ImageField("Image7", "Instructions Four", $value = null, $form = null, $rightTitle = null, self::get_image_folder_name()));
		$fields->addFieldToTab("Root.Main", new HeaderField("ClassNameLinkInfo", "Details for: ".$this->ClassNameLink), "Description");
		$fields->addFieldToTab("Root.Main", new LiteralField("BackLink", '<p><a href="'.$page->Link().'#sectionFor-'.$this->ClassNameLink.'">go back to template overview page</a> - dont forget to SAVE FIRST.</p>'));
		$fields->removeByName("ParentID");
		return $fields;
	}

	function requireDefaultRecords() {
		parent::requireDefaultRecords();
		$data = ClassInfo::subclassesFor("SiteTree");
		$templateOverviewPage = DataObject::get_one("TemplateOverviewPage");
		$fileList = null;
		if(self::get_image_source_folder()) {
			$fileList = CMSHelp::get_list_of_files(self::get_image_source_folder());
			if(!is_array($fileList)) {
				$fileList = null;
			}
			elseif(!count($fileList)) {
				$fileList = null;
			}
		}
		if($fileList) {
			$destinationDir = Director::baseFolder()."/assets/".self::get_image_folder_name()."/";
			$destinationFolder = Folder::findOrMake(self::get_image_folder_name());
		}
		if($data && $templateOverviewPage) {
			foreach($data as $className) {
				$object = DataObject::get_one("TemplateOverviewDescription", "ClassNameLink = '$className'");
				if(!$object) {
					$object = new TemplateOverviewDescription();
					$object->ClassNameLink = $className;
					$object->ParentID = $templateOverviewPage->ID;
					$object->write();
					DB::alteration_message("adding template description for $className", "created");
				}
				if($fileList) {
					$i = 0;
					foreach($fileList as $fileArray) {
						$explodeByDot = explode(".", $fileArray["FileName"]);
						if(is_array($explodeByDot) && count($explodeByDot)) {
							$base = $explodeByDot[0];
							$explodeByUnderscore = explode("_", $base);
							if(is_array($explodeByUnderscore) && count($explodeByUnderscore)) {
								$base = $explodeByUnderscore[0];
								$classNameOptionArray = array($className);
								for($j = 0; $j < 10; $j++) {
									$classNameOptionArray[] = $className.$j;
								}
								foreach($classNameOptionArray as $potentialBase) {
									if($base == $potentialBase) {
										$i++;
										$filename = "".self::get_image_folder_name()."/".$fileArray["FileName"];
										if(!file_exists($destinationDir.$fileArray["FileName"])) {
											copy($fileArray["FullLocation"], $destinationDir.$fileArray["FileName"]);
										}
										$image = DataObject::get_one("Image", "\"ParentID\" = ".$destinationFolder->ID." AND \"Name\" = '".$fileArray["FileName"]."'");
										if(!$image) {
											$image = new Image();
											$image->ParentID = $destinationFolder->ID;
											$image->Filename = $filename;
											$image->Name = $fileArray["FileName"];
											$image->Title = $fileArray["Title"];
											$image->write();
										}
										$fieldName = "Image$i"."ID";
										if($object->$fieldName != $image->ID) {
											$object->$fieldName = $image->ID;
											$object->write();
											DB::alteration_message("adding image to $className: ".$image->Title." (".$image->Filename.") using $fieldName field.", "created");
										}
									}
								}
							}
						}
					}
				}
				else {
					DB::alteration_message("no design images found for $className", "deleted");
				}
			}
		}
		$helpDirectory = Director::baseFolder()."/".CMSHelp::get_help_file_directory_name()."/";
		if(!file_exists($helpDirectory)) {
			mkdir($helpDirectory);
		}
	}

	protected function validate() {
		if($this->ID) {
			if(DataObject::get_one("TemplateOverviewDescription", "ClassNameLink = '".$this->ClassNameLink."' AND ID <> ".$this->ID)) {
				return new ValidationResult(false, _t("TemplateOverviewDescription.ALREADYEXISTS", "This template already exists"));
			}
		}
		return new ValidationResult();
	}

	function onBeforeWrite() {
		if(!$this->ParentID) {
			if($page = DataObject::get_one("TemplateOverviewPage")) {
				$this->ParentID = $page->ID;
			}
		}
		parent::onBeforeWrite();
	}


}
