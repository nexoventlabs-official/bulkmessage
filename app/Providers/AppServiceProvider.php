<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\MetaWhatsAppService::class);
        $this->app->singleton(\App\Services\VoterService::class);
        $this->app->singleton(\App\Services\CampaignService::class);
    }

    public function boot(): void
    {
        //
    }
}
