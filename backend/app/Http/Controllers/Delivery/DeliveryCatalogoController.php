<?php

namespace App\Http\Controllers\Delivery;

use App\Services\Delivery\DeliveryAccessService;
use App\Services\Delivery\DeliveryCatalogoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryCatalogoController extends DeliveryBaseController
{
    public function __construct(
        DeliveryAccessService $access,
        private readonly DeliveryCatalogoService $catalogo,
    ) {
        parent::__construct($access);
    }

    public function __invoke(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryCatalogo');
        $unidadeId = $this->access->exigirUnidade($request, $usuario);

        return response()->json($this->catalogo->consulta($unidadeId, $request));
    }
}
