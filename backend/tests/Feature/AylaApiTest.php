<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Testes da API Ayla v1 focados em autenticação, envelope e validações,
 * sem depender de tabelas legadas (usa endpoints que falham/validam antes do banco).
 */
class AylaApiTest extends TestCase
{
    private function ativar(string $token = 'TOKEN_SECRETO_AYLA'): void
    {
        Config::set('ayla.enabled', true);
        Config::set('ayla.read_only', true);
        Config::set('ayla.token', $token);
        Config::set('ayla.rate_limit', 60);
        Config::set('ayla.allowed_units', []);
    }

    public function test_status_sem_token_retorna_401(): void
    {
        $this->ativar();

        $this->getJson('/api/ayla/v1/status')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('meta.code', 'UNAUTHORIZED');
    }

    public function test_status_com_token_incorreto_retorna_401(): void
    {
        $this->ativar();

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_ERRADO'])
            ->getJson('/api/ayla/v1/status')
            ->assertStatus(401)
            ->assertJsonPath('meta.code', 'UNAUTHORIZED');
    }

    public function test_status_com_integracao_desativada_retorna_503(): void
    {
        $this->ativar();
        Config::set('ayla.enabled', false);

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/status')
            ->assertStatus(503)
            ->assertJsonPath('meta.code', 'INTEGRATION_DISABLED');
    }

    public function test_status_com_token_correto_retorna_200(): void
    {
        $this->ativar();

        $resp = $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/status')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.read_only', true)
            ->assertJsonPath('meta.read_only', true);

