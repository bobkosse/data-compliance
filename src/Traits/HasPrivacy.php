<?php

declare(strict_types=1);

namespace BobKosse\DataSecurity\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

trait HasPrivacy
{
    protected bool $revealed = false;

    /**
     * Boot method for HasPrivacy trait.
     */
    protected function bootHasPrivacy(): void
    {
        static::saving(function ($model) {
            $model->encryptPrivacyFields();
        });

        static::updating(function ($model) {
            $model->encryptPrivacyFields();
        });
    }

    /**
     * Encrypts privacy fields in the model.
     */
    protected function encryptPrivacyFields(): void
    {
        $privateFields = $this->getPrivateFields();

        foreach ($privateFields as $field) {
            if ($this->isDirty($field)) {
                $value = $this->getAttributes()[$field] ?? null;

                if (! is_string($value) || ! str_starts_with($value, '[ENCRYPTED]')) {
                    $this->attributes[$field] = $this->encryptValue($value);
                }
            }
        }
    }

    protected function isPrivacyActive(): bool
    {
        $privacyActive = $this instanceof Model && get_class($this) !== 'User';
        if (! $privacyActive) {
            Log::alert('Privacy is not active for this model');
        }

        return $privacyActive;
    }

    public function revealPrivacy(bool $reveal = false): self
    {
        $this->revealed = $reveal;

        return $this;
    }

    public function privacyFields(): array
    {
        return $this->privacyFields ?? [];
    }

    public function getAttribute($key): mixed
    {
        $value = parent::getAttribute($key);
        if ($this->isPrivacyActive() && in_array($key, $this->privacyFields())) {
            if (! $this->revealed) {
                return '[ENCRYPTED]';
            }

            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return $value;
            }
        }

        return $value;
    }

    public function setAttribute($key, $value): mixed
    {
        if ($this->isPrivacyActive() && in_array($key, $this->privacyFields ?? [])) {
            $value = Crypt::encryptString($value);
        }

        return parent::setAttribute($key, $value);
    }
}
