<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Services\Fidelidade\FidelidadeCodigoService;
use App\Services\Fidelidade\FidelidadeLedgerService;
use App\Services\Fidelidade\FidelidadeNormalizer;
use App\Services\Fidelidade\FidelidadePublicConsultaService;
use App\Services\Fidelidade\FidelidadePublicOtpEntrega;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Consulta pública de fidelidade na vitrine — mesma segurança do VendaFácil (OTP + sessão).
 */
class DeliveryFidelidadePublicController extends Controller
{
    private const OTP_TTL_MINUTES = 10;

    private const ACESSO_TTL_MINUTES = 45;

    private const OTP_TIPO_CONSULTA = 'consulta';

    private const OTP_TIPO_CADASTRO = 'cadastro';

    private const SESSION_OTP = 'sas_fid_otp_pending';

    private const SESSION_ACESSO = 'sas_fid_acesso';

    public function __construct(
        private FidelidadePublicOtpEntrega $otpEntrega,
        private FidelidadeLedgerService $ledger,
        private FidelidadePublicConsultaService $consulta,
    ) {}

    public function show(string $slug): View
    {
        $config = $this->config($slug);
        $unidadeVitrine = (int) $config->unidade_id;
        $unidadeFidPadrao = $this->consulta->unidadeFidelidade($config);
        $programa = $this->programaAtivo($unidadeFidPadrao) ?? $this->programaAtivo($unidadeVitrine);
        abort_unless($programa, 404);

        $acesso = $this->acessoValido($unidadeVitrine);
        $otpPending = $this->otpPendenteValido($unidadeVitrine);
        $otpCadastro = false;
        if ($otpPending) {
            $p = session(self::SESSION_OTP, []);
            $otpCadastro = is_array($p) && (($p['tipo'] ?? self::OTP_TIPO_CONSULTA) === self::OTP_TIPO_CADASTRO);
        }

        $conta = null;
        $mostrarProgresso = false;
        $telefoneMascara = null;
        if ($acesso !== null && ! $otpPending) {
            $mostrarProgresso = true;
            $norm = $acesso['tel_norm'];
            $telefoneMascara = strlen($norm) >= 4 ? '***'.substr($norm, -4) : $norm;
            $unidadeFid = (int) ($acesso['unidade_fidelidade_id'] ?? $unidadeFidPadrao);
            $conta = $this->consulta->buscarContaAtivaNaUnidade($unidadeFid, $norm)
                ?: $this->consulta->buscarContaAtiva($config, $norm);
            if ($conta) {
                $programa = $this->programaAtivo((int) $conta->unidade_id) ?? $programa;
            }
        }

        return view('delivery.public.fidelidade', [
            'config' => $config,
            'slug' => $slug,
            'programa' => $programa,
            'passoAtual' => 'loja',
            'fidelidadeAtiva' => true,
            'footerFixed' => false,
            'conta' => $conta,
            'mostrar_progresso_selos' => $mostrarProgresso,
            'telefone_selos_mascara' => $telefoneMascara,
            'fidelidade_otp_pending' => $otpPending,
            'fidelidade_otp_cadastro' => $otpCadastro,
        ]);
    }

