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

class ToAscii extends AbstractFilter
{
    public function __construct()
    {
        setlocale(LC_CTYPE, 'en_US');
    }

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
    public function filter($str, $delimiter = ' ', $replace = [])
    {
        if (!isset($str) || is_array($str)) {
            return $str;
        }
        $str = \ForceUTF8\Encoding::toUTF8($str);
        
        if (is_array($replace) && !empty($replace)) {
            $str = str_replace((array)$replace, ' ', $str);
        }
        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        $clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
        $clean = strtolower(trim($clean, '-'));
        $clean = preg_replace('/[\/_|+ -]/', $delimiter, $clean);
        $clean = iconv("ASCII", "UTF-8", $clean);
        return $clean;
    }
}
