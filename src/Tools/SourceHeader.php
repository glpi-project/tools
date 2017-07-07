<?php

namespace Glpi\Tools;

class SourceHeader {

   /**
    * Search for a Header template file in  the projecct first, and failback to internal header template
    *
    * @throws Exception
    *
    * @return string path to header file
    */
   protected function findHeaderTemplateFile() {
      $filename = __DIR__ . '/../../../../../../tools/HEADER';
      if (file_exists($filename)) {
         if (!is_readable($filename)) {
            throw new Exception("$filename found but is not readable");
         }
      } else {
         $filename = __DIR__ . '/../tools/HEADER';
      }

      return $filename;
   }

   /**
    * Read the header template from a file
    *
    * @throws Exception
    *
    * @return string header template
    */
   protected function getHeaderTemplate() {
      if (empty($this->headerTemplate)) {
         $this->headerTemplate = file_get_contents($this->findHeaderTemplateFile());
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
    * Update source code header in a source file
    *
    * @param string $filename source code file to process
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
    *
    * @param string source code
    *
    * @return string updated source code
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
    * Format header template for a file type based on extension
    *
    * @param string $extension
    *
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