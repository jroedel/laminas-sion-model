<?php

declare(strict_types=1);

namespace SionModel\Validator\Db;

/**
 * No row with this value exists.
 *
 * One use: a library's name must be unique.
 */
final class NoRecordExists extends AbstractDb
{
    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     */
    public function isValid($value, $context = null): bool
    {
        $this->setValue($value);

        if ($this->queryFor($value)) {
            $this->error(self::ERROR_RECORD_FOUND);

            return false;
        }

        return true;
    }
}
