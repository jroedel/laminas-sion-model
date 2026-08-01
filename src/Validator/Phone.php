<?php

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace SionModel\Validator;

use Laminas\Validator\Regex;

class Phone extends Regex
{
    public const PHONE_NUMBER_REGEX = "/^\+[0-9\- \(\)]{7,30}(?: ext\. \d{1,4})?$/";
    /**
     * Sets validator options
     *
     */
    public function __construct()
    {
        $pattern = self::PHONE_NUMBER_REGEX;
        $newMessage = 'Please begin with \'+\' and the country code, and use only numbers, '
            . 'dash, space or parenthesis. \' ext. ##\' may be added for extensions.';
        $this->messageTemplates[self::INVALID] = $newMessage;
        $this->messageTemplates[self::NOT_MATCH] = $newMessage;
        $this->messageTemplates[self::ERROROUS] = $newMessage;
        parent::__construct($pattern);
    }
}
