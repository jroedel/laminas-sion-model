<?php

namespace SionModel\Controller;

use BjyAuthorize\Exception\UnAuthorizedException;
use Laminas\Http\Header\HeaderInterface;
use Laminas\Http\Request as HttpRequest;

/**
 * Shared gate for the maintenance endpoints deploy hooks call without a
 * session (cache flushing, post-deploy data work).
 *
 * One implementation on purpose: this check lived in two controllers, in two
 * modules, and only one of them would have been remembered the next time it
 * needed to change.
 */
trait MaintenanceKeyTrait
{
    /**
     * @param array<int|string, mixed> $apiKeys The configured sion_model.api_keys
     * @throws UnAuthorizedException When the caller presented no key, or one
     *         that matches nothing configured.
     */
    protected function assertApiKeyIn(array $apiKeys): void
    {
        $key = $this->getRequestApiKey();
        if (null === $key || '' === $key) {
            throw new UnAuthorizedException();
        }
        foreach ($apiKeys as $candidate) {
            //hash_equals rather than in_array: these are long-lived shared
            //secrets, and a length-independent comparison is one less thing to
            //reason about
            if (is_string($candidate) && hash_equals($candidate, $key)) {
                return;
            }
        }
        throw new UnAuthorizedException();
    }

    /**
     * The key the caller presented, which must be in the X-Api-Key header.
     *
     * A query string is recorded verbatim in the web server's access log and kept
     * in the shell history of whatever invoked it, so for a secret that never
     * rotates `?key=` is a leak by default. It was accepted as a fallback until
     * 2026-08-17 because the deploy configuration that sent it lived in a
     * gitignored phploy.ini no commit here could reach. That is no longer true:
     * phploy is retired, every caller now sends the header
     * (FlushPersistentCacheCommand, tools/smoke-prod.sh, tools/deploy.sh), the
     * overdue-notices cron became a console command needing no key at all, and
     * the crontab was confirmed clean of `?key=`.
     *
     * Do not reintroduce it. An endpoint that accepts a secret in a URL cannot be
     * made safe by preferring the header — the log entry is written either way.
     *
     * @return string|null
     */
    protected function getRequestApiKey()
    {
        $request = $this->getRequest();
        if ($request instanceof HttpRequest) {
            $header = $request->getHeader('X-Api-Key');
            if ($header instanceof HeaderInterface) {
                //a header value is a string, but assertApiKeyIn() hands whatever
                //this returns to hash_equals(), so the type is checked here rather
                //than assumed
                $value = $header->getFieldValue();
                return is_string($value) ? $value : null;
            }
        }
        return null;
    }
}
