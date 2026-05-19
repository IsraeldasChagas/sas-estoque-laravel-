<?php

namespace App\Support\Rh;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regras de unicidade para cadastro de funcionários (evita duplicatas por CPF/e-mail).
 */
final class RhFuncionarioUnicidade
{
  /** CPFs gerados por scripts de reconciliação/restauração — não são documentos reais. */
  private const CPF_PROVISORIO_REGEX = '/^(999\.999\.|998\.998\.|000\.000\.)/';

  public static function cpfSoDigitos(?string $cpf): string
  {
    return preg_replace('/\D/', '', (string) $cpf);
  }

  public static function cpfFormatado(string $cpfLimpo): string
  {
    if (strlen($cpfLimpo) !== 11) {
      return $cpfLimpo;
    }

    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpfLimpo);
  }

  public static function isCpfProvisorio(?string $cpf): bool
  {
    return (bool) preg_match(self::CPF_PROVISORIO_REGEX, trim((string) $cpf));
  }

  public static function emailNormalizado(?string $email): string
  {
    return mb_strtolower(trim((string) $email));
  }

  public static function nomeNormalizado(?string $nome): string
  {
    $n = mb_strtolower(trim((string) $nome));

    return preg_replace('/\s+/u', ' ', $n) ?? $n;
  }

  public static function existePorCpf(string $cpfLimpo, ?int $excludeId = null): bool
  {
    if (! Schema::hasTable('funcionarios') || strlen($cpfLimpo) !== 11) {
      return false;
    }

    $q = DB::table('funcionarios')
      ->whereRaw(
        'REPLACE(REPLACE(REPLACE(cpf, ".", ""), "-", ""), " ", "") = ?',
        [$cpfLimpo]
      );
    if ($excludeId !== null) {
      $q->where('id', '!=', $excludeId);
    }

    return $q->exists();
  }

  public static function existePorEmail(string $email, ?int $excludeId = null): bool
  {
    $norm = self::emailNormalizado($email);
    if ($norm === '' || ! Schema::hasTable('funcionarios')) {
      return false;
    }

    $q = DB::table('funcionarios')
      ->whereRaw('LOWER(TRIM(email)) = ?', [$norm]);
    if ($excludeId !== null) {
      $q->where('id', '!=', $excludeId);
    }

    return $q->exists();
  }

  /**
   * Pontuação para escolher qual registro manter em deduplicação (maior = melhor).
   */
  public static function pontuacaoCadastro(object $row): int
  {
    $score = 0;
    if (! self::isCpfProvisorio($row->cpf ?? null)) {
      $score += 1000;
    }
    if (! empty($row->foto)) {
      $score += 100;
    }
    foreach (['data_nascimento', 'whatsapp', 'data_admissao', 'escolaridade', 'banco', 'pix', 'ctps', 'formacao_json'] as $col) {
      if (! empty($row->{$col})) {
        $score += 10;
      }
    }
    if (! empty($row->observacoes)) {
      $score += 5;
    }
    // Preferir cadastro mais antigo (original) em empate
    if (! empty($row->created_at)) {
      $score -= (int) strtotime((string) $row->created_at) / 86400;
    }

    return $score;
  }

  /**
   * @return list<array{email: string, manter_id: int, remover_ids: list<int>}>
   */
  public static function gruposDuplicadosPorEmail(): array
  {
    if (! Schema::hasTable('funcionarios')) {
      return [];
    }

    $rows = DB::table('funcionarios')
      ->whereNotNull('email')
      ->where('email', '!=', '')
      ->orderBy('id')
      ->get();

    $porEmail = [];
    foreach ($rows as $row) {
      $email = self::emailNormalizado($row->email);
      if ($email === '') {
        continue;
      }
      $porEmail[$email][] = $row;
    }

    $grupos = [];
    foreach ($porEmail as $email => $lista) {
      if (count($lista) < 2) {
        continue;
      }
      usort($lista, static fn ($a, $b) => self::pontuacaoCadastro($b) <=> self::pontuacaoCadastro($a));
      $manter = (int) $lista[0]->id;
      $remover = [];
      foreach (array_slice($lista, 1) as $dup) {
        $remover[] = (int) $dup->id;
      }
      $grupos[] = [
        'email' => $email,
        'manter_id' => $manter,
        'remover_ids' => $remover,
      ];
    }

    return $grupos;
  }

  public static function temReferencias(int $funcionarioId): bool
  {
    $tabelas = [
      'proventos' => 'funcionario_id',
      'proventos_logs' => 'funcionario_id',
      'financeiro_vale_consumo' => 'funcionario_id',
      'recibos_ajuda_custo' => 'funcionario_id',
    ];
    foreach ($tabelas as $tabela => $coluna) {
      if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, $coluna)) {
        continue;
      }
      if (DB::table($tabela)->where($coluna, $funcionarioId)->exists()) {
        return true;
      }
    }

    return false;
  }
}
