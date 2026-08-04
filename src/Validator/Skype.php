<?php

namespace SionModel\Validator;

/**
 * Validates a Skype user name.
 *
 * Extends AbstractPatternValidator rather than Laminas\Validator\Regex, which
 * laminas marked `@final`; the pattern and message are unchanged.
 */
class Skype extends AbstractPatternValidator
{
    public const SKYPE_USER_REGEX = "/^[a-zA-Z][a-zA-Z0-9\.,\-_]{5,31}$/";

    public function __construct()
    {
        parent::__construct(
            self::SKYPE_USER_REGEX,
            'Skype user names should begin with a letter, contain only letters, '
            . 'numbers, \',\', \'.\', \'-\', or \'_\' and be between 6 and 32 characters long.'
        );
    }
}
