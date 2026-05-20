# Database Migrations for SilverStripe

## Overview

This module provides an automatic migration system for SilverStripe that ensures your BuildTasks run exactly once (or on every update) across all environments without manual intervention.

**Problem it solves:** Remembering to run upgrade tasks on every server instance (dev, staging, production) is error-prone. This module automatically tracks and runs migrations during `dev/build`.

## Features

- **Automatic Execution**: Migrations run automatically during `dev/build`
- **Run Once or On Update**: Choose whether tasks run once ever, or re-run when the file changes
- **Tracking**: Records all migration attempts with success/failure status
- **Admin Interface**: View migration history in the CMS at `/admin/atomic-migration-admin`, but only when you are logged-in as default admin.
- **File Hash Tracking**: Detects when migration code changes and can re-run if needed
- **Error Handling**: Captures and stores error messages for failed migrations

## Installation

```bash
composer require sunnysideup/database-migrations
```

Then run:
```bash
sake dev/build flush=1
```

## Usage

There are two ways to register a BuildTask as an atomic migration:

### Option 1: Implement the Interface (Recommended)

This is best for code you control. Implement `AtomicMigrationInterface` in your BuildTask:

```php
<?php

use SilverStripe\Dev\BuildTask;
use Sunnysideup\DatabaseMigrations\Interfaces\AtomicMigrationInterface;

class MyUpgradeTask extends BuildTask implements AtomicMigrationInterface
{
    private static $segment = 'my-upgrade-task';
    
    protected $title = 'My Upgrade Task';
    
    protected $description = 'Migrates old data to new format';
    
    public function run($request)
    {
        // Your migration code here
        echo "Running migration...\n";
        
        // Example: Update all products
        $products = Product::get();
        foreach ($products as $product) {
            $product->NewField = $product->OldField;
            $product->write();
        }
        
        echo "Migration complete!\n";
    }
    
    /**
     * Return true if this task should run again after a failure
     * Return false if it should only run once (even if it failed)
     */
    public function canRunAgainOnFailure(): bool
    {
        // Return true if safe to retry on failure
        return true;
        
        // Return false if the task made partial changes
        // and re-running could cause data corruption
        // return false;
    }
}
```

### Option 2: Add to Configuration (For Third-Party Tasks)

This is useful for registering BuildTasks from modules you don't control:

Create or edit `app/_config/database-migrations.yml`:

```yaml
---
Name: app-database-migrations
After: 
  - '#database-migrations'
---
Sunnysideup\DatabaseMigrations\Api\AtomicMigrationApi:
  also_run:
    - Vendor\Module\Tasks\SomeThirdPartyTask
    - AnotherVendor\AnotherModule\Tasks\AnotherTask
```

**Note:** Tasks added via configuration don't support the `canRunAgainOnFailure()` check, so they will always attempt to re-run if they failed.

## How It Works

1. During `dev/build`, the module finds all classes implementing `AtomicMigrationInterface` (plus any configured in `also_run`)
2. For each task, it checks if it has been run successfully with the current file hash
3. If not, it creates an attempt record and runs the task
4. The attempt is marked as successful or failed (with error message)
5. Next time `dev/build` runs, successful migrations are skipped
6. Failed migrations can re-run if `canRunAgainOnFailure()` returns `true`

### File Hash Tracking

The module calculates an MD5 hash of your migration task file. If you update the task code:
- The hash changes
- The migration will run again (even if it succeeded before)
- This is useful for migrations that need to run multiple times as you refine them

## Viewing Migration Status

Visit `/admin/atomic-migration-admin` in your CMS to view:

- **Migration Tasks**: All registered migrations and their status
  - Status: Pending, Success, Failed, or Outdated
  - Number of attempts
  - Current file hash
  
- **Migration Attempts**: Detailed history of each run
  - Success/failure status
  - Error messages for failures
  - Whether the attempt used the current code version
  - Timestamp of each attempt

## Best Practices

### 1. Make Migrations Idempotent

Write migrations that can safely run multiple times:

