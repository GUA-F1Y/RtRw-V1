<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Authorization\Services\AccessResolver;
use App\Domain\File\Models\StoredFile;
use App\Domain\Household\Models\Household;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use App\Domain\Resident\Models\Resident;
use App\Policies\AuditPolicy;
use App\Policies\HouseholdPolicy;
use App\Policies\MembershipPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\OverviewPolicy;
use App\Policies\ResidentPolicy;
use App\Policies\RtPolicy;
use App\Policies\RwPolicy;
use App\Policies\StoredFilePolicy;
use App\Support\Http\RequestContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(RequestContext::class);
        $this->app->singleton(AccessResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Rw::class, RwPolicy::class);
        Gate::policy(Rt::class, RtPolicy::class);
        Gate::policy(Membership::class, MembershipPolicy::class);
        Gate::policy(StoredFile::class, StoredFilePolicy::class);
        Gate::policy(AuditLog::class, AuditPolicy::class);
        Gate::policy(Resident::class, ResidentPolicy::class);
        Gate::policy(Household::class, HouseholdPolicy::class);
        Gate::policy(\App\Domain\Finance\Models\CashAccount::class, \App\Policies\CashAccountPolicy::class);
        Gate::policy(\App\Domain\Finance\Models\FinanceCategory::class, \App\Policies\FinanceCategoryPolicy::class);
        Gate::policy(\App\Domain\Finance\Models\FinancialTransaction::class, \App\Policies\FinancialTransactionPolicy::class);
        Gate::policy(\App\Domain\Finance\Models\FinancialPeriodClosure::class, \App\Policies\FinancialPeriodClosurePolicy::class);
        Gate::policy(\App\Domain\Finance\Models\CashTransfer::class, \App\Policies\CashTransferPolicy::class);
        Gate::define('view-overview', [OverviewPolicy::class, 'view']);

        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'resident' => \App\Domain\Resident\Models\Resident::class,
            'household' => \App\Domain\Household\Models\Household::class,
            'financial_transaction' => \App\Domain\Finance\Models\FinancialTransaction::class,
            'financial_period_closure' => \App\Domain\Finance\Models\FinancialPeriodClosure::class,
            'cash_transfer' => \App\Domain\Finance\Models\CashTransfer::class,
        ]);
    }
}
