<?php

namespace SionModel\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use SionModel\View\Helper\Address;

/**
 * Factory responsible of retrieving an array containing the BjyAuthorize configuration
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 */
class AddressFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->get('SionModel\Config');
        $viewHelper = new Address($config);
        return $viewHelper;
    }
}
