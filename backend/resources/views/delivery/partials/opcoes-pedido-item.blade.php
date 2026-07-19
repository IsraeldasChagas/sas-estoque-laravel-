@php
    $opArr = is_array($opcoesLinha ?? null) ? $opcoesLinha : [];
    $lista = is_array($opArr['adicionais'] ?? null) ? $opArr['adicionais'] : [];
    $obsItem = trim((string) ($opArr['observacao'] ?? ''));
    $notaItem = (int) ($opArr['nota_produto'] ?? 0);
@endphp
@if ($lista !== [] || $obsItem !== '' || ($notaItem >= 1 && $notaItem <= 5))
    <div class="item-opcoes-inner">
        @if ($notaItem >= 1 && $notaItem <= 5)
            <div class="item-nota" aria-label="Nota {{ $notaItem }} de 5">
                @for ($i = 1; $i <= 5; $i++)
                    {{ $i <= $notaItem ? '★' : '☆' }}
                @endfor
            </div>
        @endif
        @if ($obsItem !== '')
            <div><span class="muted">Obs.:</span> {{ $obsItem }}</div>
        @endif
        @if ($lista !== [])
            <ul class="item-op-list">
                @foreach ($lista as $op)
                    <li>
                        @if (($op['tipo'] ?? '') === 'retirar' || ($op['tipo'] ?? '') === 'retirar_ingrediente')
                            @php $qRet = (int) ($op['quantidade'] ?? 1); @endphp
                            − {{ $op['nome'] ?? '' }}@if ($qRet > 1)<span class="muted"> ×{{ $qRet }}</span>@endif
                        @else
                            @php $qOp = (int) ($op['quantidade'] ?? 1); @endphp
                            + {{ $op['nome'] ?? '' }}@if ($qOp > 1)<span class="muted"> ×{{ $qOp }}</span>@endif
                            @if ((float) ($op['preco'] ?? 0) > 0)
                                <span class="price">(+ R$ {{ number_format((float) $op['preco'] * max(1, $qOp), 2, ',', '.') }})</span>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
