<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI tools
 *
 * @copyright 2017-2022 Teclib' and contributors.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://github.com/glpi-project/tools
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI tools.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

namespace Glpi\Tools;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;
use Twig\Cache\CacheInterface;
use Twig\Cache\FilesystemCache;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;
use RecursiveDirectoryIterator;
use RecursiveFilterIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class CompileTwigTemplatesCommand extends Command
{

    protected function configure()
    {
        parent::configure();

        $this->setName('glpi:tools:compile_twig_templates');
        $this->setDescription('Compile twig templates into php files.');

        $this->addArgument(
            'templates-directory',
            InputArgument::REQUIRED,
            'Templates directory'
        );

        $this->addArgument(
            'output-directory',
            InputArgument::REQUIRED,
            'Output directory'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $tpl_dir    = $input->getArgument('templates-directory');
        $output_dir = $input->getArgument('output-directory');

        $loader = new FilesystemLoader($tpl_dir, dirname($tpl_dir));
        $twig = $this->getMockedTwigEnvironment($loader);
        $twig->setCache($this->getTwigCacheHandler($output_dir));

        $files = $this->getTemplatesFiles($tpl_dir);

        $progress_bar = new ProgressBar($output);
        foreach ($progress_bar->iterate($files) as $file) {
            $twig->load($file);
        }

        $output->writeln(''); // New to next line after progress bar display

        return 0; // Success
    }

    /**
     * Return template files.
     *
     * @param string $directory
     *
     * @return array
     */
    private function getTemplatesFiles(string $directory): array
    {
        $directory = realpath($directory);

        if (!is_dir($directory) || !is_readable($directory)) {
            throw new \Symfony\Component\Console\Exception\InvalidOptionException(
                sprintf('Unable to read directory "%s"', $directory)
            );
        }

        $dir_iterator = new RecursiveDirectoryIterator($directory);

        $filter_iterator = new class($dir_iterator) extends RecursiveFilterIterator {
            public function accept(): bool
            {
                if ($this->isFile() && !preg_match('/^twig$/', $this->getExtension())) {
                    return false;
                }
                return true;
            }
        };

        $recursive_iterator = new RecursiveIteratorIterator(
            $filter_iterator,
            RecursiveIteratorIterator::SELF_FIRST
        );

        $files = [];

        /** @var SplFileInfo $file */
        foreach ($recursive_iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $files[] = preg_replace(
                '/^' . preg_quote($directory . DIRECTORY_SEPARATOR, '/') . '/',
                '',
                $file->getRealPath()
            );
        }

        return $files;
    }

    /**
     * Return a mocked Twig environment.
     * This mocked environment will prevent exceptions to be thrown when custom
     * functions, filters or tests are used in templates.
     *
     * @param LoaderInterface $loader
     *
     * @return Environment
     */
    private function getMockedTwigEnvironment(LoaderInterface $loader): Environment
    {
        return new class ($loader) extends Environment {

            public function getFunction(string $name): ?TwigFunction
            {
                // If not found by parent, return a function that has its own name as callback
                // so Twig will generate code following this pattern: `$name($parameter, ...)`,
                // e.g. `__('str')` or `_n('str', 'strs', 5)`.
                return parent::getFunction($name) ?? new TwigFunction($name, $name);
            }

            public function getFilter(string $name): ?TwigFilter
            {
                return parent::getFilter($name) ?? new TwigFilter($name, function () {});
            }

            public function getTest(string $name): ?TwigTest
            {
                if (in_array($name, ['divisible', 'same'])) {
                    // `same as` and `divisible by` will be search in 2 times.
                    // First check will be done on first word, should return `null` to
                    // trigger second search that will be done on full name.
                    return null;
                }
                return parent::getTest($name) ?? new TwigTest($name, function () {});
            }
        };
    }

    /**
     * Return a custom Twig cache handler.
     * This handler is usefull to be able to preserve filenames of compiled files.
     *
     * @param string $directory
     *
     * @return CacheInterface
     */
    private function getTwigCacheHandler(string $directory): CacheInterface
    {
        return new class($directory) extends FilesystemCache {

            private $directory;

            public function __construct(string $directory, int $options = 0)
            {
                $this->directory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                parent::__construct($directory, $options);
            }

            public function generateKey(string $name, string $className): string
            {
                return $this->directory . $name;
            }
        };
    }
}
