<?php

declare(strict_types=1);

namespace SionModel\Filter;

use ForceUTF8\Encoding;
use Laminas\Filter\AbstractFilter;

use function iconv;
use function is_array;
use function preg_replace;
use function setlocale;
use function str_replace;
use function strtolower;
use function trim;

use const LC_CTYPE;

class ToAscii extends AbstractFilter
{
    public function __construct()
    {
        setlocale(LC_CTYPE, 'en_US');
    }

    /**
     * Defined by Laminas\Filter\FilterInterface
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
        if (! isset($str) || is_array($str)) {
            return $str;
        }
        $str = Encoding::toUTF8($str);

        if (is_array($replace) && ! empty($replace)) {
            $str = str_replace((array) $replace, ' ', $str);
        }
        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        $clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
        $clean = strtolower(trim($clean, '-'));
        $clean = preg_replace('/[\/_|+ -]/', $delimiter, $clean);
        $clean = iconv("ASCII", "UTF-8", $clean);
        return $clean;
    }
}
