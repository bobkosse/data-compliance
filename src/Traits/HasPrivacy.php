<?php

namespace BobKosse\DataCompliance\Traits;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

trait HasPrivacy
{
    protected bool $revealed = false;

    protected function isPrivacyActive(): bool
    {
        $privacyActive = $this instanceof Model && get_class($this) !== 'User';
        if(!$privacyActive) {
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
            if (!$this->revealed) {
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
