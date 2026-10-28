<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ComprasRapidasApiTest extends TestCase
{
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['compras_rapidas_itens', 'compras_rapidas', 'produtos', 'unidades', 'usuarios'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('usuarios', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
            $t->string('perfil');
            $t->boolean('ativo')->default(true);
            $t->unsignedBigInteger('unidade_id')->nullable();
            $t->text('permissoes_menu')->nullable();
        });
        Schema::create('unidades', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
            $t->boolean('ativo')->default(true);
        });
        Schema::create('produtos', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
            $t->string('unidade_base')->nullable();
            $t->boolean('ativo')->default(true);
        });
        (require database_path('migrations/2026_10_28_140000_create_compras_rapidas_tables.php'))->up();

        DB::table('unidades')->insert([['id' => 1, 'nome' => 'Loja Centro'], ['id' => 2, 'nome' => 'Loja Norte']]);
        DB::table('usuarios')->insert([
            ['id' => 1, 'nome' => 'Admin', 'perfil' => 'ADMIN', 'unidade_id' => null],
            ['id' => 2, 'nome' => 'Cozinheira', 'perfil' => 'COZINHA', 'unidade_id' => 1],
            ['id' => 3, 'nome' => 'Estoquista Norte', 'perfil' => 'ESTOQUISTA', 'unidade_id' => 2],
            ['id' => 4, 'nome' => 'Caixa', 'perfil' => 'CAIXA', 'unidade_id' => 1],
        ]);
        DB::table('produtos')->insert(['id' => 10, 'nome' => 'Tomate', 'unidade_base' => 'KG']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path('uploads/unidades-teste-tmp'));
        foreach (glob(public_path('uploads/unidades/unidade-1-*')) ?: [] as $f) {
            @unlink($f);
        }
        foreach (['compras_rapidas_itens', 'compras_rapidas', 'produtos', 'unidades', 'usuarios'] as $t) {
            Schema::dropIfExists($t);
        }
        parent::tearDown();
    }

    private function api(string $method, string $uri, int $uid, array $body = [])
    {
        return $this->json($method, '/api/compras-rapidas' . $uri, $body, ['X-Usuario-Id' => (string) $uid]);
    }

    public function test_criar_editar_concluir_e_excluir_lista(): void
    {
        $this->api('GET', '/meta', 2)->assertOk()
            ->assertJsonCount(1, 'unidades')
            ->assertJsonPath('produtos.0.nome', 'Tomate');
        $this->api('GET', '/meta', 4)->assertStatus(403);

        $this->api('POST', '', 2, ['nome' => 'Feira', 'itens' => []])->assertStatus(422);
        $this->api('POST', '', 2, ['nome' => '', 'itens' => [['produto_nome' => 'Tomate', 'quantidade' => 1]]])->assertStatus(422);
        $this->api('POST', '', 2, ['nome' => 'Feira', 'unidade_id' => 2, 'itens' => [['produto_nome' => 'Tomate', 'quantidade' => 1]]])->assertStatus(403);

        $lista = $this->api('POST', '', 2, [
            'nome' => 'Feira de sábado',
            'itens' => [
                ['produto_id' => 10, 'produto_nome' => 'Tomate', 'quantidade' => '2,5', 'tipo' => 'KG', 'observacao' => 'Bem vermelho'],
                ['produto_nome' => 'Sabão em pó', 'quantidade' => 3, 'tipo' => 'xyz'],
            ],
        ])->assertOk()->json('lista');
        $this->assertSame(1, $lista['unidade_id']);
        $this->assertSame('Cozinheira', $lista['usuario_nome']);
        $this->assertSame(2.5, $lista['itens'][0]['quantidade']);
        $this->assertSame('UN', $lista['itens'][1]['tipo']);

        $this->api('GET', '', 3)->assertOk()->assertJsonCount(0);
        $this->api('PUT', "/{$lista['id']}", 3, ['status' => 'feita'])->assertStatus(403);

        $this->api('PUT', "/{$lista['id']}", 2, ['nome' => 'Feira', 'itens' => [['produto_nome' => 'Alface', 'quantidade' => 4, 'tipo' => 'UN']]])
            ->assertOk()->assertJsonCount(1, 'lista.itens')->assertJsonPath('lista.itens.0.produto_nome', 'Alface');
        $this->api('PUT', "/{$lista['id']}", 2, ['status' => 'feita'])->assertOk()->assertJsonPath('lista.status', 'feita');
        $this->api('GET', '?status=aberta', 2)->assertJsonCount(0);
        $this->api('GET', '?status=feita', 1)->assertJsonCount(1)->assertJsonPath('0.itens.0.produto_nome', 'Alface');

        $this->api('DELETE', "/{$lista['id']}", 1)->assertOk();
        $this->assertSame(0, DB::table('compras_rapidas_itens')->count());
    }

    public function test_logo_da_unidade(): void
    {
        $this->api('GET', '/logo?unidade_id=1', 2)->assertOk()->assertJsonPath('data_url', null);

        $png = UploadedFile::fake()->createWithContent('logo.png', base64_decode(self::PNG_1X1));
        $this->withHeaders(['X-Usuario-Id' => '2'])->post('/api/unidades/1/logo', ['logo' => $png])->assertStatus(403);

        $png = UploadedFile::fake()->createWithContent('logo.png', base64_decode(self::PNG_1X1));
        $path = $this->withHeaders(['X-Usuario-Id' => '1'])->post('/api/unidades/1/logo', ['logo' => $png])->assertOk()->json('logo');
        $this->assertStringStartsWith('uploads/unidades/unidade-1-', $path);
        $this->assertStringStartsWith('data:image/png;base64,', $this->api('GET', '/logo?unidade_id=1', 2)->json('data_url'));

        $this->withHeaders(['X-Usuario-Id' => '1'])->deleteJson('/api/unidades/1/logo')->assertOk();
        $this->assertNull(DB::table('unidades')->where('id', 1)->value('logo'));
        $this->assertFileDoesNotExist(public_path($path));
    }
}
