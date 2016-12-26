<?php
namespace SionModel\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use JTranslate\Model\CountriesInfo;

/**
 * Factory responsible of priming the PatresTable service
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class CountryValueOptionsFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
		/** @var CountriesInfo $countries */
		$countries = $serviceLocator->get ( 'CountriesInfo' );
		$countryNames = $countries->getTranslatedCountryNames(\Locale::getPrimaryLanguage(\Locale::getDefault()));
		asort($countryNames);

		return $countryNames;
    }
}
