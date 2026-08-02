<?php

/**
 * SionModel Module
 *
 */

namespace SionModel\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use SionModel\Problem\ProblemTable;

/**
 * Factory responsible of priming the PatresTable service
 *
 * @author Jeff Ro <jeff.roedel.isp@gmail.com>
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
        $dbAdapter = $container->get('Laminas\Db\Adapter\Adapter');

        // The acting user id is deliberately null: resolving JUser\AuthService here
        // closes a four-way dependency cycle —
        //
        //   JUser\Model\UserTable          (a SionTable, so its constructor asks for...)
        //     -> SionModel\Service\ProblemService
        //     -> SionModel\Problem\ProblemTable
        //     -> JUser\AuthService         (which needs the UserTable we are still building)
        //     -> JUser\Model\UserTable
        //
        // Until 2026-08-02 the cycle was hidden because ProblemService was registered
        // as a lazy_service, so the ServiceManager handed out an ocramius/proxy-manager
        // proxy that deferred construction past the loop. Dropping proxy-manager made
        // the cycle real: every request recursed until it exhausted memory_limit.
        //
        // Nothing is lost by passing null. ProblemTable is read-only — it defines only
        // getProblems()/getProblem() and never reads $actingUserId. SionTable also
        // exposes setActingUserId() should a writable subclass ever need it.
        // JUser\Service\UserTableFactory resolves the same conflict the same way.
        $table = new ProblemTable($dbAdapter, $container, null);
        return $table;
    }
}
