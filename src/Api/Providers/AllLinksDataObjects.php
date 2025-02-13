<?php

namespace Sunnysideup\TemplateOverview\Api\Providers;

use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use Sunnysideup\TemplateOverview\Api\AllLinksProviderBase;

class AllLinksDataObjects extends AllLinksProviderBase
{
    /**
     * @return array
     */
    public function getAllLinksInner(bool $inCMS)
    {
        //first() will return null or the object
        $return = [];
        $list = ClassInfo::subclassesFor(DataObject::class);
        $exceptForArray = array_merge($this->getListOfAllClasses(), [DataObject::class]);

        // make it easier - just read live stuff.
        Versioned::set_reading_mode('Stage.Live');
        foreach ($list as $class) {
            if (! in_array($class, $exceptForArray, true) && $this->isValidClass($class)) {
                $objects = $class::get()
                    ->filter(['ClassName' => $class])
                    ->shuffle()
                    ->limit($this->getNumberOfExamples());
                foreach ($objects as $obj) {
                    if ($inCMS) {
                        if ($obj->hasMethod('CMSEditLink')) {
                            $return[] = $obj->CMSEditLink();
                        }
                        if ($obj->hasMethod('getCMSEditLink')) {
                            $return[] = $obj->getCMSEditLink();
                        }
                        if ($obj->hasMethod('EditLink')) {
                            $return[] = $obj->EditLink();
                        }
                        if ($obj->hasMethod('getEditLink')) {
                            $return[] = $obj->getEditLink();
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
                    } elseif ($obj->hasMethod('Link') && ! (property_exists($obj, 'LinkID') && null !== $obj->LinkID)) {
                        $return[] = $obj->Link();
                        $this->checkForErrorsInGoogleSitemap($obj, $obj->Link());
                    } elseif ($obj->hasMethod('AbsoluteLink')) {
                        $return[] = $obj->AbsoluteLink();
                    } elseif ($obj->hasMethod('getLink')) {
                        $return[] = $obj->getLink();
                    }
                }
            }
        }

        return $return;
    }
}
