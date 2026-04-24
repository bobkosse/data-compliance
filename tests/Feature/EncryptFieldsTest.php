<?php

declare(strict_types=1);

namespace Tests\Feature;

use BobKosse\DataSecurity\Helpers\ModelHandlingHelper;
use BobKosse\DataSecurity\Helpers\IsEncryptedHelper;
use BobKosse\DataSecurity\Commands\PrivacyEncryptFieldCommand;
use BobKosse\DataSecurity\Traits\HasPrivacy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Mockery;

beforeEach(function () {
    Schema::dropIfExists('encrypt_customers');
    Schema::dropIfExists('encrypt_patients');
    Schema::dropIfExists('encrypt_empty_table');
    Schema::dropIfExists('encrypt_failing_models');
    Schema::dropIfExists('private_emails');

Schema::create('encrypt_empty_table', function (Blueprint $table) {
    $table->id();
    $table->string('email')->nullable();
    $table->timestamps();
});
Schema::create('private_emails', function (Blueprint $table) {
        $table->id();
        $table->string('email')->nullable();
        $table->timestamps();
    });
Schema::create('encrypt_failing_models', function (Blueprint $table) {
    $table->id();
    $table->string('email')->nullable();
    $table->timestamps();
});

    Schema::create('encrypt_customers', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->timestamps();
    });

    File::deleteDirectory(__DIR__.'/TmpEncryptModels');
    File::ensureDirectoryExists(__DIR__.'/TmpEncryptModels');

    File::put(__DIR__.'/TmpEncryptModels/PrivateEmail.php', <<<'PHP'
<?php

namespace Tests\Feature\TmpEncryptModels;

use BobKosse\DataSecurity\Traits\HasPrivacy;
use Illuminate\Database\Eloquent\Model;

class PrivateEmail extends Model
{
    use HasPrivacy;

    protected $table = 'private_emails';

    protected $fillable = [
        'email',
    ];
}
PHP);

    File::put(__DIR__.'/TmpEncryptModels/EncryptCustomer.php', <<<'PHP'
<?php

namespace Tests\Feature\TmpEncryptModels;

use BobKosse\DataSecurity\Traits\HasPrivacy;
use Illuminate\Database\Eloquent\Model;

class EncryptCustomer extends Model
{
    use HasPrivacy;

    protected $table = 'encrypt_customers';

    protected $fillable = [
        'name',
        'email',
        'phone',
    ];

    protected $privacyFields = [
        'name',
    ];
}
PHP);

    File::put(__DIR__.'/TmpEncryptModels/EncryptPatient.php', <<<'PHP'
<?php

namespace Tests\Feature\TmpEncryptModels;

use BobKosse\DataSecurity\Traits\HasPrivacy;
use Illuminate\Database\Eloquent\Model;

class EncryptPatient extends Model
{
    use HasPrivacy;

    protected $table = 'encrypt_patients';

    protected $fillable = [
        'full_name',
        'ssn',
    ];

    protected $privacyFields = [
        'full_name',
    ];
}
PHP);

    File::put(__DIR__.'/TmpEncryptModels/NoPrivacyFieldsModel.php', <<<'PHP'
<?php

namespace Tests\Feature\TmpEncryptModels;

use BobKosse\DataSecurity\Traits\HasPrivacy;
use Illuminate\Database\Eloquent\Model;

class NoPrivacyFieldsModel extends Model
{
    use HasPrivacy;

    protected $table = 'encrypt_empty_table';

    protected $fillable = [
        'email',
    ];
}
PHP);

    File::put(__DIR__.'/TmpEncryptModels/EmptyFieldsModel.php', <<<'PHP'
<?php

namespace Tests\Feature\TmpEncryptModels;

use Illuminate\Database\Eloquent\Model;

class EmptyFieldsModel extends Model
{
    protected $table = 'encrypt_empty_table';

    protected $fillable = [];
}
PHP);

    File::put(__DIR__.'/TmpEncryptModels/FailingEncryptModel.php', <<<'PHP'
<?php

namespace Tests\Feature\TmpEncryptModels;

use BobKosse\DataSecurity\Traits\HasPrivacy;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class FailingEncryptModel extends Model
{
    use HasPrivacy;

    protected $table = 'encrypt_failing_models';

    protected $fillable = [
        'email',
    ];

    protected $privacyFields = [];

    public static bool $failOnSave = false;

    protected static function booted(): void
    {
        static::saving(function () {
            if (static::$failOnSave) {
                throw new RuntimeException('Simulated save failure');
            }
        });
    }
}
PHP);

    require_once __DIR__.'/TmpEncryptModels/FailingEncryptModel.php';
    require_once __DIR__.'/TmpEncryptModels/EncryptCustomer.php';
    require_once __DIR__.'/TmpEncryptModels/EncryptPatient.php';
    require_once __DIR__.'/TmpEncryptModels/NoPrivacyFieldsModel.php';
    require_once __DIR__.'/TmpEncryptModels/PrivateEmail.php';
    require_once __DIR__.'/TmpEncryptModels/EmptyFieldsModel.php';
});

