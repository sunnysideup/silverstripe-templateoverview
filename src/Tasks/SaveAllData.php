<?php

namespace Sunnysideup\TemplateOverview\Tasks;

use Exception;
use SilverStripe\Assets\File;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\MFA\Model\RegisteredMethod;
use SilverStripe\ORM\DataObject;
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
 * A task to write (and test-create/delete) every DataObject.
 * Browser: full HTML table. CLI: one line per class, and throws if any errors occurred.
 * Use with extreme caution.
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
        'SilverStripe\\HybridSessions\\HybridSessionDataObject',
        RememberLoginHash::class,
        Permission::class,
        PermissionRole::class,
        PermissionRoleCode::class,
        MemberPassword::class,
        EditableFormField::class,
        LoginAttempt::class,
        'SilverStripe\\UserForms\\Model\\Submission\\SubmittedFileField',
    ];

    private static $limit = 100;

    private static $do_save = [];

    private static $always_write = false;

    private static $always_publish = false;

    private array $timeTakenAggregate = [];

    private array $errorLog = [];

    public function run($request)
    {
        $member = Injector::inst()->get(DefaultAdminService::class)->findOrCreateDefaultAdmin();
        Environment::increaseTimeLimitTo(600);

        if (! Director::isDev() && ! Director::is_cli()) {
            die('you can only run this in dev mode');
        }

        $dontSave      = (array) $this->config()->get('dont_save');
        $doSave        = (array) $this->config()->get('do_save');
        $limit         = (int) $this->config()->get('limit');
        $alwaysWrite   = (bool) $this->config()->get('always_write');
        $alwaysPublish = (bool) $this->config()->get('always_publish');
        if (Director::is_cli()) {
            $limit = PHP_INT_MAX;
        }

        $this->writeTableHeader();
        foreach (ClassInfo::subclassesFor(DataObject::class, false) as $class) {
            // has to be reset every loop
            Config::modify()->set(DataObject::class, 'validation_enabled', false);

            if ($this->shouldSkip($class, $dontSave, $doSave)) {
                continue;
            }

            $this->processClass($class, $member, $limit, $alwaysWrite, $alwaysPublish);
        }
        $this->writeTableFooter();
        $this->writeAverage();
        $this->line('----------------- DONE ------------------');

        // CLI only: fail loudly (non-zero exit) if anything went wrong.
        if ($this->isCli() && ! empty($this->errorLog)) {
            throw new Exception(
                count($this->errorLog) . ' error(s) occurred:' . PHP_EOL . implode(PHP_EOL, $this->errorLog)
            );
        }
    }

    private function shouldSkip(string $class, array $dontSave, array $doSave): bool
    {
        foreach ($dontSave as $dontSaveClass) {
            if (is_a($class, $dontSaveClass, true)) {
                $this->skipRow($class, 'SKIPPED (dont_save)');
                return true;
            }
        }
        if (! empty($doSave)) {
            foreach ($doSave as $doSaveClass) {
                if (is_a($class, $doSaveClass, true)) {
                    return false;
                }
            }
            $this->skipRow($class, 'SKIPPED (not in do_save)');
            return true;
        }
        return false;
    }

    private function processClass(string $class, $member, int $limit, bool $alwaysWrite, bool $alwaysPublish): void
    {
        $start        = microtime(true);
        $singleton    = Injector::inst()->get($class);
        $writeCount   = 0;
        $publishCount = 0;
        $errors       = 0;

        // 1. re-write existing records
        if ($singleton->canEdit($member) || $alwaysWrite) {
            foreach ($class::get()->limit($limit) as $obj) {
                try {
                    if (! $obj->ClassName || ! class_exists($obj->ClassName)) {
                        continue;
                    }
                    if ($obj->hasExtension(Versioned::class)) {
                        $wasPublished = $obj->isPublished() && ! $obj->isModifiedOnDraft() && $obj->canPublish($member);
                        $obj->writeToStage(Versioned::DRAFT);
                        if ($wasPublished || $alwaysPublish) {
                            $obj->publishSingle();
                            $publishCount++;
                        }
                    } else {
                        $obj->write();
                    }
                    $writeCount++;
                } catch (Exception $e) {
                    $errors++;
                    $this->errorLog[] = $class . ' #' . $obj->ID . ' (write): ' . $e->getMessage();
                }
            }
        }

        // 2. test create + delete a throwaway record
        $crud = $this->testCreateAndDelete($class, $member, $singleton, $alwaysPublish);

        $timeTaken = round(microtime(true) - $start, 2);
        $this->timeTakenAggregate[] = $timeTaken;

        $this->resultRow($class, $singleton->i18n_singular_name(), $writeCount, $publishCount, $crud, $errors, $timeTaken);
    }

    private function testCreateAndDelete(string $class, $member, $singleton, bool $alwaysPublish): string
    {
        if (! $singleton->canCreate($member)) {
            return 'create:not-allowed';
        }

        try {
            $obj = $class::create();
            if ($obj->hasExtension(Versioned::class)) {
                $obj->writeToStage(Versioned::DRAFT);
                if ($alwaysPublish) {
                    $obj->publishSingle();
                }
            } else {
                $obj->write();
            }
        } catch (Exception $e) {
            $this->errorLog[] = $class . ' (create): ' . $e->getMessage();
            return 'create:ERROR';
        }

        try {
            if ($obj->hasExtension(Versioned::class)) {
                $obj->doUnpublish();
            }
            $obj->delete();
        } catch (Exception $e) {
            $this->errorLog[] = $class . ' (delete): ' . $e->getMessage();
            return 'create:OK delete:ERROR';
        }

        return 'create+delete:OK';
    }

    // ---------------------------------------------------------------------
    // Output helpers
    // ---------------------------------------------------------------------

    private function isCli(): bool
    {
        return Director::is_cli();
    }

    private function resultRow(string $class, string $singularName, int $writeCount, int $publishCount, string $crud, int $errors, float $timeTaken): void
    {
        if ($this->isCli()) {
            echo sprintf(
                '%-55s write:%-6d publish:%-6d %-22s %6.2fs%s' . PHP_EOL,
                $class,
                $writeCount,
                $publishCount,
                $crud,
                $timeTaken,
                $errors ? "  ERRORS:{$errors}" : ''
            );
            return;
        }

        [$timeLabel, $colour] = $this->timeLabel($timeTaken);
        $type   = '<strong>' . htmlspecialchars($singularName) . '</strong><br />' . htmlspecialchars($class);
        $action = 'write (' . $writeCount . 'x)';
        if ($publishCount !== 0) {
            $action .= ' and publish (' . $publishCount . 'x)';
        }
        $name = htmlspecialchars($crud) . ($errors ? ' <span style="color:red;">ERRORS: ' . $errors . '</span>' : '');

        echo '
            <tr>
                <td>' . $type . '</td>
                <td>' . $action . '</td>
                <td>' . $name . '</td>
                <td class="right" style="background-color: ' . $colour . ';">' . $timeLabel . '</td>
            </tr>';
    }

    private function skipRow(string $class, string $reason): void
    {
        if ($this->isCli()) {
            echo sprintf('%-55s %s' . PHP_EOL, $class, $reason);
            return;
        }
        echo '
            <tr>
                <td colspan="3"><em>' . htmlspecialchars($class) . '</em></td>
                <td class="right">' . htmlspecialchars($reason) . '</td>
            </tr>';
    }

    /**
     * @return array{0:string,1:string} [label, background colour]
     */
    private function timeLabel(float $timeTaken): array
    {
        if ($timeTaken > 0.3) {
            return [$timeTaken . 's - SUPER SLOW', 'red'];
        }
        if ($timeTaken > 0.2) {
            return [$timeTaken . 's - SLOW', 'orange'];
        }
        if ($timeTaken > 0.1) {
            return [$timeTaken . 's - SLUGGISH', 'yellow'];
        }
        return [$timeTaken . 's', 'transparent'];
    }

    private function writeTableHeader(): void
    {
        if ($this->isCli()) {
            return;
        }
        echo '
            <style>
                .table { max-width: 80%; margin: auto; border-collapse: collapse; font-family: Arial, sans-serif; }
                .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; width: 25%; }
                .table th { background-color: #f4f4f4; color: #333; }
                .table .right { text-align: right; }
                .table tr:nth-child(even) { background-color: #f9f9f9; }
                .table tr:hover { background-color: #f1f1f1; }
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

    private function writeTableFooter(): void
    {
        if ($this->isCli()) {
            return;
        }
        echo '
            </tbody>
            </table>';
    }

    private function writeAverage(): void
    {
        if (empty($this->timeTakenAggregate)) {
            return;
        }
        $average = round(array_sum($this->timeTakenAggregate) / count($this->timeTakenAggregate), 2);
        $count   = count($this->timeTakenAggregate);
        if ($this->isCli()) {
            echo sprintf('Average time taken: %ss (%d actions)' . PHP_EOL, $average, $count);
            return;
        }
        echo '<h3>Average time taken: ' . $average . 's (' . $count . ' actions)</h3>';
    }

    private function line(string $string): void
    {
        if ($this->isCli()) {
            echo $string . PHP_EOL;
        } else {
            echo htmlspecialchars($string) . '<br>' . PHP_EOL;
        }
    }
}
