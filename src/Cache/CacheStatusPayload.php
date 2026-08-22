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
 * responses: the APCu keys stay top-level and first, then this package's own cache
 * settings under `sionModel`, phpVersion next, and OPcache nested under its own key.
 * Nothing new may be added at the top level under a name the APCu or OPcache blocks
 * already use — tools/smoke-prod.sh greps `percentUsed` and `expunges` out of the
 * whole document, and a second match turns a number into a shell syntax error.
 * Both callers hand this array straight to a
 * JSON encoder, and both encoders use the same flags — Laminas\Json\Json and
 * Symfony's JsonResponse both set JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|
 * JSON_HEX_AMP and nothing else — so identical arrays really do serialize to
 * identical bytes.
 */
final class CacheStatusPayload
{
    /**
     * Config keys this package once honoured and now ignores. Reported rather than
     * dropped in silence: an install whose `local.php` still names one is not broken,
     * but it is carrying a setting that stopped doing anything, and on a host where
     * that file is gitignored there is otherwise no way to find out short of an SSH
     * session. Empty is the expected state; a non-empty list is a cleanup to do.
     */
    private const RETIRED_CONFIG_KEYS = ['max_items_to_cache'];

    /**
     * @param array<string, mixed> $sionModelConfig the merged `sion_model` block,
     *        i.e. the `SionModel\Config` service. Optional so a caller that only
     *        wants the cache occupancy need not reach for it; both front controllers
     *        pass it, which is what keeps their two documents identical.
     * @return array<string, mixed>
     */
    public static function build(array $sionModelConfig = []): array
    {
        return self::apcu() + [
            'sionModel' => self::sionModel($sionModelConfig),
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
     * What this package's own cache settings are, as the running process sees them.
     *
     * `maxCachedItemSize` is the only bound left on a persistent cache write, so it
     * is the one number a reader needs when an item is silently absent from the
     * segment: an item over it is refused on every request forever, and the refusal
     * is a log line on a server nobody is tailing. Reported in bytes, with 0 meaning
     * the check is off and null meaning nothing in the merged config named the key,
     * so SionCacheTrait's own default applies. Null should not be reachable for an
     * install that loads this package's module.config.php, which sets it — the
     * distinction is kept rather than papered over with a duplicated default,
     * because a second copy of that number is exactly how the two would drift.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private static function sionModel(array $config): array
    {
        $retired = [];
        foreach (self::RETIRED_CONFIG_KEYS as $key) {
            if (array_key_exists($key, $config)) {
                $retired[] = $key;
            }
        }

        return [
            'maxCachedItemSize' => isset($config['max_cached_item_size'])
                ? (int) $config['max_cached_item_size']
                : null,
            'retiredConfigKeys' => $retired,
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
