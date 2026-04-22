<?php

declare(strict_types=1);

namespace BobKosse\DataSecurity\Builders;

use BobKosse\DataSecurity\Helpers\IsEncrypted;
use Illuminate\Database\Eloquent\Builder;

/**
 * Custom Eloquent builder with privacy encryption support.
 */
class PrivacyEloquentBuilder extends Builder
{
    use IsEncrypted;

    /**
     * Encrypts privacy payloads before inserting into the database.
     */
    public function insert(array $values): bool
    {
        return parent::insert($this->encryptPrivacyPayloads($values));
    }

    /**
     * Inserts or ignores privacy payloads into the database.
     */
    public function insertOrIgnore(array $values): int
    {
        return parent::insertOrIgnore($this->encryptPrivacyPayloads($values));
    }

    /**
     * Upserts privacy payloads into the database.
     */
    public function upsert(array $values, $uniqueBy, $update = null): int
    {
        return parent::upsert($this->encryptPrivacyPayloads($values), $uniqueBy, $update);
    }

    /**
     * Updates privacy payloads in the database.
     */
    public function update(array $values): int
    {
        return parent::update($this->encryptPrivacyPayload($values));
    }
}