afterEach(function () {
    File::deleteDirectory(__DIR__.'/TmpEncryptModels');
});

it('fails when no models are found', function () {
    config()->set('data-security.paths', [base_path('non-existing-path')]);

    $this->artisan('privacy:encrypt-field')
        ->expectsOutput('No models found')
        ->assertExitCode(1);
});

it('schould give a choice for what model to encrypt', function () {
    config()->set('data-security.paths', [__DIR__.'/TmpEncryptModels']);

    $this->artisan('privacy:encrypt-field')
        ->expectsQuestion('Which model do you want to update?','Tests\Feature\TmpEncryptModels\EncryptCustomer')
        ->expectsQuestion('Which field do you want to encrypt?','phone');
});

it('fails when no valid fields are available', function () {
    Schema::dropIfExists('encrypt_empty_table');
    config()->set('data-security.paths', [__DIR__.'/TmpEncryptModels']);

    $this->artisan('privacy:encrypt-field')
        ->expectsQuestion('Which model do you want to update?', 'Tests\\Feature\\TmpEncryptModels\\EmptyFieldsModel')
        ->expectsOutput('No valid fields available to encrypt.')
        ->assertExitCode(1);
});

it('fails when the selected field already exists in privacyFields', function () {
    config()->set('data-security.paths', [__DIR__.'/TmpEncryptModels']);

    $mockModelHelper = Mockery::mock(ModelHandlingHelper::class);
    $mockIsEncryptedHelper = Mockery::mock(IsEncryptedHelper::class);

    $expectedModels = ['Tests\\Feature\\TmpEncryptModels\\EncryptCustomer'];

    $mockModelHelper->shouldReceive('getModels')
        ->withAnyArgs() // Of specifiek: ->with('some/path') als je een argument verwacht
        ->andReturn($expectedModels);

    $mockModelHelper->shouldReceive('getModelFields')
        ->with('Tests\\Feature\\TmpEncryptModels\\EncryptCustomer')
        ->andReturn(['name', 'email', 'phone']);

    $mockModelHelper->shouldReceive('getPrivacyFields')
        ->with('Tests\\Feature\\TmpEncryptModels\\EncryptCustomer')
        ->andReturn(['name', 'phone']);

    $mockModelHelper->shouldReceive('fieldAlreadyExistsInPrivacyFields')
        ->withAnyArgs() // Of specifieker: ->with('TestClass', 'fieldName')
        ->andReturn(true);

    $command = new class($mockModelHelper, $mockIsEncryptedHelper) extends PrivacyEncryptFieldCommand
    {
        protected function getModels(string $path): array
        {
            return ['Tests\\Feature\\TmpEncryptModels\\EncryptCustomer'];
        }

        protected function getModelFields(string $modelClass): array
        {
            return ['name', 'email', 'phone'];
        }

        protected function getPrivacyFields(string $modelClass): array
        {
            return ['name', 'phone'];
        }

        protected function fieldAlreadyExistsInPrivacyFields(string $modelClass, string $field): bool
        {
            return true;
        }
    };

    $this->app->instance(PrivacyEncryptFieldCommand::class, $command);

    $this->artisan('privacy:encrypt-field')
        ->expectsQuestion('Which model do you want to update?', 'Tests\\Feature\\TmpEncryptModels\\EncryptCustomer')
        ->expectsChoice('Which field do you want to encrypt?', 'phone', ['email'])
        ->expectsOutput('Field phone already exists in privacyFields')
        ->assertExitCode(1);
});

