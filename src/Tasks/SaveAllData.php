<?php

namespace Sunnysideup\TemplateOverview\Tasks;

use SilverStripe\Assets\File;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\HybridSessions\HybridSessionDataObject;
use SilverStripe\MFA\Model\RegisteredMethod;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\DefaultAdminService;
use SilverStripe\Security\LoginAttempt;
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
        File::class,
        ChangeSet::class,
        ChangeSetItem::class,
        RegisteredMethod::class,
        'SilverStripe\\\HybridSessions\\HybridSessionDataObject',
        RememberLoginHash::class,
        Permission::class,
        PermissionRole::class,
        PermissionRoleCode::class,
        MemberPassword::class,
        EditableFormField::class,
        LoginAttempt::class,
    ];

    private static $limit = 100;

    private static $do_save = [];
    private static $always_write = false;
    private static $always_publish = false;

    /**
     * @param \SilverStripe\Control\HTTPRequest $request
     *
     * @throws \ReflectionException
     */
    public function run($request)
    {
        $member = Injector::inst()->get(DefaultAdminService::class)->findOrCreateDefaultAdmin();
        Environment::increaseTimeLimitTo(600);

        $classes = ClassInfo::subclassesFor(DataObject::class, false);
        if (! Director::isDev() && ! Director::is_cli()) {
            die('you can only run this in dev mode');
        }
        $this->writeTableHeader();
        $dontSave = $this->Config()->get('dont_save');
        $doSave = $this->Config()->get('do_save');
        $limit = $this->Config()->get('limit');
        $alwaysWrite = $this->Config()->get('always_write');
        $alwaysPublish = $this->Config()->get('always_publish');
        if (Director::is_cli()) {
            $limit = 9999999;
        }
        foreach ($classes as $class) {
            // we need to keep setting this...
            Config::modify()->set(DataObject::class, 'validation_enabled', false);
            DataObject::config()->set('validation_enabled', false);
            if (! empty($dontSave)) {
                foreach ($dontSave as $dontSaveClass) {
                    if (is_a($class, $dontSaveClass, true)) {
                        DB::alteration_message('SKIPPING ' . $class . ' (as listed in dontSave using is_a test)', 'deleted');
                        continue 2;
                    }
                }
            }
            if (! empty($doSave)) {
                $save = false;
                foreach ($doSave as $doSaveClass) {
                    if (is_a($class, $doSaveClass, true)) {
                        $save = true;
                    }
                }
                if ($save === false) {
                    DB::alteration_message('SKIPPING (as not listed in doSave using is_a test) ' . $class, 'deleted');
                    continue;
                }
            }
            DB::alteration_message('----------------- CREATING ' . $class . ' ------------------');
            $singleton = Injector::inst()->get($class);
            // space on purpose!
            $type = '<strong>' . $singleton->i18n_singular_name() . '</strong> <br />' . $singleton->ClassName;
            DB::alteration_message('-----------------TESTING ' . $class . ' ------------------');
            if ($singleton->canEdit($member) || $alwaysWrite) {
                if ($class::get()->count() > $limit) {
                    DB::alteration_message('SKIPPING some of ' . $class . ' as it has more than ' . $limit . ' records', 'deleted');
                }
                $list = $class::get()->orderBy('RAND()')->limit($limit);
                $timeBefore = microtime(true);
                $writeCount = 0;
                $publishCount = 0;
                $title = 'not-set';
                foreach ($list as $obj) {
                    $writeCount++;
                    $title = (string) $obj->getTitle() ?: (string) $obj->ID;
                    if ($obj->hasExtension(Versioned::class)) {
                        $isPublished = $obj->isPublished() && ! $obj->isModifiedOnDraft() && $obj->canPublish($member);
                        $obj->writeToStage(Versioned::DRAFT);
                        if ($isPublished || $alwaysPublish) {
                            $obj->publishSingle();
                            $publishCount++;
                        }
                    } else {
                        $obj->write();
                    }
                }
                $action = 'write (' . $writeCount . 'x)';
                if ($publishCount) {
                    $action .= ' and publish (' . $publishCount . 'x)';
                }
                $this->writeTableRow($type, $action, $title, $timeBefore, $writeCount);
            } else {
                $action = 'write (not allowed)';
                $title = 'n/a';
                $timeBefore = microtime(true);
                $this->writeTableRow($action, $type, $title, $timeBefore);
            }
            $type = '<div style="color: #555;">' . $type . '</div>';
            $createdObj = null;
            if ($singleton->canCreate($member)) {
                $timeBefore = microtime(true);
                $action = 'create';
                $title = 'NEW OBJECT';
                $createdObj = $class::create();
                $outcome = 'ERROR';
                try {
                    if ($createdObj->hasExtension(Versioned::class)) {
                        $createdObj->writeToStage(Versioned::DRAFT);
                        $isPublished = $createdObj->isPublished() && ! $createdObj->isModifiedOnDraft();
                        if ($isPublished || $alwaysPublish) {
                            $createdObj->publishSingle();
                        }
                    } else {
                        $createdObj->write();
                    }
                    $outcome = 'ID = ' . $createdObj->ID;
                } catch (\Exception $e) {
                    $action = 'create ERROR!!!';
                    $title = 'n/a';
                }
            } else {
                $action = 'create (not allowed)';
                $title = 'n/a';
                $timeBefore = microtime(true);
            }
            $this->writeTableRow($action, $type, $title . ' OUTCOME: ' . $outcome, $timeBefore);
            if ($createdObj && $createdObj->exists()) {
                $outcome = 'DELETED ID = ' . $createdObj->ID;
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
            $this->writeTableRow($action, $type, $title . ' OUTCOME: ' . $outcome, $timeBefore);
        }
        $this->writeTableFooter();
        DB::alteration_message('-----------------DONE ------------------');
    }

    protected function writeTableHeader()
    {
        if (Director::is_cli()) {
            return;
        }
        $this->output(
            '
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
            <tbody>'
        );
    }

    protected function writeTableFooter()
    {
        $this->output('
            </tbody>
            </teable>');
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
        $this->output(
            '
            <tr>
                <td>' . $type . '</td>
                <td>' . $action . '</td>
                <td>' . $title . '</td>
                <td class="right" style="background-color: ' . $colour . ';">' . $timeTaken . '</td>
            </tr>'
        );
    }

    protected function output(string $string)
    {
        if (Director::is_cli()) {
            echo strip_tags($string) . PHP_EOL;
        } else {
            echo $string;
        }
    }
}
