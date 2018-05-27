<?php
/**
 * SionModel Module
 *
 */

namespace SionModel\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use SionModel\Problem\ProblemTable;

/**
 * Factory responsible of priming the PatresTable service
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class ProblemTableFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $dbAdapter = $container->get('Zend\Db\Adapter\Adapter');

		/** @var  User $userService **/
		$userService = $container->get('zfcuser_user_service');
		$user = $userService->getAuthService()->getIdentity();
		$userId = $user ? $user->id : null;
// 		$userTable = $serviceLocator->get('JUser\Model\UserTable');
		$table = new ProblemTable( $dbAdapter, $container, $userId);
		return $table;
    }
}
