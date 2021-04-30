<?php

$config = json_decode(file_get_contents('./image-tools.config', false));

$currentDir = $config->SERVER_ROOT;


class ImageBrowser
{

    public $response = array("asas");

    public function __construct($config)
    {
      $this->config = $config;
      $this->path = $this->getPath();
      $this->subDirs = $this->getSubDirs();
      $this->images = $this->getFiles($this->config->SEARCH_PATTERNS->IMAGES);
      $this->jsons = $this->getFiles($this->config->SEARCH_PATTERNS->JSONS);
    }

    private function getSubDirs(){
      $cmd = "find " . $this->path . " -type d -maxdepth 1"; // -mtime -120
      exec($cmd, $dirs);
      $ret = array();
      foreach($dirs as $dir){
        $ret[$dir] = basename($dir);
      }
      return $ret;
    }

    private function getPath(){

      $path = $_GET["path"];
      if(!$path) return $this->config->SERVER_ROOT;
      if(preg_match("=\.\.\/|\.\/| |[^a-zA-Z0-9_\.\-]=", $path)){
        return $this->config->SERVER_ROOT;
      }
      return $this->config->SERVER_ROOT.'/'.$_GET["path"];
    }

    private function getFiles($pattern){
      $cmd = "find " . $this->path . " -type f -name '".$pattern."' -maxdepth 1"; // -mtime -120
      exec($cmd, $files);
      $ret = array();
      foreach($files as $file){
        $ret[$file] = basename($file);
      }
      return $ret;
    }

    public function responseData(){
      print json_encode($this); 
      exit;
    }
}

$browser = new ImageBrowser($config);
$browser->responseData();