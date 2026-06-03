<?php

declare(strict_types=1);

namespace Sunnysideup\TemplateOverview\Api;

use Wilr\GoogleSitemaps\GoogleSitemap;
use Wilr\GoogleSitemaps\Extensions\GoogleSitemapExtension;
use ReflectionClass;
use SilverStripe\Control\Director;
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

    protected int $numberOfExamples = 3;

    public bool $includeFrontEnd = true;

    public bool $includeBackEnd = true;

    private array $listOfAllSiteTreeClassesCache = [];

    private array $errorsInGoogleSitemap = [];
    /**
     * url snippets that if found in links should exclude the link altogether.
     * e.g. 'admin/registry'.
     *
     * @var array
     */
    private static array $exclude_list = [
        'admin/user-forms',
    ];

    /**
     * @var int
     */
    private static int $number_of_examples = 1;

    /**
     * @var array
     */
    private static array $custom_links = [
        'sitemap.xml',
        'Security/login',
        'Security/logout',
        'Security/lostpassword',
        'Security/lostpassword/passwordsent',
    ];

    /**
     * @var array
     */
    private static array $controller_name_space_filter = [];

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
        if ($this->listOfAllSiteTreeClassesCache === []) {
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
        if (class_exists(GoogleSitemap::class) && $obj instanceof DataObject && ! $obj->hasExtension(GoogleSitemapExtension::class)) {
            $this->errorsInGoogleSitemap[$obj->ClassName . ',' . $obj->ID] =
                $obj->getTitle . ' (' . $obj->i18n_singular_name() . ') is not listed in the google sitemap...' . $link;
        }
    }
    /**
      * Pushes an array of items to an array.
      *
      * @param array $array     Array to push items to (will overwrite)
      * @param array $pushArray array of items to push to $array
      */
    protected function addToArrayOfLinks($array, $pushArray): array
    {
        $excludeList = $this->config()->exclude_list;
        foreach ($pushArray as $pushItem) {
            if ($pushItem) {
                // clean
                $pushItem = rtrim(str_replace('?stage=Stage', '?', (string) $pushItem), '?');
                $pushItem = str_replace('?&', '?', $pushItem);

                $pushItem = self::sanitise_class_name($pushItem);
                $pushItem = '/' . Director::makeRelative($pushItem);
                //is it a file?
                if (strpos($pushItem, '.') > (strlen($pushItem) - 6)) {
                    $pushItem = rtrim($pushItem, '/');
                }

                if (str_starts_with($pushItem, 'http') || str_starts_with($pushItem, '//')) {
                    continue;
                } elseif ('' !== $pushItem) {
                    if (! empty($excludeList)) {
                        foreach ($excludeList as $excludeItem) {
                            if (false !== stripos($pushItem, (string) $excludeItem)) {
                                continue 2;
                            }
                        }
                    }

                    if (! in_array($pushItem, $array, true)) {
                        $array[] = $pushItem;
                    }
                }
            }
        }

        return $array;
    }

    /**
     * Sanitise a model class' name for inclusion in a link.
     *
     * @param string $class
     *
     * @return string
     */
    public static function sanitise_class_name($class)
    {
        return str_replace('\\', '-', $class);
    }

}
