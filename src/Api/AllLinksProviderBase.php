<?php

namespace Sunnysideup\TemplateOverview\Api;

use ReflectionClass;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

abstract class AllLinksProviderBase
{
    use Extensible;
    use Injectable;
    use Configurable;

    protected $numberOfExamples = 1;

    /**
     * @var bool
     */
    public $includeFrontEnd = true;

    /**
     * @var bool
     */
    public $includeBackEnd = true;

    private $listOfAllSiteTreeClassesCache = [];

    private $errorsInGoogleSitemap = [];

    public function setNumberOfExamples(int $n): static
    {
        $this->numberOfExamples = $n;

        return $this;
    }

    public function getNumberOfExamples(): int
    {
        return $this->numberOfExamples;
    }


    public function setIncludeFrontEnd(bool $b): static
    {
        $this->includeFrontEnd = $b;
        return $this;
    }

    public function setIncludeBackEnd(bool $b): static
    {
        $this->includeBackEnd = $b;
        return $this;
    }

    public function getErrorsInGoogleSitemap(): array
    {
        return $this->errorsInGoogleSitemap;
    }

    /**
     * returns a list of all SiteTree Classes.
     *
     * @return array
     */
    public function getListOfAllClasses()
    {
        if (empty($this->listOfAllSiteTreeClassesCache)) {
            $siteTreeDetails = Injector::inst()->get(SiteTreeDetails::class);
            $list = $siteTreeDetails->ListOfAllClasses();
            foreach ($list as $page) {
                $this->listOfAllSiteTreeClassesCache[] = $page->ClassName;
            }
        }

        return $this->listOfAllSiteTreeClassesCache;
    }

    protected function isValidClass($class)
    {
        $obj = new ReflectionClass($class);

        return ! $obj->isAbstract();
    }

    /**
     * Takes an array, takes one item out, and returns new array.
     *
     * @param array  $array     array which will have an item taken out of it
     * @param string $exclusion Item to be taken out of $array
     *
     * @return array new array
     */
    protected function arrayExcept($array, $exclusion)
    {
        $newArray = $array;
        $count = count($newArray);
        for ($i = 0; $i < $count; ++$i) {
            if ($newArray[$i] === $exclusion) {
                unset($newArray[$i]);
            }
        }

        return $newArray;
    }

    protected function tableExists(string $table): bool
    {
        $tables = DB::table_list();

        return array_key_exists(strtolower($table), $tables);
    }

    protected function checkForErrorsInGoogleSitemap($obj, ?string $link = '')
    {
        if(class_exists('\\Wilr\\GoogleSitemaps\\GoogleSitemap')) {
            if($obj instanceof DataObject) {
                if(! $obj->hasExtension('\\Wilr\\GoogleSitemaps\\Extensions\\GoogleSitemapExtension')) {
                    $this->errorsInGoogleSitemap[$obj->ClassName . ','.$obj->ID] =
                        $obj->getTitle .' ('.$obj->i18n_singular_name().') is not listed in the google sitemap...'.$link;
                }
            }
        }
    }
}
