<?php

/**
 *@author nicolaas[at]sunnysideup.co.nz
 *@description: more details per template. Records are auto-added
 *@link: http://localhost/www.mysite.co.nz/admin/templates/TemplateOverviewDescription/13/edit
 **/


class TemplateOverviewDescription extends DataObject
{

    private static $db = array(
        "Title" => "Varchar",
        "Description" => "Text",
        "ToDoListHyperLink" => "Varchar(255)",
        "ClassNameLink" => "Varchar(120)"
    );

    private static $has_one = array(
        "Parent" => "TemplateOverviewPage",
        "Image1" => "Image",
        "Image2" => "Image",
        "Image3" => "Image",
        "Image4" => "Image",
        "Image5" => "Image",
        "Image6" => "Image",
        "Image7" => "Image"
    );

    private static $searchable_fields = array(
        "ClassNameLink" => "PartialMatchFilter"
    );

    private static $summary_fields = array(
        "ClassNameLink",
        "ToDoListHyperLink"
    );

    private static $field_labels = array(
        "ClassNameLink" => "Page Type Name",
        "ToDoListHyperLink" => "Link to To Do"
    );

    private static $singular_name = 'Template Description';

    private static $plural_name = 'Template Descriptions';

    private static $default_sort = 'ClassNameLink ASC';

    private static $indexes = array(
        "ClassNameLink" => true
    );

    private static $casting = array(
        "Title" => "Varchar"
    );

    /**
     * Location where we keep the template overview designs.
     * @var String
     */
    private static $image_folder_name = "templateoverview/designz";

    /**
     * Location where we keep the template overview designs.
     * @var String
     */
    private static $image_source_folder = "";

    public function canCreate($member = null)
    {
        return false;
    }

    public function canDelete($member = null)
    {
        return true;
    }

    public function ClassNameLinkFancy()
    {
        return implode(" ", preg_split('/(?<=\\w)(?=[A-Z])/', $this->ClassNameLink));
        return preg_replace("/(?<=[^A-Z])([A-Z])/", "$1", $this->ClassNameLink);
    }

    public function Title()
    {
        return $this->getTitle();
    }
    public function getTitle()
    {
        return $this->ClassNameLinkFancy();
    }

