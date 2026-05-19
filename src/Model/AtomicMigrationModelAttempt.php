<?php

namespace Sunnysideup\DatabaseMigrations\Model;

use SilverStripe\ORM\DataObject;
use Sunnysideup\DatabaseMigrations\Traits\AtomicMigrationModelTrait;

class AtomicMigrationModelAttempt extends DataObject
{
    use AtomicMigrationModelTrait;

    private static string $table_name = 'AtomicMigrationModelAttempt';

    private static string $singular_name = 'Migration Attempt';

    private static string $plural_name = 'Migration Attempts';

    private static array $db = [
        'FileHash' => 'Varchar',
        'Successful' => 'Boolean',
        'Completed' => 'Boolean',
        'ErrorMessage' => 'Text',
    ];

    private static string $default_sort = 'Created DESC';

    private static array $has_one = [
        'Task' => AtomicMigrationModel::class,
    ];

    private static array $casting = [
        'Title' => 'Varchar',
        'IsCurrent' => 'Boolean',
    ];

    private static array $summary_fields = [
        'Created' => 'Date',
        'Title' => 'Title',
        'Successful' => 'Success',
        'IsCurrent' => 'Current',
    ];

    public static function start_new_attempt(AtomicMigrationModel $model): self
    {
        $me = self::create();
        $me->TaskID = $model->ID;
        $me->write();

        return $me;
    }

    public function getTitle(): string
    {
        $task = $this->Task();
        $title = $task ? $task->Title : 'Unknown Task';
        $status = $this->Successful ? 'Successful' : ($this->Completed ? 'Failed' : 'Incomplete');
        $currency = $this->getIsCurrent() ? '(Current)' : '(Outdated)';

        return sprintf('%s - %s %s', $title, $status, $currency);
    }

    public function getIsCurrent(): bool
    {
        $task = $this->Task();

        return $task && $task->getCurrentHash() === $this->FileHash;
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

    public function onBeforeWrite(): void
    {
        parent::onBeforeWrite();
        if (! $this->FileHash) {
            $task = $this->Task();
            $this->FileHash = $task ? $task->getCurrentHash() : '';
        }
    }
}
