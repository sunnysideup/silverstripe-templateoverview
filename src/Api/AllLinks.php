<?php

namespace Sunnysideup\TemplateOverview\Api;

use SilverStripe\Admin\CMSMenu;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;
use Sunnysideup\TemplateOverview\Api\Providers\AllLinksArchiveAdmin;
use Sunnysideup\TemplateOverview\Api\Providers\AllLinksControllerInfo;
use Sunnysideup\TemplateOverview\Api\Providers\AllLinksDataObjects;
use Sunnysideup\TemplateOverview\Api\Providers\AllLinksModelAdmin;
use Sunnysideup\TemplateOverview\Api\Providers\AllLinksReports;

class AllLinks extends AllLinksProviderBase
{
    /**
     * @var mixed|mixed[]
     */
    public $archiveCMSLinks;

    /**
     * @var array
     */
    protected $allNonCMSLinks = [];

    /**
     * @var array
     */
    protected $pagesOnFrontEnd = [];

    /**
     * @var array
     */
    protected $dataObjectsOnFrontEnd = [];

    /**
     * @var array
     */
    protected $otherControllerMethods = [];

    /**
     * @var array
     */
    protected $customNonCMSLinks = [];

    /**
     * @var array
     */
    protected $allCMSLinks = [];

    /**
     * @var array
     */
    protected $pagesInCMS = [];

    /**
     * @var array
     */
    protected $dataObjectsInCMS = [];

    /**
     * @var array
     */
    protected $modelAdmins = [];

    /**
     * @var array
     */
    protected $reportLinks = [];

    /**
     * @var array
     */
    protected $leftAndMainLnks = [];

    /**
     * @var array
     */
    protected $customCMSLinks = [];

    /**
     * url snippets that if found in links should exclude the link altogether.
     * e.g. 'admin/registry'.
     *
     * @var array
     */
    private static $exclude_list = [];

    /**
     * @var int
     */
    private static $number_of_examples = 1;

    /**
     * @var array
     */
    private static $custom_links = [
        'Security/login',
        'Security/logout',
        'Security/lostpassword',
        'Security/lostpassword/passwordsent',
    ];

    /**
     * @var array
     */
    private static $controller_name_space_filter = [];

