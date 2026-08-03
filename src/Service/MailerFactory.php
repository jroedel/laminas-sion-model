<?php

/**
 * SionModel Module
 */

namespace SionModel\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use SionModel\Mailing\Mailer;

/**
 * Factory responsible of priming the Mailer service
 *
 * @author Jeff Ro <jeff.roedel.isp@gmail.com>
 */
class MailerFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        //no SionTable is passed: the base mailer sends without reporting to
        //the mailings table; subclasses wire in their own table
        return new Mailer(
            $container->get('SionModel\MailTransport'),
            $container->get('ViewRenderer'),
            $container->get('translator'),
            $container->get('Config')
        );
    }
}
