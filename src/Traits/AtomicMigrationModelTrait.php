<?php

namespace Sunnysideup\DatabaseMigrations\Traits;

use Sunnysideup\DatabaseMigrations\Interfaces\AtomicMigrationApi;
use Exception;
use Sunnysideup\DatabaseMigrations\Interfaces\AtomicMigrationInterface;

trait AtomicMigrationModelTrait
{
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