it('fails when the selected column does not exist in the database', function () {
    config()->set('data-security.paths', [__DIR__.'/TmpEncryptModels']);

    $mockModelHelper = Mockery::mock(ModelHandlingHelper::class);
    $mockIsEncryptedHelper = Mockery::mock(IsEncryptedHelper::class);

    $expectedModels = ['Tests\\Feature\\TmpEncryptModels\\EncryptCustomer'];

    $mockModelHelper->shouldReceive('getModels')
        ->withAnyArgs()
        ->andReturn($expectedModels);

    $mockModelHelper->shouldReceive('getModelFields')
        ->with('Tests\\Feature\\TmpEncryptModels\\EncryptCustomer')
        ->andReturn(['name', 'email', 'phone']);

    $mockModelHelper->shouldReceive('getPrivacyFields')
        ->with('Tests\\Feature\\TmpEncryptModels\\EncryptCustomer')
        ->andReturn([]);

    $mockModelHelper->shouldReceive('fieldAlreadyExistsInPrivacyFields')
        ->withAnyArgs()
        ->andReturn(false);

    $command = new class($mockModelHelper, $mockIsEncryptedHelper) extends PrivacyEncryptFieldCommand {};

    $this->app->instance(PrivacyEncryptFieldCommand::class, $command);

    $this->artisan('privacy:encrypt-field')
        ->expectsQuestion('Which model do you want to update?', 'Tests\\Feature\\TmpEncryptModels\\EncryptCustomer')
        ->expectsChoice('Which field do you want to encrypt?', 'phonenr', ['name', 'email', 'phone'])
        ->expectsOutput('Field phonenr does not exist in database')
        ->assertExitCode(1);
});

it('stops when the selected field already appears encrypted and the user declines', function () {
    config()->set('data-security.paths', [__DIR__.'/TmpEncryptModels']);

    $customer = \Tests\Feature\TmpEncryptModels\EncryptCustomer::create([
        'name' => 'John Doe',
        'email' => 'john@doe.com',
        'phone' => '0612345678',
    ]);

    DB::table('encrypt_customers')
        ->where('id', $customer->id)
        ->update([
            'phone' => Crypt::encryptString('0612345678'),
        ]);

    $this->artisan('privacy:encrypt-field')
        ->expectsQuestion('Which model do you want to update?', 'Tests\\Feature\\TmpEncryptModels\\EncryptCustomer')
        ->expectsChoice('Which field do you want to encrypt?', 'phone', ['email', 'phone'])
        ->expectsConfirmation('Field phone already appears encrypted. Do you want to add it to privacyFields if Tests\\Feature\\TmpEncryptModels\\EncryptCustomer?', 'no')
        ->expectsOutput('Field phone is already encrypted.')
        ->assertExitCode(0);
});

