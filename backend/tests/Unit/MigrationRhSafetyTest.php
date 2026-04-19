<?php

namespace Tests\Unit;

use App\Support\MigrationRhSafetyChecker;
use PHPUnit\Framework\TestCase;

/**
 * Garante que nenhuma migration nova volte a apagar dados de RH em `funcionarios`.
 */
class MigrationRhSafetyTest extends TestCase
{
    public function test_migrations_do_not_destroy_funcionarios_data_in_up(): void
    {
        $backendRoot = dirname(__DIR__, 2);
        $violations = MigrationRhSafetyChecker::scan($backendRoot);
        $this->assertSame(
            [],
            $violations,
            "Migrations perigosas para `funcionarios`:\n" . implode("\n", $violations),
        );
    }
}
