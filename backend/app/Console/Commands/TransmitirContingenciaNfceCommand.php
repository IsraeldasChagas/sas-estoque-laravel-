<?php

namespace App\Console\Commands;

use App\Services\Fiscal\FiscalNfceCicloService;
use Illuminate\Console\Command;

class TransmitirContingenciaNfceCommand extends Command
{
    protected $signature = 'fiscal:transmitir-contingencia {--limite=50 : Máximo de vendas em contingência a consultar}';

    protected $description = 'Consulta a Focus e promove NFC-e em contingência para autorizada quando a SEFAZ efetivar';

    public function handle(): int
    {
        $limite = max(1, (int) $this->option('limite'));
        $stats = FiscalNfceCicloService::transmitirPendentes($limite);
        $this->info(sprintf(
            'Contingência: %d processada(s), %d autorizada(s), %d ainda pendente(s), %d erro(s).',
            $stats['processadas'],
            $stats['autorizadas'],
            $stats['pendentes'],
            $stats['erros']
        ));

        return $stats['erros'] > 0 ? 1 : 0;
    }
}
