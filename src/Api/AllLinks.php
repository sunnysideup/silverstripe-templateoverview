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
    private static $number_of_examples = 3;

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

    protected $controllerLinks = [];

    /**
     * returns an array of allNonCMSLinks => [] , allCMSLinks => [], otherControllerMethods => []
     * @return array
     */
    public function getAllLinks()
    {

        $this->siteTreeClassNames = $this->listOfAllSiteTreeClasses();

        foreach($this->Config()->get('custom_links') as $link) {
            $link = '/'.ltrim($link, '/').'/';
            if(substr($link,  0 , 6) === '/admin') {
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
        $this->models = $this->ListOfAllModelAdmins();
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
            'otherLinks' => $this->otherControllerMethods
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
                        $obj = DataObject::get_one($class, ["ClassName" => $class], DB::get_conn()->random().' ASC');
                        if ($obj) {
                            $url = null;
                            if ($inCMS) {
                                if($obj->hasMethod('CMSEditLink')) {
                                    $url = $obj->CMSEditLink();
                                }
                                if($obj->hasMethod('PreviewLink')) {
                                    $url = $obj->PreviewLink();
                                }
                            } else {
                                if($obj->hasMethod('Link')) {
                                    $url = $obj->Link();
                                }
                            }
                            if($url) {
                                $return[] = $url;
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
        $links = [];
        $modelAdmins = CMSMenu::get_cms_classes(ModelAdmin::class);
        if ($modelAdmins && count($modelAdmins)) {
            foreach ($modelAdmins as $modelAdmin) {
                $obj = singleton($modelAdmin);
                $modelAdminLink = '/'.$obj->Link();
                $modelAdminLinkArray = explode("?", $modelAdminLink);
                $modelAdminLink = $modelAdminLinkArray[0];
                //$extraVariablesLink = $modelAdminLinkArray[1];
                $links[] = $modelAdminLink;
                $modelsToAdd = $obj->getManagedModels();
                if ($modelsToAdd && count($modelsToAdd)) {
                    foreach ($modelsToAdd as $key => $model) {
                        if (is_array($model) || !is_subclass_of($model, DataObject::class)) {
                            $model = $key;
                        }
                        if (!is_subclass_of($model, DataObject::class)) {
                            continue;
                        }
                        $modelLink = $modelAdminLink.$this->sanitiseClassName($model)."/";
                        for($i = 0; $i < $this->Config()->number_of_examples; $i++) {
                            $item = $model::get()
                                ->sort(DB::get_conn()->random().' ASC')
                                ->First();
                            $exceptionMethod = '';
                            foreach($this->Config()->get('model_admin_alternatives') as $test => $method) {
                                if(! $method) {
                                    $method = 'do-not-use';
                                }
                                if(strpos($modelAdminLink, $test) !== false) {
                                    $exceptionMethod = $method;
                                }
                            }
                            if($exceptionMethod) {
                                if($item && $item->hasMethod($exceptionMethod)) {
                                    $links = array_merge($links, $item->$exceptionMethod($modelAdminLink));
                                }
                            } else {
                                //needs to stay here for exception!
                                $links[] = $modelLink;
                                $links[] = $modelLink."EditForm/field/".$this->sanitiseClassName($model)."/item/new/";
                                if ($item) {
                                    $links[] = $modelLink."EditForm/field/".$this->sanitiseClassName($model)."/item/".$item->ID."/edit/";
                                }
                            }
                        }
                    }
                }
            }
        }

        return $links;
    }

    /**
     *
     * @return array
     */
    public function ListOfAllControllerMethods()
    {
        $array = [];
        $finalArray = [];
        $classes = ClassInfo::subclassesFor(Controller::class);
        $routes = Config::inst()->get(Director::class, 'rules');
        //foreach($manifest as $class => $compareFilePath) {
        //if(stripos($compareFilePath, $absFolderPath) === 0) $matchedClasses[] = $class;
        //}
        // $manifest = ClassLoader::inst()->getManifest()->getClasses();
        foreach ($classes as $className) {

            //skip base class
            if ($className === Controller::class) {
                continue;
            }

            //match to filter
            $filterMatch = false;
            foreach($this->Config()->controller_name_space_filter as $filter) {
                if(strpos($className, $filter) !== false) {
                    $filterMatch = true;
                }
            }
            if(! $filterMatch) {
                // echo '<hr />Ditching because of classname: '.$className;
                continue;
            }

            //check for abstract ones
            $controllerReflectionClass = new ReflectionClass($className);
            if ($controllerReflectionClass->isAbstract()) {
                continue;
            }

            $hasRoute = false;
            $hasURLSegmentVar = false;
            $hasLinkMethod = false;

            //check for ones that can not be constructed
            $params = $controllerReflectionClass->getConstructor()->getParameters();
            if($controllerReflectionClass->isSubclassOf(ContentController::class)) {
                //special construct, can't handle ...
                if(count($params) > 1 ) {
                    // echo '<hr />Ditching because of param count > 1: '.$className;
                    continue;
                }
                $dataRecordClassName = substr($className, 0, -1 * strlen('Controller'));
                if(class_exists($dataRecordClassName)) {
                    $dataRecordClassObject = DataObject::get_one($dataRecordClassName, null, DB::get_conn()->random().' ASC');
                    if($dataRecordClassObject) {
                        $tmp = $dataRecordClassObject->Link();
                        $tmpArray = explode('?', $tmp);
                        $this->controllerLinks[$className] = $tmpArray[0];
                        $hasLinkMethod = true;
                        if($dataRecordClassObject->hasMethod('templateOverviewTests')) {
                            $customLinks = $dataRecordClassObject->templateOverviewTests();
                            foreach($customLinks as $customLink) {
                                $finalArray[] = [
                                    'ClassName' => $className,
                                    'Link' => $customLink,
                                ];
                            }
                        }
                    }
                }
            }
            elseif(count($params) > 0 ) {
                // echo '<hr />Ditching because of param count: '.$className;
                continue;
            }

            if(! $hasLinkMethod) {
                //find link in routes
                $route = array_search($className, $routes);
                if($route) {
                    $routeArray = explode('//', $route);
                    $route = $routeArray[0];
                    $this->controllerLinks[$className] = $route;
                    $hasRoute = true;
                }
                if(! $hasRoute) {
                    //check if there is a link of some sort
                    $urlSegment = Config::inst()->get($className, 'url_segment');
                    $hasURLSegmentVar =  $urlSegment ? true : false;
                    $this->controllerLinks[$className] = $urlSegment . '/';
                    if(! $hasURLSegmentVar) {
                        foreach ($controllerReflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                            if ($method->class == $className) {
                                if($method->name === 'Link') {
                                    $this->controllerLinks[$className] = '->Link';
                                    $hasLinkMethod = true;
                                    break;
                                }
                             }
                        }
                    }
                }
            }

            //add class and allowed actions
            if($hasRoute || $hasURLSegmentVar || $hasLinkMethod) {
                $array[$className] = (array)$allowedActionsArray = Config::inst()->get($className, "allowed_actions", Config::UNINHERITED);;
            } else {
                // echo '<hr />Ditching because lack of link : '.$className;
            }
        }

        //construct array!
        foreach ($array as $className  => $methods) {
            try {
                $classObject = Injector::inst()->get($className);
            } catch (Error $e) {
                $classObject = null;
            }
            if($classObject) {
                if($classObject->hasMethod('templateOverviewTests')) {
                    $customLinks = $classObject->templateOverviewTests();
                    foreach($customLinks as $customLink) {
                        $finalArray[] = [
                            'ClassName' => $className,
                            'Link' => $customLink,
                        ];
                    }
                }
                if($this->controllerLinks[$className] === '->Link') {
                    $link = $classObject->Link();
                }
                else {
                    $link = $this->controllerLinks[$className];
                }
                if(substr($link, -1) !== '/') {
                    $link = $link.'/';
                }
                $finalArray[] = [
                    'ClassName' => $className,
                    'Link' => $link,
                ];
                if($link) {
                    foreach($methods as $method) {
                        unset($array[$className][$method]);
                        $finalArray[] = [
                            'ClassName' => $className,
                            'Link' => $link.$method.'/',
                        ];
                    }
                }
            }
        }
        foreach($array as $className => $methods) {
            $finalArray[] = [
                'ClassName' => $className,
                'Link' => '???/'.$method.'/',
            ];
        }

        return $finalArray;
    }





    /**
      * Pushes an array of items to an array
      * @param Array $array Array to push items to (will overwrite)
      * @param Array $pushArray Array of items to push to $array.
      */
    private function addToArrayOfLinks($array, $pushArray)
    {
        $excludeList = $this->Config()->exclude_list;
        foreach ($pushArray as $pushItem) {
            if(is_array($excludeList) && count($excludeList)) {
                foreach($excludeList as $excludeItem) {
                    if(stripos($pushItem, $excludeItem) !== false) {
                        continue 2;
                    }
                }
            }
            $pushItem = '/'.Director::makeRelative($pushItem);
            $pushItem = $this->sanitiseClassName($pushItem);
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
      * @param Array $array Array which will have an item taken out of it.
      * @param - $exclusion Item to be taken out of $array
      * @return Array New array.
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