    public function solicitarCodigo(Request $request, string $slug): RedirectResponse
    {
        $config = $this->config($slug);
        $unidadeVitrine = (int) $config->unidade_id;
        $unidadeFidPadrao = $this->consulta->unidadeFidelidade($config);
        $programa = $this->programaAtivo($unidadeFidPadrao) ?? $this->programaAtivo($unidadeVitrine);
        if (! $programa) {
            return $this->voltar($slug)->with('warning', 'Programa de fidelidade indisponível.');
        }

        $data = $request->validate([
            'telefone' => ['required', 'string', 'min:8', 'max:32'],
        ]);
        $norm = FidelidadeNormalizer::telefone($data['telefone']);
        if (strlen($norm) < 10) {
            return back()->withErrors(['telefone' => 'Informe um telefone válido (DDD + número).'])->withInput();
        }

        $conta = $this->consulta->buscarContaAtiva($config, $norm);
        if (! $conta) {
            return back()
                ->with('warning', 'Não encontramos cartão fidelidade para este telefone nesta loja. Cadastre-se acima ou confira o número.')
                ->withInput();
        }
        $unidadeFid = (int) $conta->unidade_id;

        $rateKey = 'sas-fid-otp:'.$unidadeVitrine.':'.$norm;
        if (RateLimiter::tooManyAttempts($rateKey, 4)) {
            $seg = RateLimiter::availableIn($rateKey);

            return back()->withErrors(['telefone' => 'Aguarde '.max(1, $seg).' segundos para solicitar outro código.'])->withInput();
        }
        RateLimiter::hit($rateKey, 3600);

        $codigo = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        Cache::forget($this->keyOtpCadastro($unidadeVitrine, $norm));
        Cache::forget($this->keyFalhasCadastro($unidadeVitrine, $norm));
        Cache::put($this->keyOtp($unidadeVitrine, $norm), $codigo, now()->addMinutes(self::OTP_TTL_MINUTES));
        Cache::forget($this->keyFalhas($unidadeVitrine, $norm));

        $nomeLoja = trim((string) ($config->nome_loja ?: 'Loja'));
        $envio = $this->otpEntrega->entregar($nomeLoja, $unidadeVitrine, $norm, $codigo, self::OTP_TTL_MINUTES);
        if (! $envio['ok']) {
            Cache::forget($this->keyOtp($unidadeId, $norm));
            RateLimiter::clear($rateKey);

            return back()->withErrors(['telefone' => $this->msgFalha($envio)])->withInput();
        }

        $request->session()->put(self::SESSION_OTP, [
            'unidade_id' => $unidadeVitrine,
            'unidade_fidelidade_id' => $unidadeFid,
            'tel_norm' => $norm,
            'telefone_input' => $data['telefone'],
            'tipo' => self::OTP_TIPO_CONSULTA,
            'canal' => $envio['canal'] ?? FidelidadePublicOtpEntrega::CANAL_WAME,
            'wa_me_url' => $envio['wa_me_url'] ?? null,
        ]);

        return $this->voltar($slug)->with('status', $this->msgSucesso((string) ($envio['canal'] ?? ''), false));
    }

    public function reenviarCodigo(Request $request, string $slug): RedirectResponse
    {
        $config = $this->config($slug);
        $programa = $this->programaAtivo((int) $config->unidade_id);
        if (! $programa) {
            return $this->voltar($slug)->with('warning', 'Programa de fidelidade indisponível.');
        }

        $unidadeId = (int) $config->unidade_id;
        $pending = session(self::SESSION_OTP);
        if (! is_array($pending) || (int) ($pending['unidade_id'] ?? 0) !== $unidadeId || ! is_string($pending['tel_norm'] ?? null)) {
            return $this->voltar($slug)->with('warning', 'Peça um código informando o telefone novamente.');
        }

        $norm = $pending['tel_norm'];
        $rateKey = 'sas-fid-otp:'.$unidadeId.':'.$norm;
        if (RateLimiter::tooManyAttempts($rateKey, 4)) {
            $seg = RateLimiter::availableIn($rateKey);

            return $this->voltar($slug)->with('warning', 'Aguarde '.max(1, $seg).' segundos para solicitar outro código.');
        }
        RateLimiter::hit($rateKey, 3600);

        $tipo = (string) ($pending['tipo'] ?? self::OTP_TIPO_CONSULTA);
        if ($tipo !== self::OTP_TIPO_CONSULTA && $tipo !== self::OTP_TIPO_CADASTRO) {
            $tipo = self::OTP_TIPO_CONSULTA;
        }

        $codigo = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        [$ck, $fk] = $this->chaves($unidadeId, $norm, $tipo);
        Cache::put($ck, $codigo, now()->addMinutes(self::OTP_TTL_MINUTES));
        Cache::forget($fk);

        $emailCad = null;
        if ($tipo === self::OTP_TIPO_CADASTRO) {
            $e = strtolower(trim((string) ($pending['cadastro_email'] ?? '')));
            $emailCad = filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : null;
        }

        $nomeLoja = trim((string) ($config->nome_loja ?: 'Loja'));
        $envio = $this->otpEntrega->entregar($nomeLoja, $unidadeId, $norm, $codigo, self::OTP_TTL_MINUTES, $emailCad);
        if (! $envio['ok']) {
            Cache::forget($ck);
            RateLimiter::clear($rateKey);

            return $this->voltar($slug)->with('warning', $this->msgFalha($envio));
        }

        $pending['canal'] = $envio['canal'] ?? $pending['canal'] ?? null;
        $pending['wa_me_url'] = $envio['wa_me_url'] ?? null;
        $request->session()->put(self::SESSION_OTP, $pending);

        return $this->voltar($slug)->with('status', $this->msgSucesso((string) ($envio['canal'] ?? ''), true, $tipo === self::OTP_TIPO_CADASTRO));
    }

