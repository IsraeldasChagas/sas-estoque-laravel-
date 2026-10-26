<?php

namespace App\Services\Reservas;

use App\Models\ReservaMeioPagamento;
use App\Models\ReservaMesa;
use Illuminate\Validation\ValidationException;

/**
 * Conta paga da reserva: valor total e formas de pagamento usadas.
 */
final class ReservaContaService
{
    public function __construct(
        private ReservaMeioPagamentoService $meiosPagamento,
    ) {}

    /**
     * @return list<array{meio_id:int,tipo:string,tipo_label:string,meio_nome:string,label:string,valor:float,rotulo:?string,forma:string}>
     */
    public function normalizarPagamentos(int $unidadeId, mixed $raw): array
    {
        if (! is_array($raw) || $raw === []) {
            throw ValidationException::withMessages(['pagamentos' => 'Informe ao menos uma forma de pagamento.']);
        }

        $out = [];
        foreach ($raw as $i => $item) {
            if (! is_array($item)) {
                throw ValidationException::withMessages(['pagamentos' => 'Pagamento #'.($i + 1).' inválido.']);
            }
            if (! array_key_exists('valor', $item) || ! is_numeric($item['valor'])) {
                throw ValidationException::withMessages(['pagamentos' => 'Informe o valor na linha '.($i + 1).'.']);
            }
            $valor = round((float) $item['valor'], 2);
            if ($valor <= 0) {
                throw ValidationException::withMessages(['pagamentos' => 'Valor deve ser maior que zero na linha '.($i + 1).'.']);
            }

            $meioId = (int) ($item['meio_id'] ?? 0);
            if ($meioId <= 0) {
                throw ValidationException::withMessages(['pagamentos' => 'Selecione a forma de pagamento na linha '.($i + 1).'.']);
            }

            $meio = $this->meiosPagamento->buscarMeio($unidadeId, $meioId);
            if (! $meio) {
                throw ValidationException::withMessages(['pagamentos' => 'Forma de pagamento inválida na linha '.($i + 1).'. Cadastre em Reserva → Forma de pagamento.']);
            }

            $rotulo = trim((string) ($item['rotulo'] ?? ''));

            $out[] = [
                'meio_id' => (int) $meio->id,
                'tipo' => (string) $meio->tipo,
                'tipo_label' => (string) $meio->tipo_label,
                'meio_nome' => (string) $meio->nome,
                'label' => (string) $meio->label,
                'valor' => $valor,
                'rotulo' => $rotulo !== '' ? $rotulo : null,
                'forma' => (string) $meio->tipo,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{valor:float}>  $pagamentos
     */
    public function validarTotal(float $valorConta, array $pagamentos): void
    {
        $soma = round(array_sum(array_column($pagamentos, 'valor')), 2);
        $total = round($valorConta, 2);
        if (abs($soma - $total) > 0.009) {
            throw ValidationException::withMessages([
                'pagamentos' => 'A soma dos pagamentos (R$ '.number_format($soma, 2, ',', '.').') deve bater com o valor da conta (R$ '.number_format($total, 2, ',', '.').').',
            ]);
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function pagamentosDaReserva(ReservaMesa $reserva): array
    {
        $raw = $reserva->pagamentos_conta;
        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $valor = round((float) ($item['valor'] ?? 0), 2);
            if ($valor <= 0) {
                continue;
            }
            $tipo = strtolower(trim((string) ($item['tipo'] ?? $item['forma'] ?? '')));
            $tipoLabel = trim((string) ($item['tipo_label'] ?? ''));
            if ($tipoLabel === '' && $tipo !== '') {
                $tipoLabel = ReservaMeioPagamento::TIPOS[$tipo] ?? $tipo;
            }
            $meioNome = trim((string) ($item['meio_nome'] ?? $item['maquina'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '' && $tipoLabel !== '' && $meioNome !== '') {
                $label = $tipoLabel.' — '.$meioNome;
            }
            $rotulo = trim((string) ($item['rotulo'] ?? ''));
            $out[] = [
                'meio_id' => isset($item['meio_id']) ? (int) $item['meio_id'] : null,
                'tipo' => $tipo !== '' ? $tipo : null,
                'tipo_label' => $tipoLabel !== '' ? $tipoLabel : null,
                'meio_nome' => $meioNome !== '' ? $meioNome : null,
                'label' => $label !== '' ? $label : null,
                'valor' => $valor,
                'rotulo' => $rotulo !== '' ? $rotulo : null,
                'forma' => $tipo !== '' ? $tipo : null,
                'forma_label' => $tipoLabel !== '' ? $tipoLabel : null,
                'maquina' => $meioNome !== '' ? $meioNome : null,
            ];
        }

        return $out;
    }

    /**
     * @return array<string,mixed>
     */
    public function resumo(ReservaMesa $reserva): array
    {
        return [
            'reserva_id' => (int) $reserva->id,
            'conta_paga' => (bool) ($reserva->conta_paga ?? false),
            'valor_conta' => $reserva->valor_conta !== null ? (float) $reserva->valor_conta : null,
            'conta_paga_em' => $reserva->conta_paga_em
                ? (string) (is_string($reserva->conta_paga_em) ? $reserva->conta_paga_em : $reserva->conta_paga_em->toDateTimeString())
                : null,
            'pagamentos_conta' => $this->pagamentosDaReserva($reserva),
            'formas_pagamento_cadastro' => $this->meiosPagamento->listarAtivos((int) $reserva->unidade_id),
        ];
    }

    /**
     * @return array{reserva:ReservaMesa,replayed:bool}
     */
    public function registrarContaPaga(ReservaMesa $reserva, float $valorConta, array $pagamentos): array
    {
        if ($valorConta < 0) {
            throw ValidationException::withMessages(['valor_conta' => 'Informe o valor da conta (0 ou maior).']);
        }

        $pagamentos = $this->normalizarPagamentos((int) $reserva->unidade_id, $pagamentos);
        $this->validarTotal($valorConta, $pagamentos);

        if ($reserva->conta_paga) {
            return ['reserva' => $reserva->fresh(['mesa', 'usuario']), 'replayed' => true];
        }

        $reserva->conta_paga = true;
        $reserva->valor_conta = round($valorConta, 2);
        $reserva->conta_paga_em = now();
        $reserva->pagamentos_conta = $pagamentos;
        $reserva->save();

        return ['reserva' => $reserva->fresh(['mesa', 'usuario']), 'replayed' => false];
    }
}
