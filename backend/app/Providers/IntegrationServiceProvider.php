<?php

namespace App\Providers;

use App\Services\Integrations\IntegrationManager;
use App\Services\Integrations\Providers\VendaFacilProvider;
use Illuminate\Support\ServiceProvider;

class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IntegrationManager::class, function () {
            $manager = new IntegrationManager;
            $manager->register(new VendaFacilProvider);

            return $manager;
        });
    }
}
