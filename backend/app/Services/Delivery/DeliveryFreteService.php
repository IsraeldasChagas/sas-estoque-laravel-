<?php

namespace App\Services\Delivery;

use App\Support\Cep;
use App\Support\GoogleMapsDistanceMatrix;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DeliveryFreteService
{
    public const MODO_PADRAO = 'padrao_unico';
    public const MODO_FAIXAS = 'faixas_cep';
    public const MODO_GOOGLE = 'google_distancia';
    public const MODO_OSRM = 'osrm_distancia';

    public function __construct(
        private readonly GeocodingService $geocoding,
        private readonly OsrmService $osrm,
    ) {}

    public function calcular(int $unidadeId, array $payload): array
    {
        $config = DB::table('dlv_loja_config')->where('unidade_id', $unidadeId)->first();
        abort_unless($config, 422, 'Configuração da loja não encontrada.');

        $fulfillment = strtolower(trim((string) ($payload['fulfillment'] ?? 'entrega')));
        $subtotal = round((float) ($payload['subtotal'] ?? 0), 2);
        $chuva = array_key_exists('chuva', $payload)
            ? (bool) $payload['chuva']
            : (bool) ($config->frete_chuva_ativa ?? false);

        if ($fulfillment === 'retirada' || $fulfillment === 'pickup') {
            return $this->montarResposta($config, [
                'modo' => (string) ($config->frete_modo ?? self::MODO_PADRAO),
                'fulfillment' => 'retirada',
                'frete_base' => 0.0,
                'frete_valor' => 0.0,
                'frete_gratis' => false,
                'chuva' => false,
                'bloqueado' => false,
                'mensagem' => 'Retirada sem frete.',
                'rotulo' => 'Retirada no balcão',
            ]);
        }

        $modo = $this->modoEfetivo($config);
        $cliente = $this->normalizarCliente($payload);
        $resultado = match ($modo) {
            self::MODO_PADRAO => $this->calcularPadrao($config),
            self::MODO_FAIXAS => $this->calcularFaixasCep($config, $unidadeId, $cliente['cep8']),
            self::MODO_GOOGLE => $this->calcularGoogle($config, $cliente),
            self::MODO_OSRM => $this->calcularOsrm($config, $cliente, $subtotal),
            default => throw ValidationException::withMessages(['frete_modo' => 'Modo de frete não suportado.']),
        };

        return $this->finalizarResposta($config, $resultado, $subtotal, $chuva);
    }

    /**
     * Resumo leve para vitrine (estilo VendaFácil frete-resumo).
     *
     * @return array{taxa: float, rotulo: string, entrega_bloqueada: bool}
     */
    public function calcularResumo(object $config, ?string $cepDigits, ?float $subtotal = null): array
    {
        $modo = $this->modoEfetivo($config);
        $cep8 = Cep::normalizar8($cepDigits);
        $cliente = ['cep8' => $cep8, 'cep' => $cep8];

        $resultado = match ($modo) {
            self::MODO_PADRAO => $this->calcularPadrao($config),
            self::MODO_FAIXAS => $cep8 === null
                ? [
                    'frete_base' => $this->taxaPadrao($config),
                    'frete_valor' => $this->taxaPadrao($config),
                    'bloqueado' => false,
                    'mensagem' => 'Taxa padrão (informe o CEP para usar faixa)',
                    'rotulo' => 'Taxa padrão (informe o CEP para usar faixa)',
                ]
                : $this->calcularFaixasCep($config, (int) $config->unidade_id, $cep8),
            self::MODO_GOOGLE => $cep8 === null
                ? [
                    'frete_base' => $this->taxaPadrao($config),
                    'frete_valor' => $this->taxaPadrao($config),
                    'bloqueado' => false,
                    'mensagem' => 'Informe o CEP para calcular o frete (Google Maps)',
                    'rotulo' => 'Informe o CEP para calcular o frete (Google Maps)',
                ]
                : $this->calcularGoogle($config, array_merge($cliente, ['cep' => $cep8])),
            self::MODO_OSRM => $cep8 === null
                ? [
                    'frete_base' => $this->taxaPadrao($config),
                    'frete_valor' => $this->taxaPadrao($config),
                    'bloqueado' => false,
                    'mensagem' => 'Informe o CEP para calcular o frete (OpenStreetMap / OSRM)',
                    'rotulo' => 'Informe o CEP para calcular o frete (OpenStreetMap / OSRM)',
                ]
                : $this->calcularOsrm($config, array_merge($cliente, ['cep' => $cep8]), $subtotal),
            default => [
                'frete_base' => $this->taxaPadrao($config),
                'frete_valor' => $this->taxaPadrao($config),
                'bloqueado' => false,
                'mensagem' => 'Taxa padrão da loja',
                'rotulo' => 'Taxa padrão da loja',
            ],
        };

        $final = $this->finalizarResposta($config, $resultado, $subtotal ?? 0.0, (bool) ($config->frete_chuva_ativa ?? false));

        return [
            'taxa' => (float) $final['frete_valor'],
            'rotulo' => (string) ($final['rotulo'] ?? $final['mensagem'] ?? ''),
            'entrega_bloqueada' => (bool) ($final['bloqueado'] ?? false),
        ];
    }

    /**
     * Cálculo OSRM detalhado (estilo VendaFácil /api/calcular-entrega).
     *
     * @param  array{cep?:string, rua?:string, numero?:string, bairro?:string, cidade?:string, estado?:string}  $cliente
     * @return array<string, mixed>
     */
    public function calcularOsrmDetalhado(object $config, array $cliente, ?float $subtotalPedido = null): array
    {
        $padrao = $this->taxaPadrao($config);
        $ua = trim((string) config('services.osm_routing.http_user_agent', ''));
        if ($ua === '') {
            return $this->erroOsrm($padrao, 'Servidor sem OSM_HTTP_USER_AGENT (Nominatim/OSRM).', false);
        }

        $origem = $this->resolverCoordenadasOrigem($config);
        if ($origem === null) {
            return $this->erroOsrm($padrao, 'Coordenadas de origem da loja não configuradas ou endereço não localizado. Ajuste em Configurações.', false);
        }

        $cep8 = Cep::normalizar8($cliente['cep'] ?? null);
        if ($cep8 === null) {
            return $this->erroOsrm($padrao, 'Informe um CEP válido com 8 dígitos.', false);
        }

        $clienteNorm = [
            'cep' => substr($cep8, 0, 5).'-'.substr($cep8, 5),
            'rua' => trim((string) ($cliente['rua'] ?? '')),
            'numero' => trim((string) ($cliente['numero'] ?? '')),
            'bairro' => trim((string) ($cliente['bairro'] ?? '')),
            'cidade' => trim((string) ($cliente['cidade'] ?? '')),
            'estado' => trim((string) ($cliente['estado'] ?? '')),
        ];

        $geoCliente = $this->geocoding->geocodeClienteEndereco($clienteNorm);
        if ($geoCliente === null) {
            return [
                'success' => false,
                'message' => 'Não foi possível localizar o endereço informado.',
                'taxa_entrega' => $padrao,
                'rotulo' => 'Frete R$ '.number_format($padrao, 2, ',', '.').' (taxa base) — endereço não localizado no mapa.',
                'entrega_bloqueada' => false,
            ];
        }

        $rota = $this->osrm->routeDriving(
            $origem['lat'],
            $origem['lon'],
            $geoCliente['lat'],
            $geoCliente['lon']
        );

        if ($rota === null) {
            return $this->erroOsrm($padrao, 'Não foi possível calcular a rota até o endereço. Tente novamente em instantes.', false);
        }

        $distKm = $rota['distance_km'];
        $kmMax = $this->kmMax($config);
        if ($kmMax !== null && $distKm > $kmMax) {
            return [
                'success' => true,
                'distancia_km' => $distKm,
                'tempo_minutos' => (int) round($rota['duration_seconds'] / 60),
                'taxa_entrega' => 0.0,
                'endereco_formatado' => (string) ($geoCliente['display_name'] ?? $this->geocoding->montarQueryCliente($clienteNorm)),
                'lat_cliente' => $geoCliente['lat'],
                'lng_cliente' => $geoCliente['lon'],
                'rotulo' => 'Fora da área de entrega (máx. '.number_format((float) $kmMax, 1, ',', '.').' km)',
                'entrega_bloqueada' => true,
            ];
        }

        $taxa = $this->precificarKmIncluso($config, $distKm, $subtotalPedido);
        $minutos = max(1, (int) round($rota['duration_seconds'] / 60));
        $endFmt = (string) ($geoCliente['display_name'] ?? $this->geocoding->montarQueryCliente($clienteNorm));

        $kmInc = $this->kmIncluso($config);
        $vExtra = $this->valorKmExtra($config);
        $extraKm = max(0.0, $distKm - $kmInc);
        $unidades = (int) ceil($extraKm);
        $gratisAcima = $this->gratisAcima($config);
        $ehGratis = $taxa <= 0.0001 && $gratisAcima !== null && $subtotalPedido !== null && $subtotalPedido >= $gratisAcima;

        $rotulo = 'Entrega ~'.number_format($distKm, 1, ',', '.').' km, ~'.$minutos.' min';
        if ($ehGratis) {
            $rotulo .= ' — entrega grátis (pedido ≥ R$ '.number_format($gratisAcima, 2, ',', '.').')';
        } else {
            $rotulo .= ' — base R$ '.number_format($this->taxaPadrao($config), 2, ',', '.');
            $rotulo .= ' + '.$unidades.' × R$ '.number_format($vExtra, 2, ',', '.').' (km acima de '.number_format($kmInc, 1, ',', '.').' km)';
        }

        return [
            'success' => true,
            'distancia_km' => $distKm,
            'tempo_minutos' => $minutos,
            'taxa_entrega' => round($taxa, 2),
            'endereco_formatado' => $endFmt,
            'lat_cliente' => $geoCliente['lat'],
            'lng_cliente' => $geoCliente['lon'],
            'rotulo' => $rotulo,
            'entrega_bloqueada' => false,
        ];
    }

    public function modoEfetivo(object $config): string
    {
        $modo = strtolower(trim((string) ($config->frete_modo ?? self::MODO_PADRAO)));

        return match ($modo) {
            'fixed', 'padrao_unico' => self::MODO_PADRAO,
            'cep_band', 'faixas_cep' => self::MODO_FAIXAS,
            'google_distancia' => self::MODO_GOOGLE,
            'osrm_distancia' => self::MODO_OSRM,
            default => self::MODO_PADRAO,
        };
    }

    private function calcularPadrao(object $config): array
    {
        $taxa = $this->taxaPadrao($config);

        return [
            'frete_base' => $taxa,
            'frete_valor' => $taxa,
            'bloqueado' => false,
            'mensagem' => 'Frete taxa fixa.',
            'rotulo' => 'Taxa fixa da loja (modo sem faixas)',
        ];
    }

    private function calcularFaixasCep(object $config, int $unidadeId, ?string $cep8): array
    {
        $padrao = $this->taxaPadrao($config);
        if ($cep8 === null) {
            throw ValidationException::withMessages(['cep' => 'CEP inválido para cálculo de frete.']);
        }

        $faixa = DB::table('dlv_frete_faixas_cep')
            ->where('unidade_id', $unidadeId)
            ->where('ativo', 1)
            ->where('cep_inicio', '<=', $cep8)
            ->where('cep_fim', '>=', $cep8)
            ->orderBy('ordem')
            ->orderBy('id')
            ->first();

        if (! $faixa) {
            return [
                'frete_base' => $padrao,
                'frete_valor' => $padrao,
                'bloqueado' => false,
                'mensagem' => 'Taxa padrão da loja (CEP fora das faixas cadastradas).',
                'rotulo' => 'Taxa padrão da loja',
            ];
        }

        $taxa = round((float) $faixa->taxa, 2);

        return [
            'frete_base' => $taxa,
            'frete_valor' => $taxa,
            'bloqueado' => false,
            'mensagem' => $faixa->label ?: 'Frete por faixa de CEP.',
            'rotulo' => $faixa->label ?: 'Faixa de CEP',
        ];
    }

    /** @param array{cep8:?string, cep:?string, rua:?string, numero:?string, bairro:?string, cidade:?string, estado:?string} $cliente */
    private function calcularGoogle(object $config, array $cliente): array
    {
        $padrao = $this->taxaPadrao($config);
        $cep8 = $cliente['cep8'] ?? Cep::normalizar8($cliente['cep'] ?? null);
        if ($cep8 === null) {
            return [
                'frete_base' => $padrao,
                'frete_valor' => $padrao,
                'bloqueado' => false,
                'mensagem' => 'Informe o CEP para calcular o frete (Google Maps)',
                'rotulo' => 'Informe o CEP para calcular o frete (Google Maps)',
            ];
        }

        $apiKey = config('services.google_maps.api_key');
        $origem = $this->origemEndereco($config);
        $rsKm = $this->rsPorKm($config);
        $destino = $this->destinoGoogle($cliente, $cep8);

        if (! filled($apiKey)) {
            return $this->fallbackGoogle($padrao, 'Taxa padrão (Google Maps: configure GOOGLE_MAPS_API_KEY no servidor)');
        }
        if ($origem === null) {
            return $this->fallbackGoogle($padrao, 'Taxa padrão (informe o endereço de origem nas configurações da loja)');
        }
        if ($rsKm === null) {
            return $this->fallbackGoogle($padrao, 'Taxa padrão (defina R$ por km nas configurações)');
        }

        $km = GoogleMapsDistanceMatrix::distanciaKmRodoviaria($origem, $destino, is_string($apiKey) ? $apiKey : null);
        if ($km === null) {
            return $this->fallbackGoogle($padrao, 'Taxa padrão (rota indisponível — verifique CEP/endereço ou tente depois)');
        }

        return $this->precificarGoogle($config, $km, $padrao, $rsKm);
    }

    /** @param array<string, mixed> $cliente */
    private function calcularOsrm(object $config, array $cliente, ?float $subtotal): array
    {
        $detalhe = $this->calcularOsrmDetalhado($config, [
            'cep' => $cliente['cep'] ?? $cliente['cep8'] ?? null,
            'rua' => $cliente['rua'] ?? null,
            'numero' => $cliente['numero'] ?? null,
            'bairro' => $cliente['bairro'] ?? null,
            'cidade' => $cliente['cidade'] ?? null,
            'estado' => $cliente['estado'] ?? null,
        ], $subtotal);

        if (! ($detalhe['success'] ?? false)) {
            return [
                'frete_base' => (float) ($detalhe['taxa_entrega'] ?? $this->taxaPadrao($config)),
                'frete_valor' => (float) ($detalhe['taxa_entrega'] ?? $this->taxaPadrao($config)),
                'bloqueado' => false,
                'mensagem' => (string) ($detalhe['message'] ?? $detalhe['rotulo'] ?? 'Não foi possível calcular o frete.'),
                'rotulo' => (string) ($detalhe['rotulo'] ?? $detalhe['message'] ?? 'Frete por rota'),
                'distancia_km' => $detalhe['distancia_km'] ?? null,
                'tempo_minutos' => $detalhe['tempo_minutos'] ?? null,
            ];
        }

        return [
            'frete_base' => (float) ($detalhe['taxa_entrega'] ?? 0),
            'frete_valor' => (float) ($detalhe['taxa_entrega'] ?? 0),
            'bloqueado' => (bool) ($detalhe['entrega_bloqueada'] ?? false),
            'mensagem' => (string) ($detalhe['rotulo'] ?? 'Frete por rota'),
            'rotulo' => (string) ($detalhe['rotulo'] ?? 'Frete por rota'),
            'distancia_km' => $detalhe['distancia_km'] ?? null,
            'tempo_minutos' => $detalhe['tempo_minutos'] ?? null,
        ];
    }

    /** @param array<string, mixed> $resultado */
    private function finalizarResposta(object $config, array $resultado, float $subtotal, bool $chuva): array
    {
        $gratisAcima = $this->gratisAcima($config);
        $bloqueado = (bool) ($resultado['bloqueado'] ?? false);
        $freteBase = round((float) ($resultado['frete_base'] ?? 0), 2);
        $freteGratis = ! $bloqueado && $gratisAcima !== null && $subtotal >= $gratisAcima;
        $freteValor = ($bloqueado || $freteGratis) ? 0.0 : round((float) ($resultado['frete_valor'] ?? $freteBase), 2);

        $mensagem = (string) ($resultado['mensagem'] ?? '');
        if ($freteGratis && $gratisAcima !== null) {
            $mensagem = trim($mensagem.' Entrega grátis (pedido ≥ R$ '.number_format($gratisAcima, 2, ',', '.').').');
        }

        $chuvaPercent = (float) ($config->frete_acrescimo_chuva_percent ?? 0);
        if (! $bloqueado && ! $freteGratis && $chuva && $chuvaPercent > 0 && $freteValor > 0) {
            $freteValor = round($freteValor * (1 + ($chuvaPercent / 100)), 2);
            $mensagem = trim($mensagem.' Acréscimo chuva aplicado.');
        }

        return $this->montarResposta($config, array_merge($resultado, [
            'modo' => (string) ($config->frete_modo ?? $this->modoEfetivo($config)),
            'fulfillment' => 'entrega',
            'frete_base' => $freteBase,
            'frete_valor' => $freteValor,
            'frete_gratis' => $freteGratis,
            'chuva' => $chuva && ! $freteGratis && ! $bloqueado,
            'bloqueado' => $bloqueado,
            'mensagem' => $mensagem,
            'rotulo' => $resultado['rotulo'] ?? $mensagem,
        ]));
    }

    /** @param array<string, mixed> $dados */
    private function montarResposta(object $config, array $dados): array
    {
        return array_merge([
            'modo' => $this->modoEfetivo($config),
            'fulfillment' => 'entrega',
            'frete_base' => 0.0,
            'frete_valor' => 0.0,
            'frete_gratis' => false,
            'chuva' => false,
            'bloqueado' => false,
            'mensagem' => null,
            'rotulo' => null,
            'distancia_km' => null,
            'tempo_minutos' => null,
        ], $dados);
    }

    private function normalizarCliente(array $payload): array
    {
        $cepRaw = $payload['cep']
            ?? $payload['endereco_cep']
            ?? null;
        $cep8 = Cep::normalizar8($cepRaw);

        return [
            'cep8' => $cep8,
            'cep' => $cep8,
            'rua' => trim((string) ($payload['rua'] ?? $payload['logradouro'] ?? $payload['endereco_rua'] ?? '')),
            'numero' => trim((string) ($payload['numero'] ?? $payload['endereco_numero'] ?? '')),
            'bairro' => trim((string) ($payload['bairro'] ?? $payload['endereco_bairro'] ?? '')),
            'cidade' => trim((string) ($payload['cidade'] ?? $payload['endereco_cidade'] ?? '')),
            'estado' => trim((string) ($payload['estado'] ?? $payload['uf'] ?? $payload['endereco_uf'] ?? '')),
        ];
    }

    private function taxaPadrao(object $config): float
    {
        return round((float) ($config->frete_taxa_fixa ?? 0), 2);
    }

    private function gratisAcima(object $config): ?float
    {
        return $config->frete_gratis_acima !== null ? (float) $config->frete_gratis_acima : null;
    }

    private function rsPorKm(object $config): ?float
    {
        if (! Schema::hasColumn('dlv_loja_config', 'frete_google_rs_por_km')) {
            return null;
        }
        $v = $config->frete_google_rs_por_km ?? null;

        return $v !== null && (float) $v > 0 ? round((float) $v, 2) : null;
    }

    private function taxaMinimaGoogle(object $config): ?float
    {
        if (! Schema::hasColumn('dlv_loja_config', 'frete_google_taxa_minima')) {
            return null;
        }
        $v = $config->frete_google_taxa_minima ?? null;

        return $v !== null && (float) $v > 0 ? round((float) $v, 2) : null;
    }

    private function kmMax(object $config): ?float
    {
        if (! Schema::hasColumn('dlv_loja_config', 'frete_google_km_max')) {
            return null;
        }
        $v = $config->frete_google_km_max ?? null;

        return $v !== null && (float) $v > 0 ? round((float) $v, 2) : null;
    }

    private function kmIncluso(object $config): float
    {
        if (Schema::hasColumn('dlv_loja_config', 'frete_km_incluso') && $config->frete_km_incluso !== null) {
            return max(0.0, (float) $config->frete_km_incluso);
        }

        return 0.0;
    }

    private function valorKmExtra(object $config): float
    {
        if (Schema::hasColumn('dlv_loja_config', 'frete_valor_km_extra') && $config->frete_valor_km_extra !== null) {
            return max(0.0, round((float) $config->frete_valor_km_extra, 2));
        }
        if ($this->rsPorKm($config) !== null) {
            return $this->rsPorKm($config);
        }

        return 0.0;
    }

    private function origemEndereco(object $config): ?string
    {
        if (Schema::hasColumn('dlv_loja_config', 'frete_origem_endereco')) {
            $o = trim((string) ($config->frete_origem_endereco ?? ''));
            if ($o !== '') {
                return $o;
            }
        }

        $end = trim((string) ($config->endereco_texto ?? ''));

        return $end !== '' ? $end : null;
    }

    /** @param array<string, mixed> $cliente */
    private function destinoGoogle(array $cliente, string $cep8): string
    {
        $rua = trim((string) ($cliente['rua'] ?? ''));
        if ($rua !== '') {
            $cepFmt = substr($cep8, 0, 5).'-'.substr($cep8, 5);

            return $rua.', '.$cepFmt.', Brasil';
        }

        return substr($cep8, 0, 5).'-'.substr($cep8, 5).', Brasil';
    }

    private function fallbackGoogle(float $padrao, string $rotulo): array
    {
        return [
            'frete_base' => $padrao,
            'frete_valor' => $padrao,
            'bloqueado' => false,
            'mensagem' => $rotulo,
            'rotulo' => $rotulo,
        ];
    }

    private function precificarGoogle(object $config, float $km, float $padrao, float $rsKm): array
    {
        $kmMax = $this->kmMax($config);
        if ($kmMax !== null && $km > $kmMax) {
            return [
                'frete_base' => 0.0,
                'frete_valor' => 0.0,
                'bloqueado' => true,
                'mensagem' => 'Fora da área de entrega (máx. '.number_format($kmMax, 1, ',', '.').' km)',
                'rotulo' => 'Fora da área de entrega (máx. '.number_format($kmMax, 1, ',', '.').' km)',
                'distancia_km' => $km,
            ];
        }

        $bruto = $km * $rsKm;
        $min = $this->taxaMinimaGoogle($config);
        if ($min !== null && $bruto < $min) {
            $bruto = $min;
        }

        $rotulo = 'Google Maps (~'.number_format($km, 1, ',', '.').' km × R$ '.number_format($rsKm, 2, ',', '.').'/km)';

        return [
            'frete_base' => round($bruto, 2),
            'frete_valor' => round($bruto, 2),
            'bloqueado' => false,
            'mensagem' => $rotulo,
            'rotulo' => $rotulo,
            'distancia_km' => $km,
        ];
    }

    private function precificarKmIncluso(object $config, float $distKm, ?float $subtotalPedido): float
    {
        $gratisAcima = $this->gratisAcima($config);
        if ($gratisAcima !== null && $gratisAcima > 0 && $subtotalPedido !== null && $subtotalPedido >= $gratisAcima) {
            return 0.0;
        }

        $taxaBase = $this->taxaPadrao($config);
        $kmIncluso = $this->kmIncluso($config);
        $valorKmExtra = $this->valorKmExtra($config);
        $extraKm = max(0.0, $distKm - $kmIncluso);
        $unidades = (int) ceil($extraKm);

        return $taxaBase + ($unidades * $valorKmExtra);
    }

    /**
     * @return array{lat: float, lon: float}|null
     */
    private function resolverCoordenadasOrigem(object $config): ?array
    {
        if (Schema::hasColumn('dlv_loja_config', 'frete_entrega_lat_origem')
            && Schema::hasColumn('dlv_loja_config', 'frete_entrega_lng_origem')) {
            $lat = $config->frete_entrega_lat_origem;
            $lng = $config->frete_entrega_lng_origem;
            if ($lat !== null && $lng !== null && (float) $lat != 0.0 && (float) $lng != 0.0) {
                return ['lat' => (float) $lat, 'lon' => (float) $lng];
            }
        }

        $tentativas = [];
        $textoPrincipal = $this->origemEndereco($config);
        if ($textoPrincipal !== null) {
            $tentativas[] = $textoPrincipal;
        }
        $nome = trim((string) ($config->nome_loja ?? ''));
        if ($nome !== '' && $textoPrincipal !== null) {
            $tentativas[] = $nome.', '.$textoPrincipal;
        }

        foreach ($tentativas as $query) {
            $geo = $this->geocoding->geocodeByQuery($query);
            if ($geo !== null) {
                return ['lat' => $geo['lat'], 'lon' => $geo['lon']];
            }
        }

        Log::info('delivery_frete.origem_nao_resolvida', ['unidade_id' => $config->unidade_id ?? null]);

        return null;
    }

    /** @return array<string, mixed> */
    private function erroOsrm(float $padrao, string $message, bool $bloqueado): array
    {
        return [
            'success' => false,
            'message' => $message,
            'taxa_entrega' => $padrao,
            'rotulo' => 'Frete R$ '.number_format($padrao, 2, ',', '.').' (taxa base)',
            'entrega_bloqueada' => $bloqueado,
        ];
    }
}
