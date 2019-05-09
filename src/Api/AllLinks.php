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
use SilverStripe\Core\ClassInfo;
use SilverStripe\Admin\ModelAdmin;
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
     * @var array
     * all of the admin acessible links
     */
    private static $custom_links = [];


    /**
     * this variable can help with situations where there are
     * unfixable bugs in Live and you want to run the tests
     * on Draft instead... (or vice versa)
     * @var String (Live or '')
     */
    private $stage = '';


    /**
    * @var array
    */
    private $allNonAdmins = [];

    /**
    * @var array
    */
    private $pagesOnFrontEnd = [];

    /**
    * @var array
    */
    private $otherControllerMethods = [];

    /**
    * @var array
    */
    private $customLinksNonAdmin = [];


    /**
    * @var array
    */
    private $allAdmins = [];

    /**
    * @var array
    */
    private $pagesInCMS = [];

    /**
      * List of URLs to be checked. Excludes front end pages (Cart pages etc).
      */
    private $modelAdmins = [];


    /**
     * @var array
     */
    private $customLinksAdmin = [];

    /**
     * @var array
     * Pages to check by class name. For example, for "ClassPage", will check the first instance of the cart page.
     */
    private $classNames = [];

    /**
     * returns an array of allNonAdmins => [] , allAdmins => [], otherControllerMethods => []
     * @return array
     */
    public function getAllLinks()
    {

        $this->classNames = $this->listOfAllClasses();

        foreach($this->Config()->get('custom_links') as $link) {
            $link = '/'.ltrim($link, '/').'/';
            if(substr($link,  0 , 6) === '/admin') {
                $this->customLinksAdmin[] = $link;
            } else {
                $this->customLinksNonAdmin[] = $link;
            }
        }
        $this->pagesOnFrontEnd = $this->ListOfPagesLinks();

        $this->allNonAdmins = $this->addToArrayOfLinks($this->allNonAdmins, $this->pagesOnFrontEnd);
        $this->allNonAdmins = $this->addToArrayOfLinks($this->allNonAdmins, $this->customLinksNonAdmin);

        $this->pagesInCMS = $this->ListOfPagesLinks(1);
        $this->modelAdmins = $this->ListOfAllModelAdmins();

        $this->allAdmins = $this->addToArrayOfLinks($this->allAdmins, $this->pagesInCMS);
        $this->allAdmins = $this->addToArrayOfLinks($this->allAdmins, $this->modelAdmins);
        $this->allAdmins = $this->addToArrayOfLinks($this->allAdmins, $this->customLinksAdmin);

        $this->otherControllerMethods = $this->ListOfAllControllerMethods();

        return [
            'allNonAdmins' => $this->allNonAdmins,
            'allAdmins' => $this->allAdmins,
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
        foreach ($this->classNames as $class) {
            $excludedClasses = $this->arrayExcept($this->classNames, $class);
            if ($pageInCMS) {
                $stage = "";
            } else {
                $stage = $this->stage;
            }
            $page = Versioned::get_by_stage($class, Versioned::DRAFT)
                ->exclude(array("ClassName" => $excludedClasses))
                ->sort(DB::get_conn()->random().' ASC')
                ->limit(1);
            $page = $page->first();
            if ($page) {
                if ($pageInCMS) {
                    $url = $page->CMSEditLink();
                } else {
                    $url = $page->Link();
                }
                $return[] = $url;
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
        $models = [];
        $modelAdmins = CMSMenu::get_cms_classes(ModelAdmin::class);
        if ($modelAdmins && count($modelAdmins)) {
            foreach ($modelAdmins as $modelAdmin) {
                $obj = singleton($modelAdmin);
                $modelAdminLink = '/'.$obj->Link();
                $modelAdminLinkArray = explode("?", $modelAdminLink);
                $modelAdminLink = $modelAdminLinkArray[0];
                //$extraVariablesLink = $modelAdminLinkArray[1];
                $models[] = $modelAdminLink;
                $modelsToAdd = $obj->getManagedModels();

                if ($modelsToAdd && count($modelsToAdd)) {
                    foreach ($modelsToAdd as $key => $model) {
                        if (is_array($model) || !is_subclass_of($model, DataObject::class)) {
                            $model = $key;
                        }
                        if (!is_subclass_of($model, DataObject::class)) {
                            continue;
                        }
                        $modelAdminLink;
                        $modelLink = $modelAdminLink.$this->sanitiseClassName($model)."/";
                        $models[] = $modelLink;
                        $models[] = $modelLink."EditForm/field/".$this->sanitiseClassName($model)."/item/new/";
                        $item = $model::get()
                            ->sort(DB::get_conn()->random().' ASC')
                            ->First();
                        if ($item) {
                            $models[] = $modelLink."EditForm/field/".$this->sanitiseClassName($model)."/item/".$item->ID."/edit";
                        }
                    }
                }
            }
        }

        return $models;
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
        //foreach($manifest as $class => $compareFilePath) {
        //if(stripos($compareFilePath, $absFolderPath) === 0) $matchedClasses[] = $class;
        //}
        $manifest = ClassLoader::inst()->getManifest()->getClasses();
        $baseFolder = Director::baseFolder();
        $cmsBaseFolder = Director::baseFolder()."/cms/";
        $frameworkBaseFolder = Director::baseFolder()."/framework/";
        foreach ($classes as $className) {
            $lowerClassName = strtolower($className);
            $location = $manifest[$lowerClassName];
            if (strpos($location, $cmsBaseFolder) === 0 || strpos($location, $frameworkBaseFolder) === 0) {
                continue;
            }
            if ($className != Controller::class) {
                $controllerReflectionClass = new ReflectionClass($className);
                if (!$controllerReflectionClass->isAbstract()) {
                    if (
                        $className == "HideMailto" ||
                        $className == "HideMailtoController" ||
                        $className == "Mailto" ||
                        $className instanceof SapphireTest ||
                        $className instanceof BuildTask ||
                        $className instanceof TaskRunner
                    ) {
                        continue;
                    }
                    $methods = $this->getPublicMethodsNotInherited($controllerReflectionClass, $className);
                    foreach ($methods as $methodArray) {
                        $array[$className."_".$methodArray["Method"]] = $methodArray;
                    }
                }
            }
        }
        $finalArray = [];
        $doubleLinks = [];
        foreach ($array as $index  => $classNameMethodArray) {
            if(1 === 2) {
                try {
                    $classObject = @Injector::inst()->get($classNameMethodArray["ClassName"]);
                    if($classObject) {
                        if(Config::inst()->get($classNameMethodArray["ClassName"], 'url_segment')) {
                            if ($classNameMethodArray["Method"] == "templateoverviewtests") {
                                $this->customLinks = array_merge($classObject->templateoverviewtests(), $this->customLinks);
                            } else {
                                $link = $classObject->Link($classNameMethodArray["Method"]);
                                if ($link == $classNameMethodArray["ClassName"]."/") {
                                    $link = $classNameMethodArray["ClassName"]."/".$classNameMethodArray["Method"]."/";
                                }
                                $classNameMethodArray["Link"] = $link;
                                if ($classNameMethodArray["Link"][0] != "/") {
                                    $classNameMethodArray["Link"] = Director::baseURL().$classNameMethodArray["Link"];
                                }
                                if (!isset($doubleLinks[$link])) {
                                    $finalArray[] = $classNameMethodArray;
                                }
                                $doubleLinks[$link] = true;
                            }
                        }
                    }
                } catch (Exception $e) {
                    // echo 'Caught exception: ',  $e->getMessage(), "\n";
                }
            }
        }
        return $finalArray;
    }



    private function getPublicMethodsNotInherited($classReflection, $className)
    {
        $classMethods = $classReflection->getMethods();
        $classMethodNames = [];
        foreach ($classMethods as $index => $method) {
            if ($method->getDeclaringClass()->getName() !== $className) {
                unset($classMethods[$index]);
            } else {
                $allowedActionsArray = Config::inst()->get($className, "allowed_actions", Config::UNINHERITED);
                if (!is_array($allowedActionsArray)) {
                    $allowedActionsArray = [];
                } else {
                    //return $allowedActionsArray;
                }
                $methodName = $method->getName();
                /* Get a reflection object for the class method */
                $reflect = new ReflectionMethod($className, $methodName);
                /* For private, use isPrivate().  For protected, use isProtected() */
                /* See the Reflection API documentation for more definitions */
                if ($reflect->isPublic()) {
                    if ($methodName == strtolower($methodName)) {
                        if (strpos($methodName, "_") == null) {
                            if (!in_array($methodName, array("index", "run", "init"))) {
                                /* The method is one we're looking for, push it onto the return array */
                                $error = "";
                                if (!in_array($methodName, $allowedActionsArray) && !isset($allowedActionsArray[$methodName])) {
                                    $error = "Can not find ".$className."::".$methodName." in allowed_actions";
                                } else {
                                    unset($allowedActionsArray[$className]);
                                }
                                $classMethodNames[$methodName] = array(
                                    "ClassName" => $className,
                                    "Method" => $methodName,
                                    "Error" => $error
                                );
                            }
                        }
                    }
                }
                if (count($allowedActionsArray)) {
                    $classSpecificAllowedActionsArray = Config::inst()->get($className, "allowed_actions", Config::UNINHERITED);
                    if (is_array($classSpecificAllowedActionsArray) && count($classSpecificAllowedActionsArray)) {
                        foreach ($allowedActionsArray as $methodName => $methodNameWithoutKey) {
                            if (is_numeric($methodName)) {
                                $methodName = $methodNameWithoutKey;
                            }
                            if (isset($classSpecificAllowedActionsArray[$methodName])) {
                                $classMethodNames[$methodName] = array(
                                    "ClassName" => $className,
                                    "Method" => $methodName,
                                    "Error" => "May not follow the right method name formatting (all lower case)"
                                );
                            }
                        }
                    }
                }
            }
        }
        return $classMethodNames;
    }



    /**
      * Pushes an array of items to an array
      * @param Array $array Array to push items to (will overwrite)
      * @param Array $pushArray Array of items to push to $array.
      */
    private function addToArrayOfLinks($array, $pushArray)
    {
        foreach ($pushArray as $pushItem) {
            $pushItem = '/'.Director::makeRelative($pushItem);
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
    private function listOfAllClasses()
    {
        $pages = [];
        $siteTreeDetails = Injector::inst()->get(SiteTreeDetails::class);
        $list = $siteTreeDetails->ListOfAllClasses();
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


}
