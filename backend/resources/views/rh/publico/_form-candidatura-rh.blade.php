{{--
    Candidatura pública RH — requer: $vaga (objeto vaga aberta ou null), $vagasAbertas, $vagaBloqueada (bool)
--}}
@php
    $vagas = isset($vagasAbertas) ? $vagasAbertas : collect();
    $vagasCount = is_countable($vagas) ? count($vagas) : 0;
@endphp

@if(!empty($vagaBloqueada) && $vagaBloqueada)
    @if($vaga)
        <div class="alert alert-warning">
            Esta vaga está <strong>{{ strtoupper($vaga->status) }}</strong> no momento e não está aceitando novas candidaturas.
        </div>
    @else
        <div class="alert alert-warning">
            Não há vagas <strong>abertas</strong> para candidatura no momento.
        </div>
    @endif
@endif

@if($vaga)
<form id="formCandidaturaRh" method="POST" action="/vagas/{{ $vaga->slug }}/candidatar" enctype="multipart/form-data" class="row g-3">
    @csrf
    @php
        $disabled = (!empty($vagaBloqueada) && $vagaBloqueada);
        $cidadesRo = [
            'Alta Floresta d\'Oeste',
            'Alto Alegre dos Parecis',
            'Alto Paraíso',
            'Alvorada d\'Oeste',
            'Ariquemes',
            'Buritis',
            'Cabixi',
            'Cacaulândia',
            'Cacoal',
            'Campo Novo de Rondônia',
            'Candeias do Jamari',
            'Castanheiras',
            'Cerejeiras',
            'Chupinguaia',
            'Colorado do Oeste',
            'Corumbiara',
            'Costa Marques',
            'Cujubim',
            'Espigão d\'Oeste',
            'Governador Jorge Teixeira',
            'Guajará-Mirim',
            'Itapuã do Oeste',
            'Jaru',
            'Ji-Paraná',
            'Machadinho d\'Oeste',
            'Ministro Andreazza',
            'Mirante da Serra',
            'Monte Negro',
            'Nova Brasilândia d\'Oeste',
            'Nova Mamoré',
            'Nova União',
            'Novo Horizonte do Oeste',
            'Ouro Preto do Oeste',
            'Parecis',
            'Pimenta Bueno',
            'Pimenteiras do Oeste',
            'Porto Velho',
            'Presidente Médici',
            'Primavera de Rondônia',
            'Rio Crespo',
            'Rolim de Moura',
            'Santa Luzia d\'Oeste',
            'São Felipe d\'Oeste',
            'São Francisco do Guaporé',
            'São Miguel do Guaporé',
            'Seringueiras',
            'Teixeirópolis',
            'Theobroma',
            'Urupá',
            'Vale do Anari',
            'Vale do Paraíso',
            'Vilhena',
        ];
    @endphp
    <fieldset {{ $disabled ? 'disabled="disabled"' : '' }} class="row g-3 m-0 p-0" style="border:0;">

    @if($vagasCount > 1)
        <div class="col-12">
            <div class="vaga-choices">
                <div class="fw-semibold mb-2">Escolha a(s) vaga(s)</div>
                @foreach($vagas as $v)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="vaga_ids[]" id="vaga_{{ $v->id }}" value="{{ $v->id }}"
                               @checked(old('vaga_ids') ? in_array($v->id, (array) old('vaga_ids')) : ($v->id === $vaga->id)) />
                        <label class="form-check-label" for="vaga_{{ $v->id }}" style="display:flex; align-items:center; gap:.5rem; justify-content:space-between;">
                            <span>
                                {{ $v->titulo }}@if(!empty($v->unidade)) — <span class="text-muted">{{ $v->unidade }}</span>@endif
                            </span>
                            <a class="btn btn-sm btn-outline-primary" href="{{ url('/vagas') }}#vaga-{{ $v->slug }}">Ver na lista</a>
                        </label>
                    </div>
                @endforeach
                <div class="form-text mt-2">Se marcar mais de uma, sua candidatura será enviada para cada vaga selecionada.</div>
            </div>
        </div>
    @elseif($vagasCount === 1)
        <input type="hidden" name="vaga_ids[]" value="{{ $vaga->id }}" />
    @endif

    <div class="col-md-8">
        <label class="form-label">Nome <span class="text-danger">*</span></label>
        <input name="nome" class="form-control" value="{{ old('nome') }}" required maxlength="160" />
    </div>
    <div class="col-md-4">
        <label class="form-label">WhatsApp <span class="text-danger">*</span></label>
        <input name="telefone" class="form-control" value="{{ old('telefone') }}" required maxlength="40" />
    </div>

    <div class="col-md-6">
        <label class="form-label">Email <span class="text-danger">*</span></label>
        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required maxlength="160" />
    </div>
    <div class="col-md-3">
        <label class="form-label">Cidade <span class="text-danger">*</span></label>
        <select name="cidade" class="form-control" required>
            <option value="">Selecione a cidade</option>
            @foreach($cidadesRo as $cidadeNome)
                <option value="{{ $cidadeNome }}" @selected(old('cidade') === $cidadeNome)>{{ $cidadeNome }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Bairro <span class="text-danger">*</span></label>
        <input name="bairro" class="form-control" value="{{ old('bairro') }}" required maxlength="120" />
    </div>

    <div class="col-md-6">
        <label class="form-label">Disponibilidade <span class="text-danger">*</span></label>
        <select name="disponibilidade" class="form-control" required>
            <option value="">Selecione...</option>
            <option value="sim" @selected(old('disponibilidade') === 'sim')>Sim</option>
            <option value="nao" @selected(old('disponibilidade') === 'nao')>Não</option>
        </select>
    </div>

    <div class="col-12">
        <label class="form-label">Observações (opcional)</label>
        <textarea name="observacoes" class="form-control" rows="3" maxlength="500" placeholder="Se quiser, deixe uma observação rápida (ex.: disponibilidade de horário, informação importante, etc.).">{{ old('observacoes') }}</textarea>
        <div class="form-text">Máximo de 500 caracteres. Não é obrigatório.</div>
    </div>

    <div class="col-md-8">
        <label class="form-label">Currículo (PDF) <span class="text-danger">*</span></label>
        <input type="file" name="curriculo" class="form-control" accept="application/pdf" required />
        <div class="form-text">Tamanho máximo do PDF: <strong>7,5 MB</strong>. Se der erro, comprima o arquivo antes de enviar. Não envie CPF/RG/CTPS na candidatura (documentos só após aprovação).</div>
    </div>
    <div class="col-md-4">
        <label class="form-label">Foto <span class="text-danger">*</span></label>
        <input type="file" name="foto" class="form-control" accept="image/jpeg,image/png" required />
        <div class="form-text">JPG ou PNG, até <strong>3 MB</strong>. Se necessário, reduza a qualidade da foto.</div>
    </div>

    <div class="col-12">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" value="1" id="lgpd" name="lgpd" required />
            <label class="form-check-label" for="lgpd">
                Autorizo o uso dos meus dados para fins de recrutamento e seleção. <span class="text-danger">*</span>
            </label>
        </div>
    </div>

    <div class="col-12">
        <button type="submit" id="btnCandidaturaRh" class="btn btn-primary" {{ $disabled ? 'disabled="disabled"' : '' }}>Enviar candidatura</button>
    </div>
    </fieldset>
</form>
@endif
