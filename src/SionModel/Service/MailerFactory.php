<?php
/**
 * SionModel Module
 */

namespace SionModel\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Patres\Mailing\Mailer;

/**
 * Factory responsible of priming the Mailer service
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class MailerFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
		$translator = $serviceLocator->get ( 'translator' );
		$mailService = $serviceLocator->get ( 'acmailer.mailservice.default' );
		$config = $serviceLocator->get ( 'Config' );

		$mailer = new Mailer( $mailService, $translator, $config);
		return $mailer;
    }
}
