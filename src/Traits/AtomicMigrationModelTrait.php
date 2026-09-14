<?php

namespace Sunnysideup\DatabaseMigrations\Traits;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;

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
            if ($fieldName === 'Title') {
                continue;
            }
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

        $fields->addFieldsToTab(
            'Root.RunNow',
            [
                ReadonlyField::create(
                    'RunNowCurrentHash',
                    'Run All Uncompleted Migrations',
                    DBHTMLText::create_field(
                        'HTMLText',
                        '<a href="/dev/tasks/run-atomic-migrations" target="_blank">Run All Uncompleted Migrations</a>'
                    )
                )
                    ->setDescription('On the command line you can run: vendor/bin/sake dev/tasks/run-atomic-migrations')
            ]
        );

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
