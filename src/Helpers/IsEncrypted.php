<?php

namespace BobKosse\DataSecurity\Helpers;

use Illuminate\Support\Facades\Crypt;

trait IsEncrypted
{
    /**
     * Check if the value is already encrypted.
     */
    protected function isAlreadyEncrypted(mixed $value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        try {
            Crypt::decryptString($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Encrypt privacy fields in the given values array.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function encryptPrivacyPayload(array $values): array
    {
        $model = $this->getModel();

        foreach ($model->privacyFields() as $field) {
            if (! array_key_exists($field, $values) || $values[$field] === null) {
                continue;
            }

            if ($this->isAlreadyEncrypted($values[$field])) {
                continue;
            }

            $values[$field] = Crypt::encryptString((string) $values[$field]);
        }

        return $values;
    }

    /**
     * Encrypt privacy fields in the given values array.
     *
     * @param  array<int, array<string, mixed>>  $values
     * @return array<int, array<string, mixed>>
     */
    protected function encryptPrivacyPayloads(array $values): array
    {
        return array_map(function (array $row): array {
            return $this->encryptPrivacyPayload($row);
        }, $values);
    }
}
