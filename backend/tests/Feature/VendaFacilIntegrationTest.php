<?php

namespace Tests\Feature;

use App\Models\Integration;
use App\Models\IntegrationLog;
use App\Models\IntegrationMapping;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VendaFacilIntegrationTest extends TestCase
{
    private const API_BASE = 'https://vendaffacil-test.invalid/api/v1';

    private const STATUS_URL = self::API_BASE.'/integration/status';

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaMinimo();
        $this->seedUsuarioAdmin();
        Config::set('integrations.ssrf_allow_private', false);
    }

    private function headersAdmin(): array
    {
        return ['X-Usuario-Id' => '1'];
    }

    private function headersGerente(): array
    {
        return ['X-Usuario-Id' => '2'];
    }

    private function criarSchemaMinimo(): void
    {
        Schema::dropIfExists('integration_mappings');
        Schema::dropIfExists('integration_logs');
        Schema::dropIfExists('integrations');
        Schema::dropIfExists('unidades');
        Schema::dropIfExists('usuarios');

        Schema::create('usuarios', function ($t) {
            $t->id();
            $t->string('nome')->nullable();
            $t->string('email')->nullable();
            $t->string('perfil', 40)->default('ADMIN');
            $t->boolean('ativo')->default(true);
        });

        Schema::create('unidades', function ($t) {
            $t->id();
            $t->string('nome');
        });

        Schema::create('integrations', function ($t) {
            $t->id();
            $t->string('provider', 80);
            $t->string('name', 150);
            $t->string('api_url', 500)->nullable();
            $t->text('bearer_token')->nullable();
            $t->enum('environment', ['production', 'homologation'])->default('homologation');
            $t->string('empresa_external_id', 120)->nullable();
            $t->string('empresa_external_name', 255)->nullable();
            $t->json('unidade_mappings')->nullable();
            $t->unsignedSmallInteger('timeout_seconds')->default(30);
            $t->unsignedTinyInteger('retry_count')->default(3);
            $t->text('webhook_secret')->nullable();
            $t->json('enabled_resources')->nullable();
            $t->enum('connection_status', ['offline', 'online', 'error'])->default('offline');
            $t->string('integration_status', 40)->default('not_configured');
            $t->timestamp('last_sync_at')->nullable();
            $t->timestamp('last_successful_connection_at')->nullable();
            $t->text('last_error')->nullable();
            $t->unsignedSmallInteger('consecutive_failures')->default(0);
            $t->unsignedInteger('last_response_time_ms')->nullable();
            $t->string('api_version', 40)->nullable();
            $t->boolean('is_active')->default(false);
            $t->unsignedBigInteger('empresa_id')->nullable();
            $t->unsignedBigInteger('unidade_id')->nullable();
            $t->json('config_json')->nullable();
            $t->text('observacoes')->nullable();
            $t->timestamps();
            $t->unique(['provider', 'empresa_id', 'unidade_id']);
        });

        Schema::create('integration_logs', function ($t) {
            $t->id();
            $t->unsignedBigInteger('integration_id')->nullable();
            $t->string('provider', 80);
            $t->string('operation', 80)->nullable();
            $t->enum('direction', ['inbound', 'outbound'])->default('outbound');
            $t->string('http_method', 12)->nullable();
            $t->string('endpoint', 500)->nullable();
            $t->unsignedInteger('response_time_ms')->nullable();
            $t->unsignedTinyInteger('attempt_number')->nullable();
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->enum('status', ['success', 'error', 'pending', 'skipped'])->default('pending');
            $t->text('message')->nullable();
            $t->unsignedBigInteger('empresa_id')->nullable();
            $t->unsignedBigInteger('unidade_id')->nullable();
            $t->unsignedBigInteger('usuario_id')->nullable();
            $t->string('ip', 45)->nullable();
            $t->json('request_payload')->nullable();
            $t->json('response_payload')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('integration_mappings', function ($t) {
            $t->id();
            $t->unsignedBigInteger('integration_id');
            $t->string('entity_type', 80);
            $t->string('local_id', 80);
            $t->string('external_id', 120);
            $t->unsignedBigInteger('unidade_id')->nullable();
            $t->json('meta_json')->nullable();
            $t->timestamps();
            $t->unique(['integration_id', 'entity_type', 'local_id']);
            $t->unique(['integration_id', 'entity_type', 'external_id']);
        });
    }

    private function seedUsuarioAdmin(): void
    {
        \DB::table('usuarios')->insert([
            ['id' => 1, 'nome' => 'Admin', 'email' => 'admin@test', 'perfil' => 'ADMIN', 'ativo' => 1],
            ['id' => 2, 'nome' => 'Gerente', 'email' => 'ger@test', 'perfil' => 'GERENTE', 'ativo' => 1],
        ]);
        \DB::table('unidades')->insert([
            ['id' => 10, 'nome' => 'Unidade Centro'],
        ]);
    }

    private function payloadConfig(array $over = []): array
    {
        return array_merge([
            'api_url' => self::API_BASE,
            'bearer_token' => 'vf-secret-token-12345',
            'environment' => 'homologation',
            'timeout_seconds' => 10,
            'retry_count' => 0,
            'is_active' => true,
        ], $over);
    }

    private function fakeStatusOk(): void
    {
        Http::fake([
            self::STATUS_URL => Http::response([
                'data' => [
                    'company' => ['id' => 'emp-99', 'name' => 'Empresa Teste VF'],
                    'environment' => 'homologation',
                    'api_version' => '1.2.0',
                ],
            ], 200),
        ]);
    }

    public function test_salvar_configuracao_criptografa_token_e_mascara_retorno(): void
    {
        $this->withHeaders($this->headersAdmin())
            ->putJson('/api/integracoes/vendafacil', $this->payloadConfig())
            ->assertStatus(200)
            ->assertJsonPath('ok', true);

        $integration = Integration::query()->where('provider', 'vendafacil')->first();
        $this->assertNotNull($integration);
        $this->assertSame('vf-secret-token-12345', $integration->bearer_token);
        $this->assertNotSame('vf-secret-token-12345', $integration->getAttributes()['bearer_token']);

        $show = $this->withHeaders($this->headersAdmin())
            ->getJson('/api/integracoes/vendafacil')
            ->assertStatus(200);

        $content = $show->getContent();
        $this->assertStringNotContainsString('vf-secret-token-12345', $content);
        $this->assertTrue($show->json('integration.bearer_token_configurado'));
        $this->assertStringContainsString('•', $show->json('integration.bearer_token_mascarado') ?? '');
    }

    public function test_token_mascarado_nao_sobrescreve_ao_salvar(): void
    {
        $this->withHeaders($this->headersAdmin())
            ->putJson('/api/integracoes/vendafacil', $this->payloadConfig());

        $masked = Integration::query()->where('provider', 'vendafacil')->first()->paraPainel()['bearer_token_mascarado'];

        $this->withHeaders($this->headersAdmin())
            ->putJson('/api/integracoes/vendafacil', [
                'bearer_token' => $masked,
                'api_url' => self::API_BASE,
                'is_active' => true,
            ])
            ->assertStatus(200);

        $integration = Integration::query()->where('provider', 'vendafacil')->first();
        $this->assertSame('vf-secret-token-12345', $integration->bearer_token);
    }

    public function test_teste_conexao_200_atualiza_status_e_empresa(): void
    {
        $this->withHeaders($this->headersAdmin())
            ->putJson('/api/integracoes/vendafacil', $this->payloadConfig());

        $this->fakeStatusOk();

        $res = $this->withHeaders($this->headersAdmin())
            ->postJson('/api/integracoes/vendafacil/testar')
            ->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('resultado.success', true);

        $this->assertSame('connected', $res->json('integration.integration_status'));
        $this->assertSame('emp-99', $res->json('integration.empresa_external_id'));
        $this->assertSame('Empresa Teste VF', $res->json('integration.empresa_external_name'));
        $this->assertSame('1.2.0', $res->json('integration.api_version'));
    }

    public function test_token_invalido_401(): void
    {
        $this->withHeaders($this->headersAdmin())
            ->putJson('/api/integracoes/vendafacil', $this->payloadConfig());

        Http::fake([
            self::STATUS_URL => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->withHeaders($this->headersAdmin())
            ->postJson('/api/integracoes/vendafacil/testar')
            ->assertStatus(422)
            ->assertJsonPath('resultado.integration_status', 'authentication_error')
            ->assertJsonPath('resultado.message', 'Token inválido ou expirado.');
    }

    public function test_endpoint_inexistente_404(): void
    {
        $this->withHeaders($this->headersAdmin())
            ->putJson('/api/integracoes/vendafacil', $this->payloadConfig());

        Http::fake([
            self::STATUS_URL => Http::response([], 404),
        ]);

        $this->withHeaders($this->headersAdmin())
            ->postJson('/api/integracoes/vendafacil/testar')
            ->assertStatus(422)
            ->assertJsonPath('resultado.error.code', 'NOT_FOUND');
    }

    public function test_json_invalido(): void
    {
        $this->withHeaders($this->headersAdmin())
            ->putJson('/api/integracoes/vendafacil', $this->payloadConfig());

        Http::fake([
            self::STATUS_URL => Http::response('not-json', 200, ['Content-Type' => 'application/json']),
        ]);

        $this->withHeaders($this->headersAdmin())
            ->postJson('/api/integracoes/vendafacil/testar')
            ->assertStatus(422)
            ->assertJsonPath('resultado.error.code', 'INVALID_JSON');
    }

    public function test_integracao_desativada_nao_testa(): void
    {
        $this->withHeaders($this->headersAdmin())
            ->putJson('/api/integracoes/vendafacil', $this->payloadConfig(['is_active' => false]));

        Http::fake();

        $this->withHeaders($this->headersAdmin())
            ->postJson('/api/integracoes/vendafacil/testar')
            ->assertStatus(422)
            ->assertJsonPath('resultado.integration_status', 'disabled');

        Http::assertNothingSent();
    }

    public function test_usuario_sem_permissao_configurar(): void
    {
        $this->withHeaders($this->headersGerente())
            ->putJson('/api/integracoes/vendafacil', $this->payloadConfig())
            ->assertStatus(403);
    }

    public function test_gerente_pode_visualizar(): void
    {
        $this->withHeaders($this->headersGerente())
            ->getJson('/api/integracoes/vendafacil')
            ->assertStatus(200);
    }

    public function test_desconexao_remove_token_preserva_logs(): void
    {
        $this->withHeaders($this->headersAdmin())
            ->putJson('/api/integracoes/vendafacil', $this->payloadConfig());

        $this->fakeStatusOk();

        $this->withHeaders($this->headersAdmin())
            ->postJson('/api/integracoes/vendafacil/testar');

        $this->assertGreaterThan(0, IntegrationLog::query()->count());

        $this->withHeaders($this->headersAdmin())
            ->postJson('/api/integracoes/vendafacil/desconectar', ['clear_mappings' => false])
            ->assertStatus(200)
            ->assertJsonPath('integration.integration_status', 'disconnected');

        $integration = Integration::query()->where('provider', 'vendafacil')->first();
        $this->assertNull($integration->bearer_token);
        $this->assertFalse($integration->is_active);
        $this->assertGreaterThan(0, IntegrationLog::query()->count());
    }

    public function test_logs_nao_contem_token(): void
    {
        $this->withHeaders($this->headersAdmin())
            ->putJson('/api/integracoes/vendafacil', $this->payloadConfig());

        $this->fakeStatusOk();

        $this->withHeaders($this->headersAdmin())
            ->postJson('/api/integracoes/vendafacil/testar');

        $log = IntegrationLog::query()->first();
        $this->assertNotNull($log);
        $payload = json_encode($log->toArray());
        $this->assertStringNotContainsString('vf-secret-token-12345', $payload);
        $this->assertStringNotContainsString('Authorization', $payload);
    }

    public function test_mapeamento_unidade_manual(): void
    {
        $this->withHeaders($this->headersAdmin())
            ->putJson('/api/integracoes/vendafacil', $this->payloadConfig());

        $this->withHeaders($this->headersAdmin())
            ->putJson('/api/integracoes/vendafacil/unidades', [
                'mappings' => [
                    [
                        'local_id' => '10',
                        'external_id' => 'vf-unit-1',
                        'external_name' => 'Loja Centro VF',
                        'is_primary' => true,
                        'is_active' => true,
                    ],
                ],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('integration_mappings', [
            'entity_type' => 'unit',
            'local_id' => '10',
            'external_id' => 'vf-unit-1',
        ]);

        $integration = Integration::query()->where('provider', 'vendafacil')->first();
        $this->assertSame('vf-unit-1', $integration->unidade_mappings['10']['external_id'] ?? null);
    }

    public function test_api_offline_connection_error(): void
    {
        $this->withHeaders($this->headersAdmin())
            ->putJson('/api/integracoes/vendafacil', $this->payloadConfig(['retry_count' => 0]));

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $this->withHeaders($this->headersAdmin())
            ->postJson('/api/integracoes/vendafacil/testar')
            ->assertStatus(422)
            ->assertJsonPath('resultado.error.code', 'CONNECTION_REFUSED');
    }

    public function test_health_check_endpoint(): void
    {
        $this->withHeaders($this->headersAdmin())
            ->putJson('/api/integracoes/vendafacil', $this->payloadConfig());

        $this->fakeStatusOk();

        $this->withHeaders($this->headersAdmin())
            ->getJson('/api/integracoes/health-check')
            ->assertStatus(200)
            ->assertJsonPath('fase', 2)
            ->assertJsonStructure(['providers', 'resumo']);
    }
}
