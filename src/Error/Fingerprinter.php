<?php

namespace SionModel\Error;

use Throwable;

/**
 * Reduces a thrown exception to a short stable key identifying its *class of
 * failure*, so the same bug reports once instead of once per request.
 *
 * Two properties matter and both are load-bearing:
 *
 * 1. The key is derived from the matched **route name**, never the request URI.
 *    A URI-keyed fingerprint would mint a fresh "first occurrence" — and a
 *    fresh notification email — on every request, which turns any exception
 *    reachable with a variable URL into a mail flood.
 *
 * 2. The origin is the root cause's enclosing *function*, not its line number.
 *    Line numbers move whenever anything above them is edited, which would
 *    re-report bugs you never touched. PHP records a called function together
 *    with its call site, so frame 0 of the root cause's trace names the method
 *    the throw sits in — stable across unrelated edits to the same file.
 *
 * Dependency-free on purpose (Throwable is core), so it is unit-testable
 * without a booted application.
 */
class Fingerprinter
{
    /** Stand-in route name for failures that happened before routing finished. */
    public const NO_ROUTE = '(none)';

    /** Stand-in origin for exceptions carrying no usable trace. */
    public const UNKNOWN_ORIGIN = '(unknown)';

    /** @var string */
    private $appRoot;

    /**
     * @param string $appRoot absolute path to the application root, used to
     *                        tell first-party frames from vendor ones
     */
    public function __construct($appRoot)
    {
        $this->appRoot = rtrim(str_replace('\\', '/', (string) $appRoot), '/');
    }

    /**
     * @param Throwable   $e
     * @param string|null $route  matched route name, or null when unrouted
     * @param string|null $origin overrides the derived origin; the fatal-error
     *                            path supplies the file:line where PHP died,
     *                            because a synthesised ErrorException's trace
     *                            points at the shutdown handler and would
     *                            collapse every fatal into one fingerprint
     * @return string 8 lowercase hex characters
     */
    public function fingerprint(Throwable $e, $route = null, $origin = null)
    {
        $seed = implode('|', [
            implode('>', $this->classChain($e)),
            null === $route || '' === $route ? self::NO_ROUTE : $route,
            null === $origin || '' === $origin ? $this->origin($e) : $origin,
        ]);
        return substr(hash('sha256', $seed), 0, 8);
    }

    /**
     * Every class in the exception chain, outermost first.
     *
     * @param Throwable $e
     * @return string[]
     */
    public function classChain(Throwable $e)
    {
        $chain = [];
        $current = $e;
        //a corrupted chain could in principle cycle; bound the walk
        for ($depth = 0; null !== $current && $depth < 20; $depth++) {
            $chain[] = get_class($current);
            $current = $current->getPrevious();
        }
        return $chain;
    }

    /**
     * The deepest exception in the chain — the thing that actually went wrong,
     * as opposed to whatever wrapper the container rethrew it as.
     *
     * @param Throwable $e
     * @return Throwable
     */
    public function rootCause(Throwable $e)
    {
        $current = $e;
        for ($depth = 0; $depth < 20; $depth++) {
            $previous = $current->getPrevious();
            if (null === $previous) {
                return $current;
            }
            $current = $previous;
        }
        return $current;
    }

    /**
     * A stable, human-readable name for where the root cause was raised —
     * `Books\Model\LibraryTable::getIsbnsInLibrary`, ideally.
     *
     * @param Throwable $e
     * @return string
     */
    public function origin(Throwable $e)
    {
        $root = $this->rootCause($e);
        $trace = $root->getTrace();
        if (isset($trace[0]) && isset($trace[0]['function'])) {
            $frame = $trace[0];
            if (isset($frame['class']) && '' !== $frame['class']) {
                $type = isset($frame['type']) && '' !== $frame['type'] ? $frame['type'] : '::';
                return $frame['class'] . $type . $frame['function'];
            }
            return $frame['function'] . '()';
        }
        $file = $root->getFile();
        if ('' === $file) {
            return self::UNKNOWN_ORIGIN;
        }
        return $this->relativePath($file) . ':' . $root->getLine();
    }

    /**
     * Strip the application root from a path.
     *
     * Absolute server paths are noise in a write-up and disclose the hosting
     * layout in an email, so everything under the app root is reported
     * relative to it.
     *
     * @param string $path
     * @return string
     */
    public function relativePath($path)
    {
        $path = str_replace('\\', '/', (string) $path);
        if ('' === $this->appRoot) {
            return $path;
        }
        if (0 === strpos($path, $this->appRoot . '/')) {
            return substr($path, strlen($this->appRoot) + 1);
        }
        return $path;
    }

    /**
     * Strip the application root out of arbitrary text — an exception message,
     * or a rendered stack trace.
     *
     * Deliberately separate from relativePath(): that method normalises
     * backslashes to forward slashes because what it is handed is a filesystem
     * path, and doing the same to a message would rewrite the namespace
     * separator in every class name it mentions. `SionModel\Error\ErrorListener`
     * became `SionModel/Error/ErrorListener` exactly that way.
     *
     * @param string $text
     * @return string
     */
    public function stripAppRoot($text)
    {
        $text = (string) $text;
        if ('' === $this->appRoot) {
            return $text;
        }
        return str_replace($this->appRoot . '/', '', $text);
    }

    /**
     * Whether a path belongs to first-party code rather than a dependency.
     *
     * @param string $path
     * @return bool
     */
    public function isFirstParty($path)
    {
        $relative = $this->relativePath($path);
        return $relative !== str_replace('\\', '/', (string) $path)
            && 0 !== strpos($relative, 'vendor/');
    }
}
