<?php

// SionModel/View/Helper/Email.php

namespace SionModel\View\Helper;

use Closure;
use SionModel\View\Escape;

class Address
{
    public $defaultPlaceLineFormat = ':zip :cityState';
    public $placeLineCountryFormats = [];

    /**
     * @param Closure(string): string|null $countryName the `countryName` view helper, injected
     *        because this class no longer has a renderer to reach it through. A host that
     *        supplies none renders the raw country code.
     */
    public function __construct($config, private readonly ?Closure $countryName = null)
    {
        if (isset($config['post_place_line_format'])) {
            $this->defaultPlaceLineFormat = $config['post_place_line_format'];
        }
        if (isset($config['post_place_line_format_by_country'])) {
            $this->placeLineCountryFormats = $config['post_place_line_format_by_country'];
        }
    }

    public function __invoke($data)
    {
        $finalMarkup = '';
        if (isset($data['street1'])) {
            $finalMarkup .= Escape::html((string) $data['street1']) . '<br>';
        }
        if (isset($data['street2'])) {
            $finalMarkup .= Escape::html((string) $data['street2']) . '<br>';
        }

        if (isset($data['country']) && isset($this->placeLineCountryFormats[$data['country']])) {
            $placePattern = $this->placeLineCountryFormats[$data['country']];
        } else {
            $placePattern = $this->defaultPlaceLineFormat;
        }
        $placeLine = str_replace(':zip', isset($data['zip']) ? $data['zip'] : '', $placePattern);
        $placeLine = trim(str_replace(':cityState', isset($data['cityState']) ? $data['cityState'] : null, $placeLine));
        $finalMarkup .= Escape::html($placeLine) . '<br>';
        if (isset($data['country'])) {
            $finalMarkup .= (null !== $this->countryName
                ? ($this->countryName)($data['country'])
                : $data['country']) . '</p>';
        }
        if (strlen($finalMarkup) > 0) {
            $finalMarkup = '<p>' . $finalMarkup . '</p>';
        }
        return $finalMarkup;
    }

    public function formatNonHtmlAddress($street1, $street2, $cityState, $zip, $country)
    {
        $finalMarkup = '';
        if (isset($street1)) {
            $finalMarkup .= $street1 . PHP_EOL;
        }
        if (isset($street2)) {
            $finalMarkup .= $street2 . PHP_EOL;
        }

        if (isset($country) && isset($this->placeLineCountryFormats[$country])) {
            $placePattern = $this->placeLineCountryFormats[$country];
        } else {
            $placePattern = $this->defaultPlaceLineFormat;
        }
        $placeLine = str_replace(':zip', isset($zip) ? $zip : '', $placePattern);
        $placeLine = trim(str_replace(':cityState', isset($cityState) ? $cityState : null, $placeLine));
        $finalMarkup .= $placeLine;
        if (strlen($finalMarkup) == 0) {
            return null;
        }
        if (isset($country)) {
//            $finalMarkup .= $this->view->countryName($country);
            $finalMarkup .= PHP_EOL . $country;
        }
        return $finalMarkup;
    }
}
