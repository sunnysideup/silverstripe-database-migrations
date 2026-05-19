<?php

namespace Sunnysideup\DatabaseMigrations\Admin;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Security\DefaultAdminService;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use Sunnysideup\DatabaseMigrations\Model\AtomicMigrationModel;
use Sunnysideup\DatabaseMigrations\Model\AtomicMigrationModelAttempt;

class AtomicMigrationAdmin extends ModelAdmin
{
    private static string $url_segment = 'atomic-migration-admin';

    private static string $menu_title = 'Atomic Migrations';

    private static string $menu_icon_class = 'font-icon-database';

    private static array $managed_models = [
        AtomicMigrationModel::class,
        AtomicMigrationModelAttempt::class,
    ];

    public function canView($member = null): bool
    {
        if (! $member) {
            $member = Security::getCurrentUser();
        }

        if (! $member instanceof Member) {
            return false;
        }

        return DefaultAdminService::isDefaultAdmin($member->Email);
    }

    public function canEdit($member = null): bool
    {
        return false;
    }

    public function canDelete($member = null): bool
    {
        return false;
    }

    public function canCreate($member = null): bool
    {
        return false;
    }
}
