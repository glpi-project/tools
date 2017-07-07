<?php

namespace Glpi\Tools;

class Git {

   private $repoDir;

   public function __construct($repoDir = null) {
      if ($repoDir === null) {
         $repoDir = __DIR__;
      }
      $this->repoDir = $repoDir;
   }

   /**
    * Returns all files tracked in the repository
    *
    * @param string $refName a GIT refname (HEAD, a tag, a commit hash, ...)
    * @throws Exception
    *
    * @return array lines outputted by the command
    */
   protected function getTrackedFiles($refName = null) {
      $output = [];
      if ($refName === null) {
         $refName = 'HEAD';
      }
      try {
         $output = $this->execute("ls-tree -r '$refName' --name-only");
      } catch (Exception $e) {
         throw new Exception("Unable to get tracked files");
      }
      return $output;
   }

   /**
    * runs a git command
    *
    * @param string $command the command to run
    */
   protected function execute($command) {
      $repoDir = $this->repoDir;
      $command = "git -C '$repoDir' " . $command;
      exec($command, $output, $retCode);
      if ($retCode != 0) {
         throw new Exception('Failed to run git command: $command');
      }
      return $output;
   }

}