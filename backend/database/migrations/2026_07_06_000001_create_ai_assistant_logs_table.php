<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_assistant_logs')) {
            Schema::create('ai_assistant_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('origem', 40)->default('openclaw');
                $table->string('comando', 120)->nullable();
                $table->string('acao', 80)->nullable();
                $table->json('payload')->nullable();
                $table->json('resposta')->nullable();
                $table->string('status', 20)->default('ok');
                $table->timestamps();

                $table->index(['origem', 'created_at']);
                $table->index('acao');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_logs');
    }
};
