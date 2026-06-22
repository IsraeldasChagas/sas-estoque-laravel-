<?php

namespace App\Http\Controllers;

use App\Models\Boleto;
use App\Models\BoletoAnexo;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BoletoController extends Controller
{
    private function anexosHabilitados(): bool
    {
        return Schema::hasTable('boleto_anexos');
    }

    private function normalizarNumeroBoleto(?string $numero): string
    {
        $s = strtoupper(trim((string) $numero));

        return preg_replace('/[\s.\-\/]/', '', $s) ?? '';
    }

    private function buscarBoletoDuplicado(string $numero, ?int $ignorarId = null, ?string $dataVencimento = null): ?Boleto
    {
        $norm = $this->normalizarNumeroBoleto($numero);
        if ($norm === '') {
            return null;
        }

        $vencNovo = null;
        if ($dataVencimento) {
            try {
                $vencNovo = \Carbon\Carbon::parse($dataVencimento)->format('Y-m-d');
            } catch (\Exception $e) {
                $vencNovo = null;
            }
        }

        $query = Boleto::query()
            ->whereNotNull('numero_boleto')
            ->where('numero_boleto', '!=', '');

        if ($ignorarId) {
            $query->where('id', '!=', $ignorarId);
        }

        foreach ($query->get(['id', 'numero_boleto', 'fornecedor', 'data_vencimento']) as $boleto) {
            if ($this->normalizarNumeroBoleto($boleto->numero_boleto) !== $norm) {
                continue;
            }
            if ($vencNovo && $boleto->data_vencimento) {
                $vencExistente = \Carbon\Carbon::parse($boleto->data_vencimento)->format('Y-m-d');
                if ($vencExistente !== $vencNovo) {
                    continue;
                }
            }

            return $boleto;
        }

        return null;
    }

    /**
     * Filtra pelo status exibido na tela (alinha com o frontend).
     * A_VENCER / VENCIDO consideram data de vencimento para boletos ainda não pagos/cancelados.
     */
    private function aplicarFiltroStatus($query, ?string $status): void
    {
        if ($status === null || trim($status) === '') {
            return;
        }

        $status = strtoupper(trim($status));
        $hoje = now()->startOfDay();

        if ($status === 'PAGO') {
            $query->where('status', 'PAGO');

            return;
        }

        if ($status === 'CANCELADO') {
            $query->where('status', 'CANCELADO');

            return;
        }

        if ($status === 'A_VENCER') {
            $query->whereNotIn('status', ['PAGO', 'CANCELADO'])
                ->whereDate('data_vencimento', '>=', $hoje);

            return;
        }

        if ($status === 'VENCIDO') {
            $query->whereNotIn('status', ['PAGO', 'CANCELADO'])
                ->whereDate('data_vencimento', '<', $hoje);

            return;
        }

        $query->where('status', $status);
    }

    private function respostaNumeroBoletoDuplicado(Boleto $existente)
    {
        $venc = $existente->data_vencimento
            ? \Carbon\Carbon::parse($existente->data_vencimento)->format('d/m/Y')
            : '—';

        return response()->json([
            'message' => 'Este número de boleto já está cadastrado.',
            'errors' => [
                'numero_boleto' => [
                    "Boleto #{$existente->id} ({$existente->fornecedor}, venc. {$venc}) já usa este número.",
                ],
            ],
        ], 422);
    }

    private function salvarArquivoAnexo(UploadedFile $file, string $tipo): array
    {
        $nomeOriginal = $file->getClientOriginalName();
        $extensao = $file->getClientOriginalExtension();
        $nomeArquivo = time().'_'.uniqid().'.'.$extensao;
        $pasta = match ($tipo) {
            'nota' => 'boletos/notas',
            'comprovante' => 'boletos/comprovantes',
            default => 'boletos',
        };
        $path = $file->storeAs($pasta, $nomeArquivo, 'public');

        return [
            'tipo' => $tipo,
            'path' => $path,
            'nome' => $nomeOriginal,
            'tipo_arquivo' => $extensao,
        ];
    }

    private function registrarAnexo(Boleto $boleto, UploadedFile $file, string $tipo): ?BoletoAnexo
    {
        if (! $this->anexosHabilitados()) {
            return null;
        }

        $data = $this->salvarArquivoAnexo($file, $tipo);

        return BoletoAnexo::create([
            'boleto_id' => $boleto->id,
            'tipo' => $data['tipo'],
            'path' => $data['path'],
            'nome' => $data['nome'],
            'tipo_arquivo' => $data['tipo_arquivo'],
        ]);
    }

    private function processarAnexosRequest(Request $request, Boleto $boleto): void
    {
        if (! $this->anexosHabilitados()) {
            if ($request->hasFile('anexo')) {
                $this->salvarAnexoLegado($request->file('anexo'), $boleto);
            }

            return;
        }

        foreach (['anexos_boleto' => 'boleto', 'anexos_nota' => 'nota', 'anexos_comprovante' => 'comprovante'] as $campo => $tipo) {
            if (! $request->hasFile($campo)) {
                continue;
            }
            foreach ($request->file($campo) as $file) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $this->registrarAnexo($boleto, $file, $tipo);
                }
            }
        }

        if ($request->hasFile('anexo')) {
            $this->registrarAnexo($boleto, $request->file('anexo'), 'boleto');
        }
    }

    private function salvarAnexoLegado(UploadedFile $file, Boleto $boleto): void
    {
        if ($boleto->anexo_path && Storage::disk('public')->exists($boleto->anexo_path)) {
            Storage::disk('public')->delete($boleto->anexo_path);
        }

        $data = $this->salvarArquivoAnexo($file, 'boleto');
        $boleto->update([
            'anexo_path' => $data['path'],
            'anexo_nome' => $data['nome'],
            'anexo_tipo' => $data['tipo_arquivo'],
        ]);
    }

    private function regrasAnexos(): array
    {
        $regras = [
            'anexo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];

        if ($this->anexosHabilitados()) {
            $regras['anexos_boleto'] = 'nullable|array';
            $regras['anexos_boleto.*'] = 'file|mimes:pdf,jpg,jpeg,png|max:5120';
            $regras['anexos_nota'] = 'nullable|array';
            $regras['anexos_nota.*'] = 'file|mimes:pdf,jpg,jpeg,png|max:5120';
            $regras['anexos_comprovante'] = 'nullable|array';
            $regras['anexos_comprovante.*'] = 'file|mimes:pdf,jpg,jpeg,png|max:5120';
        }

        return $regras;
    }

    private function serializarBoleto(Boleto $boleto): Boleto
    {
        if ($this->anexosHabilitados()) {
            $boleto->loadMissing('anexos');
        }

        return $boleto;
    }

    /**
     * Lista todos os boletos
     */
    public function index(Request $request)
    {
        \Log::info('📊 BoletoController::index - Listando boletos');
        \Log::info('📥 Filtros recebidos:', $request->all());

        try {
            $query = Boleto::query();
            if ($this->anexosHabilitados()) {
                $query->with('anexos');
            }

            if ($request->has('unidade_id') && $request->unidade_id) {
                $query->where('unidade_id', $request->unidade_id);
                \Log::info('🏢 Filtrando por unidade: '.$request->unidade_id);
            }

            if ($request->filled('status')) {
                $this->aplicarFiltroStatus($query, $request->status);
                \Log::info('📌 Filtrando por status (efetivo): '.$request->status);
            }

            if ($request->has('mes_ano') && $request->mes_ano) {
                $mesAno = explode('-', $request->mes_ano);
                if (count($mesAno) == 2) {
                    $ano = $mesAno[0];
                    $mes = $mesAno[1];
                    $query->whereYear('data_vencimento', $ano)
                        ->whereMonth('data_vencimento', $mes);
                    \Log::info("📅 Filtrando por mês/ano: {$mes}/{$ano}");
                }
            }

            if ($request->filled('data_vencimento')) {
                $query->whereDate('data_vencimento', $request->data_vencimento);
                \Log::info('📅 Filtrando por data de vencimento: '.$request->data_vencimento);
            }

            if ($request->has('data_inicio')) {
                $query->where('data_vencimento', '>=', $request->data_inicio);
                \Log::info('📅 Data início: '.$request->data_inicio);
            }
            if ($request->has('data_fim')) {
                $query->where('data_vencimento', '<=', $request->data_fim);
                \Log::info('📅 Data fim: '.$request->data_fim);
            }

            $boletos = $query->orderBy('data_vencimento', 'desc')->get();

            \Log::info('✅ Total de boletos encontrados: '.$boletos->count());

            return response()->json($boletos);
        } catch (\Exception $e) {
            \Log::error('❌ Erro ao buscar boletos: '.$e->getMessage());

            return response()->json([
                'message' => 'Erro ao buscar boletos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cria um novo boleto
     */
    public function store(Request $request)
    {
        \Log::info('🚀 BoletoController::store - Iniciando criação de boleto');
        \Log::info('📥 Dados recebidos:', $request->all());

        $input = $request->all();
        if (isset($input['unidade_id']) && $input['unidade_id'] === '') {
            $request->merge(['unidade_id' => null]);
        }
        if (isset($input['data_pagamento']) && $input['data_pagamento'] === '') {
            $request->merge(['data_pagamento' => null]);
        }
        foreach (['juros_multa', 'meses_recorrencia', 'valor_pago'] as $field) {
            if ($request->has($field) && $request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }

        $validator = Validator::make($request->all(), array_merge([
            'fornecedor' => 'required|string|max:255',
            'descricao' => 'required|string|max:255',
            'data_vencimento' => 'required|date',
            'valor' => 'required|numeric|min:0.01',
            'unidade_id' => 'nullable|exists:unidades,id',
            'categoria' => 'nullable|string',
            'status' => 'required|in:A_VENCER,VENCIDO,PAGO,CANCELADO',
            'data_pagamento' => 'nullable|date',
            'valor_pago' => 'nullable|numeric|min:0',
            'juros_multa' => 'nullable|numeric|min:0',
            'observacoes' => 'nullable|string',
            'numero_boleto' => 'required|string|max:255',
            'nome_pagador' => 'nullable|string|max:255',
            'whatsapp_pagador' => 'nullable|string|max:20',
            'is_recorrente' => 'nullable|boolean',
            'meses_recorrencia' => 'nullable|integer|min:1|max:60',
            'grupo_recorrencia' => 'nullable|string',
        ], $this->regrasAnexos()));

        if ($validator->fails()) {
            \Log::warning('❌ Validação falhou:', $validator->errors()->toArray());

            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $request->except(['anexo', 'anexos_boleto', 'anexos_nota', 'anexos_comprovante']);
            $data['numero_boleto'] = trim((string) ($data['numero_boleto'] ?? ''));

            $duplicado = $this->buscarBoletoDuplicado(
                $data['numero_boleto'],
                null,
                $data['data_vencimento'] ?? null
            );
            if ($duplicado) {
                return $this->respostaNumeroBoletoDuplicado($duplicado);
            }

            $usuarioId = $request->header('X-Usuario-Id');
            if ($usuarioId) {
                $data['usuario_id'] = $usuarioId;
                \Log::info('👤 Usuario ID: '.$usuarioId);
            }

            if (! isset($data['juros_multa'])) {
                $data['juros_multa'] = 0;
            }

            \Log::info('💾 Criando boleto no banco...');
            $boleto = Boleto::create($data);
            $this->processarAnexosRequest($request, $boleto);
            $boletoFresh = $boleto->fresh();
            \App\Models\Imposto::sincronizarDeBoleto($boletoFresh);
            \App\Services\BoletoFluxoCaixaService::sincronizarDeBoleto($boletoFresh);
            $boleto = $this->serializarBoleto($boletoFresh);

            \Log::info('✅ Boleto criado com sucesso - ID: '.$boleto->id);

            return response()->json([
                'message' => 'Boleto criado com sucesso',
                'boleto' => $boleto,
            ], 201);
        } catch (\Exception $e) {
            \Log::error('❌ Erro ao criar boleto: '.$e->getMessage());
            \Log::error('Stack trace: '.$e->getTraceAsString());

            return response()->json([
                'message' => 'Erro ao criar boleto',
                'error' => $e->getMessage(),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * Exibe um boleto específico
     */
    public function show($id)
    {
        try {
            $boleto = Boleto::findOrFail($id);

            return response()->json($this->serializarBoleto($boleto));
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Boleto não encontrado',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Atualiza um boleto
     */
    public function update(Request $request, $id)
    {
        $input = $request->all();
        if (isset($input['unidade_id']) && $input['unidade_id'] === '') {
            $request->merge(['unidade_id' => null]);
        }
        if (isset($input['data_pagamento']) && $input['data_pagamento'] === '') {
            $request->merge(['data_pagamento' => null]);
        }
        foreach (['juros_multa', 'meses_recorrencia', 'valor_pago'] as $field) {
            if ($request->has($field) && $request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }

        $validator = Validator::make($request->all(), array_merge([
            'fornecedor' => 'sometimes|required|string|max:255',
            'descricao' => 'sometimes|required|string|max:255',
            'data_vencimento' => 'sometimes|required|date',
            'valor' => 'sometimes|required|numeric|min:0.01',
            'unidade_id' => 'nullable|exists:unidades,id',
            'categoria' => 'nullable|string',
            'status' => 'sometimes|required|in:A_VENCER,VENCIDO,PAGO,CANCELADO',
            'data_pagamento' => 'nullable|date',
            'valor_pago' => 'nullable|numeric|min:0',
            'juros_multa' => 'nullable|numeric|min:0',
            'observacoes' => 'nullable|string',
            'numero_boleto' => 'sometimes|required|string|max:255',
            'nome_pagador' => 'nullable|string|max:255',
            'whatsapp_pagador' => 'nullable|string|max:20',
        ], $this->regrasAnexos()));

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $boleto = Boleto::findOrFail($id);
            $data = $request->except(['anexo', 'anexos_boleto', 'anexos_nota', 'anexos_comprovante']);

            $allowed = array_flip((new Boleto)->getFillable());
            $data = array_intersect_key($data, $allowed);

            $numeroAtual = trim((string) $boleto->numero_boleto);
            if (isset($data['numero_boleto'])) {
                $data['numero_boleto'] = trim((string) $data['numero_boleto']);
            } else {
                unset($data['numero_boleto']);
            }

            $numeroNovo = $data['numero_boleto'] ?? $numeroAtual;
            $numeroMudou = $this->normalizarNumeroBoleto($numeroNovo) !== $this->normalizarNumeroBoleto($numeroAtual);

            if ($numeroMudou) {
                $dataVenc = $data['data_vencimento'] ?? ($boleto->data_vencimento ? $boleto->data_vencimento->format('Y-m-d') : null);
                $duplicado = $this->buscarBoletoDuplicado($numeroNovo, (int) $boleto->id, $dataVenc);
                if ($duplicado) {
                    return $this->respostaNumeroBoletoDuplicado($duplicado);
                }
            }

            $boleto->update($data);
            $this->processarAnexosRequest($request, $boleto);
            $boletoFresh = $boleto->fresh();
            \App\Models\Imposto::sincronizarDeBoleto($boletoFresh);
            \App\Services\BoletoFluxoCaixaService::sincronizarDeBoleto($boletoFresh);
            $boleto = $this->serializarBoleto($boletoFresh);

            return response()->json([
                'message' => 'Boleto atualizado com sucesso',
                'boleto' => $boleto,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Boleto não encontrado (pode ter sido excluído)',
                'error' => 'Boleto não encontrado',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao atualizar boleto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove um boleto
     */
    public function destroy($id)
    {
        try {
            $boleto = Boleto::findOrFail($id);

            if ($this->anexosHabilitados()) {
                foreach ($boleto->anexos as $anexo) {
                    $this->apagarArquivoAnexo($anexo);
                }
            }

            if ($boleto->anexo_path && Storage::disk('public')->exists($boleto->anexo_path)) {
                Storage::disk('public')->delete($boleto->anexo_path);
            }

            \App\Services\BoletoFluxoCaixaService::aoExcluirBoleto((int) $boleto->id);
            $boleto->delete();

            return response()->json([
                'message' => 'Boleto excluído com sucesso',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao excluir boleto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resumo financeiro de boletos
     */
    public function resumo(Request $request)
    {
        \Log::info('💰 BoletoController::resumo - Gerando resumo financeiro');
        \Log::info('📥 Filtros recebidos:', $request->all());

        try {
            $query = Boleto::query();

            if ($request->has('unidade_id') && $request->unidade_id) {
                $query->where('unidade_id', $request->unidade_id);
            }

            if ($request->filled('status')) {
                $this->aplicarFiltroStatus($query, $request->status);
            }

            if ($request->has('mes_ano') && $request->mes_ano) {
                $mesAno = explode('-', $request->mes_ano);
                if (count($mesAno) == 2) {
                    $ano = $mesAno[0];
                    $mes = $mesAno[1];
                    $query->whereYear('data_vencimento', $ano)
                        ->whereMonth('data_vencimento', $mes);
                    \Log::info("📅 Filtrando resumo por: {$mes}/{$ano}");
                }
            } else {
                \Log::info('📅 Resumo SEM filtro de mês (demais filtros podem estar ativos)');
            }

            if ($request->filled('data_vencimento')) {
                $query->whereDate('data_vencimento', $request->data_vencimento);
            }

            $boletos = $query->get();
            \Log::info('📊 Total de boletos no resumo: '.$boletos->count());

            $totalMes = $boletos->sum('valor');
            $pagoEmDia = $boletos->where('status', 'PAGO')
                ->where('juros_multa', 0)
                ->sum('valor_pago');
            $jurosPagos = $boletos->where('status', 'PAGO')->sum('juros_multa');

            $boletosPagosComAtraso = $boletos->where('status', 'PAGO')
                ->filter(function ($boleto) {
                    if ($boleto->juros_multa > 0) {
                        return true;
                    }
                    if ($boleto->data_pagamento && $boleto->data_vencimento) {
                        return $boleto->data_pagamento > $boleto->data_vencimento;
                    }

                    return false;
                })
                ->count();

            $valorPotencialJuros = $boletos->where('status', 'PAGO')->sum('valor') * 0.1;
            $economia = $valorPotencialJuros - $jurosPagos;

            $hoje = now()->startOfDay();
            $queryParaPagarHoje = Boleto::query()
                ->whereNotIn('status', ['PAGO', 'CANCELADO'])
                ->whereDate('data_vencimento', $hoje);
            if ($request->has('unidade_id') && $request->unidade_id) {
                $queryParaPagarHoje->where('unidade_id', $request->unidade_id);
            }
            $boletosParaPagarHoje = $queryParaPagarHoje->get();

            $resumo = [
                'total_mes' => $totalMes,
                'pago_em_dia' => $pagoEmDia,
                'juros_pagos' => $jurosPagos,
                'economia' => max(0, $economia),
                'boletos_pagos_com_atraso' => $boletosPagosComAtraso,
                'total_boletos' => $boletos->count(),
                'boletos_pagos' => $boletos->where('status', 'PAGO')->count(),
                'boletos_vencidos' => $boletos->where('status', 'VENCIDO')->count(),
                'boletos_a_vencer' => $boletos->where('status', 'A_VENCER')->count(),
                'para_pagar_hoje' => $boletosParaPagarHoje->count(),
                'valor_para_pagar_hoje' => $boletosParaPagarHoje->sum('valor'),
            ];

            \Log::info('✅ Resumo gerado:', $resumo);

            return response()->json($resumo);
        } catch (\Exception $e) {
            \Log::error('❌ Erro ao gerar resumo: '.$e->getMessage());

            return response()->json([
                'message' => 'Erro ao gerar resumo',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Economia por mês (últimos 6 meses)
     */
    public function economiaMensal()
    {
        try {
            $meses = [];

            for ($i = 5; $i >= 0; $i--) {
                $data = now()->subMonths($i);
                $mes = $data->format('m');
                $ano = $data->format('Y');

                $boletos = Boleto::whereYear('data_vencimento', $ano)
                    ->whereMonth('data_vencimento', $mes)
                    ->where('status', 'PAGO')
                    ->get();

                $valorTotal = $boletos->sum('valor');
                $jurosPagos = $boletos->sum('juros_multa');
                $valorPotencialJuros = $valorTotal * 0.1;
                $economia = max(0, $valorPotencialJuros - $jurosPagos);

                $meses[] = [
                    'mes' => $data->format('M'),
                    'mes_completo' => $data->format('F Y'),
                    'economia' => $economia,
                    'mes_ano' => $data->format('Y-m'),
                ];
            }

            return response()->json($meses);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao gerar economia mensal',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download do anexo do boleto (legado)
     */
    public function downloadAnexo($id)
    {
        try {
            $boleto = Boleto::findOrFail($id);

            if ($this->anexosHabilitados()) {
                $anexo = $boleto->anexos()->where('tipo', 'boleto')->orderBy('id')->first();
                if ($anexo) {
                    return $this->downloadAnexoPorId($anexo->id);
                }
            }

            if (! $boleto->anexo_path) {
                return response()->json([
                    'message' => 'Este boleto não possui anexo',
                ], 404);
            }

            $path = storage_path('app/public/'.$boleto->anexo_path);

            if (! file_exists($path)) {
                return response()->json([
                    'message' => 'Arquivo não encontrado',
                ], 404);
            }

            return response()->download($path, $boleto->anexo_nome);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao baixar anexo',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download de anexo específico
     */
    public function downloadAnexoPorId($anexoId)
    {
        try {
            if (! $this->anexosHabilitados()) {
                return response()->json(['message' => 'Módulo de anexos não configurado'], 503);
            }

            $anexo = BoletoAnexo::findOrFail($anexoId);
            $path = storage_path('app/public/'.$anexo->path);

            if (! file_exists($path)) {
                return response()->json([
                    'message' => 'Arquivo não encontrado',
                ], 404);
            }

            return response()->download($path, $anexo->nome);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao baixar anexo',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove o anexo do boleto (legado)
     */
    public function removerAnexo($id)
    {
        try {
            $boleto = Boleto::findOrFail($id);

            if ($this->anexosHabilitados()) {
                $anexo = $boleto->anexos()->where('tipo', 'boleto')->orderBy('id')->first();
                if ($anexo) {
                    return $this->removerAnexoPorId($anexo->id);
                }
            }

            if ($boleto->anexo_path && Storage::disk('public')->exists($boleto->anexo_path)) {
                Storage::disk('public')->delete($boleto->anexo_path);
            }

            $boleto->update([
                'anexo_path' => null,
                'anexo_nome' => null,
                'anexo_tipo' => null,
            ]);

            return response()->json([
                'message' => 'Anexo removido com sucesso',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao remover anexo',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove anexo específico
     */
    public function removerAnexoPorId($anexoId)
    {
        try {
            if (! $this->anexosHabilitados()) {
                return response()->json(['message' => 'Módulo de anexos não configurado'], 503);
            }

            $anexo = BoletoAnexo::findOrFail($anexoId);
            $this->apagarArquivoAnexo($anexo);
            $anexo->delete();

            return response()->json([
                'message' => 'Anexo removido com sucesso',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao remover anexo',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function apagarArquivoAnexo(BoletoAnexo $anexo): void
    {
        if ($anexo->path && Storage::disk('public')->exists($anexo->path)) {
            Storage::disk('public')->delete($anexo->path);
        }
    }
}
