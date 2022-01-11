<?php
/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2021 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

namespace Glpi\Tools;

use RecursiveDirectoryIterator;
use RecursiveFilterIterator;
use RecursiveIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LicenceHeadersCheckCommand extends Command {

   /**
    * Result code returned when some headers are missing or are outdated.
    *
    * @var integer
    */
   const ERROR_FOUND_MISSING_OR_OUTDATED = 1;

   /**
    * Result code returned when some files cannot be updated.
    *
    * @var integer
    */
   const ERROR_UNABLE_TO_FIX_FILES = 2;

   /**
    * Header lines.
    *
    * @var array
    */
   private $header_lines;

   protected function configure() {
      parent::configure();

      $this->setName('glpi:tools:licence_headers_check');
      $this->setDescription('Check licence header in code source files.');

      $project_dir = realpath(__DIR__ . str_repeat(DIRECTORY_SEPARATOR . '..', 5));
      if ($project_dir === false || !is_readable($project_dir)) {
         $project_dir = null;
      }

      $this->addOption(
         'directory',
         'd',
         InputOption::VALUE_REQUIRED,
         'Directory to parse',
         $project_dir
      );

      $header_file = $project_dir !== null
         ? realpath($project_dir . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'HEADER')
         : null;

      $this->addOption(
         'header-file',
         null,
         InputOption::VALUE_REQUIRED,
         'Header file to use',
         $header_file
      );

      $this->addOption(
         'fix',
         'f',
         InputOption::VALUE_NONE,
         'Fix missing and outdated headers'
      );
   }

   protected function execute(InputInterface $input, OutputInterface $output) {

      $files = $this->getFilesToParse($input->getOption('directory'));

      $output->writeln(
         '<comment>' . sprintf('%s files to process.', count($files)) . '</comment>',
         OutputInterface::VERBOSITY_VERBOSE
      );

      $missing_found   = 0;
      $missing_errors  = 0;
      $outdated_found  = 0;
      $outdated_errors = 0;

      foreach ($files as $filename) {
         $output->writeln(
            '<comment>' . sprintf('Processing "%s".', $filename) . '</comment>',
            OutputInterface::VERBOSITY_VERY_VERBOSE
         );

         $header_start_pattern   = null;
         $header_end_pattern     = null;
         $header_content_pattern = null;

         switch (pathinfo($filename, PATHINFO_EXTENSION)) {
            case 'pl':
            case 'sh':
            case 'yaml':
            case 'yml':
               $header_line_prefix     = '# ';
               $header_prepend_line    = "#\n";
               $header_append_line     = "#\n";
               $header_start_pattern   = '/^#[^!]/'; // Any commented line except shebang (#!)
               $header_content_pattern = '/^#/';
               break;
            case 'sql':
               $header_line_prefix     = '-- ';
               $header_prepend_line    = "--\n";
               $header_append_line     = "--\n";
               $header_content_pattern = '/^(--|#)/'; // older headers were prefixed by "#"
               break;
            case 'css':
            case 'scss':
               $header_line_prefix     = ' * ';
               $header_prepend_line    = "/*!\n";
               $header_append_line     = " */\n";
               $header_start_pattern   = '/^\/\*(\!|\*)?$/'; // older headers were starting by "/**" or "/*!"
               $header_end_pattern     = '/\*\//';
               break;
            case 'twig':
               $header_line_prefix     = ' # ';
               $header_prepend_line    = "{#\n";
               $header_append_line     = " #}\n";
               $header_start_pattern   = '/^\{#$/';
               $header_end_pattern     = '/#}/';
               break;
            default:
               $header_line_prefix     = ' * ';
               $header_prepend_line    = "/**\n";
               $header_append_line     = " */\n";
               $header_start_pattern   = '/^\/\*\*?$/';
               $header_end_pattern     = '/\*\//';
               break;
         }

         if ($header_start_pattern === null) {
            // If there is no specific "start pattern", then first regular comment line is consider are header start.
            $header_start_pattern = $header_content_pattern;
         }

         $header_found         = false;
         $header_missing       = false;
         $is_header_line       = false;
         $is_last_header_line  = false;
         $pre_header_lines     = [];
         $current_header_lines = [];
         $post_header_lines    = [];

         if (($file_lines = file($filename)) === false) {
            throw new \Exception(sprintf('Unable to read file.', $filename));
         }

         foreach ($file_lines as $line) {
            if (!$header_found && !$header_missing) {
               if (preg_match($header_start_pattern, $line)) {
                  // Line matches header opening line
                  $header_found = true;
                  $is_header_line = true;
               } else if (!$this->shouldLineBeLocatedBeforeHeader($line)) {
                  // Line does not match allowed lines before header,
                  // consider that header is missing.
                  $header_missing = true;
               }
            } else if ($is_last_header_line) {
               // Previous line was "last header line", so current line is the first line after licence header
               $is_last_header_line = false;
               $is_header_line = false;
            } else if ($is_header_line && $header_end_pattern !== null && preg_match($header_end_pattern, $line)) {
               // Line matches header end pattern
               $is_last_header_line = true;
            } else if ($is_header_line && $header_content_pattern !== null && !preg_match($header_content_pattern, $line)) {
               // Line does not match header, so it is the first line after licence header
               $is_header_line = false;
            }

            if ($header_missing || ($header_found && !$is_header_line)) {
               $post_header_lines[] = $line;
            } else if ($is_header_line) {
               $current_header_lines[] = $line;
            } else {
               $pre_header_lines[] = $line;
            }
         }

         $updated_header_lines = $this->getLicenceHeaderLines(
            $input->getOption('header-file'),
            $header_line_prefix,
            $header_prepend_line,
            $header_append_line
         );

         $header_outdated = array_slice($updated_header_lines, 1, -1) !== array_slice($current_header_lines, 1, -1);

         if (!$header_missing && !$header_outdated) {
            continue;
         }

         if ($header_missing) {
            $output->writeln(
               '<info>' . sprintf('Missing licence header in file "%s".', $filename) . '</info>',
               OutputInterface::VERBOSITY_NORMAL
            );
            $missing_found++;
         } else {
            $output->writeln(
               '<info>' . sprintf('Licence header outdated in file "%s".', $filename) . '</info>',
               OutputInterface::VERBOSITY_NORMAL
            );
            $outdated_found++;
         }

         if ($input->getOption('fix')) {
            $pre_header_lines  = $this->stripEmptyLines($pre_header_lines, false, true);
            $post_header_lines = $this->stripEmptyLines($post_header_lines, true, false);

            $file_contents = '';
            if (!empty($pre_header_lines)) {
               $file_contents .= implode('', $pre_header_lines) . "\n";
            }
            $file_contents .= implode('', $updated_header_lines) . "\n";
            $file_contents .= implode('', $post_header_lines);

            if (strlen($file_contents) !== file_put_contents($filename, $file_contents)) {
               $output->writeln(
                  '<error>' . sprintf('Unable to update licence header in file "%s".', $filename) . '</error>',
                  OutputInterface::VERBOSITY_QUIET
               );
               if ($header_missing) {
                  $missing_errors++;
               } else {
                  $outdated_errors++;
               }
            }
         }
      }

      if ($missing_found === 0 && $outdated_found === 0) {
         $output->writeln('<info>Files headers are valid.</info>', OutputInterface::VERBOSITY_QUIET);
         return 0; // Success
      }

      if (!$input->getOption('fix')) {
         $msg = sprintf(
            'Found %d file(s) without header and %d file(s) with outdated header. Use --fix option to fix these files.',
            $missing_found,
            $outdated_found
         );
         $output->writeln('<error>' . $msg . '</error>', OutputInterface::VERBOSITY_QUIET);
         return self::ERROR_FOUND_MISSING_OR_OUTDATED;
      }

      $msg = sprintf(
         'Fixed %d file(s) without header and %d file(s) with outdated header.',
         $missing_found - $missing_errors,
         $outdated_found - $outdated_errors
      );
      $output->writeln('<info>' . $msg . '</info>', OutputInterface::VERBOSITY_QUIET);

      if ($missing_errors > 0 || $outdated_errors > 0) {
         $output->writeln(
            '<error>' . sprintf('%s file(s) cannot be updated.', $missing_errors + $outdated_errors) . '</error>',
            OutputInterface::VERBOSITY_QUIET
         );
         return self::ERROR_UNABLE_TO_FIX_FILES;
      }

      return 0; // Success
   }

   /**
    * Get licence header lines.
    *
    * @param string $header_file_path
    * @param string $line_prefix
    * @param string $prepend_line
    * @param string $append_line
    *
    * @return array
    */
   private function getLicenceHeaderLines(
      string $header_file_path,
      string $line_prefix,
      string $prepend_line,
      string $append_line
   ): array {
      if ($this->header_lines === null) {
         if (($lines = file($header_file_path)) === false) {
            throw new \Exception('Unable to read header file.');
         }
         $this->header_lines = $lines;
      }

      $lines = [];
      $lines[] = $prepend_line;
      foreach ($this->header_lines as $line) {
         $lines[] = (preg_match('/^\s+$/', $line) ? rtrim($line_prefix) : $line_prefix) . $line;
      }
      $lines[] = $append_line;

      return $this->stripEmptyLines($lines, true, true);
   }

   /**
    * Return files to parse.
    *
    * @param string $directory
    *
    * @return array
    */
   private function getFilesToParse(string $directory): array {
      $directory = realpath($directory);

      if (!is_dir($directory) || !is_readable($directory)) {
         throw new \Symfony\Component\Console\Exception\InvalidOptionException(
            sprintf('Unable to read directory "%s"', $directory)
         );
      }

      $dir_iterator = new RecursiveDirectoryIterator($directory);
      $exclusion_pattern = $this->getExclusionPattern($directory);

      $filter_iterator = new class($dir_iterator, $exclusion_pattern) extends RecursiveFilterIterator {
         private $exclusion_pattern;

         public function __construct(RecursiveIterator $iterator, ?string $exclusion_pattern) {
            $this->exclusion_pattern = $exclusion_pattern;
            parent::__construct($iterator);
         }

         public function accept(): bool {
            if ($this->exclusion_pattern !== null && preg_match($this->exclusion_pattern, $this->getRealPath())) {
               return false;
            }
            if ($this->isFile() && !preg_match('/^(css|js|php|pl|scss|sh|sql|twig|ya?ml)$/', $this->getExtension())) {
               return false;
            }
            return true;
         }

         public function getChildren(): ?RecursiveFilterIterator {
            return new self($this->getInnerIterator()->getChildren(), $this->exclusion_pattern);
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

         $files[] = $file->getRealPath();
      }

      return $files;
   }

   /**
    * Indicates if a line can/should be located before licence header.
    *
    * @param string $line
    *
    * @return bool
    */
   private function shouldLineBeLocatedBeforeHeader(string $line): bool {
      // PHP opening tag
      if (rtrim($line) === '<?php') {
         return true;
      }

      // Shebang
      if (preg_match('/^#!/', $line)) {
         return true;
      }

      // File generated by bootstap
      if (strpos($line, '// webpackBootstrap') !== false
          || rtrim($line) === 'var __webpack_exports__ = {};') {
         return true;
      }

      // Empty line
      if (trim($line) === '') {
         return true;
      }

      return false;
   }

   /**
    * Strip empty top/bottom lines from an array.
    *
    * @param array $lines
    * @param bool $strip_top_lines
    * @param bool $strip_bottom_lines
    *
    * @return array
    */
   private function stripEmptyLines(array $lines, bool $strip_top_lines, bool $strip_bottom_lines): array {
      // Remove empty lines from top of an array
      $strip_top_fct = function (array $values): array {
         $filtered_values = [];
         $found_not_empty = false;

         foreach ($values as $value) {
            if (!$found_not_empty && empty(trim($value))) {
               continue;
            }
            $found_not_empty = true;
            $filtered_values[] = $value;
         }

         return $filtered_values;
      };

      if ($strip_top_lines) {
         $lines = $strip_top_fct($lines);
      }

      if ($strip_bottom_lines) {
         $lines = array_reverse($lines);
         $lines = $strip_top_fct($lines);
         $lines = array_reverse($lines);
      }

      return $lines;
   }

   /**
    * Get files exclusion pattern. All files matching this pattern will be excluded from checks.
    *
    * @param string $directory
    *
    * @return string
    */
   protected function getExclusionPattern(string $directory): ?string {
      $excluded_elements = [
         '\.dependabot', // Dependabot config
         '\.git',
         '\.github', // Github specific files
         '\.gitlab-ci.yml', // Gitlab config
         '\.travis.yml', // Travis config
         '\.tx', // Transifex config

         'lib', // Manually included libs
         'node_modules', // npm imported libs
         'vendor', // composer imported libs

         'public\/lib', // libs packaged using webpack
      ];
      if (file_exists($directory . DIRECTORY_SEPARATOR . 'setup.php')
          && file_exists($directory . DIRECTORY_SEPARATOR . 'hook.php')) {
         // Directory is a plugin root directory
         $excluded_elements = array_merge(
            $excluded_elements,
            [
                'dist', // Plugin archives
            ]
         );
      } else if (file_exists($directory . DIRECTORY_SEPARATOR . 'composer.json')
                 && preg_match('/"name"\s*:\s*"glpi\/glpi"/', file_get_contents($directory . DIRECTORY_SEPARATOR . 'composer.json'))) {
         // Directory is GLPI root directory
         $excluded_elements = array_merge(
            $excluded_elements,
            [
               'config',
               'css\/lib',
               'files',
               'marketplace',
               'plugins',
               'tests\/config',
               'tests\/config_db\.php',
               'tests\/files',
            ]
         );
      }

      if (empty($excluded_elements)) {
         return null;
      }

      return '/^'
         . preg_quote($directory . DIRECTORY_SEPARATOR, '/')
         . '(' . implode('|', $excluded_elements) . ')'
         . '$/';
   }
}
