<?php

/**
 * Prepara estoque + venda balcão + tenta emitir NFC-e.
 * Uso: php artisan app:emitir-nota-teste {unidade_id?}
 */

namespace App\Console\Commands;

use App\Services\Fiscal\FiscalDocumentoService;
use App\Services\Fiscal\FiscalEmissaoService;
use App\Support\CardapioEstoqueSupport;
use App\Support\PdvComercialSupport;
use App\Support\ProducaoEstoqueSupport;
use App\Support\VendaFiscalSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmitirNotaTesteCommand extends Command
{
    protected $signature = 'app:emitir-nota-teste {unidade_id=26} {--usuario=1}';

    protected $description = 'Cria/abastece item, faz venda PDV e tenta emitir NFC-e (homolog/prod conforme config)';

    public function handle(): int
    {
        $unidadeId = (int) $this->argument('unidade_id');
        $usuarioId = (int) $this->option('usuario');

        $unidade = DB::table('unidades')->where('id', $unidadeId)->first();
        if (! $unidade) {
            $this->error("Unidade #{$unidadeId} não encontrada.");

            return 1;
        }
        $this->info("Unidade: #{$unidade->id} {$unidade->nome}");

        $empresaId = VendaFiscalSupport::resolverEmpresaUnidade($unidadeId);
        $this->info('Empresa vinculada: ' . ($empresaId ?: 'NENHUMA'));

        if ($empresaId && Schema::hasTable('fiscal_emissao_configs')) {
            $cfg = DB::table('fiscal_emissao_configs')->where('empresa_id', $empresaId)->first();
            if (! $cfg) {
                $this->warn('Sem fiscal_emissao_configs para esta empresa — emissão será pulada.');
            } else {
                $this->info('Emissão: ativo=' . (($cfg->is_active ?? false) ? 'sim' : 'não')
                    . ' nfce_pdv=' . (($cfg->emitir_nfce_pdv ?? false) ? 'sim' : 'não')
                    . ' ambiente=' . ($cfg->environment ?? '?')
                    . ' token=' . (empty($cfg->api_token) ? 'AUSENTE' : 'OK'));
            }
        }

        // 1) Garantir produto admin + estoque
        $produtoId = $this->garantirProdutoAdmin($unidadeId);
        $this->info("Produto admin #{$produtoId}");

        // 2) Garantir item cardápio + saldo B
        $dlvId = $this->garantirItemCardapio($unidadeId, $produtoId);
        $this->info("Item cardápio #{$dlvId}");

        if (CardapioEstoqueSupport::moduloAtivo()) {
            $saldo = CardapioEstoqueSupport::saldo($unidadeId, $dlvId);
            if ($saldo < 1) {
                CardapioEstoqueSupport::entrada($unidadeId, $dlvId, 10, CardapioEstoqueSupport::ORIGEM_ABASTECIMENTO, [
                    'usuario_id' => $usuarioId,
                    'motivo' => 'Abastecimento para teste de NFC-e',
                ]);
                $this->info('Cardápio abastecido (+10).');
            } else {
                $this->info("Saldo cardápio já OK: {$saldo}");
            }
        }

        // 3) Venda balcão com emissão
        if (! VendaFiscalSupport::moduloAtivo()) {
            $this->error('Módulo de vendas fiscal indisponível.');

            return 1;
        }

        $payload = [
            'unidade_id' => $unidadeId,
            'forma_pagamento' => 'DINHEIRO',
            'pdv_terminal' => 'TESTE-NOTA',
            'origem_venda' => 'balcao',
            'emitir_nota' => true,
            'observacao' => 'Venda teste emissão NFC-e',
            'itens' => [[
                'cardapio_produto_id' => $dlvId,
                'produto_id' => $produtoId,
                'quantidade' => 1,
                'preco_unitario' => 5.00,
                'desconto' => 0,
            ]],
        ];

        try {
            $result = PdvComercialSupport::vendaBalcao($payload, $usuarioId);
        } catch (\Throwable $e) {
            // APP_KEY diferente do que criptografou o token Focus → "The MAC is invalid"
            if (str_contains($e->getMessage(), 'MAC is invalid')) {
                $this->warn('Token Focus não descriptografa neste ambiente (APP_KEY). Tentando venda sem emissão…');
                $payload['emitir_nota'] = false;
                $payload['sem_emissao'] = true;
                try {
                    $result = PdvComercialSupport::vendaBalcao($payload, $usuarioId);
                } catch (\Throwable $e2) {
                    $this->error('Falha na venda: ' . $e2->getMessage());

                    return 1;
                }
            } else {
                $this->error('Falha na venda: ' . $e->getMessage());

                return 1;
            }
        }

        $vendaId = (int) ($result['venda_id'] ?? 0);
        $this->info("Venda #{$vendaId} valor={$result['valor_liquido']}");

        $emissao = $result['emissao'] ?? null;
        if (! is_array($emissao) || ! empty($emissao['skipped'])) {
            $this->warn('Emissão automática não ocorreu: ' . ($emissao['motivo_skip'] ?? 'sem retorno'));
            $this->info('Tentando emitirNfceParaVenda forçar...');
            try {
                $emissao = FiscalEmissaoService::emitirNfceParaVenda($vendaId, true);
            } catch (\Throwable $e) {
                $this->error('Emissão falhou: ' . $e->getMessage());
                $emissao = [
                    'emitida' => false,
                    'mensagem' => $e->getMessage(),
                    'documentos' => FiscalDocumentoService::rotasRelativas($vendaId),
                ];
            }
        }

        $this->line(json_encode([
            'venda_id' => $vendaId,
            'emissao' => [
                'emitida' => $emissao['emitida'] ?? false,
                'skipped' => $emissao['skipped'] ?? false,
                'status' => $emissao['status'] ?? null,
                'mensagem' => $emissao['mensagem'] ?? ($emissao['motivo_skip'] ?? null),
                'chave' => $emissao['chave'] ?? null,
                'documentos' => $emissao['documentos'] ?? FiscalDocumentoService::rotasRelativas($vendaId),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        if (! empty($emissao['emitida']) || (($emissao['status'] ?? '') === 'autorizado')) {
            $this->info('OK — nota emitida. XML/PDF em /api/fiscal/emissao/vendas/' . $vendaId . '/xml e .../danfe.pdf');

            return 0;
        }

        $this->warn('Venda criada, mas nota não autorizada. Veja mensagem acima e Configurações → Emissão NF-e/NFC-e.');
        $this->warn('Se aparecer "The MAC is invalid", o token Focus foi gravado com outro APP_KEY. Abra o sistema no servidor, vá em Fiscal → Emissão e salve o token Focus de novo.');

        return 2;
    }

    private function garantirProdutoAdmin(int $unidadeId): int
    {
        $nome = 'TESTE NFC-e SAS';
        $exist = DB::table('produtos')->where('nome', $nome)->first();
        if ($exist) {
            $produtoId = (int) $exist->id;
        } else {
            // Preferir um produto já existente com NCM (melhor para NFC-e) se houver.
            $pronto = DB::table('produtos')
                ->when(Schema::hasColumn('produtos', 'ativo'), fn ($q) => $q->where('ativo', 1))
                ->when(Schema::hasColumn('produtos', 'ncm'), fn ($q) => $q->whereNotNull('ncm')->where('ncm', '!=', ''))
                ->orderBy('id')
                ->first();
            if ($pronto) {
                $produtoId = (int) $pronto->id;
                $this->info('Reusando produto existente #' . $produtoId . ' (' . $pronto->nome . ')');
            } else {
                $data = [
                    'nome' => $nome,
                    'categoria' => 'testes',
                    'unidade_base' => 'UND',
                    'ativo' => 1,
                ];
                if (Schema::hasColumn('produtos', 'custo_medio')) {
                    $data['custo_medio'] = 1;
                }
                if (Schema::hasColumn('produtos', 'ncm')) {
                    $data['ncm'] = '22021000';
                }
                if (Schema::hasColumn('produtos', 'cfop')) {
                    $data['cfop'] = '5102';
                }
                if (Schema::hasColumn('produtos', 'origem_mercadoria')) {
                    $data['origem_mercadoria'] = '0';
                }
                if (Schema::hasColumn('produtos', 'created_at')) {
                    $data['created_at'] = now();
                }
                if (Schema::hasColumn('produtos', 'updated_at')) {
                    $data['updated_at'] = now();
                }
                $produtoId = (int) DB::table('produtos')->insertGetId($data);
            }
        }

        $saldo = ProducaoEstoqueSupport::saldoDisponivelValido($produtoId, $unidadeId);
        if ($saldo < 1 && class_exists(\App\Services\EntradaEstoqueService::class)) {
            try {
                /** @var \App\Services\EntradaEstoqueService $svc */
                $svc = app(\App\Services\EntradaEstoqueService::class);
                $svc->registrarEntrada([
                    'produto_id' => $produtoId,
                    'unidade_id' => $unidadeId,
                    'quantidade' => 20,
                    'custo_unitario' => 1,
                    'usuario_id' => (int) $this->option('usuario'),
                    'numero_lote' => 'TESTE-NFCE-' . time(),
                    'data_validade' => now()->addYear()->format('Y-m-d'),
                    'motivo' => 'ENTRADA',
                    'observacao' => 'Entrada para teste NFC-e',
                    'origem' => 'MANUAL',
                ], false);
                $this->info('Entrada admin (+20) feita.');
            } catch (\Throwable $e) {
                $this->warn('Entrada admin falhou: ' . $e->getMessage());
            }
        }

        return $produtoId;
    }

    private function garantirItemCardapio(int $unidadeId, int $produtoId): int
    {
        if (! Schema::hasTable('dlv_produtos')) {
            throw new \RuntimeException('Tabela dlv_produtos indisponível.');
        }

        $exist = DB::table('dlv_produtos')
            ->where('unidade_id', $unidadeId)
            ->where('estoque_produto_id', $produtoId)
            ->first();
        if ($exist) {
            DB::table('dlv_produtos')->where('id', $exist->id)->update([
                'ativo' => 1,
                'visivel_pdv' => Schema::hasColumn('dlv_produtos', 'visivel_pdv') ? 1 : ($exist->visivel_pdv ?? 1),
                'controla_estoque_cardapio' => Schema::hasColumn('dlv_produtos', 'controla_estoque_cardapio') ? 1 : null,
                'updated_at' => now(),
            ]);

            return (int) $exist->id;
        }

        $catId = null;
        if (Schema::hasTable('dlv_categorias')) {
            $catId = DB::table('dlv_categorias')->where('unidade_id', $unidadeId)->where('ativo', 1)->value('id');
            if (! $catId) {
                $catId = DB::table('dlv_categorias')->insertGetId([
                    'unidade_id' => $unidadeId,
                    'nome' => 'Testes',
                    'ordem' => 99,
                    'ativo' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $row = [
            'unidade_id' => $unidadeId,
            'categoria_id' => $catId,
            'estoque_produto_id' => $produtoId,
            'nome' => 'TESTE NFC-e SAS',
            'preco' => 5.00,
            'ativo' => 1,
            'visivel_loja' => 0,
            'ordem' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('dlv_produtos', 'visivel_pdv')) {
            $row['visivel_pdv'] = 1;
        }
        if (Schema::hasColumn('dlv_produtos', 'tipo_venda')) {
            $row['tipo_venda'] = 'revenda';
        }
        if (Schema::hasColumn('dlv_produtos', 'controla_estoque_cardapio')) {
            $row['controla_estoque_cardapio'] = 1;
        }
        if (Schema::hasColumn('dlv_produtos', 'sku')) {
            $row['sku'] = 'TESTE-NFCE';
        }

        return (int) DB::table('dlv_produtos')->insertGetId($row);
    }
}
