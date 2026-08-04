<?php

namespace SionModel\Validator;

/**
 * Validates a Twitter user name.
 *
 * Extends AbstractPatternValidator rather than Laminas\Validator\Regex, which
 * laminas marked `@final`; the pattern and message are unchanged.
 */
class Twitter extends AbstractPatternValidator
{
    public const TWITTER_REGEX = "/^[A-Za-z0-9_]{1,15}$/";

    public function __construct()
    {
        parent::__construct(
            self::TWITTER_REGEX,
            'Twitter user names should contain only letters, numbers, or \'_\' '
            . 'and be between 1 and 15 characters long.'
        );
    }
}
