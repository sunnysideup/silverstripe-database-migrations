<?php

namespace Sunnysideup\DatabaseMigrations\Traits;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\FieldType\DBField;

trait AtomicMigrationModelTrait
{
    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        $casting = $this->config()->get('casting');
        if (! $casting) {
            return $fields;
        }

        foreach ($casting as $fieldName => $fieldType) {
            $method = 'get' . $fieldName;
            if (! method_exists($this, $method)) {
                continue;
            }

            $value = $this->$method();
            $dbField = DBField::create_field($fieldType, $value);
            if ($dbField->hasMethod('Nice')) {
                $value = $dbField->Nice();
            }
            $fields->addFieldsToTab(
                'Root.Main',
                [
                    ReadonlyField::create(
                        $fieldName . 'Nice',
                        $fieldName,
                        $value
                    ),
                ]
            );
        }

        return $fields;
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
}
