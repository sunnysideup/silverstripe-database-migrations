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

    private static array $db = [
        'Title' => 'Varchar',
        'TaskClassName' => 'Varchar(255)',
        'Description' => 'Text',
        'URLSegment' => 'Varchar',
        'CurrentHash' => 'Varchar',
    ];

    private static array $has_many = [
        'Attempts' => AtomicMigrationModelAttempt::class,
    ];

    private static array $casting = [
        'NumberOfAttempts' => 'Int',
        'HasRun' => 'Boolean',
        'HasRunSuccessfully' => 'Boolean',
        'HasRunWithCurrentClassConfiguration' => 'Boolean',
        'HasRunSuccessfullyWithCurrentClassConfiguration' => 'Boolean',
    ];

    private static array $indexes = [
        'URLSegment' => true,
        'TaskClassName' => true,
    ];

    private static array $summary_fields = [
        'Title' => 'Title',
        'TaskClassName' => 'Task Class',
        'HasRunSuccessfully' => 'Successful',
        'NumberOfAttempts' => 'Attempts',
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

    public function canCreate($member = null, $context = []): bool
    {
        return false;
    }

    public function canEdit($member = null, $context = []): bool
    {
        return false;
    }

    public function canDelete($member = null): bool
    {
        return false;
    }

    public function requireDefaultRecords(): void
    {
        parent::requireDefaultRecords();
        $list = AtomicMigrationApi::inst()->getListOfMigrationTasks();
        foreach ($list as $array) {
            $task = $array['Task'];
            $model = $array['Model'];
            if ($model->getHasRunSuccessfullyWithCurrentClassConfiguration() === true) {
                continue;
            }
            if ($task instanceof AtomicMigrationInterface && ! $task->canRunAgainOnFailure()) {
                continue;
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

        return md5_file($file);
    }
}
