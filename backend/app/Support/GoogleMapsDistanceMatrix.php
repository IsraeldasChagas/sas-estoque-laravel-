<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class GoogleMapsDistanceMatrix
{
    /** Distância em km pela rota (modo driving), ou null se indisponível. */
    public static function distanciaKmRodoviaria(string $origem, string $destino, ?string $apiKey): ?float
    {
        $apiKey = $apiKey !== null ? trim($apiKey) : '';
        $origem = trim($origem);
        $destino = trim($destino);
        if ($apiKey === '' || $origem === '' || $destino === '') {
            return null;
        }

        $cacheKey = 'gmaps_dm_v1:'.md5($origem.'|'.$destino);
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $response = Http::timeout(15)->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
            'origins' => $origem,
            'destinations' => $destino,
            'units' => 'metric',
            'mode' => 'driving',
            'key' => $apiKey,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json();
        if (($json['status'] ?? '') !== 'OK') {
            return null;
        }

        $row = $json['rows'][0] ?? null;
        $el = is_array($row) ? ($row['elements'][0] ?? null) : null;
        if (! is_array($el) || ($el['status'] ?? '') !== 'OK') {
            return null;
        }

        $meters = (int) ($el['distance']['value'] ?? 0);
        if ($meters <= 0) {
            return null;
        }

        $km = round($meters / 1000, 3);
        Cache::put($cacheKey, $km, now()->addDay());

        return $km;
    }
}
