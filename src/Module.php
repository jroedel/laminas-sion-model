<?php

/**
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace SionModel;

use Zend\Mvc\MvcEvent;
use BjyAuthorize\Service\Authorize;
use SionModel\Mvc\CspListener;
use Zend\Mvc\ModuleRouteListener;
use SionModel\Service\ErrorHandling;

class Module
{
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function onBootstrap(MvcEvent $e)
    {
        $app = $e->getApplication();
        $sm = $app->getServiceManager();
        // Add ACL information to the Navigation view helper
        $authorize = $sm->get(Authorize::class);
        $acl = $authorize->getAcl();
        $role = $authorize->getIdentity();
        //I think the following doesn't do anything: @todo check this
        \Zend\View\Helper\Navigation\AbstractHelper::setDefaultAcl($acl);
        \Zend\View\Helper\Navigation\AbstractHelper::setDefaultRole($role);

        $eventManager = $app->getEventManager();
        $strategy = $sm->get(CspListener::class);
        $strategy->attach($eventManager);

        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);

        //setup logging
        $eventManager->attach('dispatch.error', function ($event) {
            $exception = $event->getResult()->exception;
            if ($exception && $exception instanceof \Exception) {
                $sm = $event->getApplication()->getServiceManager();
                $service = $sm->get(ErrorHandling::class);
                $service->logException($exception);
            }
        });
    }
}
