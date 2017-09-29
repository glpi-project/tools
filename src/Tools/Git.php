<?php

namespace Glpi\Tools;

use \Exception;

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
    * @param string $version
    * @throws Exception
    * @return array
    */
   public function getTrackedFiles($version = null) {
      if ($version === null) {
         $version = 'HEAD';
      }
      $repoDir = $this->repoDir;
      $output = $retCode = null;
      exec("git -C '$repoDir' ls-tree -r '$version' --name-only", $output, $retCode);
      if ($retCode != '0') {
         throw new Exception("Unable to get tracked files");
      }
      return $output;
   }

   /**
    * Get a file from git tree
    * @param string $path
    * @param string $rev a commit hash, a tag or a branch
    * @throws Exception
    * @return string content of the file
    */
   public function getFileFromGit($path, $rev = 'HEAD') {
      $output = null;
      $repoDir = $this->repoDir;
      $output = shell_exec("git -C '$repoDir' show $rev:$path");
      if ($output === null) {
         throw new Exception ("coult not get file from git: $rev:$path");
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
      $output = $retCode = null;
      exec($command, $output, $retCode);
      if ($retCode != 0) {
         throw new Exception('Failed to run git command: $command');
      }
      return $output;
   }
}