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

class ToDateTime extends AbstractFilter
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
        if (! $tz) {
            $tz = new \DateTimeZone('UTC');
        }
        if ($value instanceof \DateTime) {
            return $value;
        }
        if (is_null($value) || $value === '') {
            return null;
        }
        $value = new \DateTime($value, $tz);

        return $value;
    }
}
