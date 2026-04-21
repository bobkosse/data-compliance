<?php

declare(strict_types=1);

namespace BobKosse\DataSecurity;

use BobKosse\DataSecurity\Commands\PrivacyAuditCommand;
use Illuminate\Support\ServiceProvider;

class DataSecurityServiceProvider extends ServiceProvider
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
