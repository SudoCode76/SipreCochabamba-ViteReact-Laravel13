<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerLegacyServiceAliases();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $username = Str::lower(trim((string) $request->input('username')));
            $key = $username !== '' ? $username : 'guest';

            return Limit::perMinute(5)->by($key.'|'.$request->ip());
        });
    }

    private function registerLegacyServiceAliases(): void
    {
        foreach ($this->legacyServiceAliases() as $legacyClass => $moduleClass) {
            if (! class_exists($legacyClass, false) && class_exists($moduleClass)) {
                class_alias($moduleClass, $legacyClass);
            }
        }
    }

    /**
     * @return array<class-string, class-string>
     */
    private function legacyServiceAliases(): array
    {
        $itemsServices = [
            'CalculationFormatPermissionService',
            'CalculationPercentageService',
            'FndrCalculationPercentageService',
            'FpsCalculationPercentageService',
            'GroupService',
            'HistoricalBreakdownPdfService',
            'ItemActionResolver',
            'ItemCompositionService',
            'LaborBreakdownPdfService',
            'LegacyUnitPriceAnalysisPdfService',
            'LegacyUnitPriceAnalysisService',
            'MachineryBreakdownPdfService',
            'MaterialBreakdownPdfService',
            'ObrasCalculationPercentageService',
            'PromanCalculationPercentageService',
            'SubgroupService',
            'UpreCalculationPercentageService',
        ];

        $itemAnalysisServices = [
            'BuildItemAnalysisContextService',
            'CreateAnalysisItemService',
            'ItemAnalysisListPriceService',
            'ItemAnalysisPermissionService',
            'ItemPriceAnalysisService',
            'ListAnalysisItemsService',
        ];

        $projectServices = [
            'ProjectBudgetByGroupPdfService',
            'ProjectBudgetService',
            'ProjectContextService',
            'ProjectCrudService',
            'ProjectFormatResolver',
            'ProjectGeneralBudgetPdfService',
            'ProjectHistoryService',
            'ProjectIncidenceSummaryPdfService',
            'ProjectInputBreakdownPdfService',
            'ProjectInputsReportPdfService',
            'ProjectItemIncidencePriceService',
            'ProjectItemInputSnapshotService',
            'ProjectItemService',
            'ProjectLegacyUnitPriceService',
            'ProjectListService',
            'ProjectPermissionService',
        ];

        $aliases = [
            'App\\Services\\Permissions\\PermissionResolverService' => 'App\\Modules\\Security\\Services\\PermissionResolverService',
            'App\\Services\\Parameters\\ParameterPermissionService' => 'App\\Modules\\Parameters\\Services\\ParameterPermissionService',
            'App\\Services\\Projects\\ModuleService' => 'App\\Modules\\Parameters\\Services\\ModuleService',
        ];

        foreach ($itemsServices as $service) {
            $aliases['App\\Services\\Items\\'.$service] = 'App\\Modules\\Items\\Services\\'.$service;
        }

        foreach ($itemAnalysisServices as $service) {
            $aliases['App\\Services\\Items\\Analysis\\'.$service] = 'App\\Modules\\Items\\Services\\Analysis\\'.$service;
        }

        foreach ($projectServices as $service) {
            $aliases['App\\Services\\Projects\\'.$service] = 'App\\Modules\\Projects\\Services\\'.$service;
        }

        return $aliases;
    }
}
