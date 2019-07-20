<?php

namespace Sunnysideup\TemplateOverview\Api;


use ReflectionClass;
use ReflectionMethod;

use Sunnysideup\TemplateOverview\Api\SiteTreeDetails;


use Sunnysideup\TemplateOverview\Api\AllLinks;
use Sunnysideup\TemplateOverview\Api\W3cValidateApi;


use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Security\Member;
use SilverStripe\Security\Group;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Admin\CMSMenu;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPApplication;
use SilverStripe\Control\HTTPRequestBuilder;
use SilverStripe\Core\Manifest\ClassLoader;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\TaskRunner;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\CoreKernel;
use SilverStripe\Core\Startup\ErrorControlChainMiddleware;
use SilverStripe\Versioned\Versioned;


use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Extensible;

class AllLinks
{

    use Extensible;
    use Injectable;
    use Configurable;

    public static function is_admin_link($link)
    {
        return substr(ltrim($link, '/'),  0 , 5) === 'admin' ? true : false;
    }

    /**
     * url snippets that if found in links should exclude the link altogether.
     * e.g. 'admin/registry'
     *
     * @var array
     */
    private static $exclude_list = [];

    /**
     * List of alternative links for modeladmins
     * e.g. 'admin/archive' => 'CMSEditLinkForTestPurposesNOTINUSE'
     *
     * @var array
     */
    private static $model_admin_alternatives = [];


    /**
     * @var int
     */
    private static $number_of_examples = 1;

    /**
     * @var array
     */
    private static $custom_links = [];

    /**
     * @var array
     */
    private static $controller_name_space_filter = [];

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
    protected $leftAndMainLnks = [];


    /**
     * @var array
     */
    protected $customCMSLinks = [];

    /**
     * @var array
     * Pages to check by class name. For example, for "ClassPage", will check the first instance of the cart page.
     */
    protected $siteTreeClassNames = [];

