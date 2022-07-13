<?php

declare(strict_types=1);

namespace SionModel\Filter;

use Laminas\Filter\AbstractFilter;
use Laminas\Filter\FilterChain;

use function is_array;
use function is_string;

class TrimStringArray extends AbstractFilter
{
    /**
     * Defined by Laminas\Filter\FilterInterface
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
        if (! is_array($value)) {
            return [];
        }

        static $filter;
        if (! isset($filter)) {
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
