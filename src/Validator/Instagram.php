<?php

namespace SionModel\Validator;

/**
 * Validates an Instagram user name.
 *
 * Extends AbstractPatternValidator rather than SionModel\Validator\Regex, which
 * laminas marked `@final`; the pattern and message are unchanged.
 */
class Instagram extends AbstractPatternValidator
{
    public const INSTAGRAM_USER_REGEX = "/^[A-Za-z0-9_](?:(?:[A-Za-z0-9_]|(?:\.(?!\.))){0,28}(?:[A-Za-z0-9_]))?$/";

    public function __construct()
    {
        parent::__construct(
            self::INSTAGRAM_USER_REGEX,
            'Instagram user names should begin with a letter, contain only letters, '
            . 'numbers, \'.\', or \'_\' and be between 1 and 30 characters long.'
        );
    }
}
