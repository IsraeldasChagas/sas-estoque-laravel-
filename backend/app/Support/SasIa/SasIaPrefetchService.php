<?php

namespace App\Support\SasIa;

use App\Services\SasIaToolService;

/**
 * Pré-consulta dados do banco conforme o assunto da pergunta — garante que a IA tenha os registros reais.
 */
final class SasIaPrefetchService
{
    public function __construct(
        private SasIaToolService $tools
    ) {}

    public function blocoParaPrompt(SasIaContext $ctx, string $mensagem): string
    {
        $partes = [];
        $lower = mb_strtolower(trim($mensagem));

        if ($this->ehSobreReservas($lower)) {
            $args = $this->argsReservaDaMensagem($lower);
            $dados = $this->tools->executar($ctx, 'consultar_reservas_periodo', $args);
            $partes[] = 'RESERVAS_DE_MESA (dados reais do banco): '.json_encode($dados, JSON_UNESCAPED_UNICODE);
        }

        if ($this->ehSobreUnidades($lower)) {
            $busca = $this->extrairTermoBusca($mensagem);
            $dados = $this->tools->executar($ctx, 'consultar_resumo_unidades', $busca !== '' ? ['busca' => $busca] : []);
            $partes[] = 'UNIDADES_EMPRESAS (CNPJ e cadastro): '.json_encode($dados, JSON_UNESCAPED_UNICODE);
        }

        if ($this->ehSobreProdutosEstoque($lower)) {
            $dados = $this->tools->executar($ctx, 'consultar_resumo_produtos', []);
            $partes[] = 'PRODUTOS_ESTOQUE: '.json_encode($dados, JSON_UNESCAPED_UNICODE);
        }

        if ($this->ehSobreRh($lower)) {
            $dados = $this->tools->executar($ctx, 'consultar_rh_recrutamento_resumo', []);
            $partes[] = 'RH_RECRUTAMENTO: '.json_encode($dados, JSON_UNESCAPED_UNICODE);
        }

        if ($partes === []) {
            return '';
        }

        return "\n\nDados já consultados no sistema para esta pergunta (obrigatório usar na resposta — não invente):\n"
            .implode("\n", $partes);
    }

    private function ehSobreReservas(string $lower): bool
    {
        return (bool) preg_match('/\b(reserva|reservas|mesa|mesas|agendamento|agendamentos)\b/u', $lower);
    }

    private function ehSobreUnidades(string $lower): bool
    {
        return (bool) preg_match('/\b(cnpj|cnpjs|unidade|unidades|empresa|empresas|loja|lojas|filial|filiais)\b/u', $lower);
    }

    private function ehSobreProdutosEstoque(string $lower): bool
    {
        return (bool) preg_match('/\b(produto|produtos|estoque|cadastrado|cadastrados|item|itens)\b/u', $lower);
    }

    private function ehSobreRh(string $lower): bool
    {
        return (bool) preg_match('/\b(candidato|candidatos|currículo|curriculo|currículos|vaga|vagas|rh|recrutamento)\b/u', $lower);
    }

    /** @return array<string, mixed> */
    private function argsReservaDaMensagem(string $lower): array
    {
        if (preg_match('/\bhoje\b/u', $lower)) {
            return ['data' => now()->format('Y-m-d')];
        }
        if (preg_match('/\bamanh[ãa]\b/u', $lower)) {
            return ['data' => now()->addDay()->format('Y-m-d')];
        }
        if (preg_match('/\bontem\b/u', $lower)) {
            return ['data' => now()->subDay()->format('Y-m-d')];
        }

        return [];
    }

    private function extrairTermoBusca(string $mensagem): string
    {
        if (preg_match('/cnpj[:\s]*([\d.\/\-]+)/iu', $mensagem, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/unidade\s+([a-záàâãéêíóôõúç0-9\s]{3,40})/iu', $mensagem, $m)) {
            return trim($m[1]);
        }

        return '';
    }
}
