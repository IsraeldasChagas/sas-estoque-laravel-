<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dlv_pedidos') && ! Schema::hasColumn('dlv_pedidos', 'pagamento_checkout_url')) {
            Schema::table('dlv_pedidos', function (Blueprint $table) {
                $table->text('pagamento_checkout_url')->nullable()->after('pagamento_pix_payload');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dlv_pedidos') && Schema::hasColumn('dlv_pedidos', 'pagamento_checkout_url')) {
            Schema::table('dlv_pedidos', function (Blueprint $table) {
                $table->dropColumn('pagamento_checkout_url');
            });
        }
    }
};