    public function cancelarOtp(Request $request, string $slug): RedirectResponse
    {
        $config = $this->config($slug);
        $unidadeId = (int) $config->unidade_id;
        $pending = $request->session()->get(self::SESSION_OTP);
        if (is_array($pending) && (int) ($pending['unidade_id'] ?? 0) === $unidadeId && is_string($pending['tel_norm'] ?? null)) {
            $tipo = (string) ($pending['tipo'] ?? self::OTP_TIPO_CONSULTA);
            [$ck, $fk] = $this->chaves($unidadeId, $pending['tel_norm'], $tipo);
            Cache::forget($ck);
            Cache::forget($fk);
        }
        $request->session()->forget(self::SESSION_OTP);

        return $this->voltar($slug);
    }

    public function sair(Request $request, string $slug): RedirectResponse
    {
        $config = $this->config($slug);
        $unidadeId = (int) $config->unidade_id;

        $acesso = $request->session()->get(self::SESSION_ACESSO);
        if (is_array($acesso) && (int) ($acesso['unidade_id'] ?? 0) === $unidadeId) {
            $request->session()->forget(self::SESSION_ACESSO);
        }

        $pending = $request->session()->get(self::SESSION_OTP);
        if (is_array($pending) && (int) ($pending['unidade_id'] ?? 0) === $unidadeId) {
            if (is_string($pending['tel_norm'] ?? null)) {
                $tipo = (string) ($pending['tipo'] ?? self::OTP_TIPO_CONSULTA);
                [$ck, $fk] = $this->chaves($unidadeId, $pending['tel_norm'], $tipo);
                Cache::forget($ck);
                Cache::forget($fk);
            }
            $request->session()->forget(self::SESSION_OTP);
        }

        return $this->voltar($slug)->with('status', 'Você saiu da consulta. Informe outro telefone para ver outro cartão.');
    }

    public function verificarCodigo(Request $request, string $slug): RedirectResponse
    {
        $config = $this->config($slug);
        $programa = $this->programaAtivo((int) $config->unidade_id);
        if (! $programa) {
            return $this->voltar($slug)->with('warning', 'Programa de fidelidade indisponível.');
        }

        $unidadeId = (int) $config->unidade_id;
        $pending = session(self::SESSION_OTP);
        if (! is_array($pending) || (int) ($pending['unidade_id'] ?? 0) !== $unidadeId || ! is_string($pending['tel_norm'] ?? null)) {
            return $this->voltar($slug)->with('warning', 'Peça um novo código antes de continuar.');
        }

        $data = $request->validate(['codigo' => ['required', 'string', 'max:32']]);
        $digits = preg_replace('/\D+/', '', $data['codigo']) ?? '';
        if (strlen($digits) !== 6) {
            return back()->withErrors(['codigo' => 'Informe os 6 dígitos do código.'])->withInput();
        }

        $tipo = (string) ($pending['tipo'] ?? self::OTP_TIPO_CONSULTA);
        if ($tipo !== self::OTP_TIPO_CONSULTA && $tipo !== self::OTP_TIPO_CADASTRO) {
            $tipo = self::OTP_TIPO_CONSULTA;
        }

        $telNorm = $pending['tel_norm'];
        [$ck, $fk] = $this->chaves($unidadeId, $telNorm, $tipo);

        if ((int) Cache::get($fk, 0) >= 8) {
            return $this->voltar($slug)->with('warning', 'Muitas tentativas incorretas. Solicite um novo código.');
        }

        $esperado = Cache::get($ck);
        if (! is_string($esperado) || ! hash_equals($esperado, $digits)) {
            Cache::put($fk, (int) Cache::get($fk, 0) + 1, now()->addMinutes(self::OTP_TTL_MINUTES));

            return back()->withErrors(['codigo' => 'Código inválido ou expirado.'])->withInput();
        }

        Cache::forget($ck);
        Cache::forget($fk);

        if ($tipo === self::OTP_TIPO_CADASTRO) {
            $cpf = FidelidadeNormalizer::cpf((string) ($pending['cadastro_cpf'] ?? ''));
            $email = strtolower(trim((string) ($pending['cadastro_email'] ?? '')));
            if (! $cpf || ! FidelidadeNormalizer::cpfValido($cpf) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $request->session()->forget(self::SESSION_OTP);

                return $this->voltar($slug)->with('warning', 'Os dados do cadastro expiraram. Preencha o formulário novamente.');
            }
            $this->persistirCadastro($unidadeId, $telNorm, $cpf, $email, FidelidadeNormalizer::nome($pending['cadastro_nome'] ?? null));
            $request->session()->forget(self::SESSION_OTP);
            $request->session()->put(self::SESSION_ACESSO, [
                'unidade_id' => $unidadeId,
                'tel_norm' => $telNorm,
                'exp' => now()->addMinutes(self::ACESSO_TTL_MINUTES)->timestamp,
            ]);

            return $this->voltar($slug)->with('status', 'Cadastro confirmado! Abaixo você já vê os selos deste telefone.');
        }

        $request->session()->forget(self::SESSION_OTP);
        $request->session()->put(self::SESSION_ACESSO, [
            'unidade_id' => $unidadeId,
            'unidade_fidelidade_id' => (int) ($pending['unidade_fidelidade_id'] ?? $this->consulta->unidadeFidelidade($config)),
            'tel_norm' => $telNorm,
            'exp' => now()->addMinutes(self::ACESSO_TTL_MINUTES)->timestamp,
        ]);

        return $this->voltar($slug)->with('status', 'Telefone confirmado! Abaixo estão seus selos.');
    }

