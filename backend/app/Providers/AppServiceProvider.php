<?php

namespace App\Providers;

use App\Domain\Repositories\AuditLogRepository;
use App\Domain\Repositories\CarrierRepository;
use App\Domain\Repositories\CompanyRepository;
use App\Domain\Repositories\ContractRepository;
use App\Domain\Repositories\FreightTableRepository;
use App\Domain\Repositories\QuotationRepository;
use App\Domain\Repositories\SystemLogRepository;
use App\Domain\Repositories\TrackingEventRepository;
use App\Domain\Repositories\UserRepository;
use App\Domain\Services\AuthService;
use App\Domain\Services\QuotationEngineService;
use App\Domain\Services\ReportService;
use App\Infrastructure\Repositories\Eloquent\EloquentAuditLogRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentCarrierRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentCompanyRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentContractRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentFreightTableRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentQuotationRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentSystemLogRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentTrackingEventRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentUserRepository;
use App\Infrastructure\Services\QuotationEngine;
use App\Infrastructure\Services\ReportGenerator;
use App\Infrastructure\Services\TokenAuthService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UserRepository::class, EloquentUserRepository::class);
        $this->app->singleton(CompanyRepository::class, EloquentCompanyRepository::class);
        $this->app->singleton(CarrierRepository::class, EloquentCarrierRepository::class);
        $this->app->singleton(FreightTableRepository::class, EloquentFreightTableRepository::class);
        $this->app->singleton(QuotationRepository::class, EloquentQuotationRepository::class);
        $this->app->singleton(ContractRepository::class, EloquentContractRepository::class);
        $this->app->singleton(TrackingEventRepository::class, EloquentTrackingEventRepository::class);
        $this->app->singleton(AuditLogRepository::class, EloquentAuditLogRepository::class);
        $this->app->singleton(SystemLogRepository::class, EloquentSystemLogRepository::class);
        $this->app->singleton(AuthService::class, TokenAuthService::class);
        $this->app->singleton(QuotationEngineService::class, QuotationEngine::class);
        $this->app->singleton(ReportService::class, ReportGenerator::class);
    }

    public function boot(): void {}
}
