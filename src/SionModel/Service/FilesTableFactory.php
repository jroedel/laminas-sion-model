<?php
/**
 * SionModel Module
 *
 */

namespace SionModel\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use SionModel\Problem\ProblemTable;
use SionModel\Problem\EntityProblem;
use SionModel\Db\Model\FilesTable;

/**
 * Factory responsible of priming the PatresTable service
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class FilesTableFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $sionModelConfig = $serviceLocator->get ( 'SionModel\Config' );
        if (!isset($config['files_directory']) || empty($config['public_files_directory'])) {
            throw new \Exception('Please specify the \'files_directory\' and \'public_files_directory\' keys to use the FilesTable.');
        }

        $dbAdapter = $serviceLocator->get('Zend\Db\Adapter\Adapter');

        /** @var  User $userService **/
        $userService = $serviceLocator->get('zfcuser_user_service');
        $user = $userService->getAuthService()->getIdentity();
        $actingUserId = $user ? $user->id : null;

        $table = new FilesTable($dbAdapter, $serviceLocator, $actingUserId, $sionModelConfig);

		return $table;
    }
}
