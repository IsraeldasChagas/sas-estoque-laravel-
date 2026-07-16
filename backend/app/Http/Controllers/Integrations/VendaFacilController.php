<?php

namespace App\Http\Controllers\Integrations;

use App\Models\IntegrationMapping;
use App\Services\Integrations\HttpIntegrationClient;
use App\Services\Integrations\IntegrationManager;
use App\Services\Integrations\Providers\VendaFacilProvider;
use App\Support\Integrations\IntegrationUrlValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class VendaFacilController extends IntegrationBaseController
{
    public function __construct(
        private readonly IntegrationManager $manager,
        private readonly VendaFacilProvider $provider,
    ) {}

    public function show(Request $request)
    {
        $u = $this->authUsuario($request);
        if (! $this->podeVisualizar($u)) {
            return $this->json(['error' => 'Não autorizado.'], 403);
        }

        if (! Schema::hasTable('integrations')) {
            return $this->json(['error' => 'Execute as migrations de integrações.'], 503);
        }

        $integration = $this->manager->findOrCreateIntegration(VendaFacilProvider::CODE);
        $unidades = Schema::hasTable('unidades')
            ? DB::table('unidades')->orderBy('nome')->get(['id', 'nome'])
            : collect();

        $mappings = IntegrationMapping::query()
            ->where('integration_id', $integration->id)
            ->where('entity_type', 'unit')
            ->get();

        return $this->json([
            'integration' => $integration->paraPainel(),
            'recursos_disponiveis' => $this->provider->getAvailableResources(),
            'unidades' => $unidades,
            'unit_mappings' => $mappings,
            'fase' => 2,
        ]);
    }

    public function update(Request $request)
    {
        return $this->save($request);
    }

    public function save(Request $request)
    {
        $u = $this->authUsuario($request);
        if (! $this->podeConfigurar($u)) {
            return $this->json(['error' => 'Somente administrador pode alterar.'], 403);
        }

        if (! Schema::hasTable('integrations')) {
            return $this->json(['error' => 'Execute as migrations de integrações.'], 503);
        }

        $data = $request->validate([
            'api_url' => 'nullable|string|max:500',
            'bearer_token' => 'nullable|string|max:2000',
            'environment' => 'nullable|in:production,homologation',
            'unidade_mappings' => 'nullable|array',
            'timeout_seconds' => 'nullable|integer|min:3|max:60',
            'retry_count' => 'nullable|integer|min:0|max:5',
            'webhook_secret' => 'nullable|string|max:500',
            'enabled_resources' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'observacoes' => 'nullable|string|max:2000',
        ]);

        $integration = $this->manager->findOrCreateIntegration(VendaFacilProvider::CODE);

        $update = [];

        if (array_key_exists('api_url', $data) && $data['api_url'] !== null) {
            $url = IntegrationUrlValidator::normalize($data['api_url']);
            IntegrationUrlValidator::validate($url, $data['environment'] ?? $integration->environment ?? 'homologation');
            $update['api_url'] = $url;
        }

        if (array_key_exists('environment', $data)) {
            $update['environment'] = $data['environment'];
        }
        if (array_key_exists('timeout_seconds', $data)) {
            $update['timeout_seconds'] = $data['timeout_seconds'];
        }
        if (array_key_exists('retry_count', $data)) {
            $update['retry_count'] = $data['retry_count'];
        }
        if (array_key_exists('enabled_resources', $data)) {
            $update['enabled_resources'] = $data['enabled_resources'];
        }
        if (array_key_exists('is_active', $data)) {
            $update['is_active'] = (bool) $data['is_active'];
        }
        if (array_key_exists('observacoes', $data)) {
            $update['observacoes'] = $data['observacoes'];
        }
        if (array_key_exists('unidade_mappings', $data)) {
            $update['unidade_mappings'] = $data['unidade_mappings'];
        }

        if (! empty($data['bearer_token']) && ! HttpIntegrationClient::isMaskedSecret($data['bearer_token'])) {
            $update['bearer_token'] = $data['bearer_token'];
        }
        if (! empty($data['webhook_secret']) && ! HttpIntegrationClient::isMaskedSecret($data['webhook_secret'])) {
            $update['webhook_secret'] = $data['webhook_secret'];
        }

        try {
            $integration = $this->manager->saveConfiguration(VendaFacilProvider::CODE, $update);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['api_url' => $e->getMessage()]);
        }

        return $this->json([
            'ok' => true,
            'integration' => $integration->paraPainel(),
            'mensagem' => 'Configuração salva com sucesso.',
        ]);
    }

    public function testarConexao(Request $request)
    {
        $u = $this->authUsuario($request);
        if (! $this->podeConfigurar($u)) {
            return $this->json(['error' => 'Somente administrador.'], 403);
        }

        $integration = $this->manager->findOrCreateIntegration(VendaFacilProvider::CODE);
        $result = $this->manager->testConnection(
            VendaFacilProvider::CODE,
            $integration,
            $request,
            (int) ($u->id ?? 0) ?: null
        );

        return $this->json([
            'ok' => (bool) ($result['success'] ?? false),
            'resultado' => $result,
            'integration' => $integration->fresh()->paraPainel(),
            'mensagem' => $result['message'] ?? 'Teste concluído.',
        ], ($result['success'] ?? false) ? 200 : 422);
    }

    public function health(Request $request)
    {
        $u = $this->authUsuario($request);
        if (! $this->podeVisualizar($u)) {
            return $this->json(['error' => 'Não autorizado.'], 403);
        }

        $integration = $this->manager->findOrCreateIntegration(VendaFacilProvider::CODE);
        $live = $request->boolean('live', true);
        $health = $this->manager->healthCheck(VendaFacilProvider::CODE, $integration, $live);

        return $this->json([
            'health' => $health,
            'integration' => $integration->fresh()->paraPainel(),
            'fase' => 2,
        ]);
    }

    public function logs(Request $request)
    {
        $u = $this->authUsuario($request);
        if (! $this->podeVisualizar($u)) {
            return $this->json(['error' => 'Não autorizado.'], 403);
        }

        $request->merge(['provider' => VendaFacilProvider::CODE]);

        return app(IntegrationLogController::class)->index($request);
    }

    public function unidades(Request $request)
    {
        $u = $this->authUsuario($request);
        if (! $this->podeVisualizar($u)) {
            return $this->json(['error' => 'Não autorizado.'], 403);
        }

        $integration = $this->manager->findOrCreateIntegration(VendaFacilProvider::CODE);
        $localUnidades = Schema::hasTable('unidades')
            ? DB::table('unidades')->orderBy('nome')->get(['id', 'nome'])
            : collect();

        $mappings = IntegrationMapping::query()
            ->where('integration_id', $integration->id)
            ->where('entity_type', 'unit')
            ->get();

        $remote = ['success' => false, 'units' => [], 'optional' => true];
        if ($integration->bearer_token && $integration->api_url && $request->boolean('fetch_remote', false)) {
            $remote = $this->provider->fetchRemoteUnits($integration->toProviderArray());
        }

        return $this->json([
            'local_unidades' => $localUnidades,
            'mappings' => $mappings,
            'remote_units' => $remote,
        ]);
    }

    public function salvarUnidades(Request $request)
    {
        $u = $this->authUsuario($request);
        if (! $this->podeConfigurar($u)) {
            return $this->json(['error' => 'Somente administrador.'], 403);
        }

        $data = $request->validate([
            'mappings' => 'required|array',
            'mappings.*.local_id' => 'required',
            'mappings.*.external_id' => 'required|string|max:120',
            'mappings.*.external_name' => 'nullable|string|max:255',
            'mappings.*.is_primary' => 'nullable|boolean',
            'mappings.*.is_active' => 'nullable|boolean',
        ]);

        $integration = $this->manager->findOrCreateIntegration(VendaFacilProvider::CODE);
        $integration = $this->manager->saveUnitMappings($integration, $data['mappings']);

        return $this->json([
            'ok' => true,
            'integration' => $integration->paraPainel(),
            'mensagem' => 'Mapeamento de unidades salvo.',
        ]);
    }

    public function sincronizar(Request $request)
    {
        $u = $this->authUsuario($request);
        if (! $this->podeConfigurar($u)) {
            return $this->json(['error' => 'Somente administrador.'], 403);
        }

        $integration = $this->manager->findOrCreateIntegration(VendaFacilProvider::CODE);
        $result = $this->manager->sync(VendaFacilProvider::CODE, $integration);

        return $this->json([
            'ok' => false,
            'resultado' => $result,
            'mensagem' => $result['message'] ?? 'Sincronização não disponível na Fase 2.',
        ], 501);
    }

    public function limparCache(Request $request)
    {
        $u = $this->authUsuario($request);
        if (! $this->podeConfigurar($u)) {
            return $this->json(['error' => 'Somente administrador.'], 403);
        }

        $this->manager->clearCache(VendaFacilProvider::CODE);

        return $this->json(['ok' => true, 'mensagem' => 'Cache limpo.']);
    }

    public function desconectar(Request $request)
    {
        $u = $this->authUsuario($request);
        if (! $this->podeConfigurar($u)) {
            return $this->json(['error' => 'Somente administrador.'], 403);
        }

        $clearMappings = $request->boolean('clear_mappings', false);
        $integration = $this->manager->findOrCreateIntegration(VendaFacilProvider::CODE);
        $integration = $this->manager->disconnect($integration, $clearMappings);

        return $this->json([
            'ok' => true,
            'integration' => $integration->paraPainel(),
            'mensagem' => 'Integração desconectada.',
        ]);
    }
}
