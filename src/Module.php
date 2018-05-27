<?php
/**
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */
namespace SionModel;

use Zend\Mvc\MvcEvent;
use BjyAuthorize\Service\Authorize;

class Module
{
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }
    
    public function onBootstrap(MvcEvent $e)
    {
        $sm = $e->getApplication()->getServiceManager();
        // Add ACL information to the Navigation view helper
        $authorize = $sm->get(Authorize::class);
        $acl = $authorize->getAcl();
        $role = $authorize->getIdentity();
        //I think the following doesn't do anything: @todo check this
        \Zend\View\Helper\Navigation\AbstractHelper::setDefaultAcl($acl);
        \Zend\View\Helper\Navigation\AbstractHelper::setDefaultRole($role);
    }
}
