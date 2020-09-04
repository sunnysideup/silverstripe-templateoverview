<?php

namespace Sunnysideup\TemplateOverview\Api\Providers;

use SilverStripe\Admin\CMSMenu;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\VersionedAdmin\ArchiveAdmin;
use Sunnysideup\TemplateOverview\Api\AllLinks;
use Sunnysideup\TemplateOverview\Api\AllLinksProviderBase;

class AllLinksArchiveAdmin extends AllLinksProviderBase
{
    /**
     * List of alternative links for modeladmins
     * e.g. 'admin/archive' => 'CMSEditLinkForTestPurposesNOTINUSE'
     *
     * @var array
     */
    private static $model_admin_alternatives = [];

    public function getAllLinksInner(): array
    {
        $links = [];
        $modelAdmins = CMSMenu::get_cms_classes(ArchiveAdmin::class);
        if (! empty($modelAdmins)) {
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
        }

        return $links;
    }

    protected function workOutLinksForModel($obj, $model, $modelAdminLink, $modelAdmin)
    {
        $links = [];
        $sanitizedModel = AllLinks::sanitise_class_name($model);
        $modelLink = $modelAdminLink . $sanitizedModel . '/';
        for ($i = 0; $i < $this->numberOfExamples; $i++) {
            $item = $this->getRandomArchivedItem($model);
            $exceptionMethod = '';
            foreach ($this->Config()->get('model_admin_alternatives') as $test => $method) {
                if (! $method) {
                    $method = 'do-not-use';
                }
                if (strpos($modelAdminLink, $test) !== false) {
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
                    if (is_subclass_of($model, SiteTree::class) || $model === SiteTree::class) {
                        $links[] = $modelLink . 'EditForm/field/Pages/item/' . $item->ID . '/view/';
                    } else {
                        $links[] = $modelLink . 'EditForm/field/Others/item/' . $item->ID . '/view/';
                    }
                }
            }
        }

        return $links;
    }

    protected function getRandomArchivedItem($class)
    {
        $list = singleton($class)->get();
        $baseTable = singleton($list->dataClass())->baseTable();
        $liveTable = $baseTable . '_Live';

        $list = $list
            ->setDataQueryParam('Versioned.mode', 'latest_versions');
        // Join a temporary alias BaseTable_Draft, renaming this on execution to BaseTable
        // See Versioned::augmentSQL() For reference on this alias
        $draftTable = $baseTable . '_Draft';
        $list = $list
            ->leftJoin(
                $draftTable,
                "\"{$baseTable}\".\"ID\" = \"{$draftTable}\".\"ID\""
            );

        $list = $list->leftJoin(
            $liveTable,
            "\"{$baseTable}\".\"ID\" = \"{$liveTable}\".\"ID\""
        );

        $list = $list->where("\"{$draftTable}\".\"ID\" IS NULL");
        $list = $list->sort(DB::get_conn()->random() . ' ASC');
        return $list->First();
    }
}
