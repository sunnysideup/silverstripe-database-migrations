<?php

namespace Sunnysideup\DatabaseMigrations\Tasks;

use Catalyst\HealthNavigator\Model\SidebarResource;
use Catalyst\HealthNavigator\Pages\IndexPage;
use Catalyst\Starter\PageType\LandingPage;
use SilverStripe\Assets\Image;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;
use Sunnysideup\DatabaseMigrations\Api\AtomicMigrationApi;

class RunAtomicMigrations extends BuildTask
{
    private static $segment = 'run-atomic-migrations';

    protected $title = 'Run Atomic Migrations';

    protected $description = 'Runs all atomic migrations.';

    private static bool $dry_run_only = false;

    public function run($request)
    {
        $dryRunOnly = $request->getVar('dryrunonly') || $this->config()->get('dry_run_only');
        DB::alteration_message('Running atomic migrations', 'created');
        AtomicMigrationApi::inst()->run($dryRunOnly);
        DB::alteration_message('Finished running atomic migrations', 'created');
    }
}
