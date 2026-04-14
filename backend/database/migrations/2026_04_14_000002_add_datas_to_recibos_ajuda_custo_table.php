<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('recibos_ajuda_custo')) {
            return;
        }
        Schema::table('recibos_ajuda_custo', function (Blueprint $table) {
            if (!Schema::hasColumn('recibos_ajuda_custo', 'data_pagamento')) {
                $table->date('data_pagamento')->nullable()->after('competencia');
            }
            if (!Schema::hasColumn('recibos_ajuda_custo', 'data_geracao')) {
                $table->timestamp('data_geracao')->nullable()->after('data_pagamento');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('recibos_ajuda_custo')) {
            return;
        }
        Schema::table('recibos_ajuda_custo', function (Blueprint $table) {
            if (Schema::hasColumn('recibos_ajuda_custo', 'data_geracao')) {
                $table->dropColumn('data_geracao');
            }
            if (Schema::hasColumn('recibos_ajuda_custo', 'data_pagamento')) {
                $table->dropColumn('data_pagamento');
            }
        });
    }
};

