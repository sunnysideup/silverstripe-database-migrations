<?php

namespace Sunnysideup\DatabaseMigrations\Api;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Manifest\ClassLoader;
use Sunnysideup\DatabaseMigrations\Interfaces\AtomicMigrationInterface;
use Sunnysideup\DatabaseMigrations\Model\AtomicMigrationModel;

class AtomicMigrationApi
{
    use Injectable;
    use Configurable;
    use Extensible;

    private static array $also_run = [];

    public function getListOfMigrationTasks(): array
    {
        $array = [];
        $list = ClassInfo::implementorsOf(AtomicMigrationInterface::class);
        $list = array_merge(
            $list,
            $this->config()->get('also_run') ?? []
        );
        foreach ($list as $className) {
            $task = Injector::inst()->get($className);
            $model = AtomicMigrationModel::find_or_create($className);
            $sortNumber = method_exists($task, 'getSortAtomicMigrationSortNumber') ? $task->getSortAtomicMigrationSortNumber() : 0;
            $array[] = [
                'ClassName' => $className,
                'Model' => $model,
                'Task' => $task,
                'SortNumber' => $sortNumber,
            ];
            array_multisort(
                array_column($array, 'SortNumber'),
                SORT_ASC,
                $array
            );
        }

        return $array;
    }

    public static function inst(): self
    {
        return Injector::inst()->get(static::class);
    }

    public function classNameToPath(string $className): ?string
    {
        $path = ClassLoader::inst()->getManifest()->getItemPath($className);

        return $path ?: null;
    }
}
