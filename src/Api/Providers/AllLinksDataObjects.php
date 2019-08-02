<?php

namespace Sunnysideup\TemplateOverview\Api\Providers;
use Sunnysideup\TemplateOverview\Api\AllLinksProviderBase;
use Sunnysideup\TemplateOverview\Api\AllLinks;
use ReflectionClass;
use ReflectionMethod;




use SilverStripe\Admin\LeftAndMain;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

class AllLinksDataObjects extends AllLinksProviderBase
{



    /**
     * @param bool $inCMS
     *
     * @return array
     */
    public function getAllLinksInner(bool $inCMS)
    {
        //first() will return null or the object
        $return = [];
        $list = ClassInfo::subclassesFor(DataObject::class);
        $exceptForArray = array_merge($this->getListOfAllSiteTreeClasses(), [DataObject::class]);
        foreach ($list as $class) {
            if (! in_array($class, $exceptForArray, true)) {
                if ($this->isValidClass($class)) {
                    for ($i = 0; $i < $this->getNumberOfExamples(); $i++) {
                        $obj = DataObject::get_one(
                            $class,
                            ['ClassName' => $class],
                            null,
                            DB::get_conn()->random() . ' ASC'
                        );
                        if ($obj) {
                            if ($inCMS) {
                                if ($obj->hasMethod('CMSEditLink')) {
                                    $return[] = $obj->CMSEditLink();
                                }
                                if ($obj->hasMethod('CMSAddLink')) {
                                    $return[] = $obj->CMSAddLink();
                                }
                                if ($obj->hasMethod('CMSListLink')) {
                                    $return[] = $obj->CMSListLink();
                                }
                                if ($obj->hasMethod('PreviewLink')) {
                                    $return[] = $obj->PreviewLink();
                                }
                            } else {
                                if ($obj->hasMethod('Link')) {
                                    $return[] = $obj->Link();
                                }
                                if ($obj->hasMethod('getLink')) {
                                    $return[] = $obj->getLink();
                                }
                            }
                        }
                    }
                }
            }
        }

        return $return;
    }
}
