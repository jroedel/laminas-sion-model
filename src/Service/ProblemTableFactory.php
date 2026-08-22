<?php

declare(strict_types=1);

namespace SionModel\Service;

use Laminas\Db\Adapter\Adapter;
use Psr\Container\ContainerInterface;
use SionModel\Problem\ProblemTable;

/**
 * Builds {@see ProblemTable}.
 *
 * This factory used to be a special case twice over, and is now a special case zero times.
 * `SionTable::__construct()` carried `! $this instanceof ProblemTable` to stop itself
 * asking `ProblemService` for a problem prototype while building the very table that
 * service depends on; the prototype no longer lives on `SionTable`, so the guard went with
 * it. The acting-user provider still defers identity resolution to write time, which is the
 * other half of what keeps the UserTable/ProblemService/ProblemTable/AuthService loop from
 * re-forming.
 */
class ProblemTableFactory
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ProblemTable
    {
        $table = new ProblemTable(
            $container->get(Adapter::class),
            $container->get(EntitiesService::class),
            $container->get('SionModel\Config'),
            $container->has(ActingUserProviderInterface::class)
                ? $container->get(ActingUserProviderInterface::class)
                : null
        );
        SionTableWiring::apply($container, $table);

        return $table;
    }
}
