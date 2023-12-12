<?php

namespace Sunnysideup\TemplateOverview\Tasks;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;
use Page;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;

/**
 * A task to manually flush InterventionBackend cache.
 */
class SaveAllData extends BuildTask
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
        Environment::increaseTimeLimitTo(600);
        DataObject::config()->set('validation_enabled', false);
        $classes = ClassInfo::subclassesFor(DataObject::class, false);
        if(!Director::isDev()) {
            die('you can only run this in dev mode');
        }
        $this->writeTableHeader();

        foreach ($classes as $class) {
            $singleton = Injector::inst()->get($class);
            $type =  $singleton->i18n_singular_name() . '<br />' . $singleton->ClassName;
            if($singleton->canEdit()) {
                $list = $class::get()->orderBy('RAND()')->limit(1);
                $action = 'write';
                foreach ($list as $obj) {
                    $timeBefore = microtime(true);
                    $title = (string) $obj->getTitle() ?: (string) $obj->ID;
                    if($obj->hasExtension(Versioned::class)) {
                        $isPublished = $obj->isPublished() && !$obj->isModifiedOnDraft();
                        $obj->writeToStage(Versioned::DRAFT);
                        if($isPublished) {
                            $action .= ' and publish';
                            $obj->publishSingle();
                        }
                    } else {
                        $obj->write();
                    }
                    $this->writeTableRow($action, $type, $title, $timeBefore);
                }
            } else {
                $action = 'write (not allowed)';
                $title = 'n/a';
                $timeBefore = microtime(true);
                $this->writeTableRow($action, $type, $title, $timeBefore);
            }
            $createdObj = null;
            if($singleton->canCreate()) {
                $timeBefore = microtime(true);
                $action = 'create';
                $title = 'NEW OBJECT';
                $createdObj = $class::create();
                if($createdObj->hasExtension(Versioned::class)) {
                    $createdObj->writeToStage(Versioned::DRAFT);
                    if($isPublished) {
                        $createdObj->publishSingle();
                    }
                } else {
                    try {
                        //$createdObj->write();
                    } catch (\Exception $e) {
                        $action = 'create (failed)';
                        $title = $e->getMessage();
                    }
                }
            } else {
                $action = 'creatre (not allowed)';
                $title = 'n/a';
                $timeBefore = microtime(true);
            }
            $this->writeTableRow($action, $type, $title, $timeBefore);
            if($createdObj && $createdObj->exists()) {
                $action = 'delete';
                $title = (string) $createdObj->getTitle() ?: (string) $createdObj->ID;
                $timeBefore = microtime(true);
                if($createdObj->hasExtension(Versioned::class)) {
                    $createdObj->doUnpublish();
                    $createdObj->delete();
                    $action .= ' and unpublish';
                } else {
                    $createdObj->delete();
                }
            } else {
                $action = 'delete (not required)';
                $title = 'n/a';
                $timeBefore = microtime(true);
            }
            $this->writeTableRow($action, $type, $title, $timeBefore);

        }
        $this->writeTableFooter();
        DB::alteration_message('-----------------DONE ------------------');
    }

    protected function writeTableHeader()
    {
        echo '
            <style>
                .table {
                    max-width: 80%;
                    margin: auto;
                    border-collapse: collapse;
                    font-family: Arial, sans-serif;
                }

                .table th,
                .table td {
                    border: 1px solid #ddd;
                    padding: 8px;
                    text-align: left;
                    width: 25%;
                }

                .table th {
                    background-color: #f4f4f4;
                    color: #333;
                }
                .table .right {
                    text-align: right;
                }
                .table tr:nth-child(even) {
                    background-color: #f9f9f9;
                }

                .table tr:hover {
                    background-color: #f1f1f1;
                }
            </style>
            <table class="table">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Type</th>
                        <th>Name</th>
                        <th class="right">Time Taken</th>
                    </tr>
                </thead>
            <tbody>';
    }

    protected function writeTableFooter()
    {
        echo '
            </tbody>
            </teable>';
    }

    protected function writeTableRow(string $action, string $type, string $title, float $timeBefore)
    {
        $timeAfter = microtime(true);
        $timeTaken = round($timeAfter - $timeBefore, 2);
        if($timeTaken > 0.3) {
            $timeTaken .= ' SUPER SLOW';
        } elseif($timeTaken > 0.2) {
            $timeTaken .= ' SLOW';
        } elseif($timeTaken > 0.1) {
            $timeTaken .= ' SLUGGISH';
        }
        echo '
            <tr>
                <td>' . $action . '</td>
                <td>' . $type . '</td>
                <td>' . $title . '</td>
                <td class="right">' . $timeTaken . '</td>
            </tr>';
    }
}
