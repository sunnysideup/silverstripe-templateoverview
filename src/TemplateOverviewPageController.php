<?php
/**
 *@author: nicolaas [at] sunnysideup.co.nz
 *@description Add a page to your site that allows you to view all the html that can be used in the typography section - if applied correctly.
 */

namespace Sunnysideup\TemplateOverview;

use SilverStripe\CMS\Model\SiteTree;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\View\Requirements;

use SilverStripe\Core\Config\Config;

use SilverStripe\ORM\DB;

use Sunnysideup\PrettyPhoto\PrettyPhoto;
use Sunnysideup\TemplateOverview\Api\SiteTreeDetails;

use \PageController;
use \Page;

class TemplateOverviewPageController extends PageController
{

    private static $url_segment = 'templates';

    /**
     * The ContentController will take the URLSegment parameter from the URL and use that to look
     * up a SiteTree record.
     *
     * @param SiteTree $dataRecord
     */
    public function __construct($dataRecord = null)
    {

        $this->dataRecord = Page::get()->first();

        parent::__construct($this->dataRecord);

    }

    private static $allowed_actions = [
        "showmore" => true,
        "quicklist" => true,
        "listofobjectsused" => true,
    ];

    public function init()
    {
        parent::init();
        if (! Director::is_cli() && !Director::isDev() && ! Permission::check('ADMIN')) {
            return Security::permissionFailure();
        }
        Requirements::javascript('sunnysideup/templateoverview: client/javascript/TemplateOverviewPage.js');
        Requirements::css("sunnysideup/templateoverview: client/css/TemplateOverviewPage.css");
        if (class_exists(PrettyPhoto::class)) {
            PrettyPhoto::include_code();
        } else {
            //user_error("It is recommended that you install the Sunny Side Up Pretty Photo Module", E_USER_NOTICE);
        }
    }

    public function index(HTTPRequest $request = null)
    {
        return $this->renderWith(['Page', 'Page']);
    }

    public function Content()
    {
        return $this->renderWith('Sunnysideup\\TemplateOverview\\Includes\\TemplateOverviewList');
    }

    public function showmore($request)
    {
        $id = $request->param("ID");
        $obj = SiteTree::get()->byID(intval($id));
        if ($obj) {
            $className = $obj->ClassName;
            $data = $className::get()
                ->filter(["ClassName" => $obj->ClassName])
                ->limit(200);
            $array = [
                "Results" => $data,
            ];
        } else {
            $array = [];
        }
        return $this->customise($array)->renderWith("Sunnysideup\\TemplateOverview\\TemplateOverviewPageShowMoreList");
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


      public function Link($action = null)
      {
          $v = '/'.$this->Config()->url_segment;
          if ($action) {
              $v .= $action . '/';
          }

          return $v;
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
        $list = $this->ListOfAllSiteTreeClasses();
        foreach ($list as $item) {
            DB::alteration_message($item->ClassName);
        }
    }

    public function listofobjectsused($request)
    {
        $classWeAreLookingFor = $request->param("ID");
        $classWeAreLookingFor = Injector::inst()->get($classWeAreLookingFor);
        if ($classWeAreLookingFor instanceof DataObject) {
            $list = $this->ListOfAllSiteTreeClasses();
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

    /**
     * returns a list of all SiteTree Classes
     * @return Array(String)
     */
    public function ListOfAllSiteTreeClasses()
    {
        $siteTreeDetails = Injector::inst()->get(SiteTreeDetails::class);

        return $siteTreeDetails->ListOfAllSiteTreeClasses();
    }


    public function TotalCount()
    {
        return count(ClassInfo::subclassesFor("SiteTree"))-1;
    }
}
