<?php

namespace SionModel\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use SionModel\Mvc\CspListener;
use SionModel\View\Helper\InlineScript;

/**
 * Factory responsible of retrieving an array containing the BjyAuthorize configuration
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 */
class InlineScriptFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $parentLocator = $container->getServiceLocator();
        $csp = $parentLocator->get(CspListener::class);
        $nonce = $csp->getNonce();
        $viewHelper = new InlineScript($nonce);
        return $viewHelper;
    }
}
