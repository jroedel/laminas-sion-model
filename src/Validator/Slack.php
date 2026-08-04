<?php

namespace SionModel\Validator;

/**
 * Validates a Slack user name.
 *
 * Extends AbstractPatternValidator rather than Laminas\Validator\Regex, which
 * laminas marked `@final`; the pattern and message are unchanged.
 */
class Slack extends AbstractPatternValidator
{
    public const SLACK_REGEX = "/^[a-z0-9][a-z0-9._-]*$/";

    public function __construct()
    {
        parent::__construct(
            self::SLACK_REGEX,
            'Slack user names should begin with a letter or number, '
            . 'and contain only letters, numbers, \'.\', \'-\', or \'_\'.'
        );
    }
}
