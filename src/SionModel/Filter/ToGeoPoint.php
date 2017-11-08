<?php
namespace SionModel\Filter;

use Zend\Filter\AbstractFilter;
use Zend\Validator\GpsPoint;
use SionModel\Db\GeoPoint;

class ToGeoPoint extends AbstractFilter
{
    public function filter($value)
    {
        if (!isset($value) || !is_string($value) || '' === $value) {
            return null;
        }
        static $validator;
        if (!isset($validator)) {
            $validator = new GpsPoint();
        }
        if (!$validator->isValid($value)) {
            return null;
        }
        $re = '/[0-9\.-]+/u';
//         $str = '76.2144, 10.5276';

        preg_match_all($re, $value, $matches, PREG_SET_ORDER, 0);

        // Print the entire match result
        if (!isset($matches)) {
            return null;
        }

        $latitude = isset($matches[0]) ? $matches[0][0] : 0;
        $longitude = isset($matches[1]) ? $matches[1][0] : 0;
        $obj = new GeoPoint($longitude, $latitude);
        return $obj;
    }
}