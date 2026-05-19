<?php

namespace Sunnysideup\DatabaseMigrations\Model;

use Sunnysideup\DatabaseMigrations\Interfaces\AtomicMigrationApi;
use Exception;
use Sunnysideup\DatabaseMigrations\Interfaces\AtomicMigrationInterface;

class AtomicMigrationModel extends DataObject
{
    use AtomicMigrationModelTrait;
    public static function find_or_create(string $className): static
    {
        $filter = ['TaskClassName' => $className];
        $model = AtomicMigrationModel::get()->filter($filter)->first();
        if (!$model) {
            $model = AtomicMigrationModel::create($filter);
            $model->write();
        }
        return $model;
    }
    private static $db = [
        'Title' => 'Varchar',
        'TaskClassName' => 'Varchar(255)',
        'Description' => 'Text',
        'URLSegment' => 'Varchar'
    ];

    private static $has_many = [
        'Attempts' => AtomicMigrationModelRunAttempt::class
    ];

    private static $casting = [
        'NumberOfAttempts' => 'Int',
        'HasRun' => 'Boolean',
        'HasRunSuccessfully' => 'Boolean',
        'HasRunWithCurrentClassConfiguration' => 'Boolean',
        'HasRunSuccessfullyWithCurrentClassConfiguration' => 'Boolean',
        'CurrentHash' => 'Varchar',
    ];

    private static $indexes = [
        'URLSegment' => true,
        'TaskClassName' => true,
    ];

    public function canCreate($member, $context = [])
    {
        return false;
    }
    public function canEdit($member)
    {
        return false;
    }

    public function canDelete($member)
    {
        return false;
    }

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        $list = AtomicMigrationApi::inst()->getListOfMigrationTasks();
        foreach ($list as $array) {
            $task = $array['Task'];
            $model = $array['Model'];
            if ($model->getHasRunSuccessfullyWithCurrentClassConfiguration() === true) {
                continue;
            }
            if ($model instanceof AtomicMigrationInterface && !$model->CanRunAgainOnFailure()) {
                continue;
            }
            $attempt = AtomicMigrationModelAttempt::start_new_attempt($model);
            try {
                $task->run(null);
                $attempt->Successful = true;
            } catch (Exception $e) {
                $attempt->ErrorMessage = $e->getMessage();

                // Handle exception if needed
            }
            $attempt->write();


        }
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $path = AtomicMigrationApi::inst()->ClassNameToPath($this->TaskClassName);
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
        return $this->Attempts()->filter(['FileHash' => $this->CurrentHash])->exists();
    }
    public function getHasRunSuccessfullyWithCurrentClassConfiguration(): bool
    {
        return $this->Attempts()->filter(['FileHash' => $this->getCurrentHash(), 'Successful' => true])->exists();
    }

    public function getCurrentHash(): string
    {
        $file = AtomicMigrationApi::inst()->ClassNameToPath($this->TaskClassName);
        return md5($file);
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        foreach ($this->config()->casting as $fieldName => $fieldType) {
            $method = 'get' . $fieldName;
            $value = $this->$method();
            $fields->addFieldsToTab(
                'Root.Main',
                [
                    ReadonlyField::create(
                        $fieldName . 'Nice',
                        $fieldName,
                        DBField::create_field($fieldType, $value)->Nice()
                    )
                ]
            );
        }
        return $fields;
    }

}
