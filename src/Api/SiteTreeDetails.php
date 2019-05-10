<?php

namespace Sunnysideup\TemplateOverview\Api;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Model\RedirectorPage;
use SilverStripe\CMS\Model\VirtualPage;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\View\ArrayData;
use SilverStripe\Core\ClassInfo;
use SilverStripe\View\ViewableData;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Extensible;

class SiteTreeDetails
{

    use Extensible;
    use Injectable;
    use Configurable;

    private static $list_of_all_classes = [];

    private static $classes_to_exclude = [
        SiteTree::class,
        RedirectorPage::class,
        VirtualPage::class
    ];

    /**
     *
     * @var Bool
     */
    protected $showAll = false;

    /**
     *
     * @var int
     */
    protected $counter = 0;


    public function ListOfAllSiteTreeClasses($checkCurrentClass = true)
    {
        if (!self::$list_of_all_classes) {
            $ArrayOfAllClasses =  [];
            //$classes = ClassInfo::subclassesFor("SiteTree");
            $classes = SiteTree::page_type_classes();
            $classesToRemove = [];

            foreach ($classes as $className) {
                if (!in_array($className, $this->config()->get("classes_to_exclude"))) {
                    if ($this->showAll) {
                        $objects = $className::get()
                            ->filter(array("ClassName" => $className))
                            ->sort(DB::get_conn()->random()." ASC")
                            ->limit(25);
                        $count = 0;
                        if ($objects->count()) {
                            foreach ($objects as $obj) {
                                if (!$count) {
                                    if ($ancestorToHide = $obj->stat('hide_ancestor')) {
                                        $classesToRemove[] = $ancestorToHide;
                                    }
                                }
                                $object = $this->createPageObject($obj, $count++);
                                $ArrayOfAllClasses[$object->indexNumber] = clone $object;
                            }
                        }
                    } else {
                        $obj = null;
                        $obj = $className::get()
                            ->filter(array("ClassName" => $className))
                            ->sort("RAND() ASC")
                            ->limit(1)
                            ->first();
                        if ($obj) {
                            $count = SiteTree::get()->filter(array("ClassName" => $obj->ClassName))->count();
                        } else {
                            $obj = $className::create();
                            $count = 0;
                        }
                        if ($ancestorToHide = $obj->stat('hide_ancestor')) {
                            $classesToRemove[] = $ancestorToHide;
                        }
                        $object = $this->createPageObject($obj, $count);
                        $ArrayOfAllClasses[$object->indexNumber] = clone $object;
                    }
                }
            }

            //remove the hidden ancestors...
            if ($classesToRemove && count($classesToRemove)) {
                $classesToRemove = array_unique($classesToRemove);
                // unset from $classes
                foreach ($ArrayOfAllClasses as $tempKey => $tempClass) {
                    if (in_array($tempClass->ClassName, $classesToRemove)) {
                        unset($ArrayOfAllClasses[$tempKey]);
                    }
                }
            }
            ksort($ArrayOfAllClasses);
            self::$list_of_all_classes =  new ArrayList();
            $currentClassname = '';
            if ($checkCurrentClass) {
                if ($c = Controller::curr()) {
                    if ($d = $c->dataRecord) {
                        $currentClassname = $d->ClassName;
                    }
                }
            }
            if (count($ArrayOfAllClasses)) {
                foreach ($ArrayOfAllClasses as $item) {
                    if ($item->ClassName == $currentClassname) {
                        $item->LinkingMode = "current";
                    } else {
                        $item->LinkingMode = "link";
                    }
                    self::$list_of_all_classes->push($item);
                }
            }
        }
        return self::$list_of_all_classes;
    }

    public function ShowAll()
    {
        $this->showAll = true;
        return [];
    }


    /**
     * @param SiteTree $obj
     * @param Int $count
     *
     * @return ArrayData
     */
    protected function createPageObject($obj, $count)
    {
        $this->counter++;
        $listArray = [];
        $indexNumber = (10000 * $count) + $this->counter;
        $listArray["indexNumber"] = $indexNumber;
        $listArray["ClassName"] = $obj->ClassName;
        $listArray["Count"] = $count;
        $listArray["ID"] = $obj->ID;
        $listArray["URLSegment"] = $obj->URLSegment;
        $listArray["ControllerLink"] = '/templates/';
        $listArray["Title"] = $obj->MenuTitle;
        $listArray["PreviewLink"] = $obj->PreviewLink();
        $listArray["CMSEditLink"] = $obj->CMSEditLink();
        $staticIcon = $obj->stat("icon", true);
        if (is_array($staticIcon)) {
            $iconArray = $obj->stat("icon");
            $icon = $iconArray[0];
        } else {
            $icon = $obj->stat("icon");
        }
        $iconFile = Director::baseFolder().'/'.$icon;
        if (!file_exists($iconFile)) {
            $icon = $icon."-file.gif";
        }
        $listArray["Icon"] = $icon;
        return new ArrayData($listArray);
    }



}
