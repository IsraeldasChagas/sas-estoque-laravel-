<?php

namespace App\Services\Delivery;

use Illuminate\Support\Facades\Schema;

final class DeliveryLojaFreteHelper
{
    public function __construct(
        private readonly GeocodingService $geocoding,
        private readonly DeliveryFreteService $frete,
    ) {}

    /**
     * @return array{api_configurada: bool, rs_por_km: bool, origem: bool, pronto: bool}
     */
    public function googleChecklist(object $config): array
    {
        $api = filled(config('services.google_maps.api_key'));
        $rs = $this->rsPorKm($config) !== null;
        $origem = $this->origemEndereco($config) !== null;

        return [
            'api_configurada' => $api,
            'rs_por_km' => $rs,
            'origem' => $origem,
            'pronto' => $api && $rs && $origem,
        ];
    }

    /**
     * @return array{user_agent: bool, origem: bool, pronto: bool}
     */
    public function osrmChecklist(object $config): array
    {
        $ua = filled(trim((string) config('services.osm_routing.http_user_agent', '')));
        $coords = $this->coordenadasOrigemSalvas($config);
        $origemTexto = $this->origemEndereco($config) !== null;

        return [
            'user_agent' => $ua,
            'origem' => $coords !== null || $origemTexto,
            'pronto' => $ua && ($coords !== null || $origemTexto),
        ];
    }

    /**
     * @return array{lat: float, lon: float}|null
     */
    public function previewMapaOrigem(object $config): ?array
    {
        if ($this->frete->modoEfetivo($config) !== DeliveryFreteService::MODO_OSRM) {
            return null;
        }

        $coords = $this->coordenadasOrigemSalvas($config);
        if ($coords !== null) {
            return $coords;
        }

        $addr = $this->origemEndereco($config);
        if ($addr === null) {
            return null;
        }

        $geo = $this->geocoding->geocodeByQuery($addr);

        return $geo !== null ? ['lat' => $geo['lat'], 'lon' => $geo['lon']] : null;
    }

    /**
     * @return array{lat: float, lon: float, display_name?: string}|null
     */
    public function geocodeOrigem(?string $enderecoLoja, ?string $origemFrete, ?float $lat, ?float $lng): ?array
    {
        if ($lat !== null && $lng !== null && (float) $lat != 0.0 && (float) $lng != 0.0) {
            return ['lat' => (float) $lat, 'lon' => (float) $lng];
        }

        $addr = trim((string) ($origemFrete ?? ''));
        if ($addr === '') {
            $addr = trim((string) ($enderecoLoja ?? ''));
        }
        if ($addr === '') {
            return null;
        }

        return $this->geocoding->geocodeByQuery($addr);
    }

    /** @return array{lat: float, lon: float}|null */
    private function coordenadasOrigemSalvas(object $config): ?array
    {
        if (! Schema::hasColumn('dlv_loja_config', 'frete_entrega_lat_origem')
            || ! Schema::hasColumn('dlv_loja_config', 'frete_entrega_lng_origem')) {
            return null;
        }
        $lat = $config->frete_entrega_lat_origem ?? null;
        $lng = $config->frete_entrega_lng_origem ?? null;
        if ($lat === null || $lng === null || (float) $lat == 0.0 || (float) $lng == 0.0) {
            return null;
        }

        return ['lat' => (float) $lat, 'lon' => (float) $lng];
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

    private function rsPorKm(object $config): ?float
    {
        if (! Schema::hasColumn('dlv_loja_config', 'frete_google_rs_por_km')) {
            return null;
        }
        $v = $config->frete_google_rs_por_km ?? null;

        return $v !== null && (float) $v > 0 ? round((float) $v, 2) : null;
    }
}
