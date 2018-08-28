<?php



class TemplateoverviewPageAPI extends ViewableData/*
### @@@@ START UPGRADE REQUIRED @@@@ ###
FIND:  extends Object
NOTE: This used to extend Object, but object does not exist anymore.  
### @@@@ END UPGRADE REQUIRED @@@@ ###
*/
{
    private static $list_of_all_classes = array();

    private static $classes_to_exclude = array("SiteTree", "RedirectorPage", "VirtualPage");

    /**
     *
     * @var Bool
     */
    protected $showAll = false;

    /**
     *
     * @var int
     */
    protected $counter = 0;


    public function ListOfAllClasses($checkCurrentClass = true)
    {
        if (!self::$list_of_all_classes) {
            $ArrayOfAllClasses =  array();
            //$classes = ClassInfo::subclassesFor("SiteTree");
            $classes = SiteTree::page_type_classes();
            $classesToRemove = array();

            foreach ($classes as $className) {
                if (!in_array($className, $this->config()->get("classes_to_exclude"))) {
                    if ($this->showAll) {
                        $objects = $className::get()
                            ->filter(array("ClassName" => $className))
                            ->sort("RAND() ASC")
                            ->limit(25);
                        $count = 0;
                        if ($objects->count()) {
                            foreach ($objects as $obj) {
                                if (!$count) {
                                    if ($ancestorToHide = $obj->stat('hide_ancestor')) {
                                        $classesToRemove[] = $ancestorToHide;
                                    }
                                }
                                $object = $this->createPageObject($obj, $count++);
                                $ArrayOfAllClasses[$object->indexNumber] = clone $object;
                            }
                        }
                    } else {
                        $obj = null;
                        $obj = $className::get()
                            ->filter(array("ClassName" => $className))
                            ->sort("RAND() ASC")
                            ->limit(1)
                            ->first();
                        if ($obj) {
                            $count = SiteTree::get()->filter(array("ClassName" => $obj->ClassName))->count();
                        } else {
                            $obj = $className::create();
                            $count = 0;
                        }
                        if ($ancestorToHide = $obj->stat('hide_ancestor')) {
                            $classesToRemove[] = $ancestorToHide;
                        }
                        $object = $this->createPageObject($obj, $count);
                        $ArrayOfAllClasses[$object->indexNumber] = clone $object;
                    }
                }
            }

            //remove the hidden ancestors...
            if ($classesToRemove && count($classesToRemove)) {
                $classesToRemove = array_unique($classesToRemove);
                // unset from $classes
                foreach ($ArrayOfAllClasses as $tempKey => $tempClass) {
                    if (in_array($tempClass->ClassName, $classesToRemove)) {
                        unset($ArrayOfAllClasses[$tempKey]);
                    }
                }
            }
            ksort($ArrayOfAllClasses);
            self::$list_of_all_classes =  new ArrayList();
            $currentClassname = '';
            if ($checkCurrentClass) {
                if ($c = Controller::curr()) {
                    if ($d = $c->dataRecord) {
                        $currentClassname = $d->ClassName;
                    }
                }
            }
            if (count($ArrayOfAllClasses)) {
                foreach ($ArrayOfAllClasses as $item) {
                    if ($item->ClassName == $currentClassname) {
                        $item->LinkingMode = "current";
                    } else {
                        $item->LinkingMode = "link";
                    }
                    self::$list_of_all_classes->push($item);
                }
            }
        }
        return self::$list_of_all_classes;
    }

    public function ShowAll()
    {
        $this->showAll = true;
        return array();
    }


    /**
     * @param SiteTree $obj
     * @param Int $count
     * @param String $ClassName
     * @return ArrayData
     */
    private function createPageObject($obj, $count)
    {
        $this->counter++;
        $listArray = array();
        $indexNumber = (10000 * $count) + $this->counter;
        $listArray["indexNumber"] = $indexNumber;
        $listArray["ClassName"] = $obj->ClassName;
        $listArray["Count"] = $count;
        $listArray["ID"] = $obj->ID;
        $listArray["URLSegment"] = $obj->URLSegment;
        $listArray["TypoURLSegment"] = $this->Link();
        $listArray["Title"] = $obj->MenuTitle;
        $listArray["PreviewLink"] = $obj->PreviewLink();
        $listArray["CMSEditLink"] = $obj->CMSEditLink();
        $staticIcon = $obj->stat("icon", true);
        if (is_array($staticIcon)) {
            $iconArray = $obj->stat("icon");
            $icon = $iconArray[0];
        } else {
            $icon = $obj->stat("icon");
        }
        $iconFile = Director::baseFolder().'/'.$icon;
        if (!file_exists($iconFile)) {
            $icon = $icon."-file.gif";
        }
        $listArray["Icon"] = $icon;
        return new ArrayData($listArray);
    }

    //not used!
    public function NoSubClasses($obj)
    {
        $array = ClassInfo::subclassesFor($obj->ClassName);
        if (count($array)) {
            foreach ($array as $class) {
                if ($class::get()->byID($obj->ID)) {
                    return false;
                }
            }
        }
        return true;
    }

    public function Link($action = null)
    {
        $v = '/templates';
        if ($action) {
            $v .= $action . '/';
        }

        return $v;
    }
}
