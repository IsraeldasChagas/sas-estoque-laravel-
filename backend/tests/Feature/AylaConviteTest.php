<?php

namespace Tests\Feature;

use App\Models\AylaConvite;
use App\Models\AylaUsuarioAutorizado;
use App\Services\Ayla\AylaConviteService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AylaConviteTest extends TestCase
{
    private const TELEFONE = '69984639070';

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchema();
        $this->seedAdmin();
        Config::set('ayla.bridge_token', 'BRIDGE_TEST_TOKEN');
        Config::set('ayla.telegram_bot_username', 'AylaSaborPsraenseBot');
        Config::set('ayla.convite_validade_horas', 24);
    }

    private function headersAdmin(): array
    {
        return ['X-Usuario-Id' => '1'];
    }

    private function headersBridge(): array
    {
        return ['Authorization' => 'Bearer BRIDGE_TEST_TOKEN'];
    }

    private function criarSchema(): void
    {
        Schema::dropIfExists('ayla_convites');
        Schema::dropIfExists('ayla_audit_logs');
        Schema::dropIfExists('ayla_usuarios_autorizados');
        Schema::dropIfExists('usuarios');

        Schema::create('usuarios', function ($t) {
            $t->id();
            $t->string('nome');
            $t->string('perfil', 40)->default('ADMIN');
            $t->boolean('ativo')->default(true);
        });

        Schema::create('ayla_usuarios_autorizados', function ($t) {
            $t->id();
            $t->unsignedBigInteger('usuario_id');
            $t->string('telegram_user_id', 60)->nullable();
            $t->string('telegram_username', 120)->nullable();
            $t->string('telegram_nome', 160)->nullable();
            $t->string('telefone_telegram', 20)->nullable();
            $t->timestamp('telegram_vinculado_em')->nullable();
            $t->timestamp('telegram_sincronizado_em')->nullable();
            $t->string('telegram_sync_status', 30)->nullable();
            $t->text('telegram_sync_erro')->nullable();
            $t->string('cargo', 120)->nullable();
            $t->json('unidades_permitidas')->nullable();
            $t->json('modulos_permitidos')->nullable();
            $t->boolean('pode_usar_texto')->default(true);
            $t->boolean('pode_usar_audio')->default(true);
            $t->boolean('pode_consultar_dados')->default(true);
            $t->boolean('pode_executar_acoes')->default(false);
            $t->string('status', 20)->default('pendente');
            $t->timestamp('ultimo_acesso_em')->nullable();
            $t->unsignedBigInteger('autorizado_por')->nullable();
            $t->timestamp('autorizado_em')->nullable();
            $t->text('observacoes')->nullable();
            $t->timestamps();
        });

        Schema::create('ayla_convites', function ($t) {
            $t->id();
            $t->unsignedBigInteger('ayla_usuario_autorizado_id');
            $t->unsignedBigInteger('usuario_id');
            $t->string('token_hash', 128);
            $t->string('status', 20)->default('pendente');
            $t->timestamp('expira_em');
            $t->timestamp('usado_em')->nullable();
            $t->timestamp('cancelado_em')->nullable();
            $t->string('telegram_user_id', 60)->nullable();
            $t->string('telegram_username', 120)->nullable();
            $t->string('telegram_nome', 160)->nullable();
            $t->string('telefone_telegram', 20)->nullable();
            $t->unsignedBigInteger('criado_por')->nullable();
            $t->timestamps();
        });

        Schema::create('ayla_audit_logs', function ($t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('acao', 120)->nullable();
            $t->string('status', 20)->nullable();
            $t->json('payload')->nullable();
            $t->json('resposta_resumo')->nullable();
            $t->timestamps();
        });
    }

    private function seedAdmin(): void
    {
        \DB::table('usuarios')->insert(['id' => 1, 'nome' => 'Admin', 'perfil' => 'ADMIN', 'ativo' => 1]);
        \DB::table('usuarios')->insert(['id' => 2, 'nome' => 'Gerente', 'perfil' => 'GERENTE', 'ativo' => 1]);
    }

    private function criarAcesso(array $over = []): AylaUsuarioAutorizado
    {
        return AylaUsuarioAutorizado::query()->create(array_merge([
            'usuario_id' => 2,
            'status' => 'pendente',
            'telefone_telegram' => self::TELEFONE,
            'modulos_permitidos' => ['dashboard'],
        ], $over));
    }

    public function test_salvar_acesso_com_telefone(): void
    {
        $this->withHeaders($this->headersAdmin())
            ->postJson('/api/ayla-admin/usuarios', [
                'usuario_id' => 2,
                'telefone_telegram' => '(69) 98463-9070',
                'status' => 'pendente',
                'modulos_permitidos' => ['dashboard'],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('ayla_usuarios_autorizados', [
            'usuario_id' => 2,
            'telefone_telegram' => self::TELEFONE,
        ]);
    }

    public function test_telefone_invalido_ao_gerar_convite(): void
    {
        $acesso = $this->criarAcesso(['telefone_telegram' => '123']);

        $this->withHeaders($this->headersAdmin())
            ->postJson("/api/ayla-admin/usuarios/{$acesso->id}/convite")
            ->assertStatus(422);
    }

    public function test_gerar_convite_retorna_url_sem_token_completo_no_log(): void
    {
        $acesso = $this->criarAcesso();

        $resp = $this->withHeaders($this->headersAdmin())
            ->postJson("/api/ayla-admin/usuarios/{$acesso->id}/convite", [
                'telefone_telegram' => self::TELEFONE,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $url = $resp->json('data.convite_url');
        $this->assertStringContainsString('t.me/AylaSaborPsraenseBot?start=', $url);
        $this->assertArrayNotHasKey('convite_token', $resp->json('data'));
    }

    public function test_vincular_via_bridge_preenche_telegram_id(): void
    {
        $acesso = $this->criarAcesso();
        $service = app(AylaConviteService::class);
        $gerado = $service->gerar($acesso, 1, self::TELEFONE);
        $token = $gerado['data']['convite_token'];

        $this->withHeaders($this->headersBridge())
            ->postJson('/api/ayla/v1/telegram/vincular', [
                'convite_token' => $token,
                'telegram_user_id' => '5431293656',
                'telegram_username' => 'iracema',
                'telegram_nome' => 'Iracema Miranda',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.telegram_user_id', '5431293656');

        $acesso->refresh();
        $this->assertSame('5431293656', $acesso->telegram_user_id);
        $this->assertSame('ativo', $acesso->status);
        $this->assertNotNull($acesso->telegram_vinculado_em);
    }

    public function test_convite_invalido_retorna_erro(): void
    {
        $this->withHeaders($this->headersBridge())
            ->postJson('/api/ayla/v1/telegram/vincular', [
                'convite_token' => 'token-invalido-xyz',
                'telegram_user_id' => '111',
            ])
            ->assertStatus(422);
    }

    public function test_convite_usado_nao_reutiliza(): void
    {
        $acesso = $this->criarAcesso();
        $service = app(AylaConviteService::class);
        $gerado = $service->gerar($acesso, 1, self::TELEFONE);
        $token = $gerado['data']['convite_token'];

        $this->withHeaders($this->headersBridge())
            ->postJson('/api/ayla/v1/telegram/vincular', [
                'convite_token' => $token,
                'telegram_user_id' => '111222333',
            ])
            ->assertStatus(200);

        $this->withHeaders($this->headersBridge())
            ->postJson('/api/ayla/v1/telegram/vincular', [
                'convite_token' => $token,
                'telegram_user_id' => '999888777',
            ])
            ->assertStatus(422);
    }

    public function test_cancelar_convite(): void
    {
        $acesso = $this->criarAcesso();
        app(AylaConviteService::class)->gerar($acesso, 1, self::TELEFONE);

        $this->withHeaders($this->headersAdmin())
            ->deleteJson("/api/ayla-admin/usuarios/{$acesso->id}/convite")
            ->assertStatus(200);

        $this->assertDatabaseMissing('ayla_convites', [
            'ayla_usuario_autorizado_id' => $acesso->id,
            'status' => AylaConvite::STATUS_PENDENTE,
        ]);
    }

    public function test_desvincular_remove_telegram_id(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);
        Config::set('ayla.vps_sync_url', 'https://vps.test');
        Config::set('ayla.vps_sync_token', 'sync-token');

        $acesso = $this->criarAcesso([
            'telegram_user_id' => '5431293656',
            'status' => 'ativo',
        ]);

        $this->withHeaders($this->headersAdmin())
            ->postJson("/api/ayla-admin/usuarios/{$acesso->id}/telegram/desvincular")
            ->assertStatus(200);

        $acesso->refresh();
        $this->assertNull($acesso->telegram_user_id);
        $this->assertSame('pendente', $acesso->status);
    }

    public function test_bridge_sem_token_retorna_401(): void
    {
        $this->postJson('/api/ayla/v1/telegram/vincular', [
            'convite_token' => 'x',
            'telegram_user_id' => '1',
        ])->assertStatus(401);
    }

    public function test_gerente_nao_gera_convite(): void
    {
        $acesso = $this->criarAcesso();
        $this->withHeaders(['X-Usuario-Id' => '2'])
            ->postJson("/api/ayla-admin/usuarios/{$acesso->id}/convite")
            ->assertStatus(403);
    }
}