    /**
     * @param string $link
     */
    public static function is_admin_link($link): bool
    {
        return 'admin' === substr(ltrim($link, '/'), 0, 5);
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

    /**
     * returns an array of allNonCMSLinks => [] , allCMSLinks => [], otherControllerMethods => [].
     */
    public function getAllLinks(): array
    {
        foreach ($this->Config()->get('custom_links') as $link) {
            $link = '/' . ltrim($link, '/') . '/';
            if (self::is_admin_link($link)) {
                $this->customCMSLinks[] = $link;
            } else {
                $this->customNonCMSLinks[] = $link;
            }
        }
        $this->pagesOnFrontEnd = $this->ListOfPagesLinks();
        $this->dataObjectsOnFrontEnd = $this->ListOfDataObjectsLinks(false);

        $this->allNonCMSLinks = $this->addToArrayOfLinks($this->allNonCMSLinks, $this->pagesOnFrontEnd);
        $this->allNonCMSLinks = $this->addToArrayOfLinks($this->allNonCMSLinks, $this->dataObjectsOnFrontEnd);
        $this->allNonCMSLinks = $this->addToArrayOfLinks($this->allNonCMSLinks, $this->customNonCMSLinks);
        sort($this->allNonCMSLinks);

        $this->pagesInCMS = $this->ListOfPagesLinks(true);
        $this->dataObjectsInCMS = $this->ListOfDataObjectsLinks(true);
        $this->modelAdmins = $this->ListOfAllModelAdmins();
        $this->archiveCMSLinks = $this->ListOfAllArchiveCMSLinks();
        $this->leftAndMainLnks = $this->ListOfAllLeftAndMains();
        $this->reportLinks = $this->listOfAllReports();

        $this->allCMSLinks = $this->addToArrayOfLinks($this->allCMSLinks, $this->pagesInCMS);
        $this->allCMSLinks = $this->addToArrayOfLinks($this->allCMSLinks, $this->dataObjectsInCMS);
        $this->allCMSLinks = $this->addToArrayOfLinks($this->allCMSLinks, $this->modelAdmins);
        $this->allCMSLinks = $this->addToArrayOfLinks($this->allCMSLinks, $this->archiveCMSLinks);
        $this->allCMSLinks = $this->addToArrayOfLinks($this->allCMSLinks, $this->leftAndMainLnks);
        $this->allCMSLinks = $this->addToArrayOfLinks($this->allCMSLinks, $this->reportLinks);
        $this->allCMSLinks = $this->addToArrayOfLinks($this->allCMSLinks, $this->customCMSLinks);
        sort($this->allCMSLinks);

        $this->otherControllerMethods = $this->ListOfAllControllerMethods();

        return [
            'allNonCMSLinks' => $this->allNonCMSLinks,
            'allCMSLinks' => $this->allCMSLinks,
            'otherLinks' => $this->otherControllerMethods,
        ];
    }

    /**
     * returns a list of all model admin links.
     *
     * @return array
     */
    public function ListOfAllModelAdmins()
    {
        $obj = Injector::inst()->get(AllLinksModelAdmin::class);
        $obj->setNumberOfExamples($this->Config()->number_of_examples);

        return $obj->getAllLinksInner();
    }

    /**
     * returns a list of all archive links.
     *
     * @return array
     */
    public function ListOfAllArchiveCMSLinks()
    {
        $obj = Injector::inst()->get(AllLinksArchiveAdmin::class);
        $obj->setNumberOfExamples($this->Config()->number_of_examples);

        return $obj->getAllLinksInner();
    }

    public function ListOfAllControllerMethods(): array
    {
        $obj = Injector::inst()->get(AllLinksControllerInfo::class);
        $obj->setValidNameSpaces($this->Config()->controller_name_space_filter);

        return $obj->getAllLinksInner();
    }

    public function ListOfDataObjectsLinks(bool $inCMS): array
    {
        $obj = Injector::inst()->get(AllLinksDataObjects::class);
        $obj->setNumberOfExamples($this->Config()->number_of_examples);

        return $obj->getAllLinksInner($inCMS);
    }

    /**
     * Takes {@link #$classNames}, gets the URL of the first instance of it
     * (will exclude extensions of the class) and
     * appends to the {@link #$urls} list to be checked.
     *
     * @param bool $pageInCMS
     *
     * @return array
     */
    public function ListOfPagesLinks($pageInCMS = false)
    {
        //first() will return null or the object
        $return = [];
        $siteTreeClassNames = $this->getListOfAllClasses();
        foreach ($siteTreeClassNames as $class) {
            for ($i = 0; $i < $this->Config()->number_of_examples; ++$i) {
                $excludedClasses = $this->arrayExcept($siteTreeClassNames, $class);
                $page = Versioned::get_by_stage($class, Versioned::LIVE)
                    ->exclude(['ClassName' => $excludedClasses])
                    ->sort(DB::get_conn()->random() . ' ASC')
                    ->first()
                ;
                if (null === $page) {
                    $page = Versioned::get_by_stage($class, Versioned::DRAFT)
                        ->exclude(['ClassName' => $excludedClasses])
                        ->sort(DB::get_conn()->random() . ' ASC')
                        ->first()
                    ;
                }
                if (null !== $page) {
                    if ($pageInCMS) {
                        $url = $page->CMSEditLink();
                        $return[] = $url;
                        $return[] = str_replace('/edit/', '/settings/', $url);
                        $return[] = str_replace('/edit/', '/history/', $url);
                    } else {
                        $url = $page->Link();
                        $return[] = $url;
                    }
                }
            }
        }

        return $return;
    }

    /**
     * @return array
     */
    public function ListOfAllLeftAndMains()
    {
        //first() will return null or the object
        $return = [];
        $list = CMSMenu::get_cms_classes();
        foreach ($list as $class) {
            if ($this->isValidClass($class)) {
                $obj = Injector::inst()->get($class);
                if ($obj) {
                    $url = $obj->Link();
                    if ($url) {
                        $return[] = $url;
                    }
                }
            }
        }

        return $return;
    }

    /**
     * returns a list of all reports.
     *
     * @return array
     */
    public function ListOfAllReports()
    {
        $reportsLinks = Injector::inst()->get(AllLinksReports::class);

        return $reportsLinks->getAllLinksInner();
    }

    /**
     * Pushes an array of items to an array.
     *
     * @param array $array     Array to push items to (will overwrite)
     * @param array $pushArray array of items to push to $array
     */
    protected function addToArrayOfLinks($array, $pushArray): array
    {
        $excludeList = $this->Config()->exclude_list;
        foreach ($pushArray as $pushItem) {
            //clean
            if (self::is_admin_link($pushItem)) {
                $pushItem = str_replace('?stage=Stage', '', $pushItem);
            }
            $pushItem = self::sanitise_class_name($pushItem);
            $pushItem = '/' . Director::makeRelative($pushItem);
            //is it a file?
            if (strpos($pushItem, '.') > (strlen($pushItem) - 6)) {
                $pushItem = rtrim($pushItem, '/');
            }
            if ('' !== $pushItem) {
                if (! empty($excludeList)) {
                    foreach ($excludeList as $excludeItem) {
                        if (false !== stripos($pushItem, $excludeItem)) {
                            continue 2;
                        }
                    }
                }
                if (! in_array($pushItem, $array, true)) {
                    $array[] = $pushItem;
                }
            }
        }

        return $array;
    }
}
