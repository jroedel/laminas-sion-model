<?php

declare(strict_types=1);

namespace SionModel\Validator;

use Laminas\Validator\Regex;

class Twitter extends Regex
{
    public const TWITTER_REGEX = "/^[A-Za-z0-9_]{1,15}$/";

    public function __construct()
    {
        $pattern                                 = self::TWITTER_REGEX;
        $newMessage                              = 'Twitter user names should contain only letters, numbers, or \'_\' '
            . 'and be between 1 and 15 characters long.';
        $this->messageTemplates[self::INVALID]   = $newMessage;
        $this->messageTemplates[self::NOT_MATCH] = $newMessage;
        $this->messageTemplates[self::ERROROUS]  = $newMessage;
        parent::__construct($pattern);
    }
}
