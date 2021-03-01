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
    * Extract translatable strings
    *
    * @return void
    */
   public function localesExtract() {
      $this->_exec('./vendor/bin/extract_template.sh');
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
      $this->_exec('./vendor/bin/plugin-release --compile-mo');
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
}
