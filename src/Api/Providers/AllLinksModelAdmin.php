<?php

namespace Sunnysideup\TemplateOverview\Api\Providers;

use SilverStripe\Admin\CMSMenu;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\VersionedAdmin\ArchiveAdmin;
use Sunnysideup\TemplateOverview\Api\AllLinks;
use Sunnysideup\TemplateOverview\Api\AllLinksProviderBase;

class AllLinksModelAdmin extends AllLinksProviderBase
{
    /**
     * List of alternative links for modeladmins
     * e.g. 'admin/archive' => 'CMSEditLinkForTestPurposesNOTINUSE'.
     *
     * @var array
     */
    private static $model_admin_alternatives = [];

    /**
     * e.g. Search => Replace.
     *
     * @var array
     */
    private static $replacers = [
        '/admin/queuedjobs/Symbiote-QueuedJobs-DataObjects-QueuedJobDescriptor/EditForm/field/Symbiote-QueuedJobs-DataObjects-QueuedJobDescriptor' => '/admin/queuedjobs/Symbiote-QueuedJobs-DataObjects-QueuedJobDescriptor/EditForm/field/QueuedJobDescriptor/',
    ];

    public function getAllLinksInner(): array
    {
        $links = [];
        $modelAdmins = CMSMenu::get_cms_classes(ModelAdmin::class);
        unset($modelAdmins[array_search(ArchiveAdmin::class, $modelAdmins, true)]);
        foreach ($modelAdmins as $modelAdmin) {
            $modelAdminSingleton = Injector::inst()->get($modelAdmin);
            $modelAdminLink = '/' . $modelAdminSingleton->Link();
            $modelAdminLinkArray = explode('?', $modelAdminLink);
            $modelAdminLink = $modelAdminLinkArray[0];
            //$extraVariablesLink = $modelAdminLinkArray[1];
            $links[] = $modelAdminLink;
            $modelsToAdd = $modelAdminSingleton->getManagedModels();
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
                        $this->workOutLinksForModel($modelAdminSingleton, $model, $modelAdminLink, $modelAdmin)
                    );
                }
            }
        }

        return $this->runReplacements($links);
    }

    protected function workOutLinksForModel($modelAdminSingleton, string $model, string $modelAdminLink, string $modelAdmin)
    {
        $links = [];
        $sanitizedModel = AllLinks::sanitise_class_name($model);
        $modelLink = $modelAdminLink . $sanitizedModel . '/';
        for ($i = 0; $i < $this->numberOfExamples; ++$i) {
            $item = $model::get()
                ->sort(DB::get_conn()->random() . ' ASC')
                ->First()
            ;
            $singleton = $item ?: Injector::inst()->get($model);
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
                if ($singleton->canCreate(null)) {
                    $links[] = $modelLink . 'EditForm/field/' . $sanitizedModel . '/item/new/';
                }
                if ($item) {
                    if ($item->canEdit()) {
                        $links[] = $modelLink . 'EditForm/field/' . $sanitizedModel . '/item/' . $item->ID . '/edit/';
                    }
                }
            }
        }

        return $links;
    }

    protected function runReplacements(array $links): array
    {
        foreach ($this->Config()->get('replacers') as $search => $replace) {
            foreach ($links as $key => $link) {
                $links[$key] = str_replace($search, $replace, $link);
            }
        }

        return $links;
    }
}
