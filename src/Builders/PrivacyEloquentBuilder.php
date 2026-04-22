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

        if (! method_exists($model, 'privacyFields')) {
            return $values;
        }

        foreach ($model->privacyFields() as $field) {
            if (! array_key_exists($field, $values)) {
                continue;
            }

            $value = $values[$field];

            if ($value === null) {
                continue;
            }

            $values[$field] = Crypt::encryptString((string) $value);
        }

        return $values;
    }
}
