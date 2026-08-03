<?php

namespace SionModel\Error;

use ErrorException;
use Throwable;

/**
 * Catches the failures the MVC error events never see.
 *
 * A PHP fatal — memory exhaustion, a hit max_execution_time, a type error
 * escaping every catch block — never reaches dispatch.error. What the visitor
 * gets is a truncated HTTP 200 with a blank body, and what the developer gets is
 * nothing at all: no log line, no error page, no record. That failure mode has
 * bitten this application before, and it is the reason this class exists.
 *
 * It registers in two phases because the useful configuration lives in the
 * service container, and the container is one of the things that can fail:
 *
 * 1. registerEarly() runs from public/index.php before the application boots. It
 *    can only fall back to defaults — the store's default path and no email,
 *    because merged module configuration does not exist yet. That is enough to
 *    leave a durable record of a bootstrap failure where the fetch script will
 *    find it.
 * 2. upgrade() runs from Module::onBootstrap() once the container is up, and
 *    swaps in the fully configured pipeline including notification. Every fatal
 *    after bootstrap — which is nearly all of them in practice, since that is
 *    where the application does its work — is reported in full.
 */
class FatalErrorHandler
{
    /** Error types that end the process, and so are only observable at shutdown. */
    public const FATAL_TYPES = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    /** Store path used before configuration is available. */
    public const DEFAULT_STORE_PATH = 'data/exceptions';

    /** Where a failure too early to record properly leaves a breadcrumb. */
    public const BOOTSTRAP_LOG = 'data/logs/bootstrap-fatal.log';

    /** @var string */
    private static $appRoot = '.';

    /**
     * Returns [ErrorHandling, RequestContext]. Invoked only on failure, so a
     * healthy request never pays for constructing either — see ErrorListener
     * for why that matters.
     *
     * @var callable|null
     */
    private static $resolver;

    /** @var bool */
    private static $registered = false;

    /** @var bool */
    private static $reported = false;

    /**
     * Memory held back so the shutdown handler can still allocate after an
     * out-of-memory fatal. Released the moment shutdown begins.
     *
     * @var string|null
     */
    private static $reserve;

    /**
     * Install the handlers. Safe to call more than once.
     *
     * @param string $appRoot absolute path to the application root
     * @return void
     */
    public static function registerEarly($appRoot)
    {
        self::$appRoot = rtrim(str_replace('\\', '/', (string) $appRoot), '/');
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        //enough headroom to build a record after memory_limit is exhausted
        self::$reserve = str_repeat(' ', 262144);

        set_exception_handler([self::class, 'onUncaughtException']);
        register_shutdown_function([self::class, 'onShutdown']);
    }

    /**
     * Replace the default-configured fallback with the container's fully wired
     * pipeline, so post-bootstrap fatals notify like any other exception.
     *
     * @param callable $resolver returns [ErrorHandling, RequestContext]
     * @return void
     */
    public static function upgrade($resolver)
    {
        self::$resolver = $resolver;
    }

    /**
     * @param Throwable $e
     * @return void
     */
    public static function onUncaughtException($e)
    {
        if (! $e instanceof Throwable) {
            return;
        }
        self::report($e, null);
    }

    /**
     * @return void
     */
    public static function onShutdown()
    {
        //free the reserve first: after an OOM fatal there may be no room to
        //build a record until we do
        self::$reserve = null;

        if (self::$reported) {
            return;
        }
        $error = error_get_last();
        if (null === $error || ! in_array($error['type'], self::FATAL_TYPES, true)) {
            return;
        }

        $exception = new ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line']
        );

        //the synthesised exception's trace points at this handler, so tell the
        //fingerprinter where PHP actually died — otherwise every fatal on a
        //route collapses into a single fingerprint
        self::report($exception, self::relativePath($error['file']) . ':' . $error['line']);
    }

    /**
     * @param Throwable   $e
     * @param string|null $origin
     * @return void
     */
    private static function report(Throwable $e, $origin)
    {
        if (self::$reported) {
            return;
        }
        self::$reported = true;

        try {
            $errorHandling  = null;
            $requestContext = null;
            if (null !== self::$resolver) {
                [$errorHandling, $requestContext] = call_user_func(self::$resolver);
            }

            $attributes = self::attributes($requestContext);
            if (null !== $origin) {
                $attributes['origin'] = $origin;
            }

            if (null !== $errorHandling) {
                $errorHandling->handle($e, $attributes);
                return;
            }
            self::reportWithoutContainer($e, $attributes);
        } catch (Throwable $reportingFailure) {
            //last resort: a bare line, since anything richer just failed
            self::appendBootstrapLog(
                sprintf(
                    "%s reporting failed: %s: %s\n",
                    date('c'),
                    get_class($reportingFailure),
                    $reportingFailure->getMessage()
                )
            );
        }
    }

    /**
     * Record a failure that happened before the container existed.
     *
     * No email is possible here — the recipients live in merged module
     * configuration that was never built. The record still lands in the store,
     * so fetch-exceptions.sh surfaces it alongside everything else, and a plain
     * line goes to the bootstrap log for the case where even the store is
     * unwritable.
     *
     * @param Throwable $e
     * @param array     $attributes
     * @return void
     */
    private static function reportWithoutContainer(Throwable $e, array $attributes)
    {
        $fingerprinter = new Fingerprinter(self::$appRoot);
        $record        = ExceptionRecord::fromThrowable($e, $fingerprinter, $attributes);

        $store = new ExceptionStore(self::$appRoot . '/' . self::DEFAULT_STORE_PATH);
        $store->record($record);

        self::appendBootstrapLog($record->toWriteUp() . "\n");
    }

    /**
     * @param RequestContext|null $requestContext
     * @return array
     */
    private static function attributes($requestContext)
    {
        $context = $requestContext instanceof RequestContext
            ? $requestContext
            : new RequestContext(self::$appRoot);

        return $context->attributesFromGlobals();
    }

    /**
     * @param string $text
     * @return void
     */
    private static function appendBootstrapLog($text)
    {
        $path = self::$appRoot . '/' . self::BOOTSTRAP_LOG;
        $dir  = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($path, $text, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param string $path
     * @return string
     */
    private static function relativePath($path)
    {
        $path = str_replace('\\', '/', (string) $path);
        if ('' !== self::$appRoot && 0 === strpos($path, self::$appRoot . '/')) {
            return substr($path, strlen(self::$appRoot) + 1);
        }
        return $path;
    }

    /**
     * Restore the class to its unregistered state. For tests only — the
     * registered handlers themselves cannot be removed.
     *
     * @return void
     */
    public static function reset()
    {
        self::$appRoot    = '.';
        self::$resolver   = null;
        self::$registered = false;
        self::$reported   = false;
        self::$reserve    = null;
    }
}
