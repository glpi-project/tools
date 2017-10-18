<?php
/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */

namespace Glpi\Tools;

class RoboFile extends \Robo\Tasks
{
   protected $csignore = ['/vendor/'];
   protected $csfiles  = ['./'];

   /**
    * Minify all
    *
    * @return void
    */
   public function minify() {
      $this->minifyCSS()
         ->minifyJS();
   }

   /**
    * Minify CSS stylesheets
    *
    * @return void
    */
   public function minifyCSS() {
      $css_dir = './css';
      if (is_dir($css_dir)) {
         foreach (glob("$css_dir/*.css") as $css_file) {
            if (!$this->endsWith($css_file, 'min.css')) {
               $this->taskMinify($css_file)
                  ->to(str_replace('.css', '.min.css', $css_file))
                  ->type('css')
                  ->run();
            }
         }
      }
      return $this;
   }

   /**
    * Minify JavaScript files stylesheets
    *
    * @return void
    */
   public function minifyJS() {
      $js_dir = './js';
      if (is_dir($js_dir)) {
         foreach (glob("$js_dir/*.js") as $js_file) {
            if (!$this->endsWith($js_file, 'min.js')) {
               $this->taskMinify($js_file)
                  ->to(str_replace('.js', '.min.js', $js_file))
                  ->type('js')
                  ->run();
            }
         }
      }
      return $this;
   }

   /**
    * Extract translatable strings
    *
    * @return void
    */
   public function localesExtract() {

      $potfile = $this->getProjectName() . '.pot';

      // create locales directory
      if (!file_exists('./locales')) {
         $success = mkdir('./locales');
         if (!$success) {
            throw new \Exception('Failed to create locales directory');
         }
      }

      // iterate subtree of the ilesystem to enumerate fiels with locales
      $directory = new \RecursiveDirectoryIterator('.');
      $filter = new \RecursiveCallbackFilterIterator($directory, function($current, $key, $iterator) {
         if ($current->getFilename()[0] === '.') {
            return false;
         }

         if (!$current->isDir()) {
            return ($current->getExtension() === 'php');
         }

         if (strpos($current->getPathname(), './lib') === 0) {
            return false;
         }
         if (strpos($current->getPathname(), './vendor') === 0) {
            return false;
         }
         if (strpos($current->getPathname(), './node_modules') === 0) {
            return false;
         }

         return true;
      });
      $iterator = new \RecursiveIteratorIterator($filter);
      $files = [];
      foreach ($iterator as $info) {
         $files[] = $info->getPathname();
      }
      $files = implode(' ', $files);

      // extract locales from source code
      $command = "xgettext $files -o locales/$potfile -L PHP --add-comments=TRANS --from-code=UTF-8 --force-po";
      $command.= " --keyword=_n:1,2,4t --keyword=__s:1,2t --keyword=__:1,2t --keyword=_e:1,2t --keyword=_x:1c,2,3t --keyword=_ex:1c,2,3t";
      $command.= " --keyword=_sx:1c,2,3t --keyword=_nx:1c,2,3,5t";
      $this->_exec($command);
      return $this;
   }

   /**
    * Push locales to transifex
    *
    * @return void
    */
   public function localesPush() {
      $this->_exec('tx push -s');
      return $this;
   }

   /**
    * Pull locales from transifex.
    *
    * @param integer $percent Completeness percentage, defaults to 70
    *
    * @return void
    */
   public function localesPull($percent = 70) {
      $this->_exec('tx pull -a --minimum-perc=' .$percent);
      return $this;
   }

   /**
    * Build MO files
    *
    * @return void
    */
   public function localesMo() {
      $localesPath = './locales';
      if ($handle = opendir($localesPath)) {
         while (($file = readdir($handle)) !== false) {
            if ($file != "." && $file != "..") {
               $poFile = "$localesPath/$file";
               if (pathinfo($poFile, PATHINFO_EXTENSION) == 'po') {
                  $moFile = str_replace('.po', '.mo', $poFile);
                  $command = "msgfmt $poFile -o $moFile";
                  $this->_exec($command);
               }
            }
         }
         closedir($handle);
      }
      return $this;
   }

   /**
    * Extract and send locales
    *
    * @return void
    */
   public function localesSend() {
      $this->localesExtract()
           ->localesPush();
      return $this;
   }

   /**
    * Retrieve locales and generate mo files
    *
    * @param integer $percent Completeness percentage, defaults to 70
    *
    * @return void
    */
   public function localesGenerate($percent = 70) {
      $this->localesPull($percent)
           ->localesMo();
      return $this;
   }

   /**
    * Code sniffer.
    *
    * Run the PHP Codesniffer on a file or directory.
    *
    * @param string $file    A file or directory to analyze.
    * @param array  $options Options:
    * @option $autofix Whether to run the automatic fixer or not.
    * @option $strict  Show warnings as well as errors.
    *    Default is to show only errors.
    *
    *    @return void
    */
   public function codeCs(
      $file = null,
      $options = [
         'autofix'   => false,
         'strict'    => false,
      ]
   ) {
      if ($file === null) {
         $file = implode(' ', $this->csfiles);
      }

      $csignore = '';
      if (count($this->csignore)) {
         $csignore .= '--ignore=';
         $csignore .= implode(',', $this->csignore);
      }

      $strict = $options['strict'] ? '' : '-n';

      $result = $this->taskExec("./vendor/bin/phpcs $csignore --standard=vendor/glpi-project/coding-standard/GlpiStandard/ {$strict} {$file}")->run();

      if (!$result->wasSuccessful()) {
         if (!$options['autofix'] && !$options['no-interaction']) {
            $options['autofix'] = $this->confirm('Would you like to run phpcbf to fix the reported errors?');
         }
         if ($options['autofix']) {
            $result = $this->taskExec("./vendor/bin/phpcbf $csignore --standard=vendor/glpi-project/coding-standard/GlpiStandard/ {$file}")->run();
         }
      }

      return $result;
   }


   /**
    * Checks if a string ends with another string
    *
    * @param string $haystack Full string
    * @param string $needle   Ends string
    *
    * @return boolean
    * @see http://stackoverflow.com/a/834355
    */
   private function endsWith($haystack, $needle) {
      $length = strlen($needle);
      if ($length == 0) {
         return true;
      }

      return (substr($haystack, -$length) === $needle);
   }

   /**
    * Finds the name of the project
    * @throws \Exception
    * @return string|null the name pf the project
    */
   private function getProjectName() {
      $projectName = null;

      if (file_exists('setup.php') && is_readable('setup.php')) {
         // The project is a plugin
         $fileContent = file_get_contents('setup.php');
         $pattern = "#^define\('PLUGIN_(.*)_VERSION', '([^']*)'\);$#m";
         $matches = null;
         preg_match($pattern, $fileContent, $matches);
         if (!isset($matches[1])) {
            throw new \Exception("Could not determine the name of of the project");
         }
         $projectName = $matches[1];
      }

      if (file_exists('inc/define.php') && is_readable('inc/define.php')) {
         //The project seems to be GLPI
         $fileContent = file_get_contents('setup.php');
         $pattern = "(GLPI)_VERSION";
         $matches = null;
         preg_match($pattern, $fileContent, $matches);
         if (!isset($matches[1])) {
            throw new \Exception("Could not determine the name of of the project");
         }
         $projectName = $matches[1];
      }

      if ($projectName === null) {
         throw new \Exception("Could not determine the name of of the project");
      }

      return strtolower($projectName);
   }
}
