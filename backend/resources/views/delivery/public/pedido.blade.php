@extends('delivery.public.layout')
@section('title', 'Pedido '.$pedido->codigo_publico)
@section('content')
@php
$steps=['pendente_loja'=>'Pedido enviado','recebido'=>'Recebido pela loja','preparo'=>'Em preparo','pronto'=>'Pronto','rota'=>'Saiu para entrega','entregue'=>'Entregue'];
$order=array_keys($steps); $current=array_search($pedido->status,$order,true);
@endphp
<div class="tracking-head"><div><p class="eyebrow">PEDIDO</p><h1>{{ $pedido->codigo_publico }}</h1><p>Atualizado em {{ \Illuminate\Support\Carbon::parse($pedido->updated_at)->format('d/m/Y H:i') }}</p></div><span class="status-pill">{{ $steps[$pedido->status] ?? str_replace('_',' ',ucfirst($pedido->status)) }}</span></div>
@if(in_array($pedido->status,['cancelado','endereco_nao_encontrado']))<div class="alert-error">Este pedido foi {{ $pedido->status === 'cancelado' ? 'cancelado' : 'encerrado: endereço não encontrado' }}.</div>
@else
<ol class="timeline">
@foreach($steps as $key=>$label)<li class="{{ $current !== false && $loop->index <= $current ? 'done' : '' }}"><i></i><div><strong>{{ $label }}</strong>@if($loop->index===$current)<small>Status atual</small>@endif</div></li>@endforeach
</ol>
@endif
<div class="tracking-grid">
<section class="form-card"><h2>Itens</h2>@foreach($itens as $item)<div class="line-item"><span>{{ rtrim(rtrim(number_format($item->quantidade,3,'.',''),'0'),'.') }}× {{ $item->nome_produto }}</span><strong>R$ {{ number_format((float)$item->subtotal,2,',','.') }}</strong></div>@endforeach
<hr><div class="line-item"><span>Subtotal</span><strong>R$ {{ number_format((float)$pedido->subtotal,2,',','.') }}</strong></div><div class="line-item"><span>Frete</span><strong>R$ {{ number_format((float)$pedido->frete_valor,2,',','.') }}</strong></div><div class="line-item grand-total"><span>Total</span><strong>R$ {{ number_format((float)$pedido->total,2,',','.') }}</strong></div></section>
<section class="form-card"><h2>Entrega e pagamento</h2><p><strong>{{ $pedido->fulfillment === 'entrega' ? 'Entrega' : 'Retirada na loja' }}</strong></p>@if($pedido->endereco_texto)<p>{{ $pedido->endereco_texto }}@if($pedido->endereco_complemento)<br>{{ $pedido->endereco_complemento }}@endif</p>@endif<p>Pagamento: {{ ucfirst($pedido->pagamento_forma) }}</p>@if($pedido->observacoes)<p>Observações: {{ $pedido->observacoes }}</p>@endif</section>
@include('delivery.partials.pix-publico', compact('config', 'pedido', 'pixConfigurada', 'pixQrDataUri', 'pixPayload', 'pixAutomatico', 'pixPollUrl'))
@include('delivery.partials.cartao-online-publico', compact('pedido', 'cartaoCheckoutUrl', 'cartaoOnlinePendente', 'cartaoOnlinePago', 'cartaoPollUrl'))
</div>
<p class="refresh-note">Esta página é atualizada automaticamente.</p>
<a class="btn ghost wide vf-back-after-action" href="{{ route('delivery.public.store', $slug) }}">← Continuar comprando</a>
@push('scripts')
@if(empty($pixPollUrl) && empty($cartaoPollUrl ?? null))
<script>setTimeout(()=>location.reload(),30000);</script>
@endif
@endpush
@endsection