    public function cadastrar(Request $request, string $slug): RedirectResponse
    {
        $config = $this->config($slug);
        $programa = $this->programaAtivo((int) $config->unidade_id);
        if (! $programa) {
            return $this->voltar($slug)->with('warning', 'Programa de fidelidade indisponível.');
        }

        $data = $request->validate([
            'cadastro_telefone' => ['required', 'string', 'min:8', 'max:32'],
            'cadastro_cpf' => ['required', 'string', 'max:18'],
            'cadastro_email' => ['required', 'email', 'max:160'],
            'cadastro_nome' => ['nullable', 'string', 'max:160'],
        ]);

        $telNorm = FidelidadeNormalizer::telefone($data['cadastro_telefone']);
        if (strlen($telNorm) < 10) {
            return back()->withErrors(['cadastro_telefone' => 'Informe um telefone válido (DDD + número).'])->withInput();
        }
        $cpf = FidelidadeNormalizer::cpf($data['cadastro_cpf']);
        if (! $cpf || ! FidelidadeNormalizer::cpfValido($cpf)) {
            return back()->withErrors(['cadastro_cpf' => 'Informe um CPF válido.'])->withInput();
        }
        $email = strtolower(trim($data['cadastro_email']));
        $unidadeId = (int) $config->unidade_id;

        $cpfOutro = DB::table('fid_contas')
            ->where('unidade_id', $unidadeId)
            ->where('cpf_normalizado', $cpf)
            ->where('telefone_normalizado', '!=', $telNorm)
            ->exists();
        if ($cpfOutro) {
            return back()->withErrors(['cadastro_cpf' => 'Este CPF já está em outro telefone nesta loja.'])->withInput();
        }

        $rateKey = 'sas-fid-otp:'.$unidadeId.':'.$telNorm;
        if (RateLimiter::tooManyAttempts($rateKey, 4)) {
            $seg = RateLimiter::availableIn($rateKey);

            return back()->withErrors(['cadastro_telefone' => 'Aguarde '.max(1, $seg).' segundos para solicitar outro código.'])->withInput();
        }
        RateLimiter::hit($rateKey, 3600);

        $codigo = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        Cache::forget($this->keyOtp($unidadeId, $telNorm));
        Cache::forget($this->keyFalhas($unidadeId, $telNorm));
        Cache::put($this->keyOtpCadastro($unidadeId, $telNorm), $codigo, now()->addMinutes(self::OTP_TTL_MINUTES));
        Cache::forget($this->keyFalhasCadastro($unidadeId, $telNorm));

        $nomeLoja = trim((string) ($config->nome_loja ?: 'Loja'));
        $envio = $this->otpEntrega->entregar($nomeLoja, $unidadeId, $telNorm, $codigo, self::OTP_TTL_MINUTES, $email);
        if (! $envio['ok']) {
            Cache::forget($this->keyOtpCadastro($unidadeId, $telNorm));
            RateLimiter::clear($rateKey);

            return back()->withErrors(['cadastro_telefone' => $this->msgFalha($envio)])->withInput();
        }

        $request->session()->put(self::SESSION_OTP, [
            'unidade_id' => $unidadeId,
            'tel_norm' => $telNorm,
            'telefone_input' => $data['cadastro_telefone'],
            'cadastro_telefone' => $data['cadastro_telefone'],
            'cadastro_cpf' => $data['cadastro_cpf'],
            'cadastro_email' => $email,
            'cadastro_nome' => $data['cadastro_nome'] ?? null,
            'tipo' => self::OTP_TIPO_CADASTRO,
            'canal' => $envio['canal'] ?? FidelidadePublicOtpEntrega::CANAL_EMAIL,
            'wa_me_url' => $envio['wa_me_url'] ?? null,
        ]);

        return $this->voltar($slug)->with('status', $this->msgSucesso((string) ($envio['canal'] ?? ''), false, true));
    }

