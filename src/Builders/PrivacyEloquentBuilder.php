<?php

declare(strict_types=1);

namespace BobKosse\DataSecurity\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;

class PrivacyEloquentBuilder extends Builder
{
    public function insert(array $values): bool
    {
        return parent::insert($this->encryptPrivacyPayloads($values));
    }

    public function insertOrIgnore(array $values): int
    {
        return parent::insertOrIgnore($this->encryptPrivacyPayloads($values));
    }

    public function upsert(array $values, $uniqueBy, $update = null): int
    {
        return parent::upsert($this->encryptPrivacyPayloads($values), $uniqueBy, $update);
    }

    public function update(array $values): int
    {
        return parent::update($this->encryptPrivacyPayload($values));
    }

    /**
     * @param  array<int, array<string, mixed>>  $values
     * @return array<int, array<string, mixed>>
     */
    protected function encryptPrivacyPayloads(array $values): array
    {
        return array_map(function (array $row): array {
            return $this->encryptPrivacyPayload($row);
        }, $values);
    }

    /**
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
}
