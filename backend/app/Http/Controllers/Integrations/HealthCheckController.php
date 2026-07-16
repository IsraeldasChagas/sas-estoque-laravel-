<?php

namespace App\Http\Controllers\Integrations;

use App\Models\Integration;
use App\Models\IntegrationLog;
use App\Services\Integrations\IntegrationManager;
use App\Services\Integrations\Providers\VendaFacilProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class HealthCheckController extends IntegrationBaseController
{
    public function __construct(
        private readonly IntegrationManager $manager,
    ) {}

    public function index(Request $request)
    {
        $u = $this->authUsuario($request);
        if (! $this->podeVisualizar($u)) {
            return $this->json(['error' => 'Não autorizado.'], 403);
        }

        $live = $request->boolean('live', true);
        $providers = [];

        if ($this->manager->has(VendaFacilProvider::CODE) && Schema::hasTable('integrations')) {
            $integration = Integration::query()->where('provider', VendaFacilProvider::CODE)->first();
            if ($integration) {
                $health = $this->manager->healthCheck(VendaFacilProvider::CODE, $integration, $live);
                $providers[] = array_merge($health, [
                    'name' => $integration->name,
                    'api_url' => $integration->api_url,
                    'is_active' => $integration->is_active,
                    'last_sync_at' => $integration->last_sync_at?->toIso8601String(),
                    'last_successful_connection_at' => $integration->last_successful_connection_at?->toIso8601String(),
                    'last_error' => $integration->last_error,
                    'consecutive_failures' => $integration->consecutive_failures ?? 0,
                ]);
            }
        }

        $online = collect($providers)->where('api_online', true)->count();

        return $this->json([
            'fase' => 2,
            'providers' => $providers,
            'resumo' => [
                'total' => count($providers),
                'online' => $online,
                'offline' => count($providers) - $online,
            ],
        ]);
    }
}
