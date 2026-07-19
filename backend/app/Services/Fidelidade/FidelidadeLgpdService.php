<?php

namespace App\Services\Fidelidade;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FidelidadeLgpdService
{
    public const VERSAO = '2026-07-18';

    public const CONTROLADOR = 'Grupo Sabor Paraense';

    public static function textoTermo(string $nomeLoja, string $nomeUnidade = '', ?string $contatoLoja = null): string
    {
        $loja = trim($nomeLoja) !== '' ? trim($nomeLoja) : 'Loja';
        $unidade = trim($nomeUnidade) !== '' ? trim($nomeUnidade) : $loja;
        $contato = self::fraseContato($contatoLoja);

        return self::CONTROLADOR.' (controlador de dados), por meio da unidade '.$unidade
            .', trata dados pessoais (nome, telefone, CPF e e-mail, quando informados) '
            .'exclusivamente para confirmar sua identidade e proteger o acesso ao seu cartão fidelidade. '
            .'Esses dados sensíveis não serão utilizados para outros fins sem novo consentimento. '
            .'Base legal: consentimento (Lei nº 13.709/2018 — LGPD). '
            .'Você pode revogar este consentimento a qualquer momento'.$contato.'.';
    }

    /** @return list<string> */
    public static function secoesPolitica(string $nomeLoja, string $nomeUnidade = '', ?string $contatoLoja = null): array
    {
        $loja = trim($nomeLoja) !== '' ? trim($nomeLoja) : 'Loja';
        $unidade = trim($nomeUnidade) !== '' ? trim($nomeUnidade) : $loja;
        $contato = self::fraseContato($contatoLoja, true);

        return [
            'Controlador de dados' => self::CONTROLADOR.', responsável pelo tratamento dos dados na consulta do cartão fidelidade da unidade '.$unidade.' ('.$loja.').',
            'Dados tratados' => 'Nome, telefone celular, CPF, e-mail e informações do cartão fidelidade (selos, pontos e histórico vinculado ao programa).',
            'Finalidade' => 'Confirmar a identidade do titular do cartão, enviar código de verificação (OTP) e exibir saldo de selos com segurança. Os dados sensíveis são usados somente para este fim.',
            'Base legal' => 'Consentimento do titular (art. 7º, I, e art. 11, I, da Lei nº 13.709/2018 — LGPD).',
            'Compartilhamento' => 'Os dados não são vendidos. O compartilhamento ocorre apenas quando necessário para prestação do serviço (ex.: envio de código por e-mail ou WhatsApp) ou por obrigação legal.',
            'Retenção' => 'Mantemos o registro do aceite e os dados do cartão enquanto durar a relação com o programa fidelidade ou até solicitação de exclusão/revogação, observadas obrigações legais.',
            'Seus direitos' => 'Você pode solicitar confirmação de tratamento, acesso, correção, anonimização, portabilidade, revogação do consentimento e eliminação dos dados, nos termos da LGPD'.$contato.'.',
            'Segurança' => 'Adotamos medidas técnicas e organizacionais para proteger seus dados, incluindo confirmação por código de 6 dígitos antes de exibir informações do cartão.',
        ];
    }

    public static function registrarAceite(int $contaId, ?string $ip): void
    {
        if ($contaId <= 0 || ! Schema::hasTable('fid_contas') || ! Schema::hasColumn('fid_contas', 'lgpd_aceite_em')) {
            return;
        }

        DB::table('fid_contas')->where('id', $contaId)->update([
            'lgpd_aceite_em' => now(),
            'lgpd_aceite_versao' => self::VERSAO,
            'lgpd_aceite_ip' => is_string($ip) && $ip !== '' ? substr($ip, 0, 45) : null,
            'updated_at' => now(),
        ]);
    }

    private static function fraseContato(?string $contatoLoja, bool $completa = false): string
    {
        $contato = trim((string) $contatoLoja);
        if ($contato === '') {
            return $completa
                ? ', entrando em contato com a loja/unidade onde você reservou ou consultou o cartão'
                : ', entrando em contato com a loja';
        }

        return $completa
            ? ', entrando em contato com a loja pelo '.$contato
            : ', entrando em contato com a loja pelo '.$contato;
    }
}
