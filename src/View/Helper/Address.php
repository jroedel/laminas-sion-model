<?php

namespace SionModel\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class Address extends AbstractHelper
{

    public function __construct(
        public string $defaultPlaceLineFormat = ':zip :cityState',
        public array $placeLineCountryFormats = []
    )
    {
    }

    public function __invoke($data)
    {
        $finalMarkup = '';
        if (isset($data['street1'])) {
            $finalMarkup .= $this->view->escapeHtml($data['street1']) . '<br>';
        }
        if (isset($data['street2'])) {
            $finalMarkup .= $this->view->escapeHtml($data['street2']) . '<br>';
        }

        if (isset($data['country']) && isset($this->placeLineCountryFormats[$data['country']])) {
            $placePattern = $this->placeLineCountryFormats[$data['country']];
        } else {
            $placePattern = $this->defaultPlaceLineFormat;
        }
        $placeLine = str_replace(':zip', isset($data['zip']) ? $data['zip'] : '', $placePattern);
        $placeLine = trim(str_replace(':cityState', isset($data['cityState']) ? $data['cityState'] : null, $placeLine));
        $finalMarkup .= $this->view->escapeHtml($placeLine) . '<br>';
        if (isset($data['country'])) {
            $finalMarkup .= $this->view->countryName($data['country']) . '</p>';
        }
        if (strlen($finalMarkup) > 0) {
            $finalMarkup = '<p>' . $finalMarkup . '</p>';
        }
        return $finalMarkup;
    }

    public function formatNonHtmlAddress($street1, $street2, $cityState, $zip, string $country): string|null
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