    public function ModelAdminLink()
    {
        return TemplateOverviewDescriptionModelAdmin::get_full_url_segment().$this->ClassName."/EditForm/field/TemplateOverviewDescription/item/".$this->ID."/edit/";
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $page = TemplateOverviewPage::get()->First();
        if (!$page) {
            user_error("Please make sure to create a TemplateOverviewPage to make use of this module.", E_USER_WARNING);
        }
        $fields->removeByName("ClassNameLink");
        $fields->removeByName("Image1");
        $fields->removeByName("Image2");
        $fields->removeByName("Image3");
        $fields->removeByName("Image4");
        $fields->removeByName("Image5");
        $fields->addFieldToTab("Root.Design", new UploadField("Image1", "Design One"));
        $fields->addFieldToTab("Root.Design", new UploadField("Image2", "Design Two"));
        $fields->addFieldToTab("Root.Design", new UploadField("Image6", "Design Three"));
        $fields->addFieldToTab("Root.Instructions", new UploadField("Image3", "Instructions One"));
        $fields->addFieldToTab("Root.Instructions", new UploadField("Image4", "Instructions Two"));
        $fields->addFieldToTab("Root.Instructions", new UploadField("Image5", "Instructions Three"));
        $fields->addFieldToTab("Root.Instructions", new UploadField("Image7", "Instructions Four"));
        $fields->addFieldToTab("Root.Main", new HeaderField("ClassNameLinkInfo", "Details for: ".$this->ClassNameLink), "Description");
        $fields->addFieldToTab("Root.Main", new LiteralField("BackLink", '<p><a href="'.$page->Link().'#sectionFor-'.$this->ClassNameLink.'">go back to template overview page</a> - dont forget to SAVE FIRST.</p>'));
        $fields->removeByName("ParentID");
        return $fields;
    }

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        $data = ClassInfo::subclassesFor("SiteTree");
        $templateOverviewPage = TemplateOverviewPage::get()->First();
        $fileList = null;
        if ($this->Config()->get("image_source_folder")) {
            $fileList = CMSHelp::get_list_of_files($this->Config()->get("image_source_folder"));
            if (!is_array($fileList)) {
                $fileList = null;
            } elseif (!count($fileList)) {
                $fileList = null;
            }
        }
        if ($fileList) {
            $destinationDir = Director::baseFolder()."/assets/".$this->Config()->get("image_folder_name")."/";
            $destinationFolder = Folder::find_or_make($this->Config()->get("image_folder_name"));
        }
        if ($data && $templateOverviewPage) {
            foreach ($data as $className) {
                $object = TemplateOverviewDescription::get()
                    ->filter(array("ClassNameLink" => $className))->First();
                if (!$object) {
                    $object = new TemplateOverviewDescription();
                    $object->ClassNameLink = $className;
                    $object->ParentID = $templateOverviewPage->ID;
                    $object->write();
                    DB::alteration_message("adding template description for $className", "created");
                } else {
                    $otherObjects = TemplateOverviewDescription::get()
                    ->filter(array("ClassNameLink" => $className))->exclude(array("ID" => $object->ID));
                    foreach ($otherObjects as $otherObject) {
                        DB::alteration_message("Deleting superfluous TemplateOverviewDescription with Class Name Link: $className", "deleted");
                        $otherObject->delete();
                    }
                }
                if ($fileList) {
                    $i = 0;
                    foreach ($fileList as $fileArray) {
                        $explodeByDot = explode(".", $fileArray["FileName"]);
                        if (is_array($explodeByDot) && count($explodeByDot)) {
                            $base = $explodeByDot[0];
                            $explodeByUnderscore = explode("_", $base);
                            if (is_array($explodeByUnderscore) && count($explodeByUnderscore)) {
                                $base = $explodeByUnderscore[0];
                                $classNameOptionArray = array($className);
                                for ($j = 0; $j < 10; $j++) {
                                    $classNameOptionArray[] = $className.$j;
                                }
                                foreach ($classNameOptionArray as $potentialBase) {
                                    if ($base == $potentialBase) {
                                        $i++;
                                        $filename = "".$this->Config()->get("image_folder_name")."/".$fileArray["FileName"];
                                        if (!file_exists($destinationDir.$fileArray["FileName"])) {
                                            copy($fileArray["FullLocation"], $destinationDir.$fileArray["FileName"]);
                                        }
                                        $image = $Image::get()
                                            ->filter(array("ParentID" => $destinationFolder-ID, "Name" => $fileArray["FileName"]))->First();
                                        if (!$image) {
                                            $image = new Image();
                                            $image->ParentID = $destinationFolder->ID;
                                            $image->Filename = $filename;
                                            $image->Name = $fileArray["FileName"];
                                            $image->Title = $fileArray["Title"];
                                            $image->write();
                                        }
                                        $fieldName = "Image$i"."ID";
                                        if ($object->$fieldName != $image->ID) {
                                            $object->$fieldName = $image->ID;
                                            $object->write();
                                            DB::alteration_message("adding image to $className: ".$image->Title." (".$image->Filename.") using $fieldName field.", "created");
                                        }
                                    }
                                }
                            }
                        }
                    }
                } else {
                    DB::alteration_message("no design images found for $className", "deleted");
                }
            }
        }
        $helpDirectory = Director::baseFolder()."/".Config::inst()->get("CMSHelp", "help_file_directory_name")."/";
        if (!file_exists($helpDirectory)) {
            mkdir($helpDirectory);
        }
        $this->createManifestExcludeFile($helpDirectory);

        $devDirectory = Director::baseFolder()."/".Config::inst()->get("CMSHelp", "dev_file_directory_name")."/";
        if (!file_exists($devDirectory)) {
            mkdir($devDirectory);
        }
        $this->createManifestExcludeFile($devDirectory);
        $this->createHTACCESSDenyAll($devDirectory);
    }

    private function createManifestExcludeFile($dir)
    {
        $myFile = $dir.'_manifest_exclude';
        if (!file_exists($myFile)) {
            $handle = fopen($myFile, 'w') or user_error('Cannot open file:  '.$myFile);
            $data = '';
            fwrite($handle, $data);
        }
    }

    private function createHTACCESSDenyAll($dir)
    {
        $myFile = $dir.'.htaccess';
        if (!file_exists($myFile)) {
            $handle = fopen($myFile, 'w') or user_error('Cannot open file:  '.$myFile);
            $data = '
Order Deny,Allow
Deny from all
		';
            fwrite($handle, $data);
        }
    }

    public function validate()
    {
        if ($this->ID) {
            if (
                TemplateOverviewDescription::get()
                    ->filter(array("ClassNameLink" => $this->ClassNameLink))
                    ->exclude(array("ID" => $this->ID))
            ) {
                return new ValidationResult(false, _t("TemplateOverviewDescription.ALREADYEXISTS", "This template already exists"));
            }
        }
        return new ValidationResult();
    }

    public function onBeforeWrite()
    {
        if (!$this->ParentID) {
            if ($page = TemplateOverviewPage::get()->First()) {
                $this->ParentID = $page->ID;
            }
        }
        parent::onBeforeWrite();
    }

    public function forTemplate()
    {
        return $this->ClassNameLink;
    }
}
