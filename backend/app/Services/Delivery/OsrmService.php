<?php

namespace App\Services\Delivery;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class OsrmService
{
    private const CACHE_PREFIX = 'osrm_route_v4:';

    /**
     * @return array{distance_km: float, duration_seconds: float}|null
     */
    public function routeDriving(float $latOrigem, float $lonOrigem, float $latDestino, float $lonDestino): ?array
    {
        $coords = $lonOrigem.','.$latOrigem.';'.$lonDestino.','.$latDestino;
        $cacheKey = self::CACHE_PREFIX.md5($coords);
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $base = (string) config('services.osm_routing.osrm_base_url', '');
        if ($base === '') {
            Log::warning('osrm.config_sem_base_url');

            return null;
        }

        $url = rtrim($base, '/').'/route/v1/driving/'.$coords;
        $ua = trim((string) config('services.osm_routing.http_user_agent', ''));

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => $ua !== '' ? $ua : 'SASEstoque',
                    'Accept' => 'application/json',
                ])
                ->get($url, [
                    'overview' => 'false',
                ]);
        } catch (\Throwable $e) {
            Log::warning('osrm.http_excecao', ['message' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('osrm.http_erro', ['status' => $response->status()]);

            return null;
        }

        $json = $response->json();
        if (! is_array($json) || ($json['code'] ?? '') !== 'Ok') {
            Log::warning('osrm.resposta_nao_ok', ['code' => $json['code'] ?? null]);

            return null;
        }

        $routes = $json['routes'] ?? null;
        $route0 = is_array($routes) && isset($routes[0]) ? $routes[0] : null;
        if (! is_array($route0)) {
            return null;
        }

        $meters = (float) ($route0['distance'] ?? 0);
        if ($meters <= 0) {
            return null;
        }

        $duration = (float) ($route0['duration'] ?? 0);

        $out = [
            'distance_km' => round($meters / 1000, 3),
            'duration_seconds' => $duration,
        ];
        Cache::put($cacheKey, $out, now()->addDay());

        return $out;
    }
}
