<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('integrations')) {
            Schema::create('integrations', function (Blueprint $table) {
                $table->id();
                $table->string('provider', 80);
                $table->string('name', 150);
                $table->string('api_url', 500)->nullable();
                $table->text('bearer_token')->nullable();
                $table->enum('environment', ['production', 'homologation'])->default('homologation');
                $table->string('empresa_external_id', 120)->nullable();
                $table->json('unidade_mappings')->nullable();
                $table->unsignedSmallInteger('timeout_seconds')->default(30);
                $table->unsignedTinyInteger('retry_count')->default(3);
                $table->text('webhook_secret')->nullable();
                $table->json('enabled_resources')->nullable();
                $table->enum('connection_status', ['offline', 'online', 'error'])->default('offline');
                $table->timestamp('last_sync_at')->nullable();
                $table->text('last_error')->nullable();
                $table->unsignedInteger('last_response_time_ms')->nullable();
                $table->string('api_version', 40)->nullable();
                $table->boolean('is_active')->default(false);
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->unsignedBigInteger('unidade_id')->nullable();
                $table->json('config_json')->nullable();
                $table->timestamps();

                $table->unique(['provider', 'empresa_id', 'unidade_id'], 'integrations_provider_tenant_unique');
                $table->index('provider');
                $table->index('connection_status');
            });
        }

        if (! Schema::hasTable('integration_logs')) {
            Schema::create('integration_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('integration_id')->nullable();
                $table->string('provider', 80);
                $table->enum('direction', ['inbound', 'outbound'])->default('outbound');
                $table->string('http_method', 12)->nullable();
                $table->string('endpoint', 500)->nullable();
                $table->unsignedInteger('response_time_ms')->nullable();
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->enum('status', ['success', 'error', 'pending', 'skipped'])->default('pending');
                $table->text('message')->nullable();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->unsignedBigInteger('unidade_id')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->json('request_payload')->nullable();
                $table->json('response_payload')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['provider', 'created_at']);
                $table->index(['integration_id', 'created_at']);
                $table->index('status');
            });
        }

        if (! Schema::hasTable('integration_mappings')) {
            Schema::create('integration_mappings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('integration_id');
                $table->string('entity_type', 80);
                $table->string('local_id', 80);
                $table->string('external_id', 120);
                $table->uuid('external_uuid')->nullable();
                $table->unsignedBigInteger('unidade_id')->nullable();
                $table->json('meta_json')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();

                $table->unique(['integration_id', 'entity_type', 'local_id'], 'integration_mappings_local_unique');
                $table->unique(['integration_id', 'entity_type', 'external_id'], 'integration_mappings_external_unique');
                $table->index(['entity_type', 'unidade_id']);
            });
        }

        if (! Schema::hasTable('integration_webhooks')) {
            Schema::create('integration_webhooks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('integration_id');
                $table->string('event_type', 120);
                $table->string('url_path', 255)->nullable();
                $table->text('secret')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_received_at')->nullable();
                $table->timestamps();

                $table->index(['integration_id', 'event_type']);
                $table->index('is_active');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_webhooks');
        Schema::dropIfExists('integration_mappings');
        Schema::dropIfExists('integration_logs');
        Schema::dropIfExists('integrations');
    }
};
