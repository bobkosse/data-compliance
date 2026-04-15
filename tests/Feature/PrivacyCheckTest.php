<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

it('scans the directory and finds models', function () {
    $scanDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'privacy-audit-' . uniqid('', true);
    $modelsDir = $scanDir;
    $vendorDir = $scanDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'Acme';

    File::makeDirectory($vendorDir, 0755, true, true);

    $protectedModelPath = $modelsDir . DIRECTORY_SEPARATOR . 'ProtectedModel.php';
    $unprotectedModelPath = $modelsDir . DIRECTORY_SEPARATOR . 'UnprotectedModel.php';
    $vendorModelPath = $vendorDir . DIRECTORY_SEPARATOR . 'VendorModel.php';

    file_put_contents($protectedModelPath, <<<'PHP'
<?php

namespace Tests\MockModels;

use BobKosse\DataCompliance\Traits\HasPrivacy;
use Illuminate\Database\Eloquent\Model;

class ProtectedModel extends Model
{
    use HasPrivacy;

    protected $privacyFields = ['email', 'phone'];
}
PHP);

    file_put_contents($unprotectedModelPath, <<<'PHP'
<?php

namespace Tests\MockModels;

use Illuminate\Database\Eloquent\Model;

class UnprotectedModel extends Model
{
    //
}
PHP);

    file_put_contents($vendorModelPath, <<<'PHP'
<?php

namespace Tests\MockModels\Vendor;

use BobKosse\DataCompliance\Traits\HasPrivacy;
use Illuminate\Database\Eloquent\Model;

class VendorModel extends Model
{
    use HasPrivacy;

    protected $privacyFields = ['secret'];
}
PHP);

    require_once $protectedModelPath;
    require_once $unprotectedModelPath;
    require_once $vendorModelPath;

    try {
        $this->artisan('privacy:audit', [
            '--scan' => $modelsDir,
        ])
            ->expectsOutputToContain('ProtectedModel')
            ->expectsOutputToContain('UnprotectedModel')
            ->expectsOutputToContain('Yes')
            ->expectsOutputToContain('No')
            ->expectsOutputToNotContain('VendorModel')
            ->assertExitCode(0);

        $this->artisan('privacy:audit', [
            '--scan' => $modelsDir,
            '--include-vendor' => true,
        ])
            ->expectsOutputToContain('ProtectedModel')
            ->expectsOutputToContain('UnprotectedModel')
            ->expectsOutputToContain('VendorModel')
            ->expectsOutputToContain('Yes')
            ->expectsOutputToContain('No')
            ->assertExitCode(0);
    } finally {
        if (File::isDirectory($scanDir)) {
            File::deleteDirectory($scanDir);
        }
    }
});

Artisan::call('privacy:audit');

expect(Artisan::output())->not->toContain('password');
