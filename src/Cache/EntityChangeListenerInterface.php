<?php

declare(strict_types=1);

namespace SionModel\Cache;

/**
 * Told when a SionTable invalidates an entity's cached data.
 *
 * For a host that keeps a cache of its own derived from entity data, and needs it
 * to expire at the same moment SionModel's does. Implement this, register it with
 * {@see EntityChangeListeners}, and every writer in every module is covered —
 * including ones that do not exist yet.
 */
interface EntityChangeListenerInterface
{
    /**
     * @param string $entity the entity name as the spec declares it, e.g. `user-role`.
     *        A listener that does not care about this one must simply return.
     */
    public function entityChanged(string $entity): void;
}