        // O token nunca pode aparecer na resposta.
        $this->assertStringNotContainsString('TOKEN_SECRETO_AYLA', $resp->getContent());
    }

    public function test_limite_invalido_retorna_422(): void
    {
        $this->ativar();

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/produtos?limite=999')
            ->assertStatus(422)
            ->assertJsonPath('meta.code', 'VALIDATION_ERROR');
    }

    public function test_unidade_nao_autorizada_retorna_403(): void
    {
        $this->ativar();
        Config::set('ayla.allowed_units', [2]);

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/produtos?unidade_id=3&busca=arroz')
            ->assertStatus(403)
            ->assertJsonPath('meta.code', 'UNIT_NOT_ALLOWED');
    }

    public function test_nenhuma_rota_de_escrita_disponivel(): void
    {
        $this->ativar();

        $headers = ['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'];

        $this->withHeaders($headers)->postJson('/api/ayla/v1/status')->assertStatus(405);
        $this->withHeaders($headers)->putJson('/api/ayla/v1/status')->assertStatus(405);
        $this->withHeaders($headers)->deleteJson('/api/ayla/v1/status')->assertStatus(405);
        $this->withHeaders($headers)->postJson('/api/ayla/v1/kanban')->assertStatus(405);
    }

    public function test_kanban_com_token_correto_retorna_200(): void
    {
        $this->ativar();

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/kanban')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['tarefas', 'total', 'resumo'],
                'meta',
            ]);
    }

    public function test_kanban_status_atrasado_retorna_200(): void
    {
        $this->ativar();

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/kanban?status=atrasado')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.filtros_aplicados.status', 'atrasado');
    }

    public function test_kanban_responsavel_retorna_200(): void
    {
        $this->ativar();

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/kanban?responsavel=Thiago')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.filtros_aplicados.responsavel', 'Thiago');
    }

    public function test_kanban_limite_invalido_retorna_422(): void
    {
        $this->ativar();

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/kanban?limit=999')
            ->assertStatus(422)
            ->assertJsonPath('meta.code', 'VALIDATION_ERROR');
    }

    public function test_kanban_unidade_nao_autorizada_retorna_403(): void
    {
        $this->ativar();
        Config::set('ayla.allowed_units', [2]);

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/kanban?unidade_id=3')
            ->assertStatus(403)
            ->assertJsonPath('meta.code', 'UNIT_NOT_ALLOWED');
    }

    public function test_patrimonio_com_token_correto_retorna_200(): void
    {
        $this->ativar();

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/patrimonio')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['bens', 'total'],
                'meta' => ['acao', 'read_only', 'duracao_ms'],
            ]);
    }

    public function test_patrimonio_resumo_retorna_200(): void
    {
        $this->ativar();

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/patrimonio/resumo')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['total_bens', 'valor_total_aquisicao', 'por_unidade', 'por_categoria', 'alertas'],
            ]);
    }

    public function test_patrimonio_alertas_retorna_200(): void
    {
        $this->ativar();

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/patrimonio/alertas')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['garantia_vencida', 'manutencao_atrasada', 'bens_sem_responsavel', 'bens_sem_unidade'],
            ]);
    }

    public function test_patrimonio_limite_invalido_retorna_422(): void
    {
        $this->ativar();

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/patrimonio?limite=999')
            ->assertStatus(422)
            ->assertJsonPath('meta.code', 'VALIDATION_ERROR');
    }

    public function test_patrimonio_status_invalido_retorna_422(): void
    {
        $this->ativar();

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/patrimonio?status=xpto')
            ->assertStatus(422)
            ->assertJsonPath('meta.code', 'VALIDATION_ERROR');
    }

    public function test_patrimonio_valor_negativo_retorna_422(): void
    {
        $this->ativar();

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/patrimonio?valor_minimo=-5')
            ->assertStatus(422)
            ->assertJsonPath('meta.code', 'VALIDATION_ERROR');
    }

    public function test_patrimonio_unidade_nao_autorizada_retorna_403(): void
    {
        $this->ativar();
        Config::set('ayla.allowed_units', [2]);

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/patrimonio?unidade_id=3')
            ->assertStatus(403)
            ->assertJsonPath('meta.code', 'UNIT_NOT_ALLOWED');
    }

    public function test_patrimonio_inexistente_retorna_404(): void
    {
        $this->ativar();

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/patrimonio/99999999')
            ->assertStatus(404)
            ->assertJsonPath('meta.code', 'NOT_FOUND');
    }

    public function test_patrimonio_nao_expoe_token(): void
    {
        $this->ativar();

        $resp = $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/patrimonio/resumo')
            ->assertStatus(200);

        $this->assertStringNotContainsString('TOKEN_SECRETO_AYLA', $resp->getContent());
    }

    public function test_patrimonio_nenhuma_rota_de_escrita(): void
    {
        $this->ativar();
        $headers = ['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'];

        $this->withHeaders($headers)->postJson('/api/ayla/v1/patrimonio')->assertStatus(405);
        $this->withHeaders($headers)->putJson('/api/ayla/v1/patrimonio/1')->assertStatus(405);
        $this->withHeaders($headers)->deleteJson('/api/ayla/v1/patrimonio/1')->assertStatus(405);
    }

    public function test_reservas_com_token_correto_retorna_200(): void
    {
        $this->ativar();

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/reservas')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.read_only', true);
    }

    public function test_reservas_resumo_retorna_200(): void
    {
        $this->ativar();

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/reservas/resumo')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_reservas_alertas_retorna_200(): void
    {
        $this->ativar();

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/reservas/alertas')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_reservas_disponibilidade_exige_params(): void
    {
        $this->ativar();

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/reservas/disponibilidade')
            ->assertStatus(422)
            ->assertJsonPath('meta.code', 'VALIDATION_ERROR');
    }

    public function test_reservas_disponibilidade_horario_invalido(): void
    {
        $this->ativar();

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/reservas/disponibilidade?unidade_id=1&data=2026-07-14&horario=25:99')
            ->assertStatus(422)
            ->assertJsonPath('meta.code', 'VALIDATION_ERROR');
    }

    public function test_reservas_disponibilidade_data_invalida(): void
    {
        $this->ativar();

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/reservas/disponibilidade?unidade_id=1&data=14-07-2026&horario=20:00')
            ->assertStatus(422)
            ->assertJsonPath('meta.code', 'VALIDATION_ERROR');
    }

    public function test_reservas_limite_invalido_retorna_422(): void
    {
        $this->ativar();

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/reservas?limite=999')
            ->assertStatus(422)
            ->assertJsonPath('meta.code', 'VALIDATION_ERROR');
    }

    public function test_reservas_status_invalido_retorna_422(): void
    {
        $this->ativar();

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/reservas?status=xpto')
            ->assertStatus(422)
            ->assertJsonPath('meta.code', 'VALIDATION_ERROR');
    }

    public function test_reservas_unidade_nao_autorizada_retorna_403(): void
    {
        $this->ativar();
        Config::set('ayla.allowed_units', [2]);

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/reservas?unidade_id=3')
            ->assertStatus(403)
            ->assertJsonPath('meta.code', 'UNIT_NOT_ALLOWED');
    }

    public function test_reservas_inexistente_retorna_404(): void
    {
        $this->ativar();

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/reservas/99999999')
            ->assertStatus(404)
            ->assertJsonPath('meta.code', 'NOT_FOUND');
    }

    public function test_reservas_nao_expoe_token(): void
    {
        $this->ativar();

        $resp = $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/reservas/resumo')
            ->assertStatus(200);

        $this->assertStringNotContainsString('TOKEN_SECRETO_AYLA', $resp->getContent());
    }

    public function test_reservas_nenhuma_rota_de_escrita(): void
    {
        $this->ativar();
        $headers = ['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'];

        // Escrita direta bloqueada (exige preparar+confirmar).
        $this->withHeaders($headers)->postJson('/api/ayla/v1/reservas')->assertStatus(403)
            ->assertJsonPath('meta.code', 'CONFIRMATION_REQUIRED');
        $this->withHeaders($headers)->putJson('/api/ayla/v1/reservas/1')->assertStatus(403)
            ->assertJsonPath('meta.code', 'CONFIRMATION_REQUIRED');
        $this->withHeaders($headers)->patchJson('/api/ayla/v1/reservas/1/status')->assertStatus(403)
            ->assertJsonPath('meta.code', 'CONFIRMATION_REQUIRED');
        $this->withHeaders($headers)->deleteJson('/api/ayla/v1/reservas/1')->assertStatus(405);
    }

    public function test_reservas_preparar_sem_usuario_retorna_401(): void
    {
        $this->ativar();
        Config::set('ayla.read_only', false);

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->postJson('/api/ayla/v1/reservas/acoes/preparar', [
                'acao' => 'criar',
                'dados' => ['unidade_id' => 1, 'nome_cliente' => 'João'],
            ])
            ->assertStatus(401)
            ->assertJsonPath('meta.code', 'INVALID_USER');
    }

    public function test_reservas_preparar_em_somente_leitura_retorna_403(): void
    {
        $this->ativar();
        Config::set('ayla.read_only', true);

        $this->criarSchemaEscritaMinimo();

        $this->withHeaders([
            'Authorization' => 'Bearer TOKEN_SECRETO_AYLA',
            'X-Usuario-Id' => '1',
        ])
            ->postJson('/api/ayla/v1/reservas/acoes/preparar', [
                'acao' => 'criar',
                'dados' => [
                    'unidade_id' => 1,
                    'nome_cliente' => 'João',
                    'data_reserva' => now()->addDay()->toDateString(),
                    'hora_reserva' => '20:00',
                    'qtd_pessoas' => 4,
                ],
            ])
            ->assertStatus(403)
            ->assertJsonPath('meta.code', 'READ_ONLY');
    }

    public function test_reservas_fluxo_preparar_confirmar_e_cancelar(): void
    {
        $this->ativar();
        Config::set('ayla.read_only', false);
        $this->criarSchemaEscritaMinimo();

        $headers = [
            'Authorization' => 'Bearer TOKEN_SECRETO_AYLA',
            'X-Usuario-Id' => '1',
            'X-Telegram-User-Id' => '999001',
        ];

        $amanha = now()->addDay()->toDateString();

        $prep = $this->withHeaders($headers)
            ->postJson('/api/ayla/v1/reservas/acoes/preparar', [
                'acao' => 'criar',
                'dados' => [
                    'unidade_id' => 1,
                    'mesa_id' => 1,
                    'nome_cliente' => 'João Teste',
                    'telefone_cliente' => '91999998888',
                    'data_reserva' => $amanha,
                    'hora_reserva' => '20:00',
                    'qtd_pessoas' => 4,
                    'forcar_duplicidade' => true,
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $acaoId = (int) $prep->json('data.acao_id');
        $this->assertGreaterThan(0, $acaoId);
        $this->assertStringNotContainsString('91999998888', $prep->getContent());

        // Outro usuário não confirma.
        $this->withHeaders([
            'Authorization' => 'Bearer TOKEN_SECRETO_AYLA',
            'X-Usuario-Id' => '2',
            'X-Telegram-User-Id' => '999002',
        ])
            ->postJson("/api/ayla/v1/reservas/acoes/{$acaoId}/confirmar")
            ->assertStatus(403);

        $conf = $this->withHeaders($headers)
            ->postJson("/api/ayla/v1/reservas/acoes/{$acaoId}/confirmar")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($conf->json('data.resultado.reserva.id'));
        $this->assertDatabaseHas('reservas_mesas', ['nome_cliente' => 'João Teste', 'status' => 'pendente']);

        // Execução duplicada bloqueada.
        $this->withHeaders($headers)
            ->postJson("/api/ayla/v1/reservas/acoes/{$acaoId}/confirmar")
            ->assertStatus(422)
            ->assertJsonPath('meta.code', 'ALREADY_EXECUTED');

        // Nova ação e cancelamento.
        $prep2 = $this->withHeaders($headers)
            ->postJson('/api/ayla/v1/reservas/acoes/preparar', [
                'acao' => 'criar',
                'dados' => [
                    'unidade_id' => 1,
                    'mesa_id' => 2,
                    'nome_cliente' => 'Maria',
                    'data_reserva' => $amanha,
                    'hora_reserva' => '21:00',
                    'qtd_pessoas' => 2,
                    'forcar_duplicidade' => true,
                ],
            ])
            ->assertStatus(200);

        $acao2 = (int) $prep2->json('data.acao_id');
        $this->withHeaders($headers)
            ->postJson("/api/ayla/v1/reservas/acoes/{$acao2}/cancelar")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelada');

        $this->assertDatabaseMissing('reservas_mesas', ['nome_cliente' => 'Maria']);
    }

    public function test_reservas_acao_expirada(): void
    {
        $this->ativar();
        Config::set('ayla.read_only', false);
        $this->criarSchemaEscritaMinimo();

        $headers = [
            'Authorization' => 'Bearer TOKEN_SECRETO_AYLA',
            'X-Usuario-Id' => '1',
        ];

        $prep = $this->withHeaders($headers)
            ->postJson('/api/ayla/v1/reservas/acoes/preparar', [
                'acao' => 'criar',
                'dados' => [
                    'unidade_id' => 1,
                    'mesa_id' => 1,
                    'nome_cliente' => 'Expirado',
                    'data_reserva' => now()->addDay()->toDateString(),
                    'hora_reserva' => '19:00',
                    'qtd_pessoas' => 2,
                    'forcar_duplicidade' => true,
                ],
            ])
            ->assertStatus(200);

        $acaoId = (int) $prep->json('data.acao_id');
        \DB::table('ayla_acoes_pendentes')->where('id', $acaoId)->update([
            'expira_em' => now()->subMinute()->toDateTimeString(),
        ]);

        $this->withHeaders($headers)
            ->postJson("/api/ayla/v1/reservas/acoes/{$acaoId}/confirmar")
            ->assertStatus(422)
            ->assertJsonPath('meta.code', 'EXPIRED');
    }

    public function test_reservas_get_continua_funcionando_apos_escrita(): void
    {
        $this->ativar();

        $this->withHeaders(['Authorization' => 'Bearer TOKEN_SECRETO_AYLA'])
            ->getJson('/api/ayla/v1/reservas/resumo')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /** Schema mínimo em SQLite :memory: para testes de escrita. */
    private function criarSchemaEscritaMinimo(): void
    {
        \Illuminate\Support\Facades\Schema::dropIfExists('ayla_acoes_pendentes');
        \Illuminate\Support\Facades\Schema::dropIfExists('reserva_mesas');
        \Illuminate\Support\Facades\Schema::dropIfExists('reservas_mesas');
        \Illuminate\Support\Facades\Schema::dropIfExists('mesas');
        \Illuminate\Support\Facades\Schema::dropIfExists('ayla_usuarios_autorizados');
        \Illuminate\Support\Facades\Schema::dropIfExists('usuarios');
        \Illuminate\Support\Facades\Schema::dropIfExists('unidades');

        \Illuminate\Support\Facades\Schema::create('unidades', function ($t) {
            $t->id();
            $t->string('nome');
            $t->timestamps();
        });
        \Illuminate\Support\Facades\Schema::create('usuarios', function ($t) {
            $t->id();
            $t->string('nome');
            $t->string('perfil')->default('ADMIN');
            $t->boolean('ativo')->default(1);
            $t->unsignedBigInteger('unidade_id')->nullable();
            $t->text('permissoes_menu')->nullable();
            $t->timestamps();
        });
        \Illuminate\Support\Facades\Schema::create('mesas', function ($t) {
            $t->id();
            $t->unsignedBigInteger('unidade_id');
            $t->string('numero_mesa', 20);
            $t->string('nome_mesa')->nullable();
            $t->unsignedInteger('capacidade')->default(4);
            $t->unsignedInteger('capacidade_base')->nullable();
            $t->boolean('permite_cadeiras_extras')->default(0);
            $t->unsignedInteger('cadeiras_extras_max')->default(0);
            $t->unsignedInteger('capacidade_maxima')->nullable();
            $t->string('localizacao')->nullable();
            $t->boolean('pode_juntar')->default(0);
            $t->boolean('pode_separar')->default(0);
            $t->string('grupo_composicao', 100)->nullable();
            $t->string('status', 30)->default('livre');
            $t->text('observacao')->nullable();
            $t->boolean('ativo')->default(1);
            $t->boolean('cadastro_emergencial')->default(0);
            $t->boolean('cadastrado_pela_ayla')->default(0);
            $t->unsignedBigInteger('cadastrado_por_usuario_id')->nullable();
            $t->string('motivo_cadastro')->nullable();
            $t->timestamps();
        });
        \Illuminate\Support\Facades\Schema::create('reservas_mesas', function ($t) {
            $t->id();
            $t->unsignedBigInteger('unidade_id');
            $t->unsignedBigInteger('mesa_id');
            $t->unsignedBigInteger('usuario_id')->nullable();
            $t->string('nome_cliente');
            $t->string('telefone_cliente', 30)->nullable();
            $t->date('data_reserva');
            $t->time('hora_reserva');
            $t->unsignedInteger('qtd_pessoas')->default(1);
            $t->string('status', 30)->default('pendente');
            $t->text('observacao')->nullable();
            $t->string('local', 100)->nullable();
            $t->string('ocasiao')->nullable();
            $t->timestamps();
        });
        \Illuminate\Support\Facades\Schema::create('reserva_mesas', function ($t) {
            $t->id();
            $t->unsignedBigInteger('reserva_id');
            $t->unsignedBigInteger('mesa_id');
            $t->unsignedInteger('capacidade_utilizada')->default(0);
            $t->unsignedInteger('cadeiras_extras_utilizadas')->default(0);
            $t->boolean('principal')->default(0);
            $t->boolean('configuracao_emergencial')->default(0);
            $t->timestamps();
            $t->unique(['reserva_id', 'mesa_id']);
        });
        \Illuminate\Support\Facades\Schema::create('ayla_acoes_pendentes', function ($t) {
            $t->id();
            $t->unsignedBigInteger('usuario_id')->nullable();
            $t->string('telegram_user_id', 60)->nullable();
            $t->string('canal', 40)->nullable();
            $t->string('modulo', 60)->default('reservas');
            $t->string('acao', 60);
            $t->json('payload');
            $t->text('resumo')->nullable();
            $t->string('status', 20)->default('pendente');
            $t->timestamp('expira_em')->nullable();
            $t->timestamp('confirmado_em')->nullable();
            $t->timestamp('executado_em')->nullable();
            $t->json('resultado')->nullable();
            $t->timestamps();
        });
        \Illuminate\Support\Facades\Schema::create('ayla_usuarios_autorizados', function ($t) {
            $t->id();
            $t->unsignedBigInteger('usuario_id');
            $t->string('telegram_user_id', 60)->nullable();
            $t->boolean('pode_executar_acoes')->default(1);
            $t->string('status', 20)->default('ativo');
            $t->timestamps();
        });
        \Illuminate\Support\Facades\Schema::create('ayla_audit_logs', function ($t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('metodo', 10)->nullable();
            $t->string('rota')->nullable();
            $t->string('acao')->nullable();
            $t->json('payload')->nullable();
            $t->json('resposta_resumo')->nullable();
            $t->string('status', 20)->nullable();
            $t->unsignedInteger('http_status')->nullable();
            $t->unsignedInteger('duracao_ms')->nullable();
            $t->timestamps();
        });

        \DB::table('unidades')->insert(['id' => 1, 'nome' => 'Sabor Paraense 1', 'created_at' => now(), 'updated_at' => now()]);
        \DB::table('usuarios')->insert([
            ['id' => 1, 'nome' => 'Admin', 'perfil' => 'ADMIN', 'ativo' => 1, 'unidade_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'nome' => 'Outro', 'perfil' => 'GERENTE', 'ativo' => 1, 'unidade_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
        \DB::table('mesas')->insert([
            [
                'id' => 1, 'unidade_id' => 1, 'numero_mesa' => '8', 'nome_mesa' => 'Mesa 8',
                'capacidade' => 6, 'capacidade_base' => 6, 'capacidade_maxima' => 8,
                'permite_cadeiras_extras' => 1, 'cadeiras_extras_max' => 2,
                'pode_juntar' => 1, 'grupo_composicao' => 'salão',
                'status' => 'livre', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'id' => 2, 'unidade_id' => 1, 'numero_mesa' => '9', 'nome_mesa' => 'Mesa 9',
                'capacidade' => 4, 'capacidade_base' => 4, 'capacidade_maxima' => 4,
                'permite_cadeiras_extras' => 0, 'cadeiras_extras_max' => 0,
                'pode_juntar' => 1, 'grupo_composicao' => 'salão',
                'status' => 'livre', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'id' => 3, 'unidade_id' => 1, 'numero_mesa' => '10', 'nome_mesa' => 'Mesa 10',
                'capacidade' => 4, 'capacidade_base' => 4, 'capacidade_maxima' => 4,
                'permite_cadeiras_extras' => 0, 'cadeiras_extras_max' => 0,
                'pode_juntar' => 1, 'grupo_composicao' => 'salão',
                'status' => 'livre', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
        \DB::table('ayla_usuarios_autorizados')->insert([
            ['id' => 1, 'usuario_id' => 1, 'telegram_user_id' => '999001', 'pode_executar_acoes' => 1, 'status' => 'ativo', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'usuario_id' => 2, 'telegram_user_id' => '999002', 'pode_executar_acoes' => 1, 'status' => 'ativo', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_reservas_composicao_e_extras_com_confirmacao(): void
    {
        $this->ativar();
        Config::set('ayla.read_only', false);
        $this->criarSchemaEscritaMinimo();

        $headers = [
            'Authorization' => 'Bearer TOKEN_SECRETO_AYLA',
            'X-Usuario-Id' => '1',
            'X-Telegram-User-Id' => '999001',
        ];
        $amanha = now()->addDay()->toDateString();

        // Extras: 7 pessoas na mesa 8 (base 6 + 2 extras).
        $prepExtras = $this->withHeaders($headers)
            ->postJson('/api/ayla/v1/reservas/acoes/preparar', [
                'acao' => 'criar',
                'dados' => [
                    'unidade_id' => 1,
                    'mesa_id' => 1,
                    'nome_cliente' => 'Grupo Extras',
                    'data_reserva' => $amanha,
                    'hora_reserva' => '19:00',
                    'qtd_pessoas' => 7,
                    'forcar_duplicidade' => true,
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $acaoExtras = (int) $prepExtras->json('data.acao_id');
        $this->withHeaders($headers)
            ->postJson('/api/ayla/v1/reservas/acoes/'.$acaoExtras.'/confirmar')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('reservas_mesas', ['nome_cliente' => 'Grupo Extras', 'qtd_pessoas' => 7]);
        $this->assertDatabaseHas('reserva_mesas', ['mesa_id' => 1, 'cadeiras_extras_utilizadas' => 1, 'principal' => 1]);

        // Composição explícita: mesas 2+3 para 8 pessoas.
        $prepComp = $this->withHeaders($headers)
            ->postJson('/api/ayla/v1/reservas/acoes/preparar', [
                'acao' => 'preparar_composicao_mesas',
                'dados' => [
                    'unidade_id' => 1,
                    'nome_cliente' => 'Grupo Composto',
                    'data_reserva' => $amanha,
                    'hora_reserva' => '21:00',
                    'qtd_pessoas' => 8,
                    'forcar_duplicidade' => true,
                    'mesas' => [
                        ['mesa_id' => 2, 'principal' => true, 'capacidade_utilizada' => 4],
                        ['mesa_id' => 3, 'principal' => false, 'capacidade_utilizada' => 4],
                    ],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $acaoComp = (int) $prepComp->json('data.acao_id');
        $this->withHeaders($headers)
            ->postJson('/api/ayla/v1/reservas/acoes/'.$acaoComp.'/confirmar')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $reservaId = (int) \DB::table('reservas_mesas')->where('nome_cliente', 'Grupo Composto')->value('id');
        $this->assertGreaterThan(0, $reservaId);
        $this->assertSame(2, \DB::table('reserva_mesas')->where('reserva_id', $reservaId)->count());

        // Mesa emergencial: sempre com confirmação.
        $prepEm = $this->withHeaders($headers)
            ->postJson('/api/ayla/v1/reservas/acoes/preparar', [
                'acao' => 'criar_mesa_emergencial',
                'dados' => [
                    'unidade_id' => 1,
                    'numero_mesa' => 'E1',
                    'capacidade' => 10,
                    'motivo_cadastro' => 'Cliente grande sem mesa',
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $acaoEm = (int) $prepEm->json('data.acao_id');
        $this->withHeaders($headers)
            ->postJson('/api/ayla/v1/reservas/acoes/'.$acaoEm.'/confirmar')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('mesas', [
            'numero_mesa' => 'E1',
            'cadastro_emergencial' => 1,
            'cadastrado_pela_ayla' => 1,
        ]);
    }
}
