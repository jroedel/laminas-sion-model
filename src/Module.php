<?php
/**
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace SionModel;

use Zend\Mvc\MvcEvent;
use SionModel\View\Helper\ControllerName;
use SionModel\View\Helper\RouteName;

class Module
{
    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\ClassMapAutoloader' => array(
                __DIR__ . '/../autoload_classmap.php',
            ),
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/' . __NAMESPACE__,
                ),
            ),
        );
    }

    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function onBootstrap(MvcEvent $e)
    {
        $sm = $e->getApplication()->getServiceManager();
        // Add ACL information to the Navigation view helper
        $authorize = $sm->get('BjyAuthorizeServiceAuthorize');
        $acl = $authorize->getAcl();
        $role = $authorize->getIdentity();
        //I think the following doesn't do anything: @todo check this
        \Zend\View\Helper\Navigation\AbstractHelper::setDefaultAcl($acl);
        \Zend\View\Helper\Navigation\AbstractHelper::setDefaultRole($role);
    }

    public function getViewHelperConfig()
    {
        //@todo make Factory classes
        return array(
             'factories' => array(
                'ControllerName' => function ($sm) {
                   $match = $sm->getServiceLocator()->get('application')->getMvcEvent()->getRouteMatch();
                   $viewHelper = new ControllerName($match);
                   return $viewHelper;
                },
                'RouteName' => function ($sm) {
                   $match = $sm->getServiceLocator()->get('application')->getMvcEvent()->getRouteMatch();
                   $viewHelper = new RouteName($match);
                   return $viewHelper;
                },
             ),
        );
    }
}
