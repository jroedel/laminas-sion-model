<?php
/**
 * SionModel Module
 *
 */

namespace SionModel\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Db\TableGateway\TableGateway;
use SionModel\Problem\ProblemTable;

/**
 * Factory responsible of priming the PatresTable service
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class ProblemTableFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $dbAdapter = $serviceLocator->get('Zend\Db\Adapter\Adapter');
		$tableGateway = new TableGateway('', $dbAdapter);

		$config = $serviceLocator->get ( 'Config' );
		/** @var  User $userService **/
		$userService = $serviceLocator->get('zfcuser_user_service');
		$user = $userService->getAuthService()->getIdentity();
		$userTable = $serviceLocator->get('JUser\Model\UserTable');
		$table = new ProblemTable( $tableGateway, $config['sion_model']['entities'], $user->id, 'a_data_changes' );
		return $table;
    }
}