    /**
     * returns an array of allNonCMSLinks => [] , allCMSLinks => [], otherControllerMethods => []
     * @return array
     */
    public function getAllLinks()
    {

        $this->siteTreeClassNames = $this->listOfAllSiteTreeClasses();

        foreach($this->Config()->get('custom_links') as $link) {
            $link = '/'.ltrim($link, '/').'/';
            if(self::is_admin_link($link)) {
                $this->customCMSLinks[] = $link;
            } else {
                $this->customNonCMSLinks[] = $link;
            }
        }
        $this->pagesOnFrontEnd = $this->ListOfPagesLinks(false);
        $this->dataObjectsOnFrontEnd = $this->ListOfDataObjectsLinks(false);

        $this->allNonCMSLinks = $this->addToArrayOfLinks($this->allNonCMSLinks, $this->pagesOnFrontEnd);
        $this->allNonCMSLinks = $this->addToArrayOfLinks($this->allNonCMSLinks, $this->dataObjectsOnFrontEnd);
        $this->allNonCMSLinks = $this->addToArrayOfLinks($this->allNonCMSLinks, $this->customNonCMSLinks);
        sort($this->allNonCMSLinks);

        $this->pagesInCMS = $this->ListOfPagesLinks(true);
        $this->dataObjectsInCMS = $this->ListOfDataObjectsLinks(true);
        $this->modelAdmins = $this->ListOfAllModelAdmins();
        $this->leftAndMainLnks = $this->ListOfAllLeftAndMains();

        $this->allCMSLinks = $this->addToArrayOfLinks($this->allCMSLinks, $this->pagesInCMS);
        $this->allCMSLinks = $this->addToArrayOfLinks($this->allCMSLinks, $this->dataObjectsInCMS);
        $this->allCMSLinks = $this->addToArrayOfLinks($this->allCMSLinks, $this->modelAdmins);
        $this->allCMSLinks = $this->addToArrayOfLinks($this->allCMSLinks, $this->leftAndMainLnks);
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
     * Takes {@link #$classNames}, gets the URL of the first instance of it
     * (will exclude extensions of the class) and
     * appends to the {@link #$urls} list to be checked
     *
     * @param bool $pageInCMS
     *
     * @return array
     */
    private function ListOfPagesLinks($pageInCMS = false)
    {
        //first() will return null or the object
        $return = [];
        foreach ($this->siteTreeClassNames as $class) {
            for($i = 0; $i < $this->Config()->number_of_examples; $i++) {
                $excludedClasses = $this->arrayExcept($this->siteTreeClassNames, $class);
                $page = Versioned::get_by_stage($class, Versioned::LIVE)
                    ->exclude(["ClassName" => $excludedClasses])
                    ->sort(DB::get_conn()->random().' ASC')
                    ->first();
                if (! $page) {
                    $page = Versioned::get_by_stage($class, Versioned::DRAFT)
                        ->exclude(["ClassName" => $excludedClasses])
                        ->sort(DB::get_conn()->random().' ASC')
                        ->first();
                }
                if($page) {
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
     *
     * @param bool $inCMS
     *
     * @return array
     */
    private function ListOfDataObjectsLinks($inCMS = false)
    {
        //first() will return null or the object
        $return = [];
        $list = ClassInfo::subclassesFor(DataObject::class);
        foreach ($list as $class) {
            if(! in_array($class, array_merge($this->siteTreeClassNames, [DataObject::class]))) {
                if($this->isValidClass($class)) {
                    for($i = 0; $i < $this->Config()->number_of_examples; $i++) {
                        $obj = DataObject::get_one(
                            $class,
                            ["ClassName" => $class],
                            null,
                            DB::get_conn()->random().' ASC'
                        );
                        if ($obj) {
                            if ($inCMS) {
                                if($obj->hasMethod('CMSEditLink')) {
                                    $return[] = $obj->CMSEditLink();
                                }
                                if($obj->hasMethod('CMSAddLink')) {
                                    $return[] = $obj->CMSAddLink();
                                }
                                if($obj->hasMethod('CMSListLink')) {
                                    $return[] = $obj->CMSListLink();
                                }
                                if($obj->hasMethod('PreviewLink')) {
                                    $return[] = $obj->PreviewLink();
                                }
                            } else {
                                if($obj->hasMethod('Link')) {
                                    $return[] = $obj->Link();
                                }
                                if($obj->hasMethod('getLink')) {
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


    /**
     *
     * @return array
     */
    private function ListOfAllLeftAndMains()
    {
        //first() will return null or the object
        $return = [];
        $list = CMSMenu::get_cms_classes();
        foreach ($list as $class) {
            if($this->isValidClass($class)) {
                $obj = Injector::inst()->get($class);
                if ($obj) {
                    $url = $obj->Link();
                    if($url) {
                        $return[] = $url;
                    }
                }
            }
        }

        return $return;
    }


    /**
     * returns a list of all model admin links
     * @return array
     */
    public function ListOfAllModelAdmins()
    {
        $obj = Injector::inst()->get(AllLinksModelAdmin::class);
        $obj->setNumberOfExamples($this->Config()->number_of_examples);

        return $obj->findModelAdminLinks();
    }

    /**
     * @return array
     */
    public function ListOfAllControllerMethods() : array
    {
        $finalFinalArray = [];

        $obj = Injector::inst()->get(AllLinksControllerInfo::class);
        $obj->setValidNameSpaces($this->Config()->controller_name_space_filter);

        $linksAndActions = $obj->getLinksAndActions();
        $allowedActions = $linksAndActions['Actions'];
        $controllerLinks = $linksAndActions['Links'];
        $finalArray = $linksAndActions['CustomLinks'];

        // die('---');
        //construct array!
        foreach ($allowedActions as $className  => $methods) {
            $link = $controllerLinks[$className];
            if($link) {
                $finalArray[$link] = $className;
            } else {
                $link = '???';
            }
            if(substr($link, -1) !== '/') {
                $link = $link.'/';
            }
            if(is_array($methods)) {
                foreach($methods as $method) {
                    unset($allowedActions[$className][$method]);
                    $finalArray[$link.$method.'/'] = $className;
                }
            }
        }

        foreach($finalArray as $link => $className) {
            $finalFinalArray[] = [
                'ClassName' => $className,
                'Link' => $link,
            ];
        }
        usort($finalFinalArray, function ($a, $b) {
            if ($a['ClassName'] !== $b['ClassName']) {
               return $a['ClassName'] <=> $b['ClassName'];
            }

            return $a['Link'] <=> $b['Link'];
        });

        return $finalFinalArray;
    }



    /**
      * Pushes an array of items to an array
      * @param array $array Array to push items to (will overwrite)
      * @param array $pushArray Array of items to push to $array.
      *
      * @return array
      */
    private function addToArrayOfLinks($array, $pushArray) : array
    {
        $excludeList = $this->Config()->exclude_list;
        foreach ($pushArray as $pushItem) {
            //clean
            if(self::is_admin_link($pushItem)) {
                $pushItem = str_replace('?stage=Stage', '', $pushItem);
            }
            $pushItem = $this->sanitiseClassName($pushItem);
            $pushItem = '/'.Director::makeRelative($pushItem);

            if(is_array($excludeList) && count($excludeList)) {
                foreach($excludeList as $excludeItem) {
                    if(stripos($pushItem, $excludeItem) !== false) {
                        continue 2;
                    }
                }
            }
            if(! in_array($pushItem, $array)) {
                array_push($array, $pushItem);
            }
        }
        return $array;
    }

    /**
     * returns a list of all SiteTree Classes
     * @return array
     */
    private function listOfAllSiteTreeClasses()
    {
        $pages = [];
        $siteTreeDetails = Injector::inst()->get(SiteTreeDetails::class);
        $list = $siteTreeDetails->ListOfAllSiteTreeClasses();
        foreach ($list as $page) {
            $pages[] = $page->ClassName;
        }

        return $pages;
    }

    /**
      * Takes an array, takes one item out, and returns new array
      *
      * @param array $array Array which will have an item taken out of it.
      * @param string $exclusion Item to be taken out of $array
      *
      * @return array New array.
      */
    private function arrayExcept($array, $exclusion)
    {
        $newArray = $array;
        for ($i = 0; $i < count($newArray); $i++) {
            if ($newArray[$i] == $exclusion) {
                unset($newArray[$i]);
            }
        }
        return $newArray;
    }



    /**
     * Sanitise a model class' name for inclusion in a link
     *
     * @param string $class
     * @return string
     */
    protected function sanitiseClassName($class)
    {
        return str_replace('\\', '-', $class);
    }


    protected function isValidClass($class)
    {
        $obj = new ReflectionClass($class);
        if($obj->isAbstract()) {
            return false;
        }
        return true;
    }




}
