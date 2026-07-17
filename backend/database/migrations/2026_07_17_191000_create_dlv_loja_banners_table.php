<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dlv_loja_banners')) {
            Schema::create('dlv_loja_banners', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id')->index();
                $table->unsignedBigInteger('loja_config_id')->index();
                $table->string('caminho', 255);
                $table->unsignedInteger('ordem')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dlv_loja_config') || ! Schema::hasColumn('dlv_loja_config', 'banner_path')) {
            return;
        }

        $configs = DB::table('dlv_loja_config')
            ->whereNotNull('banner_path')
            ->where('banner_path', '!=', '')
            ->get(['id', 'unidade_id', 'banner_path']);

        foreach ($configs as $config) {
            $exists = DB::table('dlv_loja_banners')
                ->where('loja_config_id', $config->id)
                ->where('caminho', $config->banner_path)
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('dlv_loja_banners')->insert([
                'unidade_id' => $config->unidade_id,
                'loja_config_id' => $config->id,
                'caminho' => $config->banner_path,
                'ordem' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dlv_loja_banners');
    }
};
