<?php

namespace Sunnysideup\DatabaseMigrations\Interfaces;

interface AtomicMigrationInterface
{
    public function CanRunAgainOnFailure(): bool;
}
