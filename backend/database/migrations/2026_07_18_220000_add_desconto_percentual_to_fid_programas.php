<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fid_programas')) {
            return;
        }

        Schema::table('fid_programas', function (Blueprint $table) {
            if (! Schema::hasColumn('fid_programas', 'desconto_percentual')) {
                $table->decimal('desconto_percentual', 5, 2)->nullable()->after('valor_desconto');
            }
            if (! Schema::hasColumn('fid_programas', 'base_desconto_percentual')) {
                $table->string('base_desconto_percentual', 40)->nullable()->after('desconto_percentual');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fid_programas')) {
            return;
        }

        Schema::table('fid_programas', function (Blueprint $table) {
            $cols = [];
            foreach (['desconto_percentual', 'base_desconto_percentual'] as $col) {
                if (Schema::hasColumn('fid_programas', $col)) {
                    $cols[] = $col;
                }
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
