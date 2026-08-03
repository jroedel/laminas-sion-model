<?php

/**
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace SionModel;

use Laminas\Mvc\MvcEvent;
use BjyAuthorize\Service\Authorize;
use SionModel\Error\ErrorListener;
use SionModel\Error\FatalErrorHandler;
use SionModel\Mvc\CspListener;
use Laminas\Mvc\ModuleRouteListener;
use SionModel\Service\ErrorReportingResolver;

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
        \Laminas\View\Helper\Navigation\AbstractHelper::setDefaultAcl($acl);
        \Laminas\View\Helper\Navigation\AbstractHelper::setDefaultRole($role);

        $eventManager = $app->getEventManager();
        $strategy = $sm->get(CspListener::class);
        $strategy->attach($eventManager);

        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);

        //Exception reporting: log every failure as before, record each distinct
        //one under data/exceptions, and email the first occurrence.
        //
        //This replaces an inline closure that read the exception from
        //$event->getResult()->exception — a path that only worked because
        //ExceptionStrategy happened to have run first and stuffed it into a
        //ViewModel. See ErrorListener for why that mattered.
        $sm->get(ErrorListener::class)->attach($eventManager);

        //Fatals never reach the MVC error events at all: the visitor gets a
        //blank HTTP 200 and nothing is logged. index.php installs a handler
        //before the container exists; now that it does, upgrade it to the fully
        //configured pipeline so post-bootstrap fatals notify like anything else.
        //A resolver rather than the services themselves: nothing the reporting
        //stack needs may be constructed on a request that never fails.
        FatalErrorHandler::upgrade(ErrorReportingResolver::forContainer($sm));
    }
}
