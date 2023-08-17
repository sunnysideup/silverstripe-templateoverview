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
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use SilverStripe\View\SSViewer;
use Sunnysideup\PrettyPhoto\PrettyPhoto;
use Sunnysideup\TemplateOverview\Api\SiteTreeDetails;
use Sunnysideup\TemplateOverview\Api\TemplateOverviewArrayMethods;

/**
 * Class \Sunnysideup\TemplateOverview\Control\TemplateOverviewPageController
 *
 */
class TemplateOverviewPageController extends PageController
{
    protected $myMoreList;
    protected $totalPageCount = 0;

    private static $url_segment = 'admin/templates';

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
        $obj = $className::get_by_id((int) $id);
        if (null !== $obj) {
            $className = $obj->ClassName;
            $list = $className::get()
                ->filter(['ClassName' => $obj->ClassName])
                ->limit(300);
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
        $listOfAllClasses = $providerClass->CountsPerClass();
        $newList = ArrayList::create();
        foreach ($originalList as $obj) {
            $count = (int) ($listOfAllClasses[$obj->ClassName] ?? 0);
            $newList->push($this->createPageObject($obj, $count));
            $this->totalPageCount += $count;
        }

        return $newList;
    }

    public function HasElemental(): bool
    {
        return class_exists('\\DNADesign\\Elemental\\Models\\BaseElement');
    }

    public function TotalTemplateCount(): int
    {
        $className = $this->getBaseClass();

        return count(ClassInfo::subclassesFor($className)) - 1;
    }

    public function TotalPageCount(): int
    {
        $this->ListOfAllClasses();
        return $this->totalPageCount;
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
            Config::modify()->set(SSViewer::class, 'theme_enabled', false);
            Requirements::css('sunnysideup/templateoverview: client/css/TemplateOverviewPage.css');
            Requirements::css('silverstripe/admin: client/dist/styles/bundle.css');
            Requirements::javascript('https://code.jquery.com/jquery-3.6.3.min.js');
            Requirements::javascript('sunnysideup/templateoverview: client/javascript/TemplateOverviewPage.js');
            // if (class_exists(PrettyPhoto::class)) {
            //     PrettyPhoto::include_code();
            // }

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
        $parent = $obj->Parent();
        $canCreateString = ($obj->canCreate() ? 'Yes' : 'No');
        $isAdmin = Permission::check('ADMIN');
        $listArray = [];
        $listArray['Name'] = 1 === $count ? $obj->i18n_singular_name() : $obj->i18n_plural_name();
        $listArray['Description'] = DBField::create_field('HTMLText', $obj->hasMethod('i18n_classDescription') ? $obj->i18n_classDescription() : Config::inst()->get($obj->ClassName, 'description'));
        $listArray['ClassName'] = $obj->ClassName;
        $listArray['Count'] = $count;
        $listArray['ID'] = $obj->ID;
        $listArray['URLSegment'] = $obj->URLSegment ?? 'n/a';
        $listArray['ControllerLink'] = $this->Link();
        $listArray['Title'] = $obj->getTitle();
        $listArray['LiveLink'] = $obj->hasMethod('Link') ? str_replace('?stage=Stage', '', (string) $obj->Link()) : 'please-add-Link-method';
        $listArray['PreviewLink'] = $obj->hasMethod('PreviewLink') ? $obj->PreviewLink() : 'please-add-PreviewLink-method';
        $listArray['CMSEditLink'] = $obj->hasMethod('CMSEditLink') ? $obj->CMSEditLink() : 'please-add-CMSEditLink-method';
        $listArray['MoreCanBeCreated'] = $isAdmin ? $canCreateString : 'Please login as ADMIN to see this value';
        $listArray['AllowedChildren'] = 'none';
        $listArray['AllowedActions'] = 'none';
        $listArray['Breadcrumbs'] = $parent ? $parent->Breadcrumbs() : '';
        $listArray['Icon'] = $this->getIcon($obj);
        if ($obj instanceof SiteTree) {
            $children = $this->listOfTitles($obj->allowedChildren());
            if(count($children)) {
                $listArray['AllowedChildren'] = implode(', ', $children);
            }
            $actions = TemplateOverviewArrayMethods::get_best_array_keys($obj->Config()->get('allowed_actions'));
            if(count($actions)) {
                $listArray['AllowedActions'] = implode(', ', $actions);
            }
        }

        return new ArrayData($listArray);
    }

    protected function getIcon($obj): string
    {
        $icon = '';
        $icon = $this->getIconInner($obj, 'getPageIconURL');
        if (!$icon) {
            $icon = $this->getIconInner($obj, 'getIconClass');
            if (!$icon) {
                $icon = $this->getIconInner($obj, 'getIcon');
                if (!$icon) {
                    return (string) LeftAndMain::menu_icon_for_class($obj->ClassName);
                }
            }
        }

        if (!$icon) {
            $icon = 'font-page';
        }

        if (false === strpos('.', $icon)) {
            // $icon = str_replace('font-icon-', 'fa-', $icon);
        }

        return $icon;
    }

    protected function getIconInner($obj, $method): ?string
    {
        if ($obj->hasMethod($method)) {
            $icon = (string) $obj->{$method}();
            if ($icon) {
                return $icon;
            }
        }

        return null;
    }

    protected function listOfTitles($array)
    {
        if (is_array($array) && count($array)) {
            $newArray = [];
            foreach ($array as $item) {
                $obj = Injector::inst()->get($item);
                $newArray[] = $obj->i18n_singular_name();
            }

            return $newArray;
        }

        return ['none'];
    }
}
