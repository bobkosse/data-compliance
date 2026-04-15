<?php

namespace BobKosse\DataCompliance;

use BobKosse\DataCompliance\Commands\PrivacyAuditCommand;
use Illuminate\Support\ServiceProvider;

class DataComplianceServiceProvider extends ServiceProvider
{
    public function boot(): void {
        $this->commands([
            PrivacyAuditCommand::class,
        ]);
    }
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PrivacyAuditCommand::class,
            ]);
        }

    }
}
