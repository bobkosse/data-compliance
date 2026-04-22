<?php

declare(strict_types=1);

namespace BobKosse\DataSecurity\Traits;

use BobKosse\DataSecurity\Builders\PrivacyEloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

trait HasPrivacy
{
    /**
     * Indicates whether privacy is revealed for the model.
     */
    protected bool $revealed = false;

    /**
     * Boot method for HasPrivacy trait.
     */
    protected static function bootHasPrivacy(): void
    {
        // Intentionally left empty.
        // Attribute encryption happens in setAttribute().
        // Bulk operations are handled by the custom builder.
    }

    /**
     * Use the privacy-aware Eloquent builder.
     */
    public function newEloquentBuilder($query): Builder
    {
        return new PrivacyEloquentBuilder($query);
    }

    /**
     * Checks if privacy is active for the model.
     */
    protected function isPrivacyActive(): bool
    {
        $privacyActive = $this instanceof Model && get_class($this) !== 'User';

        if (! $privacyActive) {
            Log::alert('Privacy is not active for this model');
        }

        return $privacyActive;
    }

    /**
     * Reveals or hides privacy fields for the model.
     */
    public function revealPrivacy(bool $reveal = false): self
    {
        $this->revealed = $reveal;

        return $this;
    }

    /**
     * Retrieves the privacy fields for the model.
     */
    public function privacyFields(): array
    {
        return $this->privacyFields ?? [];
    }

    /**
     * Retrieves the attribute value for the given key.
     */
    public function getAttribute($key): mixed
    {
        $value = parent::getAttribute($key);

        if ($this->isPrivacyActive() && in_array($key, $this->privacyFields(), true)) {
            if (! $this->revealed) {
                return '[ENCRYPTED]';
            }

            try {
                return Crypt::decryptString((string) $value);
            } catch (\Exception $e) {
                return $value;
            }
        }

        return $value;
    }

    /**
     * Sets the attribute value for the given key.
     */
    public function setAttribute($key, $value): mixed
    {
        if ($this->isPrivacyActive() && in_array($key, $this->privacyFields(), true)) {
            if ($value !== null) {
                $value = Crypt::encryptString((string) $value);
            }
        }

        return parent::setAttribute($key, $value);
    }
}
