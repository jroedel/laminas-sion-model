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
     * The key the caller presented: the X-Api-Key header, or failing that the
     * legacy `?key=` query parameter.
     *
     * The header is the supported channel because a query string is recorded
     * verbatim in the web server's access log and kept in the shell history of
     * whatever invoked it — for a key that never rotates, that is a leak by
     * default. `?key=` still works on purpose: the deploy configuration that
     * sends it lives in the gitignored phploy.ini on each machine, so it cannot
     * be updated in the same commit as this code. Drop the fallback once every
     * caller sends the header (see docs/BACKLOG.md).
     *
     * @return string|null
     */
    protected function getRequestApiKey()
    {
        $request = $this->getRequest();
        if ($request instanceof HttpRequest) {
            $header = $request->getHeader('X-Api-Key');
            if ($header instanceof HeaderInterface) {
                return $header->getFieldValue();
            }
        }
        //an array (?key[]=…) must not reach hash_equals, which only takes strings
        $fromQuery = $this->params()->fromQuery('key', null);
        return is_string($fromQuery) ? $fromQuery : null;
    }
}
