<?php

/**
 * SionModel Module
 */

namespace SionModel\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SionModel\Mailing\Mailer;

class MailerFactory implements FactoryInterface
{
    /**
     * @todo FACTOR OUT!
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $translator = $container->get('translator');
        $mailService = $container->get('acmailer.mailservice.default');
        $config = $container->get('Config');

        $mailer = new Mailer($mailService, $translator, $config);
        return $mailer;
    }
}
