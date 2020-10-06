<?php

namespace Sunnysideup\TemplateOverview;

use \PageController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;

use SilverStripe\ORM\DataObject;

use SilverStripe\ORM\DB;
use SilverStripe\Security\Permission;

use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;

use Sunnysideup\PrettyPhoto\PrettyPhoto;
use Sunnysideup\TemplateOverview\Api\SiteTreeDetails;

/**
 *@author: nicolaas [at] sunnysideup.co.nz
 *@description Add a page to your site that allows you to view all the html that can be used in the typography section - if applied correctly.
 */
class TemplateOverviewPageController extends PageController
{
    private static $url_segment = 'templates';

    private static $allowed_actions = [
        'showmore' => true,
        'quicklist' => true,
        'listofobjectsused' => true,
    ];

    public function init()
    {
        parent::init();
        if (Director::is_cli() || Director::isDev() || Permission::check('ADMIN')) {
            Requirements::javascript('//code.jquery.com/jquery-1.7.2.min.js');
            Requirements::javascript('sunnysideup/templateoverview: client/javascript/TemplateOverviewPage.js');
            Requirements::themedCSS('client/css/TemplateOverviewPage');
            if (class_exists(PrettyPhoto::class)) {
                PrettyPhoto::include_code();
            }
            //user_error("It is recommended that you install the Sunny Side Up Pretty Photo Module", E_USER_NOTICE);
        } else {
            return Security::permissionFailure();
        }
    }

    public function index(HTTPRequest $request = null)
    {
        // $this->renderWith(['Sunnysideup\\TemplateOverview\\TemplateOverviewPageController']);
        return [];
    }

    public function Content()
    {
        return $this->renderWith('Sunnysideup\\TemplateOverview\\Includes\\TemplateOverviewList');
    }

    public function showmore($request)
    {
        $id = $request->param('ID');
        /** @var SiteTree|null */
        $obj = SiteTree::get()->byID(intval($id));
        if ($obj) {
            $className = $obj->ClassName;
            $data = $className::get()
                ->filter(['ClassName' => $obj->ClassName])
                ->limit(200);
            $array = [
                'Results' => $data,
            ];
        } else {
            $array = [];
        }
        return $this->customise($array)->renderWith('Sunnysideup\\TemplateOverview\\TemplateOverviewPageShowMoreList');
    }

    public function Link($action = null)
    {
        $v = '/' . $this->Config()->url_segment;
        if ($action) {
            $v .= $action . '/';
        }

        return $v;
    }

    public function TestTaskLink()
    {
        return '/dev/tasks/CheckAllTemplates/';
    }

    public function QuickListLink()
    {
        return $this->Link('quicklist');
    }

    public function ImagesListLink()
    {
        return $this->Link('listofobjectsused/Image');
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
        $classWeAreLookingFor = $request->param('ID');
        $classWeAreLookingFor = Injector::inst()->get($classWeAreLookingFor);
        if ($classWeAreLookingFor instanceof DataObject) {
            $list = $this->ListOfAllSiteTreeClasses();
            foreach ($list as $item) {
                $config = Config::inst();

                /** @var array */
                $listOfImages = $config->get($item->ClassName, 'has_one')
                    + $config->get($item->ClassName, 'has_many')
                    + $config->get($item->ClassName, 'many_many');
                foreach ($listOfImages as $fieldName => $potentialImage) {
                    $innerSingleton = singleton($potentialImage);
                    if ($innerSingleton instanceof $classWeAreLookingFor) {
                        DB::alteration_message($item->ClassName . '.' . $fieldName);
                    }
                }
            }
        } else {
            user_error('Please specify the ID for the model you are looking for - e.g. /listofobjectsused/Image/', E_USER_ERROR);
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
        return count(ClassInfo::subclassesFor(SiteTree::class)) - 1;
    }
}
