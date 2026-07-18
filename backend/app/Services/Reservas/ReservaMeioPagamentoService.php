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
     * @return list<object>
     */
    public function listarAtivos(int $unidadeId): array
    {
        if (! $this->tabelaDisponivel()) {
            return [];
        }

        return ReservaMeioPagamento::query()
            ->where('unidade_id', $unidadeId)
            ->where('ativo', true)
            ->orderBy('ordem')
            ->orderBy('tipo')
            ->orderBy('nome')
            ->get()
            ->map(fn (ReservaMeioPagamento $row) => $this->toDto($row))
            ->all();
    }

    /**
     * @return array<string, list<object>>
     */
    public function agrupadosPorUnidade(int $unidadeId, bool $somenteAtivos = true): array
    {
        $out = [];
        foreach (array_keys(ReservaMeioPagamento::TIPOS) as $tipo) {
            $out[$tipo] = [];
        }

        if (! $this->tabelaDisponivel()) {
            return $out;
        }

        $query = ReservaMeioPagamento::query()
            ->where('unidade_id', $unidadeId)
            ->orderBy('ordem')
            ->orderBy('nome');

        if ($somenteAtivos) {
            $query->where('ativo', true);
        }

        foreach ($query->get() as $row) {
            $tipo = (string) $row->tipo;
            if (! array_key_exists($tipo, $out)) {
                continue;
            }
            $out[$tipo][] = $this->toDto($row);
        }

        return $out;
    }

    public function buscarMeio(int $unidadeId, int $meioId): ?object
    {
        if (! $this->tabelaDisponivel() || $meioId <= 0) {
            return null;
        }

        $row = ReservaMeioPagamento::query()
            ->where('id', $meioId)
            ->where('unidade_id', $unidadeId)
            ->where('ativo', true)
            ->first();

        return $row ? $this->toDto($row) : null;
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
            ->map(fn (ReservaMeioPagamento $row) => $this->toDto($row))
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

        return $this->toDto($row);
    }

    public function atualizar(int $unidadeId, int $id, array $data): object
    {
        $this->assertTabela();
        $row = ReservaMeioPagamento::query()->where('id', $id)->where('unidade_id', $unidadeId)->first();
        if (! $row) {
            throw ValidationException::withMessages(['id' => 'Forma de pagamento não encontrada.']);
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

        return $this->toDto($row);
    }

    public function excluir(int $unidadeId, int $id): void
    {
        $this->assertTabela();
        $row = ReservaMeioPagamento::query()->where('id', $id)->where('unidade_id', $unidadeId)->first();
        if (! $row) {
            throw ValidationException::withMessages(['id' => 'Forma de pagamento não encontrada.']);
        }
        $row->delete();
    }

    private function validarTipo(string $tipo): string
    {
        $tipo = strtolower(trim($tipo));
        if (! array_key_exists($tipo, ReservaMeioPagamento::TIPOS)) {
            throw ValidationException::withMessages(['tipo' => 'Tipo inválido.']);
        }

        return $tipo;
    }

    private function toDto(ReservaMeioPagamento $row): object
    {
        $tipo = (string) $row->tipo;

        return (object) [
            'id' => (int) $row->id,
            'unidade_id' => (int) $row->unidade_id,
            'tipo' => $tipo,
            'tipo_label' => ReservaMeioPagamento::TIPOS[$tipo] ?? $tipo,
            'nome' => (string) $row->nome,
            'label' => ReservaMeioPagamento::rotuloCompleto($tipo, (string) $row->nome),
            'ativo' => (bool) $row->ativo,
            'ordem' => (int) $row->ordem,
        ];
    }

    private function assertTabela(): void
    {
        if (! $this->tabelaDisponivel()) {
            throw ValidationException::withMessages(['formas_pagamento' => 'Cadastro indisponível. Execute as migrations.']);
        }
    }
}
