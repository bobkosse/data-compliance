<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use BobKosse\DataCompliance\Traits\HasPrivacy;

beforeEach(function () {
    Schema::create('test_customers', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->string('address');
        $table->string('internal_note'); // Non-private field
        $table->timestamps();
    });
});

class TestCustomer extends Model {
    use HasPrivacy;

    protected $table = 'test_customers';
    protected $guarded = [];
    protected $privacyFields = [
        'name', 'email', 'address'
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
