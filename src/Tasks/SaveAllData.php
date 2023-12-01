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
            echo '<h2>' . Injector::inst()->get($class)->i18n_singular_name() . '</h2>';
            $list = $class::get()->orderBy('RAND()')->limit(10);
            foreach ($list as $obj) {
                DB::alteration_message('Writing ' . $obj->getTitle());
                if($obj->hasExtension(Versioned::class)) {
                    $isPublished = $obj->isPublished();
                    $obj->writeToStage(Versioned::DRAFT);
                    if($isPublished) {
                        $obj->publishSingle();
                    }
                } else {
                    $obj->write();
                }
            }
        }
        DB::alteration_message('-----------------DONE ------------------');
    }
}
