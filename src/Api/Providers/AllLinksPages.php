<?php

namespace Sunnysideup\TemplateOverview\Api\Providers;

use SilverStripe\Versioned\Versioned;
use Sunnysideup\TemplateOverview\Api\AllLinksProviderBase;

class AllLinksPages extends AllLinksProviderBase
{
    /**
     * @return array
     */
    public function getAllLinksInner(bool $inCMS)
    {
        //first() will return null or the object
        $return = [];

        // make it easier - just read live stuff.
        if ($inCMS) {
            Versioned::set_stage(Versioned::DRAFT);
        } else {
            Versioned::set_stage(Versioned::LIVE);
        }
        //first() will return null or the object
        $return = [];
        $siteTreeClassNames = $this->getListOfAllClasses();
        foreach ($siteTreeClassNames as $class) {
            $excludedClasses = $this->arrayExcept($siteTreeClassNames, $class);
            $pages = Versioned::get_by_stage($class, Versioned::LIVE)
                ->exclude(['ClassName' => $excludedClasses])
                ->shuffle()
                ->limit($this->numberOfExamples);
            if (! $pages->exists()) {
                $pages = Versioned::get_by_stage($class, Versioned::DRAFT)
                    ->exclude(['ClassName' => $excludedClasses])
                    ->shuffle()
                    ->limit($this->numberOfExamples);
            }

            foreach ($pages as $page) {
                if ($inCMS) {
                    $url = (string) $page->CMSEditLink();
                    $return[] = $url;
                    $return[] = str_replace('/edit/', '/settings/', $url);
                    $return[] = str_replace('/edit/', '/history/', $url);
                } else {
                    $url = $page->Link();
                    $return[] = $url;
                }
            }
        }

        return $return;
    }
}
