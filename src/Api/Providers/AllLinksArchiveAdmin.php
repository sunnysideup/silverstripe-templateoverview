<?php

namespace Sunnysideup\TemplateOverview\Api\Providers;

use SilverStripe\Admin\CMSMenu;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\VersionedAdmin\ArchiveAdmin;
use Sunnysideup\TemplateOverview\Api\AllLinks;
use Sunnysideup\TemplateOverview\Api\AllLinksProviderBase;

class AllLinksArchiveAdmin extends AllLinksProviderBase
{
    /**
     * List of alternative links for modeladmins
     * e.g. 'admin/archive' => 'CMSEditLinkForTestPurposesNOTINUSE'.
     *
     * @var array
     */
    private static $model_admin_alternatives = [];

    public function getAllLinksInner(): array
    {
        $links = [];
        $modelAdmins = CMSMenu::get_cms_classes(ArchiveAdmin::class);
        foreach ($modelAdmins as $modelAdmin) {
            $obj = Injector::inst()->get($modelAdmin);
            $modelAdminLink = '/' . $obj->Link();
            $modelAdminLinkArray = explode('?', $modelAdminLink);
            $modelAdminLink = $modelAdminLinkArray[0];
            //$extraVariablesLink = $modelAdminLinkArray[1];
            $links[] = $modelAdminLink;
            $modelsToAdd = $obj->getManagedModels();
            if ($modelsToAdd && count($modelsToAdd)) {
                foreach ($modelsToAdd as $key => $model) {
                    if (is_array($model) || ! is_subclass_of($model, DataObject::class)) {
                        $model = $key;
                    }

                    if (! is_subclass_of($model, DataObject::class)) {
                        continue;
                    }

                    $links = array_merge(
                        $links,
                        $this->workOutLinksForModel($obj, $model, $modelAdminLink, $modelAdmin)
                    );
                }
            }
        }

        return $links;
    }

    protected function workOutLinksForModel($obj, $model, $modelAdminLink, $modelAdmin)
    {
        $links = [];
        $sanitizedModel = AllLinks::sanitise_class_name($model);
        $modelLink = $modelAdminLink . $sanitizedModel . '/';
        $items = $this->getRandomArchivedItem($model);
        if ($items) {
            foreach ($items as $item) {
                $exceptionMethod = '';
                foreach ($this->Config()->get('model_admin_alternatives') as $test => $method) {
                    if (! $method) {
                        $method = 'do-not-use';
                    }

                    if (false !== strpos($modelAdminLink, $test)) {
                        $exceptionMethod = $method;
                    }
                }

                if ($exceptionMethod) {
                    if ($item && $item->hasMethod($exceptionMethod)) {
                        $links = array_merge($links, $item->{$exceptionMethod}($modelAdminLink));
                    }
                } else {
                    //needs to stay here for exception!
                    $links[] = $modelLink;
                    if ($item) {
                        $test1 = is_subclass_of($model, SiteTree::class);
                        $test2 = SiteTree::class === (string) $model;
                        if ($test1 || $test2) {
                            $links[] = $modelLink . 'EditForm/field/Pages/item/' . $item->ID . '/view/';
                        } else {
                            $links[] = $modelLink . 'EditForm/field/Others/item/' . $item->ID . '/view/';
                        }
                    }
                }
            }
        }

        return $links;
    }

    protected function getRandomArchivedItem($class)
    {
        $list = \Singleton($class)->get();
        $baseTable = \Singleton($list->dataClass())->baseTable();
        $liveTable = $baseTable . '_Live';
        $draftTable = $baseTable . '_Draft';
        if ($this->tableExists($liveTable) && $this->tableExists($draftTable)) {
            $list = $list
                ->setDataQueryParam('Versioned.mode', 'latest_versions')
            ;
            // Join a temporary alias BaseTable_Draft, renaming this on execution to BaseTable
            // See Versioned::augmentSQL() For reference on this alias
            $list = $list
                ->leftJoin(
                    $draftTable,
                    "\"{$baseTable}\".\"ID\" = \"{$draftTable}\".\"ID\""
                )
            ;

            $list = $list->leftJoin(
                $liveTable,
                "\"{$baseTable}\".\"ID\" = \"{$liveTable}\".\"ID\""
            );

            $list = $list->where("\"{$draftTable}\".\"ID\" IS NULL");
            $list = $list->shuffle();

            return $list->limit($this->getNumberOfExamples());
        }
        return null;
    }
}