```php
// GOOD: Check before creating
public function run($request)
{
    $page = HomePage::get()->first();
    if (!$page) {
        $page = HomePage::create();
        $page->Title = 'Home';
        $page->write();
        $page->publishSingle();
    }
}

// BAD: Always creates, could cause duplicates
public function run($request)
{
    $page = HomePage::create();
    $page->Title = 'Home';
    $page->write();
}
```

### 2. Use Descriptive Names

```php
// GOOD
class MigrateProductPricesToNewCurrencyFormat extends BuildTask

// BAD
class UpdateProducts extends BuildTask
```

### 3. Handle Failures Gracefully

```php
public function run($request)
{
    $products = Product::get();
    $count = 0;
    $errors = 0;
    
    foreach ($products as $product) {
        try {
            // Migration logic
            $product->NewField = $this->transformData($product->OldField);
            $product->write();
            $count++;
        } catch (Exception $e) {
            $errors++;
            echo "Error migrating product {$product->ID}: {$e->getMessage()}\n";
        }
    }
    
    echo "Migrated {$count} products with {$errors} errors\n";
    
    // Throw exception if there were errors so the attempt is marked as failed
    if ($errors > 0) {
        throw new Exception("Migration completed with {$errors} errors");
    }
}
```

### 4. Consider Retry Logic

```php
public function canRunAgainOnFailure(): bool
{
    // If migration is read-only (e.g., data export), safe to retry
    // return true;
    
    // If migration modifies data, might not be safe to retry
    // return false;
    
    // Middle ground: Check if migration is partially complete
    $complete = Product::get()->filter('NewField:not', null)->count();
    $total = Product::get()->count();
    
    // If less than 50% done, safe to retry
    return $complete < ($total * 0.5);
}
```

## Troubleshooting

### Migration Not Running

1. Check it implements `AtomicMigrationInterface` or is in the `also_run` config
2. Run `sake dev/build flush=1` to clear caches
3. Check the admin interface to see if it's been marked as successful already

### Migration Keeps Running

If the file hash keeps changing:
- Ensure you're not using dynamic code that changes on every execution
- Check your editor isn't modifying line endings or whitespace

### Migration Failed

1. Visit `/admin/atomic-migration-admin`
2. Click on "Migration Attempts"
3. Find the failed attempt and view the error message
4. Fix the issue in your task code
5. If `canRunAgainOnFailure()` returns `true`, just run `dev/build` again
6. If it returns `false`, you may need to manually delete the failed attempt record

## Advanced Configuration

### Disable Automatic Running

If you want to manually control when migrations run:

```php
<?php

use Sunnysideup\DatabaseMigrations\Model\AtomicMigrationModel;

class DisableAutoMigrations
{
    public function onBeforeBuild()
    {
        // Temporarily disable by overriding the method
        AtomicMigrationModel::config()->set('run_on_build', false);
    }
}
```

### Custom Admin Permissions

The admin interface is restricted to default admins by default. To customize:

```php
<?php

namespace App\Admin;

use Sunnysideup\DatabaseMigrations\Admin\AtomicMigrationAdmin as BaseAdmin;

class AtomicMigrationAdmin extends BaseAdmin
{
    public function canView($member = null): bool
    {
        return Permission::check('ADMIN', 'any', $member);
    }
}
```

## API Reference

### AtomicMigrationInterface

```php
interface AtomicMigrationInterface
{
    /**
     * Return true if this task should run again after a previous failure
     * Return false if it should only run once (even if it failed)
     */
    public function canRunAgainOnFailure(): bool;
}
```

### Helpful Methods

Available on `AtomicMigrationModel`:

```php
$model->getHasRun(): bool
$model->getHasRunSuccessfully(): bool  
$model->getHasRunWithCurrentClassConfiguration(): bool
$model->getHasRunSuccessfullyWithCurrentClassConfiguration(): bool
$model->getNumberOfAttempts(): int
$model->getLastAttempt(): ?AtomicMigrationModelAttempt
$model->getStatus(): string // 'Pending', 'Success', 'Failed', or 'Outdated'
$model->getStatusMessage(): string // Human-readable status
$model->getCurrentHash(): string
```

## Support

For issues, please visit: https://github.com/sunnysideup/silverstripe-database-migrations/issues
