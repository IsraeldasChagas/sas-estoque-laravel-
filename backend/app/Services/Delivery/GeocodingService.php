<?php

namespace App\Services\Delivery;

use App\Support\Cep;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GeocodingService
{
    private const CACHE_PREFIX = 'nominatim_geo_v4:';

    /**
     * @return array{lat: float, lon: float, display_name?: string}|null
     */
    public function geocodeByQuery(string $query): ?array
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        $cacheKey = self::CACHE_PREFIX.md5($query);
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $base = (string) config('services.osm_routing.nominatim_base_url', '');
        $ua = trim((string) config('services.osm_routing.http_user_agent', ''));
        if ($base === '' || $ua === '') {
            Log::warning('geocoding.config_incompleta', ['nominatim' => $base === '', 'user_agent' => $ua === '']);

            return null;
        }

        $headers = [
            'User-Agent' => $ua,
            'Accept' => 'application/json',
        ];

        $first = $this->primeiroResultadoNominatim($base, $headers, [
            'q' => $query,
            'format' => 'json',
            'limit' => 1,
        ]);

        if ($first === null) {
            $cep8Extraido = $this->extrairCepBrasil8DaString($query);
            if ($cep8Extraido !== null) {
                $first = $this->geocodeCepBrasilFallback($base, $headers, $cep8Extraido);
            }
        }

        if ($first === null) {
            return null;
        }

        $lat = isset($first['lat']) ? (float) $first['lat'] : null;
        $lon = isset($first['lon']) ? (float) $first['lon'] : null;
        if ($lat === null || $lon === null) {
            return null;
        }

        $out = [
            'lat' => $lat,
            'lon' => $lon,
            'display_name' => isset($first['display_name']) && is_string($first['display_name']) ? $first['display_name'] : null,
        ];
        Cache::put($cacheKey, $out, now()->addDays(7));

        return $out;
    }

    /**
     * @param  array{cep?:string, rua?:string, numero?:string, bairro?:string, cidade?:string, estado?:string}  $p
     */
    public function montarQueryCliente(array $p): string
    {
        $cep = trim((string) ($p['cep'] ?? ''));
        $cepDigits = preg_replace('/\D+/', '', $cep);
        $cepFmt = strlen($cepDigits) === 8
            ? substr($cepDigits, 0, 5).'-'.substr($cepDigits, 5)
            : '';

        $rua = trim((string) ($p['rua'] ?? ''));
        $numero = trim((string) ($p['numero'] ?? ''));
        $bairro = trim((string) ($p['bairro'] ?? ''));
        $cidade = trim((string) ($p['cidade'] ?? ''));
        $estado = trim((string) ($p['estado'] ?? ''));

        if ($cepFmt !== '') {
            $ruaLen = function_exists('mb_strlen') ? mb_strlen($rua, 'UTF-8') : strlen($rua);
            if ($rua === '' || $ruaLen < 3) {
                return $cepFmt.', Brasil';
            }
        }

        $linha1 = trim(implode(', ', array_filter([
            trim($rua.($numero !== '' ? ', '.$numero : '')),
            $bairro !== '' ? $bairro : null,
        ], fn ($x) => $x !== null && $x !== '')));

        $loc = trim(implode(' — ', array_filter([
            $cidade !== '' && $estado !== '' ? $cidade.' - '.$estado : ($cidade !== '' ? $cidade : $estado),
        ], fn ($x) => $x !== null && $x !== '')));

        $partes = array_filter([$linha1, $loc, $cepFmt !== '' ? $cepFmt : null, 'Brasil']);

        return implode(', ', $partes);
    }

    /**
     * @param  array{cep?:string, rua?:string, numero?:string, bairro?:string, cidade?:string, estado?:string}  $p
     * @return array{lat: float, lon: float, display_name?: string}|null
     */
    public function geocodeClienteEndereco(array $p): ?array
    {
        $q = $this->montarQueryCliente($p);
        if (trim($q) === '' || $q === 'Brasil') {
            return null;
        }

        return $this->geocodeByQuery($q);
    }

    /**
     * @param  array<string, scalar|null>  $params
     * @return array<string, mixed>|null
     */
    private function primeiroResultadoNominatim(string $base, array $headers, array $params): ?array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders($headers)
                ->get(rtrim($base, '/').'/search', $params);
        } catch (\Throwable $e) {
            Log::warning('geocoding.nominatim_excecao', ['message' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('geocoding.nominatim_http', ['status' => $response->status()]);

            return null;
        }

        $json = $response->json();
        if (! is_array($json) || $json === []) {
            return null;
        }

        $first = $json[0];

        return is_array($first) ? $first : null;
    }

    private function extrairCepBrasil8DaString(string $query): ?string
    {
        if (preg_match('/\b(\d{5})-?(\d{3})\b/', $query, $m)) {
            return Cep::normalizar8($m[1].$m[2]);
        }

        $digits = preg_replace('/\D+/', '', $query);

        return strlen($digits) === 8 ? Cep::normalizar8($digits) : null;
    }

    private function geocodeCepBrasilFallback(string $base, array $headers, string $cep8): ?array
    {
        $cepFmt = substr($cep8, 0, 5).'-'.substr($cep8, 5);

        $try = $this->primeiroResultadoNominatim($base, $headers, [
            'postalcode' => $cepFmt,
            'countrycodes' => 'br',
            'format' => 'json',
            'limit' => 1,
        ]);
        if ($try !== null) {
            return $try;
        }

        $try = $this->primeiroResultadoNominatim($base, $headers, [
            'postalcode' => $cep8,
            'countrycodes' => 'br',
            'format' => 'json',
            'limit' => 1,
        ]);
        if ($try !== null) {
            return $try;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => $headers['User-Agent'] ?? 'SASEstoque',
                    'Accept' => 'application/json',
                ])
                ->get('https://viacep.com.br/ws/'.$cep8.'/json/');
        } catch (\Throwable $e) {
            Log::warning('geocoding.viacep_excecao', ['message' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        if (! is_array($data)) {
            return null;
        }

        $erroVia = $data['erro'] ?? false;
        if ($erroVia === true || $erroVia === 'true') {
            return null;
        }

        $cidade = trim((string) ($data['localidade'] ?? ''));
        $uf = trim((string) ($data['uf'] ?? ''));
        if ($cidade === '' || $uf === '') {
            return null;
        }

        $partes = [];
        $log = trim((string) ($data['logradouro'] ?? ''));
        $bai = trim((string) ($data['bairro'] ?? ''));
        if ($log !== '') {
            $partes[] = $log;
        }
        if ($bai !== '') {
            $partes[] = $bai;
        }
        $partes[] = $cidade;
        $partes[] = $uf;
        $partes[] = 'Brasil';
        $q = implode(', ', $partes);

        return $this->primeiroResultadoNominatim($base, $headers, [
            'q' => $q,
            'format' => 'json',
            'limit' => 1,
        ]);
    }
}
