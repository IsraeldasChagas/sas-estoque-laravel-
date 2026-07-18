<?php

namespace App\Services\Fidelidade;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Valida unicidade de CPF/e-mail por unidade no cartão fidelidade.
 */
final class FidelidadeIdentidadeService
{
    /**
     * @return array{nome:?string,cpf:?string,email:?string}
     */
    public function normalizar(?string $nome, ?string $cpf, ?string $email): array
    {
        return [
            'nome' => FidelidadeNormalizer::nome($nome),
            'cpf' => FidelidadeNormalizer::cpf($cpf),
            'email' => FidelidadeNormalizer::email($email),
        ];
    }

    /**
     * @return array{nome:string,cpf:string,email:string}
     */
    public function exigirCompletos(?string $nome, ?string $cpf, ?string $email): array
    {
        $dados = $this->normalizar($nome, $cpf, $email);

        if (! $dados['nome'] || mb_strlen($dados['nome']) < 3) {
            throw ValidationException::withMessages(['fidelidade_nome' => 'Informe o nome completo do cliente.']);
        }
        if (! $dados['cpf'] || ! FidelidadeNormalizer::cpfValido($dados['cpf'])) {
            throw ValidationException::withMessages(['fidelidade_cpf' => 'CPF inválido.']);
        }
        if (! $dados['email'] || ! filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['fidelidade_email' => 'Informe um e-mail válido.']);
        }

        return [
            'nome' => $dados['nome'],
            'cpf' => $dados['cpf'],
            'email' => $dados['email'],
        ];
    }

    public function validarUnicidade(int $unidadeId, string $telefone, string $cpf, string $email, ?int $contaId = null): void
    {
        if (! Schema::hasTable('fid_contas')) {
            return;
        }

        $cpfOutro = DB::table('fid_contas')
            ->where('unidade_id', $unidadeId)
            ->where('cpf_normalizado', $cpf)
            ->where('telefone_normalizado', '!=', $telefone)
            ->when($contaId, fn ($q) => $q->where('id', '!=', $contaId))
            ->exists();
        if ($cpfOutro) {
            throw ValidationException::withMessages([
                'fidelidade_cpf' => 'Este CPF já está cadastrado em outro telefone nesta unidade.',
            ]);
        }

        $emailOutro = DB::table('fid_contas')
            ->where('unidade_id', $unidadeId)
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->where('telefone_normalizado', '!=', $telefone)
            ->when($contaId, fn ($q) => $q->where('id', '!=', $contaId))
            ->exists();
        if ($emailOutro) {
            throw ValidationException::withMessages([
                'fidelidade_email' => 'Este e-mail já está cadastrado em outro telefone nesta unidade.',
            ]);
        }

        $contaTel = DB::table('fid_contas')
            ->where('unidade_id', $unidadeId)
            ->where('telefone_normalizado', $telefone)
            ->when($contaId, fn ($q) => $q->where('id', '!=', $contaId))
            ->first();

        if ($contaTel) {
            if ($contaTel->cpf_normalizado && (string) $contaTel->cpf_normalizado !== $cpf) {
                throw ValidationException::withMessages([
                    'fidelidade_cpf' => 'Este telefone já possui cartão com outro CPF.',
                ]);
            }
            $emailConta = FidelidadeNormalizer::email($contaTel->email ?? null);
            if ($emailConta && $emailConta !== $email) {
                throw ValidationException::withMessages([
                    'fidelidade_email' => 'Este telefone já possui cartão com outro e-mail.',
                ]);
            }
        }
    }

    /**
     * @return array{nome:string,cpf:string,email:string}
     */
    public function validarCadastro(int $unidadeId, string $telefone, ?string $nome, ?string $cpf, ?string $email, ?int $contaId = null): array
    {
        $dados = $this->exigirCompletos($nome, $cpf, $email);
        $this->validarUnicidade($unidadeId, $telefone, $dados['cpf'], $dados['email'], $contaId);

        return $dados;
    }
}
