<?php

namespace SionModel\Cache;

/**
 * The one place the /sm/cache-status payload is composed.
 *
 * Two front controllers now answer that URL — SionModelController::cacheStatusAction()
 * under laminas-mvc, which production still serves, and
 * App\Controller\CacheStatusController under the Symfony kernel, which the capsule
 * serves — and they must produce the *same* document. Not "the same as far as
 * anyone remembers": tools/smoke-prod.sh and test/Smoke/SionModelSmokeTest.php
 * read these keys by name, so a key that exists on one path and not the other
 * would silence a production warning rather than fail anything.
 *
 * Hence a single builder rather than two callers each composing the three parts
 * themselves. The parts stay pure and separately testable (ApcuStatus,
 * OpcacheStatus); this class is only the composition plus the extension calls,
 * which cannot be exercised without a live APCu/OPcache anyway.
 *
 * Key order is part of the contract too, because a reader diffs the two
 * responses: the APCu keys stay top-level and first, phpVersion next, and
 * OPcache nested under its own key. Both callers hand this array straight to a
 * JSON encoder, and both encoders use the same flags — Laminas\Json\Json and
 * Symfony's JsonResponse both set JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|
 * JSON_HEX_AMP and nothing else — so identical arrays really do serialize to
 * identical bytes.
 */
final class CacheStatusPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function build(): array
    {
        return self::apcu() + [
            'phpVersion' => PHP_VERSION,
            'opcache' => OpcacheStatus::summarize(
                function_exists('opcache_get_status') ? opcache_get_status(false) : null,
                [
                    'opcache.validate_timestamps' => ini_get('opcache.validate_timestamps'),
                    'opcache.revalidate_freq' => ini_get('opcache.revalidate_freq'),
                    'opcache.max_accelerated_files' => ini_get('opcache.max_accelerated_files'),
                    //PHP_INI_SYSTEM, so this is what the pool booted with — not what
                    //the ini file currently says. That difference is the whole point:
                    //it is how a raise that has been written but not yet picked up by
                    //a running pool becomes visible without SSH.
                    'opcache.interned_strings_buffer' => ini_get('opcache.interned_strings_buffer'),
                ]
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function apcu(): array
    {
        if (! function_exists('apcu_enabled') || ! apcu_enabled()) {
            return ['apcuEnabled' => false];
        }

        return ApcuStatus::summarize(
            apcu_sma_info(true),
            apcu_cache_info(true),
            //the `true` variant above omits the entry list, so this second call
            //is the one that has to walk every entry — the only expensive part
            //of the endpoint, and the reason it is not called twice
            apcu_cache_info()
        );
    }
}
