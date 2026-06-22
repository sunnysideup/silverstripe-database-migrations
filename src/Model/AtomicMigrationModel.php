<?php

namespace Sunnysideup\DatabaseMigrations\Model;

use Exception;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use Sunnysideup\DatabaseMigrations\Api\AtomicMigrationApi;
use Sunnysideup\DatabaseMigrations\Interfaces\AtomicMigrationInterface;
use Sunnysideup\DatabaseMigrations\Traits\AtomicMigrationModelTrait;

class AtomicMigrationModel extends DataObject
{
    use AtomicMigrationModelTrait;

    private static string $table_name = 'AtomicMigrationModel';

    private static string $singular_name = 'Migration Task';

    private static string $plural_name = 'Migration Tasks';

    private static array $db = [
        'Title' => 'Varchar',
        'TaskClassName' => 'Varchar(255)',
        'Description' => 'Text',
        'URLSegment' => 'Varchar',
        'CurrentHash' => 'Varchar',
    ];

    private static string $default_sort = 'ID DESC';

    private static array $has_many = [
        'Attempts' => AtomicMigrationModelAttempt::class,
    ];

    private static array $casting = [
        'NumberOfAttempts' => 'Int',
        'HasRun' => 'Boolean',
        'HasRunSuccessfully' => 'Boolean',
        'HasRunWithCurrentClassConfiguration' => 'Boolean',
        'HasRunSuccessfullyWithCurrentClassConfiguration' => 'Boolean',
        'StatusMessage' => 'Varchar',
        'Status' => 'Varchar',
    ];

    private static array $indexes = [
        'URLSegment' => true,
        'TaskClassName' => true,
        'CurrentHash' => true,
    ];

    private static array $summary_fields = [
        'Title' => 'Title',
        'TaskClassName' => 'Task Class',
        'Status' => 'Status',
        'NumberOfAttempts' => 'Attempts',
        'Created.Ago' => 'Created',
    ];

    public static function find_or_create(string $className): self
    {
        $filter = ['TaskClassName' => $className];
        $model = self::get()->filter($filter)->first();
        $task = Injector::inst()->get($className);
        if (! $model) {
            $model = self::create($filter);
            $model->Title = $task?->getTitle() ?: $className;
            $model->Description = $task?->getDescription() ?: '';
            $model->write();

        }

        return $model;
    }

    public function requireDefaultRecords(): void
    {
        parent::requireDefaultRecords();
        AtomicMigrationApi::inst()->run(true);
    }

    public function getShouldRun(): bool
    {

        // Skip if already run successfully with current configuration
        if ($this->getHasRunSuccessfullyWithCurrentClassConfiguration() === true) {
            return false;
        }

        // Skip if task has failed before and cannot run again
        $task = Injector::inst()->get($this->TaskClassName);
        if ($task instanceof AtomicMigrationInterface) {
            if ($this->getHasRun() && ! $this->getHasRunSuccessfully() && ! $task->canRunAgainOnFailure()) {
                return false;
            }
        }
        return (bool) $task->isEnabled() === true;
    }

    /**
    * Get a nice status message for display
    */

    public function onBeforeWrite(): void
    {
        parent::onBeforeWrite();
        $this->CurrentHash = $this->getCurrentHash();

        // Auto-populate Title from class name if not set
        $task = Injector::inst()->get($this->TaskClassName);
        if (! $this->Title && $this->TaskClassName) {
            if ($task) {
                $this->Title = $task->getTitle();
            }
        }
        if (! $this->Description && $this->TaskClassName) {
            if ($task) {
                $this->Description = $task->getDescription();
            }
        }

        // Auto-populate URLSegment if not set
        if (! $this->URLSegment && $this->TaskClassName) {
            $this->URLSegment = $task?->config()->get('segment');
            if (! $this->URLSegment) {
                $this->URLSegment = str_replace('\\', '-', $this->TaskClassName);
            }
        }
    }

    public function getTitle(): string
    {
        return $this->getField('Title') ?: $this->TaskClassName;
    }

    public function getNumberOfAttempts(): int
    {
        return $this->Attempts()->count();
    }

    public function getHasRun(): bool
    {
        return $this->Attempts()->exists();
    }

    public function getHasRunSuccessfully(): bool
    {
        return $this->Attempts()->filter(['Successful' => true])->exists();
    }

    public function getHasRunWithCurrentClassConfiguration(): bool
    {
        return $this->Attempts()->filter(['FileHash' => $this->getCurrentHash()])->exists();
    }

    public function getHasRunSuccessfullyWithCurrentClassConfiguration(): bool
    {
        return $this->Attempts()->filter(['FileHash' => $this->getCurrentHash(), 'Successful' => true])->exists();
    }

    public function getCurrentHash(): string
    {
        if (! $this->TaskClassName) {
            return '';
        }
        $file = AtomicMigrationApi::inst()->classNameToPath($this->TaskClassName);
        if (! $file || ! file_exists($file)) {
            return '';
        }

        $hash = md5_file($file);

        return $hash !== false ? $hash : '';
    }

    /**
     * Get the last attempt for this migration
     */
    public function getLastAttempt(): ?AtomicMigrationModelAttempt
    {
        return $this->Attempts()->sort('Created DESC')->first();
    }

    /**
     * Get a nice status message for display
     */
    public function getStatusMessage(): string
    {
        if (! $this->getHasRun()) {
            return 'Not yet run';
        }

        if ($this->getHasRunSuccessfullyWithCurrentClassConfiguration()) {
            return 'Successfully run (current version)';
        }

        if ($this->getHasRunSuccessfully()) {
            return 'Successfully run (outdated version)';
        }

        return 'Failed';
    }

    /**
     * Get nice status for gridfield/display
     */
    public function getStatus(): string
    {
        if (! $this->getHasRun()) {
            return 'Pending';
        }

        if ($this->getHasRunSuccessfullyWithCurrentClassConfiguration()) {
            return 'Success';
        }

        if ($this->getHasRunSuccessfully()) {
            return 'Outdated';
        }

        return 'Failed';
    }
}
