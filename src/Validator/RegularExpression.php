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
use Laminas\Validator\AbstractValidator;

class RegularExpression extends AbstractValidator
{
    public function isValid($regex)
    {
        try {
            $validator = new Regex($regex);
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }
}
