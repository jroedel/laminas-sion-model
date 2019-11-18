<?php
namespace SionModel\Filter;

use Zend\Filter\AbstractFilter;

class ToBit extends AbstractFilter
{
    public function __construct()
    {
        $this->options['null_defaults_to'] = false;
    }
    
    public function filter($value)
    {
        if (!isset($value)) {
            if (isset($this->options['null_defaults_to']) && is_bool($this->options['null_defaults_to'])) {
                return $this->options['null_defaults_to'];
            }
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
