<?php

namespace App\Services\Fidelidade;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Entrega OTP da vitrine (espelha VendaFácil): e-mail do cartão e/ou link wa.me.
 */
final class FidelidadePublicOtpEntrega
{
    public const CANAL_EMAIL = 'email';

    public const CANAL_WAME = 'whatsapp_link';

    public const FALHA_EMAIL = 'email_falhou';

    public const FALHA_WHATSAPP = 'whatsapp_falhou';

    public const FALHA_SEM_DESTINO = 'sem_destino';

    /**
     * @return array{ok:bool,canal?:string,resultado?:string,wa_me_url?:string}
     */
    public function entregar(string $nomeLoja, int $unidadeId, string $telNorm, string $codigo, int $ttlMinutos, ?string $emailOverride = null): array
    {
        $msgWa = '['.$nomeLoja.'] Seu código para ver o cartão fidelidade: '.$codigo.'. Válido por '.$ttlMinutos.' minutos. Não compartilhe com ninguém.';

        $emailResult = null;
        if ((bool) config('services.fidelidade_otp.email_fallback', true)) {
            $emailResult = $this->entregarEmail($unidadeId, $telNorm, $codigo, $ttlMinutos, $nomeLoja, $emailOverride);
            if ($emailResult['ok']) {
                return $emailResult;
            }
        }

        $waMeUrl = $this->montarWaMe($telNorm, $msgWa);
        if ($waMeUrl !== null) {
            Log::notice('[sas-fid-otp] Usando link wa.me para OTP', [
                'unidade_id' => $unidadeId,
                'tel_sufixo' => strlen($telNorm) >= 4 ? substr($telNorm, -4) : null,
            ]);

            return ['ok' => true, 'canal' => self::CANAL_WAME, 'wa_me_url' => $waMeUrl];
        }

        if ($emailResult && ($emailResult['resultado'] ?? '') === self::FALHA_SEM_DESTINO) {
            return ['ok' => false, 'resultado' => self::FALHA_SEM_DESTINO];
        }

        return $emailResult ?? ['ok' => false, 'resultado' => self::FALHA_WHATSAPP];
    }

    /**
     * @return array{ok:bool,canal?:string,resultado?:string}
     */
    private function entregarEmail(int $unidadeId, string $telNorm, string $codigo, int $ttlMinutos, string $nomeLoja, ?string $emailOverride): array
    {
        $email = null;
        if ($emailOverride) {
            $e = strtolower(trim($emailOverride));
            if (filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $email = $e;
            }
        }
        if ($email === null && Schema::hasTable('fid_contas')) {
            $row = DB::table('fid_contas')
                ->where('unidade_id', $unidadeId)
                ->where('telefone_normalizado', $telNorm)
                ->first(['email']);
            $e = strtolower(trim((string) ($row->email ?? '')));
            if (filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $email = $e;
            }
        }
        if ($email === null) {
            return ['ok' => false, 'resultado' => self::FALHA_SEM_DESTINO];
        }

        $assunto = $nomeLoja.' — código fidelidade';
        $corpo = "Olá!\n\nSeu código para ver o cartão fidelidade na loja {$nomeLoja} é: {$codigo}\n\nVálido por {$ttlMinutos} minutos. Não compartilhe com ninguém.\n";

        try {
            Mail::raw($corpo, function ($m) use ($email, $assunto) {
                $m->to($email)->subject($assunto);
            });

            return ['ok' => true, 'canal' => self::CANAL_EMAIL];
        } catch (Throwable $e) {
            Log::error('[sas-fid-otp] Falha e-mail OTP', ['error' => $e->getMessage()]);

            return ['ok' => false, 'resultado' => self::FALHA_EMAIL];
        }
    }

    private function montarWaMe(string $telNorm, string $mensagem): ?string
    {
        if (! (bool) config('services.fidelidade_otp.wa_me_fallback', true)) {
            return null;
        }
        $d = preg_replace('/\D+/', '', $telNorm) ?? '';
        if (strlen($d) < 10) {
            return null;
        }
        if (strlen($d) === 10 || strlen($d) === 11) {
            $d = '55'.$d;
        }

        return 'https://wa.me/'.$d.'?text='.rawurlencode($mensagem);
    }
}
