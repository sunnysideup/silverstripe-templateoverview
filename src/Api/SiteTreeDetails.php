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

use SilverStripe\Admin\LeftAndMain;

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

    protected $arrayOfAllClasses = [];

    private static $list_of_all_classes = [];

    private static $list_of_all_classes_counter = [];

    private static $classes_to_exclude = [
        SiteTree::class,
        RedirectorPage::class,
        VirtualPage::class,
    ];


    public function CountsPerClass() : array
    {
        $this->ListOfAllClasses();
        return self::$list_of_all_classes_counter;
    }

    public function ListOfAllClasses() : ArrayList
    {
        if (0 === count(self::$list_of_all_classes)) {
            $this->getArrayOfAllClasses();

            self::$list_of_all_classes = new ArrayList();

            foreach ($this->arrayOfAllClasses as $item) {
                self::$list_of_all_classes->push($item);
            }
        }

        return self::$list_of_all_classes;
    }

    public function ShowAll()
    {
        $this->showAll = true;

        return [];
    }

    protected function getClassList()
    {
        return SiteTree::page_type_classes();
    }



    protected function getArrayOfAllClasses()
    {
        $classes = $this->getClassList();
        foreach ($classes as $className) {
            if (! in_array($className, $this->config()->get('classes_to_exclude'), true)) {
                if ($this->showAll) {
                    $objects = $className::get()
                        ->filter(['ClassName' => $className])
                        ->sort(DB::get_conn()->random() . ' ASC')
                        ->limit(25)
                    ;
                    $count = 0;
                    if ($objects->exists()) {
                        foreach ($objects as $obj) {
                            $count++;
                            $this->arrayOfAllClasses[$this->getIndexNumber($count)] = $obj;
                        }
                    }
                    self::$list_of_all_classes_counter[$className] = $count;
                } else {
                    $obj = $className::get()
                        ->filter(['ClassName' => $className])
                        ->sort(DB::get_conn()->random() . ' ASC')
                        ->limit(1)
                        ->first()
                    ;
                    if ($obj) {
                        $count = $className::get()->filter(['ClassName' => $obj->ClassName])->count();
                    } else {
                        $obj = $className::create();
                        $count = 0;
                    }
                    self::$list_of_all_classes_counter[$className] = $count;
                    $this->arrayOfAllClasses[$this->getIndexNumber($count)] = $obj;
                }
            }
        }

        //remove the hidden ancestors...

        ksort($this->arrayOfAllClasses);

        return $this->arrayOfAllClasses;
    }


    /**
     * @param int      $count
     *
     * @return int
     */
    protected function getIndexNumber($count)
    {
        ++$this->counter;
        return (100000 * $count) + $this->counter;
    }


}
