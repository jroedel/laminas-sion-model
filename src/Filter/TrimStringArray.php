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
use Zend\Filter\FilterChain;
class TrimStringArray extends AbstractFilter
{
    /**
     * Defined by Zend\Filter\FilterInterface
     *
     * Returns $value
     *
     * If the value provided is non-scalar, the value will remain unfiltered
     *
     * @param  string|array $value
     * @return array
     */
    public function filter($value)
    {
        if (is_string($value)) {
            $value = [$value];
        }
        if (!is_array($value)) {
            return [];
        }

        static $filter;
        if (!isset($filter)) {
            $filter = new FilterChain();
            $filter->attachByName('StripTags')
                ->attachByName('StripNewlines')
                ->attachByName('StringTrim');
        }
        $result = [];
        foreach ($value as $item) {
            $result = $filter->filter($item);
        }

        return $result;
    }
}
