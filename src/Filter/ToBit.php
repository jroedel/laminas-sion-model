<?php
namespace SionModel\Filter;
use Zend\Filter\AbstractFilter;

class ToBit extends AbstractFilter
{
    public function filter($value)
    {
        if (is_null($value)) {
            return 0;
        }
        if ($value === '0' || $value === '1') {
            return (int)$value;
        }
        if (is_string($value) && is_numeric($value)) {
            return $value != 0 ? '1' : 0;
        }
        if (is_bool($value)) {
            return $value ? '1' : 0;
        }
        return 0;
    }
}
