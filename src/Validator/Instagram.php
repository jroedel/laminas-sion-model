<?php

declare(strict_types=1);

namespace SionModel\Validator;

use Laminas\Validator\Regex;

class Instagram extends Regex
{
    public const INSTAGRAM_USER_REGEX = "/^[A-Za-z0-9_](?:(?:[A-Za-z0-9_]|(?:\.(?!\.))){0,28}(?:[A-Za-z0-9_]))?$/";
    /**
     * Sets validator options
     */
    public function __construct()
    {
        $pattern                                 = self::INSTAGRAM_USER_REGEX;
        $newMessage                              = 'Instagram user names should begin with a letter, contain only letters, '
            . 'numbers, \'.\', or \'_\' and be between 1 and 30 characters long.';
        $this->messageTemplates[self::INVALID]   = $newMessage;
        $this->messageTemplates[self::NOT_MATCH] = $newMessage;
        $this->messageTemplates[self::ERROROUS]  = $newMessage;
        parent::__construct($pattern);
    }
}
