<?php

declare(strict_types=1);

namespace SionModel;

use BjyAuthorize\Service\Authorize;
use Exception;
use Laminas\Mvc\ModuleRouteListener;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Helper\Navigation\AbstractHelper;
use SionModel\Mvc\CspListener;
use SionModel\Service\ErrorHandling;

class Module
{
    public function getConfig(): array
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function onBootstrap(MvcEvent $e): void
    {
        $app = $e->getApplication();
        $sm  = $app->getServiceManager();
        // Add ACL information to the Navigation view helper
        $authorize = $sm->get(Authorize::class);
        $acl       = $authorize->getAcl();
        $role      = $authorize->getIdentity();
        //I think the following doesn't do anything: @todo check this
        AbstractHelper::setDefaultAcl($acl);
        AbstractHelper::setDefaultRole($role);

        $eventManager = $app->getEventManager();
        $strategy     = $sm->get(CspListener::class);
        $strategy->attach($eventManager);

        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);

        //setup logging
        $eventManager->attach('dispatch.error', function ($event) {
            $exception = $event->getResult()->exception;
            if ($exception instanceof Exception) {
                $sm      = $event->getApplication()->getServiceManager();
                $service = $sm->get(ErrorHandling::class);
                $service->logException($exception);
            }
        });
    }
}
