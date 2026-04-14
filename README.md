# Data Compliance

[![Main Branch Status](https://github.com)](https://github.com)
[![Develop Branch Status](https://github.com/workflows/tests.yml/badge.svg?branch=develop)](https://github.com)

Laravel Shielded Privacy is a "Privacy by Design" toolkit that automatically secures sensitive user data through 
transparent database encryption. By enforcing an "encrypted-by-default" policy, it ensures that Personally Identifiable 
Information (PII) remains inaccessible to unauthorized users and logs, while providing seamless utilities for GDPR 
compliance, such as automated data portability exports and secure data anonymization.

## Installation

Install the package via composer: 
``` 
composer require bobkosse/data-compliance
```

## Usage

Add the trait to your model and specify the fields that should be anonymized:

```php
use BobKosse\DataCompliance\Traits\HasPrivacy;

class User extends Model
{
    use HasPrivacy;
    
    protected $privacyFields = [
        'name',
        'email',
    ];
}
```
The trait will automatically encrypt and hash the specified fields when saving the model. Default encrypted fields will
be filled with `[ENCRYPED]` when requested. To reveal the original values use the `revealPrivacy()` method.

## Example
An example of using the trait to anonymize a user's personal data:

```php
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@doe.com',
]);

var_dump($user->toArray());
/**
 * This will return:
 * [
 *      'name' => '[ENCRYPTED]',
 *      'email' => '[ENCRYPTED]',
 *  ]
 */
 
$user->revealPrivacy(true);
var_dump($user->toArray());
/**
 * This will return:
 * [
 *      'name' => 'John Doe',
 *      'email' => 'hashed_email',
 *  ]
 */
```

