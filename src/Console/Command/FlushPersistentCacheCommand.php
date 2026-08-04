<?php

namespace SionModel\Console\Command;

use Laminas\Http\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Flush the persistent cache by asking the running site to do it.
 *
 * This command makes an HTTP request instead of calling apcu_clear_cache(),
 * and that is not an oversight. The persistent cache is the APCu storage
 * adapter, and an APCu segment belongs to the SAPI that created it: the
 * FastCGI pool's cache is not visible from a CLI process, which gets its own
 * segment or — with the default apc.enable_cli=0 — none at all. So a CLI
 * flush would report cheerful success while the web server carried on serving
 * the very entries it was told to drop. Only a request that lands in the web
 * SAPI can flush that segment.
 *
 * What this command does buy over the `wget …?key=…` it replaces is that the
 * key travels in a header. A query string is written to the web server's
 * access log and kept in shell history; for a key that never rotates, that is
 * a standing leak.
 */
#[AsCommand(
    name: 'cache:flush-persistent',
    description: "Flush the web server's persistent (APCu) cache over HTTP",
)]
final class FlushPersistentCacheCommand extends Command
{
    /**
     * Locale-prefixed on purpose: `/sm/…` answers 302 to `/en/sm/…`, and a
     * custom header surviving a redirect is not something to depend on.
     */
    public const DEFAULT_PATH = '/en/sm/clear-persistent-cache';

    /** Env var checked when no --key is given; preferred over --key. */
    public const KEY_ENV_VAR = 'SCH_MAINTENANCE_KEY';

    private Client $client;
    private string $baseUrl;
    /** @var list<string> */
    private array $apiKeys;
    private string $path;

    /**
     * @param list<string> $apiKeys From sion_model.api_keys — populated on the
     *        server, so a run there needs no key passed in at all.
     */
    public function __construct(
        Client $client,
        string $baseUrl,
        array $apiKeys,
        string $path = self::DEFAULT_PATH
    ) {
        $this->client = $client;
        $this->baseUrl = $baseUrl;
        $this->apiKeys = $apiKeys;
        $this->path = $path;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'url',
                null,
                InputOption::VALUE_REQUIRED,
                'Base URL of the site to flush (defaults to the configured one)'
            )
            ->addOption(
                'key',
                null,
                InputOption::VALUE_REQUIRED,
                'Maintenance API key. Prefer ' . self::KEY_ENV_VAR . ', which stays out of shell history'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $key = $this->resolveKey($input, $io);
        if (null === $key) {
            $io->error([
                'No maintenance API key available.',
                sprintf(
                    'Set %s in the environment, or configure sion_model.api_keys, or pass --key.',
                    self::KEY_ENV_VAR
                ),
            ]);
            return Command::INVALID;
        }

        $baseUrl = rtrim((string) ($input->getOption('url') ?? '') ?: $this->baseUrl, '/');
        if ('' === $baseUrl) {
            $io->error('No base URL configured; pass --url.');
            return Command::INVALID;
        }

        $uri = $baseUrl . $this->path;
        $io->writeln(sprintf('Flushing persistent cache via <info>%s</info>', $uri));

        $this->client->resetParameters();
        $this->client->setUri($uri);
        $this->client->setMethod('GET');
        //no redirect following: an unauthenticated request is answered with a
        //302 to the sign-in page, and that redirect is how a rejected key
        //announces itself. Following it would turn a 401 into a cheerful 200.
        $this->client->setOptions(['maxredirects' => 0, 'timeout' => 30]);
        $this->client->setHeaders([
            'X-Api-Key' => $key,
            'Accept' => 'application/json',
        ]);

        try {
            $response = $this->client->send();
        } catch (\Throwable $e) {
            $io->error(sprintf('Request failed: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        $status = $response->getStatusCode();
        $body = trim((string) $response->getBody());

        if (302 === $status || 301 === $status) {
            $io->error([
                'The site redirected instead of answering, which is how it rejects an unknown key.',
                'Check that the key matches an entry in the target site\'s sion_model.api_keys.',
            ]);
            return Command::FAILURE;
        }

        if (200 !== $status) {
            $io->error(sprintf('HTTP %d: %s', $status, $this->summarize($body)));
            return Command::FAILURE;
        }

        //the action answers 200 with {"message":"Success"}; a failed flush is
        //reported as "Unsuccessful flush" (and its own status), so the body is
        //the authority on whether anything was actually dropped
        if (! str_contains($body, 'Success')) {
            $io->error(sprintf('The endpoint answered 200 but did not report success: %s', $this->summarize($body)));
            return Command::FAILURE;
        }

        $io->success('Persistent cache flushed.');
        return Command::SUCCESS;
    }

    /**
     * @return string|null
     */
    private function resolveKey(InputInterface $input, SymfonyStyle $io)
    {
        $fromOption = $input->getOption('key');
        if (is_string($fromOption) && '' !== $fromOption) {
            $io->warning(sprintf(
                '--key puts the secret in this shell\'s history and in the process list; %s avoids both.',
                self::KEY_ENV_VAR
            ));
            return $fromOption;
        }

        $fromEnv = getenv(self::KEY_ENV_VAR);
        if (is_string($fromEnv) && '' !== $fromEnv) {
            return $fromEnv;
        }

        foreach ($this->apiKeys as $candidate) {
            if (is_string($candidate) && '' !== $candidate) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Keep an HTML error page from burying the console output.
     */
    private function summarize(string $body): string
    {
        $flat = trim(preg_replace('/\s+/', ' ', $body) ?? '');
        if ('' === $flat) {
            return '(empty body)';
        }
        return strlen($flat) > 200 ? substr($flat, 0, 200) . '…' : $flat;
    }
}
