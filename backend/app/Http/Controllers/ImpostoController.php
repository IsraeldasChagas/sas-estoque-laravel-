<?php

namespace App\Http\Controllers;

use App\Models\Boleto;
use App\Models\Imposto;
use App\Models\ImpostoAnexo;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ImpostoController extends Controller
{
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

    private function salvarArquivo(UploadedFile $file, string $tipo): array
    {
        $pasta = match ($tipo) {
            'nota' => 'impostos/notas',
            default => 'impostos/guias',
        };
        $ext = $file->getClientOriginalExtension();
        $path = $file->storeAs($pasta, time().'_'.uniqid().'.'.$ext, 'public');

        return [
            'tipo' => $tipo,
            'path' => $path,
            'nome' => $file->getClientOriginalName(),
            'tipo_arquivo' => $ext,
        ];
    }

    private function registrarAnexo(Imposto $imposto, UploadedFile $file, string $tipo): ImpostoAnexo
    {
        $data = $this->salvarArquivo($file, $tipo);

        return ImpostoAnexo::create([
            'imposto_id' => $imposto->id,
            'tipo' => $data['tipo'],
            'path' => $data['path'],
            'nome' => $data['nome'],
            'tipo_arquivo' => $data['tipo_arquivo'],
        ]);
    }

    private function processarAnexos(Request $request, Imposto $imposto): void
    {
        foreach (['anexos_guia' => 'guia', 'anexos_nota' => 'nota'] as $campo => $tipo) {
            if (! $request->hasFile($campo)) {
                continue;
            }
            foreach ($request->file($campo) as $file) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $this->registrarAnexo($imposto, $file, $tipo);
                }
            }
        }
    }

    private function regrasAnexos(): array
    {
        return [
            'anexos_guia' => 'nullable|array',
            'anexos_guia.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
            'anexos_nota' => 'nullable|array',
            'anexos_nota.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];
    }

    private function serializar(Imposto $imposto): Imposto
    {
        $imposto->loadMissing(['anexos', 'boleto.anexos']);
        $imposto->sincronizarComBoleto($imposto->boleto);

        return $imposto->fresh(['anexos', 'boleto']);
    }

    public function index(Request $request)
    {
        $query = Imposto::query()->with(['anexos', 'boleto']);

        if ($request->filled('unidade_id')) {
            $query->where('unidade_id', $request->unidade_id);
        }
        if ($request->filled('status')) {
            $this->aplicarFiltroStatus($query, $request->status);
        }
        if ($request->filled('mes_ano')) {
            [$ano, $mes] = array_pad(explode('-', $request->mes_ano), 2, null);
            if ($ano && $mes) {
                $query->whereYear('data_vencimento', $ano)->whereMonth('data_vencimento', $mes);
            }
        }
        if ($request->filled('competencia')) {
            $query->where('competencia', $request->competencia);
        }

        $lista = $query->orderByDesc('data_vencimento')->get();
        foreach ($lista as $imposto) {
            $imposto->sincronizarComBoleto($imposto->boleto);
        }

        return response()->json($lista->load(['anexos', 'boleto']));
    }

    public function store(Request $request)
    {
        if ($request->input('unidade_id') === '') {
            $request->merge(['unidade_id' => null]);
        }

        $validator = Validator::make($request->all(), array_merge([
            'unidade_id' => 'nullable|exists:unidades,id',
            'tipo_imposto' => 'required|string|max:40',
            'descricao' => 'required|string|max:255',
            'orgao' => 'nullable|string|max:255',
            'competencia' => 'nullable|string|max:7',
            'numero_documento' => 'nullable|string|max:120',
            'data_vencimento' => 'required|date',
            'valor' => 'required|numeric|min:0.01',
            'observacoes' => 'nullable|string',
        ], $this->regrasAnexos()));

        if ($validator->fails()) {
            return response()->json(['message' => 'Dados inválidos', 'errors' => $validator->errors()], 422);
        }

        $data = $request->only([
            'unidade_id', 'tipo_imposto', 'descricao', 'orgao', 'competencia',
            'numero_documento', 'data_vencimento', 'valor', 'observacoes',
        ]);
        $data['status'] = 'A_VENCER';
        if ($uid = $request->header('X-Usuario-Id')) {
            $data['usuario_id'] = $uid;
        }

        $imposto = Imposto::create($data);
        $this->processarAnexos($request, $imposto);
        $imposto->update(['status' => $imposto->statusAberto()]);

        return response()->json([
            'message' => 'Imposto cadastrado com sucesso',
            'imposto' => $this->serializar($imposto),
        ], 201);
    }

    public function show($id)
    {
        $imposto = Imposto::with(['anexos', 'boleto.anexos'])->findOrFail($id);
        $imposto->sincronizarComBoleto($imposto->boleto);

        return response()->json($imposto->fresh(['anexos', 'boleto']));
    }

    public function update(Request $request, $id)
    {
        if ($request->input('unidade_id') === '') {
            $request->merge(['unidade_id' => null]);
        }

        $validator = Validator::make($request->all(), array_merge([
            'unidade_id' => 'nullable|exists:unidades,id',
            'tipo_imposto' => 'sometimes|required|string|max:40',
            'descricao' => 'sometimes|required|string|max:255',
            'orgao' => 'nullable|string|max:255',
            'competencia' => 'nullable|string|max:7',
            'numero_documento' => 'nullable|string|max:120',
            'data_vencimento' => 'sometimes|required|date',
            'valor' => 'sometimes|required|numeric|min:0.01',
            'observacoes' => 'nullable|string',
        ], $this->regrasAnexos()));

        if ($validator->fails()) {
            return response()->json(['message' => 'Dados inválidos', 'errors' => $validator->errors()], 422);
        }

        $imposto = Imposto::findOrFail($id);
        $data = $request->except(['anexos_guia', 'anexos_nota']);
        $allowed = array_flip($imposto->getFillable());
        $imposto->update(array_intersect_key($data, $allowed));
        $this->processarAnexos($request, $imposto);
        $imposto->sincronizarComBoleto($imposto->boleto);

        return response()->json([
            'message' => 'Imposto atualizado',
            'imposto' => $this->serializar($imposto),
        ]);
    }

    public function destroy($id)
    {
        $imposto = Imposto::with('anexos')->findOrFail($id);
        foreach ($imposto->anexos as $anexo) {
            if ($anexo->path && Storage::disk('public')->exists($anexo->path)) {
                Storage::disk('public')->delete($anexo->path);
            }
        }
        $imposto->delete();

        return response()->json(['message' => 'Imposto excluído']);
    }

    /** Cria boleto vinculado — pagamento e comprovante ficam no módulo Boletos. */
    public function gerarBoleto(Request $request, $id)
    {
        if (! Schema::hasTable('boletos')) {
            return response()->json(['message' => 'Módulo de boletos não disponível'], 503);
        }

        $imposto = Imposto::findOrFail($id);

        if ($imposto->boleto_id) {
            $boleto = Boleto::with('anexos')->find($imposto->boleto_id);
            if ($boleto) {
                return response()->json([
                    'message' => 'Este imposto já possui boleto vinculado',
                    'boleto' => $boleto,
                    'imposto' => $this->serializar($imposto),
                ]);
            }
        }

        $fornecedor = trim($imposto->orgao ?: 'Impostos / Tributos');
        $numero = trim((string) ($imposto->numero_documento ?: ''));
        if ($numero === '') {
            $numero = 'IMP-'.$imposto->id.'-'.time();
        }

        $boleto = Boleto::create([
            'unidade_id' => $imposto->unidade_id,
            'fornecedor' => $fornecedor,
            'descricao' => $imposto->descricao,
            'data_vencimento' => $imposto->data_vencimento,
            'valor' => $imposto->valor,
            'categoria' => 'IMPOSTOS',
            'status' => $imposto->statusAberto(),
            'observacoes' => 'Gerado do imposto #'.$imposto->id.($imposto->competencia ? ' — competência '.$imposto->competencia : ''),
            'numero_boleto' => $numero,
            'imposto_id' => $imposto->id,
            'usuario_id' => $request->header('X-Usuario-Id'),
        ]);

        $imposto->update(['boleto_id' => $boleto->id, 'status' => $imposto->statusAberto()]);

        return response()->json([
            'message' => 'Boleto criado. Registre o pagamento em Financeiro → Boletos.',
            'boleto' => $boleto,
            'imposto' => $this->serializar($imposto),
        ], 201);
    }

    public function downloadAnexo($anexoId)
    {
        $anexo = ImpostoAnexo::findOrFail($anexoId);
        $path = storage_path('app/public/'.$anexo->path);
        if (! is_file($path)) {
            return response()->json(['message' => 'Arquivo não encontrado'], 404);
        }

        return response()->download($path, $anexo->nome);
    }

    public function removerAnexo($anexoId)
    {
        $anexo = ImpostoAnexo::findOrFail($anexoId);
        if ($anexo->path && Storage::disk('public')->exists($anexo->path)) {
            Storage::disk('public')->delete($anexo->path);
        }
        $anexo->delete();

        return response()->json(['message' => 'Anexo removido']);
    }
}
