<?php

namespace Tests\Unit;

use App\Services\Fiscal\FocusNfeClient;
use App\Support\FocusEmpresaCscSync;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FocusEmpresaCscSyncTest extends TestCase
{
    public function test_sincroniza_csc_producao_na_empresa_focus(): void
    {
        Http::fake([
            'api.focusnfe.com.br/v2/empresas*' => Http::sequence()
                ->push([
                    [
                        'id' => 99,
                        'cnpj' => '56936257000104',
                        'habilita_nfce' => true,
                        'csc_nfce_producao' => null,
                        'id_token_nfce_producao' => null,
                    ],
                ], 200)
                ->push([
                    'id' => 99,
                    'cnpj' => '56936257000104',
                    'csc_nfce_producao' => 'TOKENCSC',
                    'id_token_nfce_producao' => 1,
                ], 200),
        ]);

        $cfg = new \App\Models\FiscalEmissaoConfig([
            'provider' => 'focus_nfe',
            'environment' => 'production',
            'api_token' => 'tok-teste',
            'csc_id' => '000001',
            'csc_token' => 'TOKENCSC',
        ]);
        // Avoid encrypted cast side effects in unit context by setting attributes raw.
        $cfg->syncOriginal();

        $empresa = (object) ['cnpj' => '56.936.257/0001-04'];
        $out = FocusEmpresaCscSync::sincronizar($cfg, $empresa);

        $this->assertTrue($out['ok'] ?? false, json_encode($out));
        $this->assertSame(99, $out['focus_empresa_id'] ?? null);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/v2/empresas/99')) {
                return false;
            }
            if ($request->method() !== 'PUT') {
                return false;
            }
            $data = $request->data();

            return ($data['csc_nfce_producao'] ?? null) === 'TOKENCSC'
                && (int) ($data['id_token_nfce_producao'] ?? 0) === 1;
        });
    }
}
