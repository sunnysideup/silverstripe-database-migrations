<?php

namespace Sunnysideup\DatabaseMigrations\Interfaces;

use SilverStripe\Core\Manifest\ClassLoader;
use Sunnysideup\DatabaseMigrations\Model\AtomicMigrationModel;

class AtomicMigrationApi
{
    use Injectable;
    use Configurable;
    use Extensible;

    private static array $also_run = [

    ];

    public function getListOfMigrationTasks(): array
    {
        $array = [];
        $list = ClassInfo::implements(AtomicMigrationInterface::class);
        $list = array_merge(
            $list,
            $this->Config()->also_run
        );
        foreach ($list as $className) {
            $task = Injector::inst()->get($className);
            $model = AtomicMigrationModel::find_or_create($className);
            $array[] = [
                'ClassName' => $className,
                'Model' => $model,
                'Task' => $task,
            ];
        }
        return $array;
    }

    public static function inst(): AtomicMigrationApi
    {
        return Injector::inst()->get(static::class);
    }

    public function ClassNameToPath(string $className): ?string
    {
        $path = ClassLoader::inst()->getManifest()->getItemPath($className);
        return $path ?: null;
    }
}
