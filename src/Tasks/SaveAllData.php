<?php

namespace Sunnysideup\TemplateOverview\Tasks;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;
use Page;

/**
 * A task to manually flush InterventionBackend cache.
 */
class SaveAll extends BuildTask
{
    protected $title = 'Write all dataobjects - use with extreme caution.';

    protected $description = 'for testing purposes only';
    private static $segment = 'write-all-data-objects';

    /**
     * @param \SilverStripe\Control\HTTPRequest $request
     *
     * @throws \ReflectionException
     */
    public function run($request)
    {
        DataObject::config()->set('validation_enabled', false);
        $classes = ClassInfo::subclassesFor(DataObject::class, false);
        foreach ($classes as $class) {
            $singleton = Injector::inst()->get($class);
            echo '<h2>' . $singleton->i18n_singular_name() . '</h2>';
            if($singleton->canEdit()) {
                $list = $class::get()->orderBy('RAND()')->limit(10);
                foreach ($list as $obj) {
                    DB::alteration_message('Writing ' . $obj->getTitle());
                    if($obj->hasExtension(Versioned::class)) {
                        $isPublished = $obj->isPublished() && !$obj->isModifiedOnDraft();
                        $obj->writeToStage(Versioned::DRAFT);
                        if($isPublished) {
                            $obj->publishSingle();
                        }
                    } else {
                        $obj->write();
                    }
                }
            } else {
                DB::alteration_message('No permission to edit ' . $singleton->i18n_singular_name());
            }
            $createdObj = null;
            if($singleton->canCreate()) {
                $createdObj = $class::create();
                if($createdObj->hasExtension(Versioned::class)) {
                    $createdObj->writeToStage(Versioned::DRAFT);
                    if($isPublished) {
                        $createdObj->publishSingle();
                    }
                } else {
                    $createdObj->write();
                }
            } else {
                DB::alteration_message('No permission to create ' . $singleton->i18n_singular_name());
            }
            if($createdObj) {
                if($createdObj->hasExtension(Versioned::class)) {
                    $createdObj->doUnpublish();
                    $createdObj->delete();
                } else {
                    $createdObj->deleted();
                }
                DB::alteration_message('Deleted new items of ' . $singleton->i18n_singular_name());
            }

        }
        DB::alteration_message('-----------------DONE ------------------');
    }
}
