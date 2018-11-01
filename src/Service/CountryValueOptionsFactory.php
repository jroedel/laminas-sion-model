<?php
namespace SionModel\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use JTranslate\Model\CountriesInfo;

/**
 * Factory responsible of priming the PatresTable service
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class CountryValueOptionsFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        /** @var CountriesInfo $countries */
        $countries = $container->get(CountriesInfo::class);
        $countryNames = $countries->getTranslatedCountryNames(\Locale::getPrimaryLanguage(\Locale::getDefault()));
        asort($countryNames);

        return $countryNames;
    }
}
