<?php

declare(strict_types=1);

namespace SionModel\Service;

use Laminas\Mail\Transport\Smtp;
use Laminas\Mail\Transport\SmtpOptions;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class MailTransportFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Smtp
    {
        $config      = $container->get('Config');
        $smtpOptions = $config['zend_mail_transport_options'];
        return new Smtp(new SmtpOptions($smtpOptions));
    }
}
