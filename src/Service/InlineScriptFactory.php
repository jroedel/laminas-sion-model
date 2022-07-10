<?php

declare(strict_types=1);

namespace SionModel\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SionModel\Mvc\CspListener;
use SionModel\View\Helper\InlineScript;

class InlineScriptFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $csp           = $container->get(CspListener::class);
        $nonce         = $csp->getNonce();
        return new InlineScript($nonce);
    }
}
