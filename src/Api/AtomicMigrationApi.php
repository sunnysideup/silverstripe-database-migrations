<?php

namespace Sunnysideup\DatabaseMigrations\Api;

use Exception;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Manifest\ClassLoader;
use Sunnysideup\DatabaseMigrations\Interfaces\AtomicMigrationInterface;
use Sunnysideup\DatabaseMigrations\Model\AtomicMigrationModel;
use Sunnysideup\DatabaseMigrations\Model\AtomicMigrationModelAttempt;

class AtomicMigrationApi
{
    use Injectable;
    use Configurable;
    use Extensible;

    private static array $also_run = [];

    public function getListOfMigrationTasks(): array
    {
        $array = [];

        // Implementors have no explicit sort key → fall back to the task's own number.
        foreach (ClassInfo::implementorsOf(AtomicMigrationInterface::class) as $className) {
            $array[] = $this->buildMigrationTaskEntry($className, null);
        }

        // also_run: the keys ARE the sort numbers, so iterate it directly (no array_merge).
        foreach ($this->config()->get('also_run') ?? [] as $className => $sort) {
            if (!class_exists($className) && class_exists($sort)) {
                $sortNew = $className;
                $className = $sort;
                $sort = $sortNew;
                unset($sortNew);
            }
            $array[] = $this->buildMigrationTaskEntry($className, (int) $sort);
        }

        array_multisort(
            array_column($array, 'SortNumber'),
            SORT_ASC,
            $array
        );

        return $array;
    }

    private function buildMigrationTaskEntry(string $className, ?int $sort): array
    {
        $task = Injector::inst()->get($className);
        $model = AtomicMigrationModel::find_or_create($className);

        $sortNumber = $sort
            ?? (method_exists($task, 'getSortAtomicMigrationSortNumber')
                ? $task->getSortAtomicMigrationSortNumber()
                : 0);

        return [
            'ClassName' => $className,
            'Model' => $model,
            'Task' => $task,
            'SortNumber' => (int) $sortNumber,
        ];
    }

    public static function inst(): self
    {
        return Injector::inst()->get(static::class);
    }

    public function run(?bool $dryRunOnly = false)
    {
        $list = AtomicMigrationApi::inst()->getListOfMigrationTasks();
        $hasTasks = false;
        foreach ($list as $array) {
            $attempt = null;
            $task = $array['Task'];
            $model = $array['Model'];
            if ($model->getShouldRun()) {
                $hasTasks = true;
                $link = $task->config()->get('segment') ?: $task->config()->get('commandName');
                if ($dryRunOnly) {
                    echo PHP_EOL.
                        'NB!!! Please run: vendor/bin/sake ' . $link .
                        PHP_EOL. ' => #' . $array['SortNumber'] . ': ' . $task->getTitle() .
                        PHP_EOL . ' => ' . $task->getDescription() . PHP_EOL. PHP_EOL;
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
                    $attempt->write();
                    echo PHP_EOL . 'ERROR: ' . $e->getMessage() . PHP_EOL;
                    echo PHP_EOL . 'Task: ' . $task->getTitle() . PHP_EOL;
                }
            } else {
                if ($model->getHasRunSuccessfullyWithCurrentClassConfiguration() !== true) {
                    $attempt = AtomicMigrationModelAttempt::start_new_attempt($model);
                }
            }
            if ($attempt) {
                $attempt->write();
            }
        }
        if ($hasTasks && $dryRunOnly) {
            echo PHP_EOL . 'NB!!! Please run: vendor/bin/sake dev/tasks/atomic-migration flush=all to run all database migration tasks.' . PHP_EOL. PHP_EOL;
        }
    }

    public function classNameToPath(string $className): ?string
    {
        $path = ClassLoader::inst()->getManifest()->getItemPath($className);

        return $path ?: null;
    }
}
