<?php

namespace SionModel\Validator;

/**
 * Validates an international phone number, with an optional extension.
 *
 * Extends AbstractPatternValidator rather than SionModel\Validator\Regex, which
 * laminas marked `@final`; the pattern and message are unchanged.
 */
class Phone extends AbstractPatternValidator
{
    public const PHONE_NUMBER_REGEX = "/^\+[0-9\- \(\)]{7,30}(?: ext\. \d{1,4})?$/";

    public function __construct()
    {
        parent::__construct(
            self::PHONE_NUMBER_REGEX,
            'Please begin with \'+\' and the country code, and use only numbers, '
            . 'dash, space or parenthesis. \' ext. ##\' may be added for extensions.'
        );
    }
}
