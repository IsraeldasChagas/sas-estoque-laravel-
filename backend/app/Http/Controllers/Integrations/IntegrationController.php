<?php

namespace App\Http\Controllers\Integrations;

use App\Services\Integrations\IntegrationManager;
use App\Services\Integrations\Providers\VendaFacilProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class IntegrationController extends IntegrationBaseController
{
    public function __construct(
        private readonly IntegrationManager $manager,
    ) {}

    /** Catálogo de aplicações conectadas (cards de exemplo + status local). */
    public function aplicacoes(Request $request)
    {
        $u = $this->authUsuario($request);
        if (! $this->podeVisualizar($u)) {
            return $this->json(['error' => 'Não autorizado.'], 403);
        }

        $integracoes = [];
        if (Schema::hasTable('integrations')) {
            $integracoes = \App\Models\Integration::query()->get(['provider', 'name', 'connection_status', 'is_active', 'last_sync_at']);
        }

        $statusPorProvider = [];
        foreach ($integracoes as $row) {
            $statusPorProvider[$row->provider] = [
                'connection_status' => $row->connection_status,
                'is_active' => $row->is_active,
                'last_sync_at' => $row->last_sync_at?->toIso8601String(),
            ];
        }

        $catalogo = [
            [
                'code' => VendaFacilProvider::CODE,
                'name' => 'VendaFácil',
                'description' => 'PDV, delivery, vendas e fiscal comerciais.',
                'icon' => '🛒',
                'implemented' => true,
                'section' => 'integracaoVendafacil',
            ],
            [
                'code' => 'openclaw',
                'name' => 'OpenClaw',
                'description' => 'Assistente IA via WhatsApp (configuração em Configurações).',
                'icon' => '🦞',
                'implemented' => true,
                'section' => 'openClawIntegracao',
            ],
            [
                'code' => 'whatsapp',
                'name' => 'WhatsApp',
                'description' => 'Canal de mensagens — expansão futura.',
                'icon' => '💬',
                'implemented' => false,
                'section' => null,
            ],
            [
                'code' => 'ifood',
                'name' => 'iFood',
                'description' => 'Marketplace de delivery — expansão futura.',
                'icon' => '🍔',
                'implemented' => false,
                'section' => null,
            ],
            [
                'code' => 'marketplace',
                'name' => 'Marketplace',
                'description' => 'Integrações com marketplaces diversos.',
                'icon' => '🏪',
                'implemented' => false,
                'section' => null,
            ],
            [
                'code' => 'erp',
                'name' => 'ERP',
                'description' => 'Sistemas ERP externos.',
                'icon' => '🏛️',
                'implemented' => false,
                'section' => null,
            ],
            [
                'code' => 'outros',
                'name' => 'Outros',
                'description' => 'APIs e conectores personalizados.',
                'icon' => '🔌',
                'implemented' => false,
                'section' => null,
            ],
        ];

        $apps = array_map(function (array $app) use ($statusPorProvider) {
            $st = $statusPorProvider[$app['code']] ?? null;
            $app['connection_status'] = $st['connection_status'] ?? 'offline';
            $app['is_active'] = $st['is_active'] ?? false;
            $app['last_sync_at'] = $st['last_sync_at'] ?? null;

            return $app;
        }, $catalogo);

        return $this->json([
            'apps' => $apps,
            'fase' => 2,
            'mensagem' => 'VendaFácil com conexão real (Fase 2). Demais providers permanecem estruturais.',
        ]);
    }
}
