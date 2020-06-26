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

class Twitter extends Regex
{
    public const TWITTER_REGEX = "/^[A-Za-z0-9_]{1,15}$/";
    /**
     * Sets validator options
     *
     */
    public function __construct()
    {
        $pattern = self::TWITTER_REGEX;
        $newMessage = 'Twitter user names should contain only letters, numbers, or \'_\' '
            . 'and be between 1 and 15 characters long.';
        $this->messageTemplates[self::INVALID] = $newMessage;
        $this->messageTemplates[self::NOT_MATCH] = $newMessage;
        $this->messageTemplates[self::ERROROUS] = $newMessage;
        parent::__construct($pattern);
    }
}
