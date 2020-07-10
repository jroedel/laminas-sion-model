<?php

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace SionModel\Validator;

use Zend\Validator\Regex;

class Skype extends Regex
{
    public const SKYPE_USER_REGEX = "/^[a-zA-Z][a-zA-Z0-9\.,\-_]{5,31}$/";
    /**
     * Sets validator options
     *
     */
    public function __construct()
    {
        $pattern = self::SKYPE_USER_REGEX;
        $newMessage = 'Skype user names should begin with a letter, contain only letters, '
            . 'numbers, \',\', \'.\', \'-\', or \'_\' and be between 6 and 32 characters long.';
        $this->messageTemplates[self::INVALID] = $newMessage;
        $this->messageTemplates[self::NOT_MATCH] = $newMessage;
        $this->messageTemplates[self::ERROROUS] = $newMessage;
        parent::__construct($pattern);
    }
}
