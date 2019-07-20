<?php

namespace Sunnysideup\TemplateOverview\Api;

use ReflectionClass;
use ReflectionMethod;





use SilverStripe\Admin\LeftAndMain;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

class AllLinksControllerInfo
{
    /**
     * @var array
     */
    protected static $reflectionClasses = [];

    /**
     * @var array
     */
    protected static $classObjects = [];

    /**
     * @var array
     */
    protected static $dataRecordClassNames = [];

    /**
     * @var array
     */
    protected static $dataRecordClassObjects = [];

    /**
     * @var array|null
     */
    protected static $routes = null;

    protected static $nameSpaces = [];

    public static function set_valid_name_spaces($nameSpaces)
    {
        self::$nameSpaces = $nameSpaces;
    }

    /**
     * can it be used?
     * @param  string $className
     * @return bool
     */
    public static function is_valid_controller($className): bool
    {
        return self::controller_reflection_class($className) ? true : false;
    }

    /**
     * @param  string $className
     * @return ReflectionClass|null
     */
    public static function controller_reflection_class($className)
    {
        if (! isset(self::$reflectionClasses[$className])) {
            self::$reflectionClasses[$className] = null;
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
            $filterMatch = count(self::$nameSpaces) ? false : true;
            foreach (self::$nameSpaces as $filter) {
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

            self::$reflectionClasses[$className] = $controllerReflectionClass;

            return self::$reflectionClasses[$className];
        }
        return self::$reflectionClasses[$className];
    }

    /**
     * @param  string $className
     * @return string
     */
    public static function find_singleton($className)
    {
        if (self::controller_reflection_class($className)) {
            self::$classObjects[$className] = null;
            if (! isset(self::$classObjects[$className])) {
                try {
                    self::$classObjects[$className] = Injector::inst()->get($className);
                } catch (\Error $e) {
                    self::$classObjects[$className] = null;
                }
            }
        }

        return self::$classObjects[$className];
    }

    /**
     * @param  string $className
     * @return DataObject|null
     */
    public static function find_data_record($className)
    {
        if (! isset(self::$dataRecordClassObjects[$className])) {
            self::$dataRecordClassObjects[$className] = null;
            $dataRecordClassName = substr($className, 0, -1 * strlen('Controller'));
            if (class_exists($dataRecordClassName)) {
                self::$dataRecordClassNames[$className] = $dataRecordClassName;
                self::$dataRecordClassObjects[$className] = DataObject::get_one(
                    $dataRecordClassName,
                    null,
                    null,
                    DB::get_conn()->random() . ' ASC'
                );
            }
        }

        return self::$dataRecordClassObjects[$className];
    }

    /**
     * @param  string $className
     * @return array
     */
    public static function find_custom_links($className): array
    {
        $array1 = [];
        $array2 = [];
        $classObject = self::find_singleton($className);
        if ($classObject) {
            if ($classObject->hasMethod('templateOverviewTests')) {
                $array1 = $classObject->templateOverviewTests();
            }
        }
        $object = self::find_data_record($className);
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
    public static function find_allowed_actions($className): array
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
    public static function find_link($className): string
    {
        $link = self::find_controller_link($className);
        if (! $link) {
            $link = self::find_route_link($className);
            if (! $link) {
                $link = self::find_segment_link($className);
                if (! $link) {
                    $link = self::find_method_link($className);
                }
            }
        }

        return $link;
    }

    /**
     * @param  string $className
     * @return string
     */
    protected static function find_controller_link($className): string
    {
        $object = self::find_data_record($className);
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
    protected static function find_route_link($className): string
    {
        if (self::$routes === null) {
            self::$routes = Config::inst()->get(Director::class, 'rules');
        }
        $route = array_search($className, self::$routes, true);
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
    protected static function find_segment_link($className): string
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
    protected static function find_method_link($className): string
    {
        $controllerReflectionClass = self::controller_reflection_class($className);
        if ($controllerReflectionClass) {
            foreach ($controllerReflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->class === $className) {
                    if ($method->name === 'Link') {
                        $classObject = self::find_singleton($className);
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
