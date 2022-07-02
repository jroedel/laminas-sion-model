<?php

declare(strict_types=1);

// SionModel/View/Helper/Telephone.php

namespace SionModel\View\Helper;

use Laminas\Filter\FilterChain;
use Laminas\Filter\FilterInterface;
use Laminas\Filter\PregReplace;
use Laminas\Filter\StringTrim;
use Laminas\View\Helper\AbstractHelper;
use libphonenumber\geocoding\PhoneNumberOfflineGeocoder;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

use function is_object;

class Telephone extends AbstractHelper
{
    protected FilterInterface $filter;
    protected PhoneNumberUtil $phoneUtil;
    protected PhoneNumberOfflineGeocoder $geocoder;

    public function __construct()
    {
        $this->filter = new FilterChain();
        $this->filter->attach(new StringTrim())
                    ->attach(new PregReplace([
                        'pattern'     => '/[^-+0-9]+/',
                        'replacement' => '-',
                    ]));
        $this->phoneUtil = PhoneNumberUtil::getInstance();
        $this->geocoder  = PhoneNumberOfflineGeocoder::getInstance();
    }

    public function __invoke(string $telephone, bool $whatsApp = false, bool $displayWarning = false): string
    {
        $filteredTelephone = $this->filter->filter($telephone);
        $numberProto       = null;
        if ('' === $filteredTelephone) {
            return '';
        }
        try {
            $numberProto = $this->phoneUtil->parse($telephone, "DE");
            //@todo add in the current user's locale
            if (isset($numberProto)) {
                $telephone         = $this->phoneUtil->format($numberProto, PhoneNumberFormat::INTERNATIONAL);
                $tooltip           = $this->geocoder->getDescriptionForNumber($numberProto, "en_US");
                $filteredTelephone = $this->phoneUtil->format($numberProto, PhoneNumberFormat::E164);
                $telUrl            = $this->phoneUtil->format($numberProto, PhoneNumberFormat::RFC3966);
            }
        } catch (NumberParseException $e) {
            $tooltip = null;
        }
        $return  = '<a href="';
        $return .= $telUrl ?? 'tel:' . $this->getView()->escapeHtml($filteredTelephone);
        $return .= '" '
            . ($tooltip
                ? 'data-toggle="tooltip" data-placement="bottom" data-container="body" data-original-title="'
                    . $tooltip
                    . '"'
                : "")
            . '>' . $this->getView()->escapeHtml($telephone) . '</a>';
        if ($whatsApp) {
            $return .= ' <i class="fa fa-whatsapp" aria-hidden="true" '
                . 'data-toggle="tooltip" data-placement="bottom" data-container="body" data-original-title="'
                . $this->view->translate('WhatsApp number')
                . '"></i>';
        }

        if ((! is_object($numberProto) || ! $this->phoneUtil->isValidNumber($numberProto)) && $displayWarning) {
            $return .= ' <i class="fa fa-exclamation-triangle" aria-hidden="true" '
                . 'data-toggle="tooltip" data-placement="bottom" data-container="body" data-original-title="'
                . $this->view->translate('Unrecognized phone number')
                . '"></i>';
        }
        return $return;
    }
}
