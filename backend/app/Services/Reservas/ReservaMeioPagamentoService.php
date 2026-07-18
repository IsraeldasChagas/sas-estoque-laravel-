<?php

namespace App\Services\Reservas;

use App\Models\ReservaMeioPagamento;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class ReservaMeioPagamentoService
{
    public function tabelaDisponivel(): bool
    {
        return Schema::hasTable('reserva_meios_pagamento');
    }

    /**
     * @return array{pix:list<object>,maquininha:list<object>,dinheiro:list<object>}
     */
    public function agrupadosPorUnidade(int $unidadeId, bool $somenteAtivos = true): array
    {
        $vazio = ['pix' => [], 'maquininha' => [], 'dinheiro' => []];
        if (! $this->tabelaDisponivel()) {
            return $vazio;
        }

        $query = ReservaMeioPagamento::query()
            ->where('unidade_id', $unidadeId)
            ->orderBy('ordem')
            ->orderBy('nome');

        if ($somenteAtivos) {
            $query->where('ativo', true);
        }

        $out = $vazio;
        foreach ($query->get() as $row) {
            $tipo = (string) $row->tipo;
            if (! array_key_exists($tipo, $out)) {
                continue;
            }
            $out[$tipo][] = (object) [
                'id' => (int) $row->id,
                'tipo' => $tipo,
                'tipo_label' => ReservaMeioPagamento::TIPOS[$tipo] ?? $tipo,
                'nome' => (string) $row->nome,
                'ativo' => (bool) $row->ativo,
                'ordem' => (int) $row->ordem,
            ];
        }

        return $out;
    }

    public function tipoParaForma(string $forma): ?string
    {
        return match (strtolower(trim($forma))) {
            'pix' => ReservaMeioPagamento::TIPO_PIX,
            'credito', 'debito', 'maquininha' => ReservaMeioPagamento::TIPO_MAQUININHA,
            'dinheiro' => ReservaMeioPagamento::TIPO_DINHEIRO,
            default => null,
        };
    }

    public function buscarMeio(int $unidadeId, int $meioId, ?string $tipoEsperado = null): ?object
    {
        if (! $this->tabelaDisponivel() || $meioId <= 0) {
            return null;
        }

        $row = ReservaMeioPagamento::query()
            ->where('id', $meioId)
            ->where('unidade_id', $unidadeId)
            ->where('ativo', true)
            ->first();

        if (! $row) {
            return null;
        }

        if ($tipoEsperado !== null && (string) $row->tipo !== $tipoEsperado) {
            return null;
        }

        return (object) [
            'id' => (int) $row->id,
            'tipo' => (string) $row->tipo,
            'tipo_label' => ReservaMeioPagamento::TIPOS[(string) $row->tipo] ?? (string) $row->tipo,
            'nome' => (string) $row->nome,
        ];
    }

    /**
     * @return list<object>
     */
    public function listarAdmin(int $unidadeId): array
    {
        if (! $this->tabelaDisponivel()) {
            return [];
        }

        return ReservaMeioPagamento::query()
            ->where('unidade_id', $unidadeId)
            ->orderBy('tipo')
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get()
            ->map(fn (ReservaMeioPagamento $row) => (object) [
                'id' => (int) $row->id,
                'unidade_id' => (int) $row->unidade_id,
                'tipo' => (string) $row->tipo,
                'tipo_label' => ReservaMeioPagamento::TIPOS[(string) $row->tipo] ?? (string) $row->tipo,
                'nome' => (string) $row->nome,
                'ativo' => (bool) $row->ativo,
                'ordem' => (int) $row->ordem,
            ])
            ->all();
    }

    public function criar(int $unidadeId, array $data): object
    {
        $this->assertTabela();
        $tipo = $this->validarTipo($data['tipo'] ?? '');
        $nome = trim((string) ($data['nome'] ?? ''));
        if ($nome === '') {
            throw ValidationException::withMessages(['nome' => 'Informe o nome do recebedor ou identificação.']);
        }

        $row = ReservaMeioPagamento::create([
            'unidade_id' => $unidadeId,
            'tipo' => $tipo,
            'nome' => $nome,
            'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : true,
            'ordem' => (int) ($data['ordem'] ?? 0),
        ]);

        return (object) [
            'id' => (int) $row->id,
            'unidade_id' => (int) $row->unidade_id,
            'tipo' => (string) $row->tipo,
            'tipo_label' => ReservaMeioPagamento::TIPOS[(string) $row->tipo],
            'nome' => (string) $row->nome,
            'ativo' => (bool) $row->ativo,
            'ordem' => (int) $row->ordem,
        ];
    }

    public function atualizar(int $unidadeId, int $id, array $data): object
    {
        $this->assertTabela();
        $row = ReservaMeioPagamento::query()->where('id', $id)->where('unidade_id', $unidadeId)->first();
        if (! $row) {
            throw ValidationException::withMessages(['id' => 'Meio de pagamento não encontrado.']);
        }

        if (array_key_exists('tipo', $data)) {
            $row->tipo = $this->validarTipo($data['tipo']);
        }
        if (array_key_exists('nome', $data)) {
            $nome = trim((string) $data['nome']);
            if ($nome === '') {
                throw ValidationException::withMessages(['nome' => 'Informe o nome do recebedor ou identificação.']);
            }
            $row->nome = $nome;
        }
        if (array_key_exists('ativo', $data)) {
            $row->ativo = (bool) $data['ativo'];
        }
        if (array_key_exists('ordem', $data)) {
            $row->ordem = (int) $data['ordem'];
        }
        $row->save();

        return (object) [
            'id' => (int) $row->id,
            'unidade_id' => (int) $row->unidade_id,
            'tipo' => (string) $row->tipo,
            'tipo_label' => ReservaMeioPagamento::TIPOS[(string) $row->tipo],
            'nome' => (string) $row->nome,
            'ativo' => (bool) $row->ativo,
            'ordem' => (int) $row->ordem,
        ];
    }

    public function excluir(int $unidadeId, int $id): void
    {
        $this->assertTabela();
        $row = ReservaMeioPagamento::query()->where('id', $id)->where('unidade_id', $unidadeId)->first();
        if (! $row) {
            throw ValidationException::withMessages(['id' => 'Meio de pagamento não encontrado.']);
        }
        $row->delete();
    }

    private function validarTipo(string $tipo): string
    {
        $tipo = strtolower(trim($tipo));
        if (! array_key_exists($tipo, ReservaMeioPagamento::TIPOS)) {
            throw ValidationException::withMessages(['tipo' => 'Tipo inválido. Use pix, maquininha ou dinheiro.']);
        }

        return $tipo;
    }

    private function assertTabela(): void
    {
        if (! $this->tabelaDisponivel()) {
            throw ValidationException::withMessages(['meios_pagamento' => 'Cadastro de meios de pagamento indisponível. Execute as migrations.']);
        }
    }
}
