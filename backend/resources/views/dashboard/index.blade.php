@extends('layouts.app')

@section('title', 'Dashboard - Fundamentalista PRO')

@section('content')
    <h1 class="text-3xl font-bold text-gray-900 mb-8">Dashboard</h1>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        {{-- Card Patrimônio --}}
        <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
            <h3 class="text-gray-500 text-sm font-medium uppercase tracking-wider mb-2">Patrimônio</h3>
            <p class="text-2xl font-bold text-gray-900">R$ 0,00</p>
            <p class="text-sm text-gray-400 mt-1">Valor total investido</p>
        </div>

        {{-- Card Variação --}}
        <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
            <h3 class="text-gray-500 text-sm font-medium uppercase tracking-wider mb-2">Variação</h3>
            <p class="text-2xl font-bold text-green-600">0,00%</p>
            <p class="text-sm text-gray-400 mt-1">Variação no período</p>
        </div>

        {{-- Card Total de ativos --}}
        <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
            <h3 class="text-gray-500 text-sm font-medium uppercase tracking-wider mb-2">Total de ativos</h3>
            <p class="text-2xl font-bold text-gray-900">0</p>
            <p class="text-sm text-gray-400 mt-1">Ativos na carteira</p>
        </div>
    </div>
@endsection
