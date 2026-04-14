<?php

namespace BobKosse\DataCompliance\Traits;

use Illuminate\Support\Facades\Crypt;

trait HasPrivacy
{
    protected bool $revealed = false;

    public function revealPrivacy(bool $reveal = false): self
    {
        $this->revealed = $reveal;
        return $this;
    }

    public function privacyFields(): array
    {
        return [];
    }

    public function getAttribute($key): mixed
    {
        $value = parent::getAttribute($key);

        if (in_array($key, $this->privacyFields ?? [])) {
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
        if (in_array($key, $this->privacyFields ?? [])) {
            $value = Crypt::encryptString($value);
        }
        return parent::setAttribute($key, $value);
    }
}