    private function persistirCadastro(int $unidadeId, string $telNorm, string $cpf, string $email, ?string $nome): void
    {
        $existente = DB::table('fid_contas')
            ->where('unidade_id', $unidadeId)
            ->where('telefone_normalizado', $telNorm)
            ->first();

        $agora = now();
        if ($existente) {
            DB::table('fid_contas')->where('id', $existente->id)->update([
                'cpf_normalizado' => $cpf,
                'email' => $email,
                'nome' => $nome ?: $existente->nome,
                'status' => 'ativo',
                'updated_at' => $agora,
            ]);

            return;
        }

        $id = DB::table('fid_contas')->insertGetId([
            'unidade_id' => $unidadeId,
            'telefone_normalizado' => $telNorm,
            'cpf_normalizado' => $cpf,
            'email' => $email,
            'nome' => $nome,
            'codigo_fidelidade' => FidelidadeCodigoService::gerar(),
            'status' => 'ativo',
            'saldo_selos' => 0,
            'saldo_pontos' => 0,
            'total_resgates' => 0,
            'origem_tipo' => 'vitrine',
            'origem_id' => null,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        $this->ledger->aplicar([
            'conta_id' => $id,
            'tipo' => 'geracao',
            'delta_selos' => 0,
            'delta_pontos' => 0,
            'descricao' => 'Cadastro pela vitrine',
            'idempotency_key' => 'geracao-conta-'.$id,
        ]);
    }

    private function acessoValido(int $unidadeId): ?array
    {
        $v = session(self::SESSION_ACESSO);
        if (! is_array($v) || (int) ($v['unidade_id'] ?? 0) !== $unidadeId) {
            return null;
        }
        if (time() > (int) ($v['exp'] ?? 0)) {
            session()->forget(self::SESSION_ACESSO);

            return null;
        }
        if (! is_string($v['tel_norm'] ?? null) || strlen($v['tel_norm']) < 8) {
            return null;
        }

        return $v;
    }

    private function otpPendenteValido(int $unidadeId): bool
    {
        $pending = session(self::SESSION_OTP);
        if (! is_array($pending) || (int) ($pending['unidade_id'] ?? 0) !== $unidadeId) {
            return false;
        }
        $tel = $pending['tel_norm'] ?? null;
        if (! is_string($tel) || strlen($tel) < 8) {
            session()->forget(self::SESSION_OTP);

            return false;
        }
        $tipo = (string) ($pending['tipo'] ?? self::OTP_TIPO_CONSULTA);
        [$ck] = $this->chaves($unidadeId, $tel, $tipo);
        $codigo = Cache::get($ck);
        if (! is_string($codigo) || strlen($codigo) !== 6) {
            session()->forget(self::SESSION_OTP);

            return false;
        }

        return true;
    }

    /** @return array{0:string,1:string} */
    private function chaves(int $unidadeId, string $telNorm, string $tipo): array
    {
        if ($tipo === self::OTP_TIPO_CADASTRO) {
            return [$this->keyOtpCadastro($unidadeId, $telNorm), $this->keyFalhasCadastro($unidadeId, $telNorm)];
        }

        return [$this->keyOtp($unidadeId, $telNorm), $this->keyFalhas($unidadeId, $telNorm)];
    }

    private function keyOtp(int $u, string $t): string
    {
        return 'sas_fid_otp:'.$u.':'.$t;
    }

    private function keyFalhas(int $u, string $t): string
    {
        return 'sas_fid_otp_falhas:'.$u.':'.$t;
    }

    private function keyOtpCadastro(int $u, string $t): string
    {
        return 'sas_fid_otp_cad:'.$u.':'.$t;
    }

    private function keyFalhasCadastro(int $u, string $t): string
    {
        return 'sas_fid_otp_falhas_cad:'.$u.':'.$t;
    }

    private function msgFalha(array $envio): string
    {
        return match ($envio['resultado'] ?? '') {
            FidelidadePublicOtpEntrega::FALHA_EMAIL => 'Não foi possível enviar o e-mail com o código. Tente mais tarde ou fale com a loja.',
            FidelidadePublicOtpEntrega::FALHA_SEM_DESTINO => 'Não há e-mail no cartão e o WhatsApp (link) não pôde ser aberto. Atualize o cadastro ou fale com a loja.',
            default => 'Não foi possível enviar o código. Tente mais tarde ou fale com a loja.',
        };
    }

    private function msgSucesso(string $canal, bool $reenvio, bool $cadastro = false): string
    {
        if ($cadastro) {
            if ($canal === FidelidadePublicOtpEntrega::CANAL_EMAIL) {
                return $reenvio
                    ? 'Enviamos um novo código para o e-mail do cadastro (confira spam). Digite-o para confirmar.'
                    : 'Enviamos o código de 6 dígitos para o e-mail informado. Digite-o abaixo para confirmar o cadastro.';
            }

            return $reenvio
                ? 'Geramos um novo código. Use o botão verde para abrir o WhatsApp e leia os 6 dígitos.'
                : 'Use o botão verde: ele abre o WhatsApp com o código na mensagem. Digite os 6 dígitos abaixo.';
        }

        if ($canal === FidelidadePublicOtpEntrega::CANAL_EMAIL) {
            return $reenvio
                ? 'Enviamos um novo código para o e-mail do seu cartão (confira spam). Digite-o para ver seus selos.'
                : 'Enviamos o código de 6 dígitos para o e-mail do seu cartão (confira spam). Digite-o abaixo.';
        }

        return $reenvio
            ? 'Geramos um novo código. Use o botão verde nesta página para abrir o WhatsApp com o texto pronto.'
            : 'Use o botão verde nesta página: ele abre o WhatsApp com o código na mensagem — leia e digite abaixo.';
    }

    private function voltar(string $slug): RedirectResponse
    {
        return redirect()->route('delivery.public.fidelity', ['slug' => $slug]);
    }

    private function programaAtivo(int $unidadeId): ?object
    {
        if (! Schema::hasTable('fid_programas')) {
            return null;
        }

        return DB::table('fid_programas')->where('unidade_id', $unidadeId)->where('ativo', 1)->first();
    }

    private function config(string $slug): object
    {
        $config = DB::table('dlv_loja_config')->where('slug', $slug)->where('ativo', 1)->first();
        abort_unless($config, 404);

        $defaults = [
            'logo_url' => null,
            'banner_url' => null,
            'filial_logo_url' => null,
            'aberta' => true,
            'cor_primaria' => '#2563eb',
            'endereco_texto' => null,
            'whatsapp' => null,
            'telefone' => null,
            'instagram_url' => null,
            'facebook_url' => null,
            'filial_nome' => null,
            'filial_link_url' => null,
            'entrega_texto' => null,
            'nome_loja' => 'Loja',
        ];

        foreach ($defaults as $key => $default) {
            if (! property_exists($config, $key)) {
                $config->{$key} = $default;
            }
        }

        $config->aberta = (bool) $config->aberta;
        $config->nome_loja = trim((string) ($config->nome_loja ?? '')) ?: 'Loja';

        return $config;
    }
}
