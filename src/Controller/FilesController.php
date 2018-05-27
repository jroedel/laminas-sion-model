<?php
/**
 * Zend Framework (http://framework.zend.com/)
*
* @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
* @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
* @license   http://framework.zend.com/license/new-bsd New BSD License
*/

namespace SionModel\Controller;

use SionModel;

class FilesController extends SionController
{
    public function __construct()
    {
        return parent::__construct('file');
    }

    protected function getSionModelConfig()
    {
        $sm = $this->getServiceLocator();
        return $sm->get('SionModel\Config');
    }

    protected function getPersistentCache()
    {
        $sm = $this->getServiceLocator();
        if ($sm->has('SionModel\PersistentCache')) {
            return $sm->get('SionModel\PersistentCache');
        }
        return null;
    }
}
