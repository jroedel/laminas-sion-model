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
class Slack extends Regex
{
    /**
     * Sets validator options
     *
     */
    public function __construct()
    {
        $pattern = "/^[a-z0-9][a-z0-9._-]*$/";
        $newMessage = 'Slack user names should begin with a letter or number, and contain only letters, numbers, \'.\', \'-\', or \'_\'.';
        $this->messageTemplates[self::INVALID] = $newMessage;
        $this->messageTemplates[self::NOT_MATCH] = $newMessage;
        $this->messageTemplates[self::ERROROUS] = $newMessage;
        parent::__construct($pattern);
    }
}
