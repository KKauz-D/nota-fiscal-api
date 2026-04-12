<?php

namespace App\Providers;

use App\Services\Certificate\CertificateService;
use App\Services\Certificate\CertificateStorageService;
use App\Services\External\BrasilApiService;
use App\Services\External\ViaCepService;
use App\Services\Ginfes\BatchSyncService;
use App\Services\Ginfes\CancelService;
use App\Services\Ginfes\SoapService;
use App\Services\Ginfes\XmlService;
use App\Services\Import\CnaeService;
use App\Services\Import\CustomerImportService;
use App\Services\Import\ExcelImportService;
use Illuminate\Support\ServiceProvider;

class GinfesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SoapService::class);
        $this->app->singleton(XmlService::class);
        $this->app->singleton(CertificateService::class);
        $this->app->singleton(CertificateStorageService::class);
        $this->app->singleton(CnaeService::class);
        $this->app->singleton(BrasilApiService::class);
        $this->app->singleton(ViaCepService::class);

        $this->app->singleton(CancelService::class, function ($app) {
            return new CancelService(
                $app->make(SoapService::class),
                $app->make(XmlService::class),
                $app->make(CertificateService::class),
            );
        });

        $this->app->singleton(BatchSyncService::class, function ($app) {
            return new BatchSyncService(
                $app->make(XmlService::class),
                $app->make(SoapService::class),
                $app->make(CertificateService::class),
                $app->make(CertificateStorageService::class),
                $app->make(CnaeService::class),
            );
        });

        $this->app->singleton(ExcelImportService::class, function ($app) {
            return new ExcelImportService(
                $app->make(CnaeService::class),
                $app->make(BrasilApiService::class),
                $app->make(ViaCepService::class),
            );
        });

        $this->app->singleton(CustomerImportService::class, function ($app) {
            return new CustomerImportService(
                $app->make(ViaCepService::class),
            );
        });
    }
}
