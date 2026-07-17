<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Services\Delivery\DeliveryAccessService;
use Illuminate\Http\Request;

abstract class DeliveryBaseController extends Controller
{
    public function __construct(
        protected readonly DeliveryAccessService $access,
    ) {}

    protected function auth(Request $request, string $modulo): object
    {
        $this->access->verificarTabelas();

        return $this->access->autorizar($request, $modulo);
    }
}
