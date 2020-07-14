<?php

namespace SionModel\Filter;

use Zend\Filter\AbstractFilter;

/**
 * A filter which guarantees an output of 1 or 0. This is helpful for validating checkboxes
 * that are to be inserted into certain types of databases since MySql, for example, handles
 * 1 and 0 well for bool values.
 * 
 * Since 1 or 0 is guaranteed, no additional validation is required on a form
 */
class ToBit extends AbstractFilter
{
    public function __construct()
    {
        $this->options['null_defaults_to'] = 0;
    }

    public function filter($value)
    {
        if (! isset($value)) {
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
