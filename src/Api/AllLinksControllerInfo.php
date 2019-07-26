<?php

namespace Sunnysideup\TemplateOverview\Api;

use ReflectionClass;
use ReflectionMethod;




use SilverStripe\Admin\LeftAndMain;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

class AllLinksControllerInfo
{
    use Configurable;
    use Injectable;

    /**
     * @var array
     */
    protected $linksAndActions = [];

    /**
     * @var array
     */
    protected $reflectionClasses = [];

    /**
     * @var array
     */
    protected $classObjects = [];

    /**
     * @var array
     */
    protected $dataRecordClassNames = [];

    /**
     * @var array
     */
    protected $dataRecordClassObjects = [];

    /**
     * @var array|null
     */
    protected $routes = null;

    /**
     * @var array
     */
    protected $nameSpaces = [];

    /**
     * @param  array $nameSpaces
     *
     * @return AllLinksControllerInfo
     */
    public function setValidNameSpaces($nameSpaces): self
    {
        $this->nameSpaces = $nameSpaces;

        return $this;
    }

    /**
     * returns Array with Links and Actions
     * @return array
     */
    public function getLinksAndActions(): array
    {
        if (count($this->linksAndActions) === 0) {
            $this->linksAndActions['Links'] = [];
            $this->linksAndActions['Actions'] = [];
            $this->linksAndActions['CustomLinks'] = [];
            $classes = ClassInfo::subclassesFor(Controller::class);
            foreach ($classes as $className) {
                $isValid = $this->isValidController($className);
                if (! $isValid) {
                    continue;
                }
                $this->linksAndActions['Links'][$className] = '';

                //main links

                //custom links
                $customLinks = $this->findCustomLinks($className);
                foreach ($customLinks as $customLink) {
                    $this->linksAndActions['CustomLinks'][$customLink] = $className;
                }
                $link = $this->findLink($className);
                if ($link) {
                    $this->linksAndActions['Links'][$className] = $link;
                }
                $this->linksAndActions['Actions'][$className] = $this->findAllowedActions($className);
            }
        }

        return $this->linksAndActions;
    }

    /**
     * can it be used?
     * @param  string $className
     * @return bool
     */
    protected function isValidController($className): bool
    {
        return $this->controllerReflectionClass($className) ? true : false;
    }

    /**
     * @param  string $className
     * @return ReflectionClass|null
     */
    protected function controllerReflectionClass($className)
    {
        if (! isset($this->reflectionClasses[$className])) {
            $this->reflectionClasses[$className] = null;
            //skip base class
            if ($className === Controller::class) {
                return null;
            }

            //check for abstract ones
            $controllerReflectionClass = new ReflectionClass($className);
            if ($controllerReflectionClass->isAbstract()) {
                // echo '<hr />Ditching because of abstract: '.$className;
                return null;
            }

            //match to filter
            $filterMatch = count($this->nameSpaces) ? false : true;
            foreach ($this->nameSpaces as $filter) {
                if (strpos($className, $filter) !== false) {
                    $filterMatch = true;
                }
            }
            if ($filterMatch === false) {
                return null;
            }

            //check for ones that can not be constructed
            if ($controllerReflectionClass->isSubclassOf(LeftAndMain::class)) {
                return null;
            }
            $params = $controllerReflectionClass->getConstructor()->getParameters();
            if ($controllerReflectionClass->isSubclassOf(ContentController::class)) {
                //do nothing
            } elseif (count($params) > 0) {
                return null;
            }

            $this->reflectionClasses[$className] = $controllerReflectionClass;

            return $this->reflectionClasses[$className];
        }
        return $this->reflectionClasses[$className];
    }

    /**
     * @param  string $className
     * @return string
     */
    protected function findSingleton($className)
    {
        if ($this->controllerReflectionClass($className)) {
            $this->classObjects[$className] = null;
            if (! isset($this->classObjects[$className])) {
                try {
                    $this->classObjects[$className] = Injector::inst()->get($className);
                } catch (\Error $e) {
                    $this->classObjects[$className] = null;
                }
            }
        }

        return $this->classObjects[$className];
    }

    /**
     * @param  string $className
     * @return DataObject|null
     */
    protected function findDataRecord($className)
    {
        if (! isset($this->dataRecordClassObjects[$className])) {
            $this->dataRecordClassObjects[$className] = null;
            $dataRecordClassName = substr($className, 0, -1 * strlen('Controller'));
            if (class_exists($dataRecordClassName)) {
                $this->dataRecordClassNames[$className] = $dataRecordClassName;
                $this->dataRecordClassObjects[$className] = DataObject::get_one(
                    $dataRecordClassName,
                    null,
                    null,
                    DB::get_conn()->random() . ' ASC'
                );
            }
        }

        return $this->dataRecordClassObjects[$className];
    }

    /**
     * @param  string $className
     * @return array
     */
    protected function findCustomLinks($className): array
    {
        $array1 = [];
        $array2 = [];
        $classObject = $this->findSingleton($className);
        if ($classObject) {
            if ($classObject->hasMethod('templateOverviewTests')) {
                $array1 = $classObject->templateOverviewTests();
            }
        }
        $object = $this->findDataRecord($className);
        if ($object) {
            if ($object->hasMethod('templateOverviewTests')) {
                $array2 = $object->templateOverviewTests();
            }
        }
        return $array1 + $array2;
    }

    /**
     * @param  string $className
     * @return array
     */
    protected function findAllowedActions($className): array
    {
        $allowedActions = Config::inst()->get($className, 'allowed_actions', Config::UNINHERITED);
        if (is_array($allowedActions)) {
            return $allowedActions;
        }
        return [];
    }

    /**
     * @param  string $className
     * @return string
     */
    protected function findLink($className): string
    {
        $link = $this->findControllerLink($className);
        if (! $link) {
            $link = $this->findRouteLink($className);
            if (! $link) {
                $link = $this->findSegmentLink($className);
                if (! $link) {
                    $link = $this->findMethodLink($className);
                }
            }
        }

        return $link;
    }

    /**
     * @param  string $className
     * @return string
     */
    protected function findControllerLink($className): string
    {
        $object = $this->findDataRecord($className);
        if ($object) {
            $tmp = $object->Link();
            $tmpArray = explode('?', $tmp);
            return $tmpArray[0];
        }

        return '';
    }

    /**
     * @param  string $className
     * @return string
     */
    protected function findRouteLink($className): string
    {
        if ($this->routes === null) {
            $this->routes = Config::inst()->get(Director::class, 'rules');
        }
        $route = array_search($className, $this->routes, true);
        if ($route) {
            $routeArray = explode('//', $route);
            return $routeArray[0];
        }

        return '';
    }

    /**
     * @param  string $className
     * @return string
     */
    protected function findSegmentLink($className): string
    {
        //check if there is a link of some sort
        $urlSegment = Config::inst()->get($className, 'url_segment');
        if ($urlSegment) {
            $urlSegment .= '/';
        } else {
            $urlSegment = '';
        }
        return $urlSegment;
    }

    /**
     * @param  string $className
     * @return string
     */
    protected function findMethodLink($className): string
    {
        $controllerReflectionClass = $this->controllerReflectionClass($className);
        if ($controllerReflectionClass) {
            foreach ($controllerReflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->class === $className) {
                    if ($method->name === 'Link') {
                        $classObject = $this->findSingleton($className);
                        if ($classObject) {
                            return $classObject->Link();
                        }
                    }
                }
            }
        }

        return '';
    }
}