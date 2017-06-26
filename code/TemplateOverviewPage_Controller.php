<?php
/**
 *@author: nicolaas [at] sunnysideup.co.nz
 *@description Add a page to your site that allows you to view all the html that can be used in the typography section - if applied correctly.
 */



class TemplateOverviewPage_Controller extends Page_Controller
{
    private static $allowed_actions = array(
        "showmore" => true,
        "quicklist" => true,
        "listofobjectsused" => true,
        "clearalltemplatedescriptions" => "ADMIN"
    );

    public function init()
    {
        parent::init();
        if (!Director::is_cli() && !Director::isDev() && !Permission::check('ADMIN')) {
            return Security::permissionFailure();
        }
        Requirements::javascript(THIRDPARTY_DIR."/jquery/jquery.js");
        Requirements::javascript('templateoverview/javascript/TemplateOverviewPage.js');
        Requirements::css("templateoverview/css/TemplateOverviewPage.css");
        if (class_exists("PrettyPhoto")) {
            PrettyPhoto::include_code();
        } else {
            user_error("It is recommended that you install the Sunny Side Up Pretty Photo Module", E_USER_NOTICE);
        }
    }

    public function showmore($request)
    {
        $id = $request->param("ID");
        $obj = SiteTree::get()->byID(intval($id));
        if ($obj) {
            $className = $obj->ClassName;
            $data = $className::get()
                ->filter(array("ClassName" => $obj->ClassName))
                ->limit(200);
            $array = array(
                "Results" => $data,
                "MoreDetail" => TemplateOverviewDescription::get()->filter(array("ClassNameLink" => $obj->ClassName))->First()
            );
        } else {
            $array = array();
        }
        return $this->customise($array)->renderWith("TemplateOverviewPageShowMoreList");
    }


    public function ConfigurationDetails()
    {
        $m = Member::currentUser();
        if ($m) {
            if ($m->inGroup("ADMIN")) {
                $baseFolder = Director::baseFolder();
                $myFile = $baseFolder."/".$this->project()."/_config.php";
                $fh = fopen($myFile, 'r');
                $string = '';
                while (!feof($fh)) {
                    $string .= fgets($fh, 1024);
                }
                fclose($fh);
                return $string;
            }
        }
    }

    public function clearalltemplatedescriptions()
    {
        if ($m = Member::currentUser()) {
            if ($m->inGroup("ADMIN")) {
                DB::query("DELETE FROM TemplateOverviewDescription");
                die("all descriptions have been deleted");
            }
        }
    }

    public function TestTaskLink()
    {
        return "/dev/tasks/CheckAllTemplates/";
    }

    public function QuickListLink()
    {
        return $this->Link("quicklist");
    }

    public function ImagesListLink()
    {
        return $this->Link("listofobjectsused/Image");
    }

    public function quicklist()
    {
        $list = $this->ListOfAllClasses();
        foreach ($list as $item) {
            DB::alteration_message($item->ClassName);
        }
    }

    public function listofobjectsused($request)
    {
        $classWeAreLookingFor = $request->param("ID");
        $classWeAreLookingFor = singleton($classWeAreLookingFor);
        if ($classWeAreLookingFor instanceof DataObject) {
            $list = $this->ListOfAllClasses();
            foreach ($list as $item) {
                $config = Config::inst();
                $listOfImages = $config->get($item->ClassName, "has_one")
                 + $config->get($item->ClassName, "has_many")
                 + $config->get($item->ClassName, "many_many");
                foreach ($listOfImages as $fieldName => $potentialImage) {
                    $innerSingleton = singleton($potentialImage);
                    if ($innerSingleton instanceof $classWeAreLookingFor) {
                        DB::alteration_message($item->ClassName.".". $fieldName);
                    }
                }
            }
        } else {
            user_error("Please specify the ID for the model you are looking for - e.g. /listofobjectsused/Image/", E_USER_ERROR);
        }
    }
}