it('adds the field to privacyFields when the selected field already appears encrypted and the user confirms', function () {
    config()->set('data-security.paths', [__DIR__.'/TmpEncryptModels']);

    $customer = \Tests\Feature\TmpEncryptModels\EncryptCustomer::create([
        'name' => 'John Doe',
        'email' => 'john@doe.com',
        'phone' => '0612345678',
    ]);

    DB::table('encrypt_customers')
        ->where('id', $customer->id)
        ->update([
            'phone' => Crypt::encryptString('0612345678'),
        ]);

    $this->artisan('privacy:encrypt-field')
        ->expectsQuestion('Which model do you want to update?', 'Tests\\Feature\\TmpEncryptModels\\EncryptCustomer')
        ->expectsChoice('Which field do you want to encrypt?', 'phone', ['email', 'phone'])
        ->expectsConfirmation('Field phone already appears encrypted. Do you want to add it to privacyFields if Tests\\Feature\\TmpEncryptModels\\EncryptCustomer?', 'yes')
        ->expectsOutput('Field phone added to privacyFields of model Tests\\Feature\\TmpEncryptModels\\EncryptCustomer')
        ->assertExitCode(0);
});

it('returns failure when the encryption transaction throws an exception', function () {
    config()->set('data-security.paths', [__DIR__.'/TmpEncryptModels']);

    \Tests\Feature\TmpEncryptModels\FailingEncryptModel::$failOnSave = false;

    \Tests\Feature\TmpEncryptModels\FailingEncryptModel::create([
        'email' => 'john@doe.com',
    ]);

    \Tests\Feature\TmpEncryptModels\FailingEncryptModel::$failOnSave = true;

    $this->artisan('privacy:encrypt-field')
        ->expectsQuestion('Which model do you want to update?', 'Tests\\Feature\\TmpEncryptModels\\FailingEncryptModel')
        ->expectsChoice('Which field do you want to encrypt?', 'email', ['email'])
        ->expectsOutput('Encryption failed: Simulated save failure')
        ->assertExitCode(1);
});

it('encrypts records through the HasPrivacy model path', function () {
    config()->set('data-security.paths', [__DIR__.'/TmpEncryptModels']);

    \Tests\Feature\TmpEncryptModels\NoPrivacyFieldsModel::create([
        'email' => 'john@doe.com',
    ]);

    $this->artisan('privacy:encrypt-field')
        ->expectsQuestion('Which model do you want to update?', 'Tests\\Feature\\TmpEncryptModels\\NoPrivacyFieldsModel')
        ->expectsChoice('Which field do you want to encrypt?', 'email', ['email'])
        ->expectsOutput('Encryption completed')
        ->assertExitCode(0);

    $rawRow = DB::table('encrypt_empty_table')->first();

    expect($rawRow->email)->not->toBe('john@doe.com');
});

it('skips null values during the encryption loop', function () {
    config()->set('data-security.paths', [__DIR__.'/TmpEncryptModels']);

    \Tests\Feature\TmpEncryptModels\NoPrivacyFieldsModel::create([
        'email' => null,
    ]);

    $this->artisan('privacy:encrypt-field')
        ->expectsQuestion('Which model do you want to update?', 'Tests\\Feature\\TmpEncryptModels\\NoPrivacyFieldsModel')
        ->expectsChoice('Which field do you want to encrypt?', 'email', ['email'])
        ->expectsOutput('Encryption completed')
        ->assertExitCode(0);

    $rawRow = DB::table('encrypt_empty_table')->first();

    expect($rawRow->email)->toBeNull();
});

it('skips already encrypted values during the encryption loop', function () {
    config()->set('data-security.paths', [__DIR__.'/TmpEncryptModels']);

    DB::table('private_emails')->insert([
        'email' => Crypt::encryptString('john@doe.com'),
        'created_at' => now(),
        'updated_at' => now(),
    ],[
        'email' => 'jane.doe@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('privacy:encrypt-field')
        ->expectsQuestion('Which model do you want to update?', 'Tests\\Feature\\TmpEncryptModels\\PrivateEmail')
        ->expectsChoice('Which field do you want to encrypt?', 'email', ['email'])
        ->expectsConfirmation('Field email already appears encrypted. Do you want to add it to privacyFields if Tests\Feature\TmpEncryptModels\PrivateEmail?', 'yes');

    $rawRow = DB::table('private_emails')->first();

    expect($rawRow->email)->not->toBeNull();
});
