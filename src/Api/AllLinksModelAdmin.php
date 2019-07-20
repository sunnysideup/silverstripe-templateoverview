<?php

namespace Sunnysideup\TemplateOverview\Api;

use SilverStripe\Admin\CMSMenu;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Core\Config\Configurable;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

class AllLinksModelAdmin
{
    use Configurable;
    use Injectable;

    protected $numberOfExamples = 1;

    /**
     * List of alternative links for modeladmins
     * e.g. 'admin/archive' => 'CMSEditLinkForTestPurposesNOTINUSE'
     *
     * @var array
     */
    private static $model_admin_alternatives = [];

    public function setNumberOfExamples($n): self
    {
        $this->numberOfExamples = $n;

        return $this;
    }

    public function findModelAdminLinks(): array
    {
        $links = [];
        $modelAdmins = CMSMenu::get_cms_classes(ModelAdmin::class);
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
                            $this->workOutLinksForModel($obj, $model, $modelAdminLink)
                        );
                    }
                }
            }
        }

        return $links;
    }

    protected function workOutLinksForModel($obj, $model, $modelAdminLink)
    {
        $links = [];
        $sanitizedModel = AllLinks::sanitise_class_name($model);
        $modelLink = $modelAdminLink . $sanitizedModel . '/';
        for ($i = 0; $i < $this->numberOfExamples; $i++) {
            $item = $model::get()
                ->sort(DB::get_conn()->random() . ' ASC')
                ->First();
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
                $links[] = $modelLink . 'EditForm/field/' . $sanitizedModel . '/item/new/';
                if ($item) {
                    $links[] = $modelLink . 'EditForm/field/' . $sanitizedModel . '/item/' . $item->ID . '/edit/';
                }
            }
        }

        return $links;
    }
}
