<?php

$config = json_decode(file_get_contents('./image-tools.config', false));

class ImageBrowser
{
    public function __construct($config)
    {
      $this->config = $config;
      $this->cache = $this->config->CACHEDIR;
      $this->level = 0;
      $this->from = 0;
      $this->size = $this->config->SIZE;
      $this->path = $this->getPath();
      $this->cleanPath = $this->getPath("clean");
      $this->subDirs = $this->getSubDirs();
      $this->images = $this->getFiles($this->config->SEARCH_PATTERNS->IMAGES);
      $this->jsons = $this->getFiles($this->config->SEARCH_PATTERNS->JSONS);
      $this->dzi = $this->getFiles($this->config->SEARCH_PATTERNS->DZI);
    }

    private function getCachedFile($id){
      $fn = $this->cache.'/'.$id;
      return file_exists($fn) ? json_decode(file_get_contents($fn)) : false;
    }

    private function cacheFile($id, $data){
      $fn = $this->cache.'/'.$id;
      file_put_contents($fn, json_encode($data));
    }

    private function getSubDirs(){
      $cmd = "find " . $this->path . " -maxdepth 1 -type d "; // -mtime -120

      $id = md5($cmd);
      if($cached = $this->getCachedFile($id)) return $cached;

      exec($cmd, $dirs);
      sort($dirs);
      array_shift($dirs);
      $dirs = array_slice($dirs, $this->from, $this->size);
      $ret = array();
      foreach($dirs as $dir){
        $dir_clean = $this->cleanPath($dir);
        if(preg_match("=_files$=", $dir)) continue;
        array_push($ret, ['src' => $dir_clean, 'name' => basename($dir), 'type'=> 'folder']);
      }

      $this->cacheFile($id, $ret);
      return $ret;
    }

    private function cleanPath($path){
      $pattern = $this->config->SERVER_ROOT;
      return preg_replace('='.$pattern.'/=', "", $path);
    }

    private function getPath($mode = false){

      $path = isset($_GET["path"]) ? $_GET["path"] : false;
      if(!$path) return $this->config->SERVER_ROOT;
      if(preg_match("=\.\.\/|\.\/| |[^a-zA-Z0-9_\.\-\/]=", $path)){
        $this->level = 0;
        return $this->config->SERVER_ROOT;
      }
      $this->level = $this->getLevel($path);
      if($mode === "clean") return $path;
      return $this->config->SERVER_ROOT.'/'.$path;
    }

    private function getType($fn){
      switch (true) {
        case 1 === (preg_match("=s\.jpg|m\.jpg=", $fn)):
          return 'image';
          case 1 === (preg_match("=\.json=", $fn)):
            return 'json';
            case 1 === (preg_match("=\.dzi=", $fn)):
              return 'dzi';
        default:
          return 'zoomableImage';
      }
    }

    private function getFiles($pattern){
      $cmd = "find " . $this->path . " -maxdepth 1 -name '".$pattern."'  -type f"; // -mtime -120
      exec($cmd, $files);
      sort($files);
      $ret = array();
      foreach($files as $file){
        $fn_clean = $this->cleanPath($file);
        $type = $this->getType($fn_clean);
        array_push($ret, ['src' => $fn_clean, 'name' => basename($file), 'type'=> $type]);
      }
      return $ret;
    }

    private function getPrevLevel($path){
      if($this->level === 0) return false;
      $segments = explode("/", $path);
      array_pop($segments);
      return implode("/", $segments);
    }

    private function getLevel($path){
      $segments = explode("/", $path);
      return sizeof($segments);
    }

    private function getMeta(){
      $ret = array();
      $ret['images'] = sizeof($this->images);
      $ret['folder'] = sizeof($this->subDirs);
      $ret['jsons'] = sizeof($this->jsons);
      $ret['dzi'] = sizeof($this->dzi);
      $ret['from'] = $this->from;
      $ret['size'] = $this->size;
      $ret['level'] = $this->level;
      
      return $ret;
    }

    public function responseData(){
      $res = array();
      $res["path"] = $this->cleanPath;
      $res['prevLevel'] = $this->getPrevLevel($this->cleanPath);
      $res["images"] = $this->images;
      $res["folder"] = $this->subDirs;
      $res["jsons"] = $this->jsons;
      $res["dzi"] = $this->dzi;
      $res["meta"] = $this->getMeta();
      header('Content-Type: application/json');
      print json_encode($res); 
      exit;
    }

    private function mapIPTCData($iptc){
      if (is_array($iptc)) {
        $data = array();
        $data['caption']              = $iptc["2#120"][0];
        $data['graphic_name']         = $iptc["2#005"][0];
        $data['urgency']              = $iptc["2#010"][0];
        $data['category']             = $iptc["2#015"][0];
        $data['supp_categories']      = $iptc["2#020"][0];
        $data['spec_instr']           = $iptc["2#040"][0];
        $data['creation_date']        = $iptc["2#055"][0];
        $data['photog']               = $iptc["2#080"][0];
        $data['credit_byline_title']  = $iptc["2#085"][0];
        $data['city']                 = $iptc["2#090"][0];
        $data['state']                = $iptc["2#095"][0];
        $data['country']              = $iptc["2#101"][0];
        $data['otr']                  = $iptc["2#103"][0];
        $data['headline']             = $iptc["2#105"][0];
        $data['source']               = $iptc["2#110"][0];
        $data['photo_source']         = $iptc["2#115"][0];
        $data['keywords']             = $iptc["2#025"][0];
        return $data;
      }else{
        return false;
      }
    }

    public function respondITPC(){
      $size = getimagesize($this->path, $info);
      $iptc = iptcparse($info['APP13']);
      $res = array();
      $res["size"] = $size;
      $res["info"] = $info;
      $res["iptc"] = $this->mapIPTCData($iptc);
      header('Content-Type: application/json');
      print json_encode($res); 
      exit;
    }
}

$browser = new ImageBrowser($config);
if(preg_match("=jpg$=", $browser->cleanPath)){
  $browser->respondITPC();
  exit;
}
$browser->responseData();