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
}
