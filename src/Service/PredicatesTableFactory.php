<?php

declare(strict_types=1);

namespace SionModel\Service;

use Laminas\Db\Adapter\Adapter;
use Psr\Container\ContainerInterface;
use SionModel\Db\Model\PredicatesTable;

/**
 * Builds {@see PredicatesTable}, the comments and suggestions table.
 *
 * `SionTableWiring::apply()` is what gives it a user directory, which is how the comments
 * list names who wrote each comment. Without it the name column renders blank — which is
 * the designed outcome, not a failure, and is what a host with no user table gets.
 */
class PredicatesTableFactory
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): PredicatesTable
    {
        $table = new PredicatesTable(
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
