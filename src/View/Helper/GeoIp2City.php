<?php

declare(strict_types=1);

namespace SionModel\View\Helper;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use GeoIp2\Model\City;
use Laminas\Validator\Ip;
use Laminas\View\Helper\AbstractHelper;

class GeoIp2City extends AbstractHelper
{
    protected Ip $ipValidator;

    public function __construct(public Reader $geoip2)
    {
        $this->ipValidator = new Ip();
    }

    public function __invoke(string $ipAddress): ?City
    {
        if (! $this->ipValidator->isValid($ipAddress)) {
            return null;
        }
        try {
            return $this->geoip2->city($ipAddress);
        } catch (AddressNotFoundException) {
            return null;
        }
    }
}
