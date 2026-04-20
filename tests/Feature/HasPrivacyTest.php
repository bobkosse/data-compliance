<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use BobKosse\DataSecurity\Traits\HasPrivacy;

beforeEach(function () {
    Schema::create('test_customers', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->string('address');
        $table->string('internal_note'); // Non-private field
        $table->timestamps();
    });

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('username');
        $table->string('email');
        $table->timestamps();
    });
});

class TestCustomer extends Model {
    use HasPrivacy;

    protected $table = 'test_customers';

    protected $fillable = [
        'name', 'email', 'address', 'internal_note'
    ];

    protected $privacyFields = [
        'name', 'email', 'address'
    ];
}

class User extends Model {
    use HasPrivacy;

    protected $table = 'users';

    protected $fillable = [
        'username', 'email'
    ];

    protected $privacyFields = [
        'username', 'email'
    ];
}

class NonModel {
    use HasPrivacy;

    public function __construct(public string $email) {}

    protected $privacyFields = [
        'email'
    ];
}

it('encrypts sensitive data in the database', closure: function () {
    $customer = TestCustomer::create([
        'name' => 'John Doe',
        'email' => 'john@doe.com',
        'address' => '123 Road Avenue',
        'internal_note' => 'This is a secret note',
    ]);

    $rawDbData = DB::table('test_customers')->where('id', $customer->id)->first();

    expect($rawDbData->name)->not->toBe('John Doe');
    expect(strlen($rawDbData->name))->toBeGreaterThan(50);

    expect($rawDbData->email)->not->toBe('john@doe.com');
    expect(strlen($rawDbData->email))->toBeGreaterThan(50);

    expect($rawDbData->address)->not->toBe('123 Road Avenue');
    expect(strlen($rawDbData->address))->toBeGreaterThan(50);
});

it('returns encrypted placeholder by default', function () {
    $customer = TestCustomer::create([
        'name' => 'John Doe',
        'email' => 'john@doe.com',
        'address' => '123 Road Avenue',
        'internal_note' => 'This is a secret note',
    ]);

    expect($customer->name)->toBe('[ENCRYPTED]');
    expect($customer->email)->toBe('[ENCRYPTED]');
    expect($customer->address)->toBe('[ENCRYPTED]');
    expect($customer->internal_note)->toBe('This is a secret note');
});

it('only shows real data when explicitly revealed', function () {
    $customer = TestCustomer::create([
        'name' => 'John Doe',
        'email' => 'john@doe.com',
        'address' => '123 Road Avenue',
        'internal_note' => 'This is a secret note',
    ]);

    expect($customer->name)->toBe('[ENCRYPTED]');
    expect($customer->email)->toBe('[ENCRYPTED]');
    expect($customer->address)->toBe('[ENCRYPTED]');
    expect($customer->internal_note)->toBe('This is a secret note');

    $customer->revealPrivacy(true);

    expect($customer->name)->toBe('John Doe');
    expect($customer->email)->toBe('john@doe.com');
    expect($customer->address)->toBe('123 Road Avenue');
    expect($customer->internal_note)->toBe('This is a secret note');
});

it('shows encrypted data after set back to reveal false state', function () {
    $customer = TestCustomer::create([
        'name' => 'John Doe',
        'email' => 'john@doe.com',
        'address' => '123 Road Avenue',
        'internal_note' => 'This is a secret note',
    ]);

    $customer->revealPrivacy(true);

    expect($customer->name)->toBe('John Doe');
    expect($customer->email)->toBe('john@doe.com');
    expect($customer->address)->toBe('123 Road Avenue');
    expect($customer->internal_note)->toBe('This is a secret note');

    $customer->revealPrivacy(false);

    expect($customer->name)->toBe('[ENCRYPTED]');
    expect($customer->email)->toBe('[ENCRYPTED]');
    expect($customer->address)->toBe('[ENCRYPTED]');
    expect($customer->internal_note)->toBe('This is a secret note');
});

it('should not be usable on the User model of Laravel', function () {
    $user = User::create([
        'username' => 'johndoe',
        'email' => 'john@doe.com',
    ]);

    expect($user->username)->toBe('johndoe');
    expect($user->email)->toBe('john@doe.com');

    $user->revealPrivacy(true);

    expect($user->username)->toBe('johndoe');
    expect($user->email)->toBe('john@doe.com');
});

it('should only run on Laravel models', function () {
    $nonModel = new NonModel('john@doe.com');
    expect($nonModel->email)->toBe('john@doe.com');
    $nonModel->revealPrivacy(true);
    expect($nonModel->email)->toBe('john@doe.com');
    $nonModel->revealPrivacy(false);
    expect($nonModel->email)->toBe('john@doe.com');
});

it('should log an alert if HasPrivacy is used on User model', function () {
    Log::shouldReceive('alert')
        ->once()
        ->with(Mockery::on(function ($message) {
            return str_contains($message, 'Privacy is not active for this model');
        }));

    // Act
    $model = new User();
    $model->getAttribute('any_key');
});

it('returns the raw value when decryption fails', function () {
    Crypt::shouldReceive('decryptString')
        ->once()
        ->andThrow(new Exception('Decrypt failed'));

    $model = new TestCustomer();
    $model->setRawAttributes([
        'email' => 'encrypted-value',
    ]);
    $model->revealPrivacy(true);

    expect($model->getAttribute('email'))->toBe('encrypted-value');
});
