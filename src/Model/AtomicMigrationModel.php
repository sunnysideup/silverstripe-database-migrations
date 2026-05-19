<?php

namespace Sunnysideup\DatabaseMigrations\Model;

use Exception;
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

    private static string $default_sort = 'Created ID';

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
        'Created' => 'Created',
    ];

    public static function find_or_create(string $className): self
    {
        $filter = ['TaskClassName' => $className];
        $model = self::get()->filter($filter)->first();
        if (! $model) {
            $model = self::create($filter);
            $model->write();
        }

        return $model;
    }

    public function requireDefaultRecords(): void
    {
        parent::requireDefaultRecords();
        $list = AtomicMigrationApi::inst()->getListOfMigrationTasks();
        foreach ($list as $array) {
            $task = $array['Task'];
            $model = $array['Model'];

            // Skip if already run successfully with current configuration
            if ($model->getHasRunSuccessfullyWithCurrentClassConfiguration() === true) {
                continue;
            }

            // Skip if task has failed before and cannot run again
            if ($task instanceof AtomicMigrationInterface) {
                if ($model->getHasRun() && ! $model->getHasRunSuccessfully() && ! $task->canRunAgainOnFailure()) {
                    continue;
                }
            }

            $attempt = AtomicMigrationModelAttempt::start_new_attempt($model);
            try {
                $task->run(null);
                $attempt->Successful = true;
                $attempt->Completed = true;
            } catch (Exception $e) {
                $attempt->ErrorMessage = $e->getMessage();
                $attempt->Completed = true;
            }
            $attempt->write();
        }
    }

    public function onBeforeWrite(): void
    {
        parent::onBeforeWrite();
        $this->CurrentHash = $this->getCurrentHash();

        // Auto-populate Title from class name if not set
        if (! $this->Title && $this->TaskClassName) {
            $task = Injector::inst()->get($this->TaskClassName);
            if ($task) {
                $this->Title = $task->getTitle();
                $this->Description = $task->getDescription();
            }
        }

        // Auto-populate URLSegment if not set
        if (! $this->URLSegment && $this->TaskClassName) {
            $task = Injector::inst()->get($this->TaskClassName);
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
