<?php

declare(strict_types=1);

namespace SionModel\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SionModel\View\Helper\Address;
use Webmozart\Assert\Assert;

use function is_array;

class AddressFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config                 = $container->get('SionModel\Config');
        $defaultPlaceLineFormat = $config['post_place_line_format'] ?? null;
        if (isset($defaultPlaceLineFormat)) {
            Assert::stringNotEmpty($defaultPlaceLineFormat);
        }
        $placeLineCountryFormats = $config['post_place_line_format_by_country'] ?? null;
        if (is_array($placeLineCountryFormats) && empty($placeLineCountryFormats)) {
            $placeLineCountryFormats = null;
        }
        if (isset($placeLineCountryFormats)) {
            Assert::isArray($placeLineCountryFormats);
            Assert::allStringNotEmpty($placeLineCountryFormats);
        }
        if (isset($defaultPlaceLineFormat) && isset($placeLineCountryFormats)) {
            return new Address(
                defaultPlaceLineFormat: $defaultPlaceLineFormat,
                placeLineCountryFormats: $placeLineCountryFormats
            );
        } elseif (isset($defaultPlaceLineFormat)) {
            return new Address(defaultPlaceLineFormat: $defaultPlaceLineFormat);
        } elseif (isset($placeLineCountryFormats)) {
            return new Address(placeLineCountryFormats: $placeLineCountryFormats);
        } else {
            return new Address();
        }
    }
}
