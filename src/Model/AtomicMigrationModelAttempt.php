<?php

namespace Sunnysideup\DatabaseMigrations\Model;

class AtomicMigrationModelAttempt extends DataObject
{
    use AtomicMigrationModelTrait;
    public static function start_new_attempt(AtomicMigrationModel $model)
    {
        $me = AtomicMigrationModelAttempt::create();
        $me->TaskID = $model->ID;
        $me->write();
        return $me;
    }

    private static $db = [
        'FileHash' => 'Varchar',
        'Successful' => 'Boolean',
        'Completed' => 'Boolean'
    ];

    private static $has_one = [
        'Task' => AtomicMigrationModel::class
    ];

    private static $casting = [
        'Title' => 'Varchar',
        'IsCurrent' => 'Boolean'
    ];

    public function getTitle()
    {
        return
            $this->Task()?->Title .
            ' ' . ($this->Successful ? 'Successful' : ($this->Completed ? 'Failed' : 'Incomplete')) .
            ' ' . ($this->getIsCurrent() ? '(Current)' : '(Outdated)');
    }

    public function getIsCurrent()
    {
        return $this->Task()?->getCurrentHash() === $this->FileHash;
    }

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

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (! $this->FileHash) {
            $this->FileHash = (string) $this->Task()?->getCurrentHash();
        }
    }
}
