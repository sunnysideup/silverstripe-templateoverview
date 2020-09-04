<?php

namespace Sunnysideup\TemplateOverview\Api;

namespace Sunnysideup\TemplateOverview\Api;

use ReflectionClass;





use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;

abstract class AllLinksProviderBase
{
    use Extensible;
    use Injectable;
    use Configurable;

    protected $numberOfExamples = 1;

    private $listOfAllSiteTreeClassesCache = [];

    public function setNumberOfExamples($n): self
    {
        $this->numberOfExamples = $n;

        return $this;
    }

    public function getNumberOfExamples(): int
    {
        return $this->numberOfExamples;
    }

    /**
     * returns a list of all SiteTree Classes
     * @return array
     */
    public function getListOfAllSiteTreeClasses()
    {
        if (empty($this->listOfAllSiteTreeClassesCache)) {
            $siteTreeDetails = Injector::inst()->get(SiteTreeDetails::class);
            $list = $siteTreeDetails->ListOfAllSiteTreeClasses();
            foreach ($list as $page) {
                $this->listOfAllSiteTreeClassesCache[] = $page->ClassName;
            }
        }
        return $this->listOfAllSiteTreeClassesCache;
    }

    protected function isValidClass($class)
    {
        $obj = new ReflectionClass($class);
        if ($obj->isAbstract()) {
            return false;
        }
        return true;
    }

    /**
     * Takes an array, takes one item out, and returns new array
     *
     * @param array $array Array which will have an item taken out of it.
     * @param string $exclusion Item to be taken out of $array
     *
     * @return array New array.
     */
    protected function arrayExcept($array, $exclusion)
    {
        $newArray = $array;
        $count = count($newArray);
        for ($i = 0; $i < $count; $i++) {
            if ($newArray[$i] === $exclusion) {
                unset($newArray[$i]);
            }
        }
        return $newArray;
    }
}
