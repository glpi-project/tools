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
   protected $headerTemplate;

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

   /**
    * Update headers in source files
    */
   public function codeHeadersUpdate() {
      $toUpdate = $this->getTrackedFiles();
      foreach ($toUpdate as $file) {
         $this->replaceSourceHeader($file);
      }
   }

   /**
    * Returns all files tracked in the repository
    *
    * @param string $version
    * @throws Exception
    * @return array
    */
   protected function getTrackedFiles($version = null) {
      $output = [];
      if ($version === null) {
         $version = 'HEAD';
      }
      exec("git ls-tree -r '$version' --name-only", $output, $retCode);
      if ($retCode != '0') {
         throw new Exception("Unable to get tracked files");
      }
      return $output;
   }

   /**
    * Update source code header in a source file
    * @param string $filename
    */
   protected function replaceSourceHeader($filename) {
      // get the content of the file to update
      $source = file_get_contents($filename);

      // define regex for the file type
      $ext = pathinfo($filename, PATHINFO_EXTENSION);
      switch ($ext) {
         case 'php':
            $source = $this->replacesourceHeaderForPHP($source);
            break;

         default:
            // Unhandled file format
            return;
      }

      if (file_put_contents($filename, $source) === false) {
         throw new Exception('Failed to write ' . $filename);
      }
   }

   /**
    * Update the header in given source and returns the result
    * @return string
    */
   protected function replacesourceHeaderForPHP($source) {
      $prefix              = "\<\?php\\n/\*(\*)?\\n";
      $replacementPrefix   = "<?php\n/**\n";
      $suffix              = "\\n( )?\*/";
      $replacementSuffix   = "\n */";

      // format header template for the file type
      $header = trim($this->getHeaderTemplate());
      $formatedHeader = $replacementPrefix . $this->getFormatedHeaderTemplate('php', $header) . $replacementSuffix;

      // update authors in formated template
      $headerMatch = [];
      $originalAuthors = [];
      $authors = [];
      $authorsRegex = "#^.*(\@author .*)$#Um";
      preg_match('#^' . $prefix . '(.*)' . $suffix . '#Us', $source, $headerMatch);
      if (isset($headerMatch[0])) {
         $originalHeader = $headerMatch[0];
         preg_match_all($authorsRegex, $originalHeader, $originalAuthors);
         if (isset($originalAuthors[1])) {
            $originalAuthors = $this->getFormatedHeaderTemplate('php', implode("\n", $originalAuthors[1]));
            $formatedHeader = preg_replace($authorsRegex, $originalAuthors, $formatedHeader, 1);
         }
      }

      // replace the header if it exists
      $source = preg_replace('#^' . $prefix . '(.*)' . $suffix . '#Us', $formatedHeader, $source, 1);
      if (empty($source)) {
         throw new Exception("An error occurred while processing $filename");
      }

      return $source;
   }

   /**
    * Read the header template from a file
    * @throws Exception
    * @return string
    */
   protected function getHeaderTemplate() {
      if (empty($this->headerTemplate)) {
         //$this->headerTemplate = file_get_contents(__DIR__ . '../../../../../tools/HEADER');
         $this->headerTemplate = file_get_contents('tools/HEADER');
         if (empty($this->headerTemplate)) {
            throw new Exception('Header template file not found');
         }
      }

      $copyrightRegex = "#Copyright (\(c\)|©) (\d{4}-)?(\d{4}) #iUm";
      $year = date("Y");
      $replacement = 'Copyright © ${2}' . $year . ' ';
      $this->headerTemplate = preg_replace($copyrightRegex, $replacement, $this->headerTemplate);

      return $this->headerTemplate;
   }

   /**
    * Format header template for a file type based on extension
    *
    * @param string $extension
    * @return string
    */
   protected function getFormatedHeaderTemplate($extension, $template) {
      switch ($extension) {
         case 'php':
            $lines = explode("\n", $template);
            foreach ($lines as &$line) {
               $line = rtrim(" * $line");
            }
            return implode("\n", $lines);
            break;

         default:
            return $template;
      }
   }

}
