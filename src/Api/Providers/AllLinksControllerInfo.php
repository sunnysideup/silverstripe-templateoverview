<?php

namespace Sunnysideup\TemplateOverview\Api\Providers;

use ReflectionClass;
use ReflectionMethod;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use Sunnysideup\TemplateOverview\Api\AllLinksProviderBase;

class AllLinksControllerInfo extends AllLinksProviderBase
{
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
     * @var array
     */
    protected $routes = [];

    /**
     * @var array
     */
    protected $nameSpaces = [];

    /**
     * @param array $nameSpaces
     */
    public function setValidNameSpaces($nameSpaces): self
    {
        $this->nameSpaces = $nameSpaces;

        return $this;
    }

    public function getAllLinksInner(): array
    {
        $finalFinalArray = [];

        $linksAndActions = $this->getLinksAndActions();
        $allowedActions = $linksAndActions['Actions'];
        $controllerLinks = $linksAndActions['Links'];
        $finalArray = $linksAndActions['CustomLinks'];

        // die('---');
        //construct array!
        foreach ($allowedActions as $className => $methods) {
            $link = $controllerLinks[$className];
            if ($link) {
                $finalArray[$link] = $className;
            } else {
                $link = '???';
            }
            if ('/' !== substr($link, -1)) {
                $link .= '/';
            }
            if ('/' !== substr($link, 0, 1)) {
                $link = '/' . $link;
            }
            if (is_array($methods)) {
                foreach ($methods as $method) {
                    unset($allowedActions[$className][$method]);
                    $finalArray[$link . $method . '/'] = $className;
                }
            }
        }

        foreach ($finalArray as $link => $className) {
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
     * returns Array with Links and Actions.
     */
    public function getLinksAndActions(): array
    {
        if (0 === count($this->linksAndActions)) {
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
                if ('' !== $link) {
                    $this->linksAndActions['Links'][$className] = $link;
                }
                $array = array_merge(
                    $this->findAllowedActions($className),
                    $this->findURLHandlers($className)
                );
                $this->linksAndActions['Actions'][$className] = $array;
            }
        }

        return $this->linksAndActions;
    }

    /**
     * can it be used?
     *
     * @param string $className
     */
    protected function isValidController($className): bool
    {
        return (bool) $this->controllerReflectionClass($className);
    }

    /**
     * @param string $className
     *
     * @return null|ReflectionClass
     */
    protected function controllerReflectionClass($className)
    {
        if (! isset($this->reflectionClasses[$className])) {
            $this->reflectionClasses[$className] = null;
            //skip base class
            if (Controller::class === $className) {
                return null;
            }

            //check for abstract ones
            $controllerReflectionClass = new ReflectionClass($className);
            if ($controllerReflectionClass->isAbstract()) {
                // echo '<hr />Ditching because of abstract: '.$className;
                return null;
            }

            //match to filter
            $filterMatch = ! (bool) count($this->nameSpaces);
            foreach ($this->nameSpaces as $filter) {
                if (false !== strpos($className, $filter)) {
                    $filterMatch = true;
                }
            }
            if (! $filterMatch) {
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
     * @return null|DataObject
     */
    protected function findSingleton(string $className)
    {
        if (null !== $this->controllerReflectionClass($className)) {
            $this->classObjects[$className] = null;
            if (! isset($this->classObjects[$className])) {
                try {
                    $this->classObjects[$className] = Injector::inst()->get($className);
                } catch (\Error $error) {
                    $this->classObjects[$className] = null;
                }
            }
        }

        return $this->classObjects[$className];
    }

    /**
     * @param string $className
     *
     * @return null|DataObject
     */
    protected function findDataRecord($className)
    {
        if (! isset($this->dataRecordClassObjects[$className])) {
            $this->dataRecordClassObjects[$className] = null;
            $dataRecordClassName = substr($className, 0, -1 * strlen('Controller'));
            if (class_exists($dataRecordClassName) && is_subclass_of($dataRecordClassName, DataObject::class)) {
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
     * @param string $className
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
        if (null !== $object) {
            if ($object->hasMethod('templateOverviewTests')) {
                $array2 = $object->templateOverviewTests();
            }
        }
        print_r($array1);
        print_r($array2);
        return $array1 + $array2;
    }

    /**
     * @param string $className
     */
    protected function findAllowedActions($className): array
    {
        $allowedActions = Config::inst()->get($className, 'allowed_actions', Config::UNINHERITED);

        return $this->getBestArray($allowedActions);
    }

    /**
     * @param string $className
     */
    protected function findURLHandlers($className): array
    {
        $urlHandlers = Config::inst()->get($className, 'url_handlers', Config::UNINHERITED);

        return $this->getBestArray($urlHandlers);
    }

    protected function getBestArray($array): array
    {
        if (is_array($array)) {
            if ($this->isAssociativeArray($array)) {
                $array = array_keys($array);
            }
        } else {
            $array = [];
        }

        return $array;
    }

    /**
     * @param string $className
     */
    protected function findLink($className): string
    {
        $link = $this->findControllerLink($className);
        if ('' === $link) {
            $link = $this->findRouteLink($className);
            if ('' === $link) {
                $link = $this->findSegmentLink($className);
                if ('' === $link) {
                    $link = $this->findMethodLink($className);
                }
            }
        }
        $link = '/' . $link . '/';

        return str_replace('//', '/', $link);
    }

    /**
     * @param string $className
     */
    protected function findControllerLink($className): string
    {
        $object = $this->findDataRecord($className);
        if ($object && $object->hasMethod('Link')) {
            $tmp = $object->Link();
            $tmpArray = explode('?', $tmp);

            return $tmpArray[0];
        }

        return '';
    }

    /**
     * @param string $className
     */
    protected function findRouteLink($className): string
    {
        if ([] === $this->routes) {
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
     * @param string $className
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
     * @param string $className
     */
    protected function findMethodLink($className): string
    {
        $controllerReflectionClass = $this->controllerReflectionClass($className);
        if (null !== $controllerReflectionClass) {
            foreach ($controllerReflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->class === $className) {
                    if ('Link' === $method->name) {
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

    private function isAssociativeArray(array $arr): bool
    {
        if ([] === $arr) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
