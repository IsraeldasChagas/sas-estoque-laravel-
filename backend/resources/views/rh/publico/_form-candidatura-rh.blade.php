{{--
    Candidatura pública RH — uma vaga por envio.
    Requer: $vaga (vaga aberta ou null), $vagaBloqueada (bool). $vagasAbertas é opcional (legado).
--}}
@php
    $oldIds = old('vaga_ids');
    $hiddenVagaId = $vaga ? (int) $vaga->id : 0;
    if (is_array($oldIds) && count($oldIds)) {
        $hiddenVagaId = (int) reset($oldIds);
    }
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
<form id="formCandidaturaRh" method="POST" action="/vagas/{{ $vaga->slug }}/candidatar" enctype="multipart/form-data" class="candidatura-rh-form">
    @csrf
    <input type="hidden" name="vaga_ids[]" id="rhCandidaturaVagaId" value="{{ $hiddenVagaId }}" />
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
    <fieldset {{ $disabled ? 'disabled="disabled"' : '' }} class="row g-3 m-0 p-0 border-0">

        <div class="col-12 col-md-6">
            <label class="form-label" for="rhCandNome">Nome completo <span class="text-danger">*</span></label>
            <input id="rhCandNome" name="nome" class="form-control" value="{{ old('nome') }}" required maxlength="160" autocomplete="name" />
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="rhCandTel">WhatsApp <span class="text-danger">*</span></label>
            <input id="rhCandTel" name="telefone" class="form-control" value="{{ old('telefone') }}" required maxlength="40" inputmode="tel" autocomplete="tel" />
        </div>

        <div class="col-12 col-md-6">
            <label class="form-label" for="rhCandEmail">E-mail <span class="text-danger">*</span></label>
            <input id="rhCandEmail" type="email" name="email" class="form-control" value="{{ old('email') }}" required maxlength="160" autocomplete="email" />
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label" for="rhCandCidade">Cidade <span class="text-danger">*</span></label>
            <select id="rhCandCidade" name="cidade" class="form-select" required>
                <option value="">Selecione a cidade</option>
                @foreach($cidadesRo as $cidadeNome)
                    <option value="{{ $cidadeNome }}" @selected(old('cidade') === $cidadeNome)>{{ $cidadeNome }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label" for="rhCandBairro">Bairro <span class="text-danger">*</span></label>
            <input id="rhCandBairro" name="bairro" class="form-control" value="{{ old('bairro') }}" required maxlength="120" autocomplete="address-level3" />
        </div>

        <div class="col-12 col-md-6">
            <label class="form-label" for="rhCandDisp">Disponibilidade imediata? <span class="text-danger">*</span></label>
            <select id="rhCandDisp" name="disponibilidade" class="form-select" required>
                <option value="">Selecione…</option>
                <option value="sim" @selected(old('disponibilidade') === 'sim')>Sim</option>
                <option value="nao" @selected(old('disponibilidade') === 'nao')>Não</option>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label" for="rhCandObs">Observações <span class="text-muted fw-normal">(opcional)</span></label>
            <textarea id="rhCandObs" name="observacoes" class="form-control" rows="3" maxlength="500" placeholder="Ex.: horários em que prefere ser contatado(a).">{{ old('observacoes') }}</textarea>
            <div class="form-text">Até 500 caracteres.</div>
        </div>

        <div class="col-12 col-md-6">
            <label class="form-label" for="rhCandCv">Currículo (PDF) <span class="text-danger">*</span></label>
            <input id="rhCandCv" type="file" name="curriculo" class="form-control candidatura-rh-file" accept="application/pdf" required />
            <div class="form-text">Máx. <strong>7,5 MB</strong>. Não envie CPF/RG na candidatura.</div>
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="rhCandFoto">Foto <span class="text-danger">*</span></label>
            <input id="rhCandFoto" type="file" name="foto" class="form-control candidatura-rh-file" accept="image/jpeg,image/png" required />
            <div class="form-text">JPG ou PNG, máx. <strong>3 MB</strong>.</div>
        </div>

        <div class="col-12">
            <div class="candidatura-rh-lgpd-box">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" value="1" id="lgpd" name="lgpd" required />
                    <label class="form-check-label" for="lgpd">
                        Autorizo o uso dos meus dados para recrutamento e seleção. <span class="text-danger">*</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="col-12 pt-1">
            <button type="submit" id="btnCandidaturaRh" class="btn btn-primary px-4" {{ $disabled ? 'disabled="disabled"' : '' }}>Enviar candidatura</button>
        </div>
    </fieldset>
</form>

@once
<style>
    /* Campos de arquivo em destaque (azul Bootstrap) */
    .candidatura-rh-form input[type="file"].candidatura-rh-file.form-control {
        border: 2px solid rgba(13, 110, 253, 0.65);
        background: linear-gradient(180deg, rgba(13, 110, 253, 0.09) 0%, rgba(255, 255, 255, 0.98) 55%);
        color: #052c65;
        cursor: pointer;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    }
    .candidatura-rh-form input[type="file"].candidatura-rh-file.form-control:hover {
        border-color: #0d6efd;
        background: rgba(13, 110, 253, 0.06);
    }
    .candidatura-rh-form input[type="file"].candidatura-rh-file.form-control:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.22rem rgba(13, 110, 253, 0.38);
        background: #fff;
        outline: 0;
    }

    /* LGPD em caixa com sombra */
    .candidatura-rh-lgpd-box {
        border-radius: 14px;
        padding: 1rem 1.2rem;
        margin-top: 0.15rem;
        background: linear-gradient(165deg, rgba(13, 110, 253, 0.07) 0%, rgba(255, 255, 255, 0.98) 45%);
        border: 1px solid rgba(13, 110, 253, 0.32);
        box-shadow:
            0 12px 32px rgba(13, 110, 253, 0.18),
            0 4px 14px rgba(0, 0, 0, 0.08),
            inset 0 1px 0 rgba(255, 255, 255, 0.95);
    }
    .candidatura-rh-lgpd-box .form-check-input {
        width: 1.2rem;
        height: 1.2rem;
        margin-top: 0.15rem;
        border-width: 2px;
        border-color: #0d6efd;
    }
    .candidatura-rh-lgpd-box .form-check-input:checked {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }
    .candidatura-rh-lgpd-box .form-check-label {
        font-weight: 500;
        padding-left: 0.15rem;
        line-height: 1.45;
    }
</style>
@endonce
@endif
