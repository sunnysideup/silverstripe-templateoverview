<?php

namespace Sunnysideup\TemplateOverview\Api;

use SilverStripe\CMS\Model\RedirectorPage;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Model\VirtualPage;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DB;
use SilverStripe\View\ArrayData;

class SiteTreeDetails
{
    use Extensible;
    use Injectable;
    use Configurable;

    /**
     * @var bool
     */
    protected $showAll = false;

    /**
     * @var int
     */
    protected $counter = 0;

    protected $classesToRemove = [];
    protected $arrayOfAllClasses = [];

    private static $list_of_all_classes = [];

    private static $classes_to_exclude = [
        SiteTree::class,
        RedirectorPage::class,
        VirtualPage::class,
    ];

    public function ListOfAllSiteTreeClasses($checkCurrentClass = true)
    {
        if (count(self::$list_of_all_classes) === 0) {

            $this->getArrayOfAllClasses();

            self::$list_of_all_classes = new ArrayList();
            $currentClassname = '';
            if ($checkCurrentClass) {
                if ($c = Controller::curr()) {
                    if ($d = $c->dataRecord) {
                        $currentClassname = $d->ClassName;
                    }
                }
            }
            if (count($this->arrayOfAllClasses)) {
                foreach ($this->arrayOfAllClasses as $item) {
                    if ($item->ClassName === $currentClassname) {
                        $item->LinkingMode = 'current';
                    } else {
                        $item->LinkingMode = 'link';
                    }
                    self::$list_of_all_classes->push($item);
                }
            }
        }
        return self::$list_of_all_classes;
    }

    protected function getArrayOfAllClasses()
    {
        //$classes = ClassInfo::subclassesFor("SiteTree");
        $classes = SiteTree::page_type_classes();
        foreach ($classes as $className) {
            if (! in_array($className, $this->config()->get('classes_to_exclude'), true)) {
                if ($this->showAll) {
                    $objects = $className::get()
                        ->filter(['ClassName' => $className])
                        ->sort(DB::get_conn()->random() . ' ASC')
                        ->limit(25);
                    $count = 0;
                    if ($objects->count()) {
                        foreach ($objects as $obj) {
                            $object = $this->createPageObject($obj, $count++);
                            $this->arrayOfAllClasses[$object->indexNumber] = clone $object;
                        }
                    }
                } else {
                    $obj = $className::get()
                        ->filter(['ClassName' => $className])
                        ->sort('RAND() ASC')
                        ->limit(1)
                        ->first();
                    if ($obj) {
                        $count = SiteTree::get()->filter(['ClassName' => $obj->ClassName])->count();
                    } else {
                        $obj = $className::create();
                        $count = 0;
                    }
                    $object = $this->createPageObject($obj, $count);
                    $this->arrayOfAllClasses[$object->indexNumber] = clone $object;
                }
                $this->removeHideAncestorBasedOnObject($obj);
            }
        }
        $this->removeHideAncestors();

        //remove the hidden ancestors...

        ksort($this->arrayOfAllClasses);

        return $this->arrayOfAllClasses;
    }


    protected function removeHideAncestorBasedOnObject($obj)
    {
        if($obj) {
            $ancestorToHide = Config::inst()->get($obj->ClassName, 'hide_ancestor');
            if ($ancestorToHide) {
                $this->classesToRemove[] = $ancestorToHide;
            }
        }
    }

    protected function removeHideAncestors()
    {
        if (! empty($this->classesToRemove)) {
            $this->classesToRemove = array_unique($this->classesToRemove);
            // unset from $classes
            foreach ($this->arrayOfAllClasses as $tempKey => $tempClass) {
                if (in_array($tempClass->ClassName, $this->classesToRemove, true)) {
                    unset($this->arrayOfAllClasses[$tempKey]);
                }
            }
        }
    }

    public function ShowAll()
    {
        $this->showAll = true;
        return [];
    }

    /**
     * @param SiteTree $obj
     * @param int $count
     *
     * @return ArrayData
     */
    protected function createPageObject($obj, $count)
    {
        $this->counter++;
        $listArray = [];
        $indexNumber = (10000 * $count) + $this->counter;
        $listArray['indexNumber'] = $indexNumber;
        $listArray['ClassName'] = $obj->ClassName;
        $listArray['Count'] = $count;
        $listArray['ID'] = $obj->ID;
        $listArray['URLSegment'] = $obj->URLSegment;
        $listArray['ControllerLink'] = '/templates/';
        $listArray['Title'] = $obj->MenuTitle;
        $listArray['PreviewLink'] = $obj->PreviewLink();
        $listArray['CMSEditLink'] = $obj->CMSEditLink();
        $staticIcon = Config::inst()->get($obj->ClassName, 'icon');
        if (is_array($staticIcon)) {
            $icon = $staticIcon[0];
        } else {
            $icon = $staticIcon;
        }
        $iconFile = Director::baseFolder() . '/' . $icon;
        if (! file_exists($iconFile)) {
            $icon .= '-file.gif';
        }
        $listArray['Icon'] = $icon;
        return new ArrayData($listArray);
    }
}
