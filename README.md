# Laravel Data Compliance (Privacy Trait & Audit)
This package provides a lightweight solution for handling sensitive data within Laravel Eloquent models. It allows you to automatically encrypt and decrypt specific fields and includes an audit tool to monitor privacy compliance across your application.

## Features
- **Automatic Encryption:** Automatically encrypts sensitive data when saving to the database.
- **On-the-fly Decryption:** Decrypts data automatically when accessing model attributes.
- **Privacy Masking:** By default, encrypted fields return ```[ENCRYPTED]``` unless explicitly "revealed."
- **Privacy Audit:** A built-in CLI tool to scan your models and verify which fields are protected.

## Installation
1. Add the trait to your project (ensure it is placed in the ```BobKosse\DataCompliance\Traits``` namespace).
2. Register the ```PrivacyAuditCommand``` in your ```app/Console/Kernel.php``` if not automatically discovered.

## Usage
### 1. Preparing your Models
Add the ```HasPrivacy``` trait to any Eloquent model containing sensitive data. Define which fields should be encrypted by adding a ```$privacyFields``` array.

```php
use BobKosse\DataCompliance\Traits\HasPrivacy;
use Illuminate\Database\Eloquent\Model;

class PatientProfile extends Model
{
  use HasPrivacy;

  protected $privacyFields = [
    'phone_number',
    'address',
    'social_security_number'
  ];
}
```

### 2. How it works
- **Saving Data:** When you set a value for a field defined in ```$privacyFields```, the trait automatically encrypts it using Laravel's ```Crypt``` facade before it hits the database.
- **Accessing Data:** By default, accessing these fields will return the string ```[ENCRYPTED]```. This prevents accidental leaking of sensitive data in logs, API responses, or views.
- **Revealing Data:** To access the actual decrypted value, you must explicitly call the revealPrivacy() method.

```php
$profile = PatientProfile::find(1);

// Returns "[ENCRYPTED]"
echo $profile->phone_number;

// Returns the decrypted value (e.g., "+31 6 12345678")
$profile->revealPrivacy(true);
echo $profile->phone_number;
```

This can also be used in combination with authorization policies to ensure sensitive data is only accessible by authorized users.

## Privacy Audit Command
The package includes a console command to give you an overview of your data compliance status. It scans a directory for Eloquent models and reports which ones are using the privacy trait.

### Run the audit:
```bash
php artisan privacy:audit app/Models
```

### Output:
The command will display a table showing:
- **Model:** The full class name of the model.
- **Has Privacy Trait:** A green "Yes" or red "No" indicating if the trait is implemented.
- **Privacy Fields:** A list of the fields currently being encrypted.

## Important Notes
- **User Model:** The trait contains a safety check (```isPrivacyActive```) that prevents it from running on the default ```User``` class to avoid locking users out of their accounts if email/password fields are accidentally encrypted.
- **Database Requirements:** Ensure the database columns for privacy fields are large enough to hold encrypted strings (typically ```TEXT``` or ```BLOB```).
