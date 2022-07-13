<?php

declare(strict_types=1);

namespace SionModel\Validator;

use Laminas\Validator\Regex;

class Slack extends Regex
{
    public const SLACK_REGEX = "/^[a-z0-9][a-z0-9._-]*$/";

    public function __construct()
    {
        $pattern                                 = self::SLACK_REGEX;
        $newMessage                              = 'Slack user names should begin with a letter or number, '
            . 'and contain only letters, numbers, \'.\', \'-\', or \'_\'.';
        $this->messageTemplates[self::INVALID]   = $newMessage;
        $this->messageTemplates[self::NOT_MATCH] = $newMessage;
        $this->messageTemplates[self::ERROROUS]  = $newMessage;
        parent::__construct($pattern);
    }
}
