<?php

declare(strict_types=1);

namespace SionModel\Service;

use JTranslate\Model\CountriesInfo;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Locale;
use Psr\Container\ContainerInterface;

use function asort;

class CountryValueOptionsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /** @var CountriesInfo $countries */
        $countries    = $container->get(CountriesInfo::class);
        $countryNames = $countries->getTranslatedCountryNames(Locale::getPrimaryLanguage(Locale::getDefault()));
        asort($countryNames);

        return $countryNames;
    }
}
