<?php

namespace Sunnysideup\TemplateOverview\Tasks;

use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\HybridSessions\HybridSessionDataObject;
use SilverStripe\MFA\Model\RegisteredMethod;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\MemberPassword;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionRole;
use SilverStripe\Security\PermissionRoleCode;
use SilverStripe\Security\RememberLoginHash;
use SilverStripe\UserForms\Model\EditableFormField;
use SilverStripe\Versioned\ChangeSet;
use SilverStripe\Versioned\ChangeSetItem;
use SilverStripe\Versioned\Versioned;

/**
 * A task to manually flush InterventionBackend cache.
 */
class SaveAllData extends BuildTask
{
    protected $title = 'Write all dataobjects - use with extreme caution.';

    protected $description = 'for testing purposes only';

    private static $segment = 'write-all-data-objects';

    private static $dont_save = [
        ChangeSet::class,
        ChangeSetItem::class,
        RegisteredMethod::class,
        HybridSessionDataObject::class,
        RememberLoginHash::class,
        Permission::class,
        PermissionRole::class,
        PermissionRoleCode::class,
        MemberPassword::class,
        EditableFormField::class,
    ];

    private static $limit = 100;

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
        if (! Director::isDev()) {
            die('you can only run this in dev mode');
        }
        $this->writeTableHeader();
        $dontSave = $this->Config()->get('dont_save');
        $limit = $this->Config()->get('limit');
        foreach ($classes as $class) {
            if (in_array($class, $dontSave, true)) {
                DB::alteration_message('SKIPPING ' . $class, 'deleted');
                continue;
            }
            $singleton = Injector::inst()->get($class);
            foreach ($dontSave as $dontSaveClass) {
                if ($singleton instanceof $dontSaveClass) {
                    DB::alteration_message('SKIPPING ' . $class, 'deleted');
                    continue 2;
                }
            }
            $type = '<strong>' . $singleton->i18n_singular_name() . '</strong><br />' . $singleton->ClassName;
            DB::alteration_message('-----------------TESTING ' . $class . ' ------------------');
            if ($singleton->canEdit()) {
                $list = $class::get()->orderBy('RAND()')->limit($limit);
                $timeBefore = microtime(true);
                $action = 'write ('.$list->count().'x)';
                foreach ($list as $obj) {
                    $title = (string) $obj->getTitle() ?: (string) $obj->ID;
                    if ($obj->hasExtension(Versioned::class)) {
                        $isPublished = $obj->isPublished() && ! $obj->isModifiedOnDraft();
                        $obj->writeToStage(Versioned::DRAFT);
                        if ($isPublished) {
                            $obj->publishSingle();
                        }
                    } else {
                        $obj->write();
                    }
                }
                $this->writeTableRow($type, $action, $title, $timeBefore, $limit);
            } else {
                $action = 'write (not allowed)';
                $title = 'n/a';
                $timeBefore = microtime(true);
                $this->writeTableRow($action, $type, $title, $timeBefore);
            }
            $type = '<div style="color: #555;">' . $type . '</div>';
            $createdObj = null;
            if ($singleton->canCreate()) {
                $timeBefore = microtime(true);
                $action = 'create';
                $title = 'NEW OBJECT';
                $createdObj = $class::create();
                try {
                    if ($createdObj->hasExtension(Versioned::class)) {
                        $createdObj->writeToStage(Versioned::DRAFT);
                        if ($isPublished) {
                            $createdObj->publishSingle();
                        }
                    } else {
                        try {
                            $createdObj->write();
                        } catch (\Exception $e) {
                            $action = 'create (failed)';
                            $title = $e->getMessage();
                        }
                    }
                } catch (\Exception $e) {
                    $action = 'create ERROR!!!';
                    $title = 'n/a';
                }
            } else {
                $action = 'creatre (not allowed)';
                $title = 'n/a';
                $timeBefore = microtime(true);
            }
            $this->writeTableRow($action, $type, $title, $timeBefore);
            if ($createdObj && $createdObj->exists()) {
                $action = 'delete';
                $title = (string) $createdObj->getTitle() ?: (string) $createdObj->ID;
                $timeBefore = microtime(true);
                if ($createdObj->hasExtension(Versioned::class)) {
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
                        <th>Record</th>
                        <th>Action</th>
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

    protected function writeTableRow(string $type, string $action, string $title, float $timeBefore, int $divider = 1)
    {
        $timeAfter = microtime(true);
        $timeTaken = round(($timeAfter - $timeBefore) / $divider, 2);
        $colour = 'transparent';
        if ($timeTaken > 0.3) {
            $timeTaken .= 's - SUPER SLOW';
            $colour = 'red';
        } elseif ($timeTaken > 0.2) {
            $timeTaken .= 's - SLOW';
            $colour = 'orange';
        } elseif ($timeTaken > 0.1) {
            $timeTaken .= 's - SLUGGISH';
            $colour = 'yellow';
        } else {
            $timeTaken .= 's';
        }
        echo '
            <tr>
                <td>' . $type . '</td>
                <td>' . $action . '</td>
                <td>' . $title . '</td>
                <td class="right" style="background-color: '.$colour.';">' . $timeTaken . '</td>
            </tr>';
    }
}
