<?php

declare(strict_types=1);

namespace SionModel\Validator\Db;

/**
 * A row with this value exists.
 *
 * Two uses: a phrase key on the phrase-edit form, and an assignment id. The message a
 * visitor sees is overridden in both — "Phrase not found in database" — because the default
 * says nothing about what was looked for.
 */
final class RecordExists extends AbstractDb
{
    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     */
    public function isValid($value, $context = null): bool
    {
        $this->setValue($value);

        if (! $this->queryFor($value)) {
            $this->error(self::ERROR_NO_RECORD_FOUND);

            return false;
        }

        return true;
    }
}
