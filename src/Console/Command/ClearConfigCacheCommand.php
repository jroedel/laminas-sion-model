<?php

namespace SionModel\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Delete the cached merged config and module class map.
 *
 * Production boots with config caching on, so a deploy that ships changed
 * configuration keeps serving the previously merged array until these files
 * are gone. The deploy used to do it with a hand-written
 * `rm -f data/config/module-*-cache.*.php`; the paths here come from the module
 * listener options instead, so a changed cache key cannot leave the deploy
 * silently deleting nothing.
 *
 * Unlike the persistent (APCu) cache, this one is a plain file cache — there is
 * no shared-memory segment tied to a SAPI, so a CLI process really can clear
 * it.
 */
#[AsCommand(
    name: 'cache:clear-config',
    description: 'Delete the cached merged config and module class map',
)]
final class ClearConfigCacheCommand extends Command
{
    /** @var list<string> */
    private array $cacheFiles;

    /**
     * @param list<string> $cacheFiles Absolute paths, or paths relative to the
     *        application root (the console entry point chdir()s there).
     */
    public function __construct(array $cacheFiles)
    {
        $this->cacheFiles = $cacheFiles;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ([] === $this->cacheFiles) {
            //no cache_dir configured means nothing was ever written
            $io->warning('No config cache is configured; nothing to clear.');
            return Command::SUCCESS;
        }

        $removed = [];
        $failed = [];
        foreach ($this->cacheFiles as $file) {
            if (! file_exists($file)) {
                $io->writeln(sprintf('  <fg=gray>absent</>  %s', $file));
                continue;
            }
            if (! is_file($file)) {
                //something other than a file sitting on a cache path is a
                //misconfiguration to report, not an unlink() target
                $failed[] = $file;
                $io->writeln(sprintf('  <error>not a file</error> %s', $file));
                continue;
            }
            if (@unlink($file)) {
                $removed[] = $file;
                $io->writeln(sprintf('  <info>removed</info> %s', $file));
                continue;
            }
            $failed[] = $file;
            $io->writeln(sprintf('  <error>failed</error>  %s', $file));
        }

        if ([] !== $failed) {
            //the usual cause is ownership: the web SAPI writes these files as
            //its own user, so a deploy account can be unable to remove them,
            //which pins the site to a stale merged config
            $io->error(sprintf(
                'Could not delete %d cache file(s). Check ownership — the web server writes'
                . ' them as its own user, and a file this account cannot replace leaves the'
                . ' application on its old configuration.',
                count($failed)
            ));
            return Command::FAILURE;
        }

        $io->success(sprintf('Config cache cleared (%d file(s) removed).', count($removed)));
        return Command::SUCCESS;
    }
}
