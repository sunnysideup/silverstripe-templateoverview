<?php

namespace Sunnysideup\TemplateOverview\Control;

use PageController;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Assets\Image;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use Sunnysideup\PrettyPhoto\PrettyPhoto;
use Sunnysideup\TemplateOverview\Api\SiteTreeDetails;

/**
 *@author: nicolaas [at] sunnysideup.co.nz
 *@description Add a page to your site that allows you to view all the html that can be used in the typography section - if applied correctly.
 */
class TemplateOverviewPageController extends PageController
{
    protected $myMoreList;
    private static $url_segment = 'admin/templateoverviewtemplates';

    /**
     *  folder for example images from root dir
     *  it is recommended to keep this outside of public dir!
     *
     *  @var string
     */
    private static $folder_for_example_images = '';

    private static $allowed_actions = [
        'showmore' => true,
        'quicklist' => true,
        'listofobjectsused' => true,
        'exampleimage' => true,
    ];

    private static $base_class = SiteTree::class;

    private static $base_class_provider = SiteTreeDetails::class;

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
        $className = $this->getBaseClass();
        /** @var null|DataObject $obj */
        $obj = $className::get()->byID((int) $id);
        if (null !== $obj) {
            $className = $obj->ClassName;
            $list = $className::get()
                ->filter(['ClassName' => $obj->ClassName])
                ->limit(300)
            ;
            $this->myMoreList = ArrayList::create();
            foreach ($list as $count => $item) {
                $this->myMoreList->push(clone $this->createPageObject($item, $count));
            }
        }

        return $this->renderWith('Sunnysideup\\TemplateOverview\\TemplateOverviewPageShowMoreList');
    }

    public function getMyMoreList()
    {
        return $this->myMoreList;
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
        return $this->Link('listofobjectsused/' . Image::class);
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
        $classWeAreLookingFor = '\\' . str_replace('-', '\\', $request->param('ID'));
        $classWeAreLookingFor = Injector::inst()->get($classWeAreLookingFor);
        if ($classWeAreLookingFor instanceof DataObject) {
            $list = $this->ListOfAllClasses();
            foreach ($list as $item) {
                $config = Config::inst();

                /** @var array $listOfImages */
                $a = (array) $config->get($item->ClassName, 'has_one', Config::UNINHERITED);
                $b = (array) $config->get($item->ClassName, 'has_many', Config::UNINHERITED);
                $c = (array) $config->get($item->ClassName, 'many_many', Config::UNINHERITED);
                $listOfImages = array_merge($a, $b, $c);
                foreach ($listOfImages as $fieldName => $potentialImage) {
                    if (is_array($potentialImage)) {
                        $potentialImage = $potentialImage['to'] ?? '';
                    }
                    $potentialImage = (explode('.', $potentialImage))[0];
                    if (class_exists($potentialImage)) {
                        $innerSingleton = Injector::inst()->get($potentialImage);
                        if ($innerSingleton instanceof $classWeAreLookingFor) {
                            DB::alteration_message($item->ClassName . '.' . $fieldName);
                        }
                    }
                }
            }
        } else {
            user_error('Please specify the ID for the model you are looking for - e.g. /listofobjectsused/Image/', E_USER_ERROR);
        }
    }

    /**
     * returns a list of all (SiteTree) Classes.
     *
     * @return ArrayList
     */
    public function ListOfAllClasses()
    {
        $providerClass = Injector::inst()->get($this->Config()->get('base_class_provider'));

        $originalList = $providerClass->ListOfAllClasses();
        $countsPerClass = $providerClass->CountsPerClass();
        $newList = ArrayList::create();
        foreach ($originalList as $obj) {
            $count = (int) ($countsPerClass[$obj->ClassName] ?? 0);
            $newList->push($this->createPageObject($obj, $count));
        }

        return $newList;
    }

    public function TotalCount()
    {
        $className = $this->getBaseClass();

        return count(ClassInfo::subclassesFor($className)) - 1;
    }

    protected function getBaseClass(): string
    {
        return $this->Config()->get('base_class');
    }

    protected function init()
    {
        parent::init();
        //important
        Versioned::set_stage(Versioned::DRAFT);
        if (Director::is_cli() || Director::isDev() || Permission::check('ADMIN')) {
            Requirements::javascript('//code.jquery.com/jquery-1.7.2.min.js');
            Requirements::javascript('sunnysideup/templateoverview: client/javascript/TemplateOverviewPage.js');
            Requirements::themedCSS('client/css/TemplateOverviewPage');
            if (class_exists(PrettyPhoto::class)) {
                PrettyPhoto::include_code();
            }
            //user_error("It is recommended that you install the Sunny Side Up Pretty Photo Module", E_USER_NOTICE);
        } else {
            return Security::permissionFailure($this, 'Please login to access this list');
        }
    }

    /**
     * @param SiteTree $obj
     * @param int      $count
     *
     * @return ArrayData
     */
    protected function createPageObject($obj, $count)
    {
        $listArray = [];
        $listArray['Name'] = 1 === $count ? $obj->i18n_singular_name() : $obj->i18n_plural_name();
        $listArray['ClassName'] = $obj->ClassName;
        $listArray['Count'] = $count;
        $listArray['ID'] = $obj->ID;
        $listArray['URLSegment'] = $obj->URLSegment ?? 'n/a';
        $listArray['ControllerLink'] = $this->Link();
        $listArray['Title'] = $obj->getTitle();
        $listArray['LiveLink'] = $obj->hasMethod('Link') ? str_replace('?stage=Stage', '', $obj->Link()) : 'please-add-Link-method';
        $listArray['PreviewLink'] = $obj->hasMethod('PreviewLink') ? $obj->PreviewLink() : 'please-add-PreviewLink-method';
        $listArray['CMSEditLink'] = $obj->hasMethod('CMSEditLink') ? $obj->CMSEditLink() : 'please-add-CMSEditLink-method';
        $listArray['Icon'] = $this->getIcon($obj);

        return new ArrayData($listArray);
    }

    protected function getIcon($obj): string
    {
        if ($obj->hasMethod('getPageIconURL')) {
            return (string) $obj->getPageIconURL();
        }
        if ($obj->hasMethod('getIcon')) {
            return (string) $obj->getIcon();
        }

        return (string) LeftAndMain::menu_icon_for_class($obj->ClassName);
    }
}
