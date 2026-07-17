<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dlv_produtos')) {
            return;
        }

        if (! Schema::hasColumn('dlv_produtos', 'estoque')) {
            Schema::table('dlv_produtos', function (Blueprint $table) {
                $table->unsignedInteger('estoque')->default(0)->after('preco');
            });
        }

        $indexNames = collect(Schema::getIndexes('dlv_produtos'))
            ->pluck('name')
            ->map(fn ($name) => strtolower((string) $name));

        $hasSkuIndex = $indexNames->contains(fn ($name) => str_contains($name, 'unidade_id_sku'));

        if (! $hasSkuIndex) {
            Schema::table('dlv_produtos', function (Blueprint $table) {
                $table->index(['unidade_id', 'sku'], 'dlv_produtos_unidade_id_sku_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('dlv_produtos')) {
            return;
        }

        $indexNames = collect(Schema::getIndexes('dlv_produtos'))
            ->pluck('name')
            ->map(fn ($name) => strtolower((string) $name));

        if ($indexNames->contains('dlv_produtos_unidade_id_sku_index')) {
            Schema::table('dlv_produtos', function (Blueprint $table) {
                $table->dropIndex('dlv_produtos_unidade_id_sku_index');
            });
        }

        if (Schema::hasColumn('dlv_produtos', 'estoque')) {
            Schema::table('dlv_produtos', function (Blueprint $table) {
                $table->dropColumn('estoque');
            });
        }
    }
};
