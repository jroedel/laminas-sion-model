<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace SionModel\Filter;

use Zend\Filter\AbstractFilter;
use Zend\Validator\Regex;

class DateSelectNoYear extends AbstractFilter
{
    /**
     * Defined by Zend\Filter\FilterInterface
     *
     * Returns (int) $value
     *
     * If the value provided is non-scalar, the value will remain unfiltered
     *
     * @param  string $value
     * @return int|mixed
     */
    public function filter($value)
    {
        static $tz;
        static $validator;
        if (!$validator) {
            $validator = new Regex("/\\d{4}-\\d{2}-\\d{2}/");
        }
        if (!$tz) {
            $tz = new \DateTimeZone('UTC');
        }
        if ($value instanceof \DateTime) {
            return $value;
        }

        if (is_null($value) || $value === '' || $value === '1900--') {
            return null;
        }
        if ($validator->isValid($value)) {
            $value = new \DateTime($value, $tz);
        }
        return $value;
    }
}
