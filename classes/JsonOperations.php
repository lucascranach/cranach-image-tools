<?php

class JsonOperations
{
    public function __construct(Object $config, Array $params)
    {
        $this->config = $config;
        $this->source = $params['source'];
        $this->target = $params['target'];
        $this->pattern = $params['pattern'];
        $this->period = $params['period'];
        $this->images = [];
        $this->rkdImages = [];
    }
    
    public function createJSONS():void{
      $this->getImageVariants();
      $artefactIds = $this->stripArtefactIds();

      foreach($artefactIds as $artefactId){
        $artefactImages = $this->getImagesForArtefact($artefactId);
        $imagesWithoutRkd = $this->removeSelectedItems("_RKD", $artefactImages);
        $artefactImagesByType = $this->getArtefactImagesByType($imagesWithoutRkd);
        $imageStack = $this->createImageStack($artefactImagesByType, $artefactImages);
        $this->writeJson($artefactId, $imageStack);
      }

      // $this->config->TYPES['rkd'])
      // var_dump($this->config->TYPES['rkd']); exit;
      // exec($cmd, $this->images);
      // var_dump($this->imageBundle);
    }

    private function getImageDimensions(String $imagePath):Array{
      var_dump($imagePath);
      $path = $this->config->LOCALCONFIG->targetPath . "/" . $imagePath;
      $cmd = "identify -quiet $path";
      $ret = explode(" ", shell_exec($cmd));
      list($width, $height) = explode("x", $ret[2]);
      return array('width' => $width, 'height' => $height);
    }

    private function getSizeVariantsForImagesOfType(Array $images, Array $artefactImages):Array{

      $sizeVariants = $this->config->SIZES;
      $sizeVariantsForImagesOfType = [];
      $maxWidth = 0;
      $maxHeight = 0;

      foreach($images as $image){
        preg_match("=.*/(.*)\-=", $image, $res);
        if(!$res[1]) continue;

        $basename = $res[1];
        $data = array();


        foreach($sizeVariants as $sizeVariantName=>$sizeVariantProptertyString){
          $data[$sizeVariantName] = array();
          $data[$sizeVariantName]= array();

          $sizeVariantPropterties = json_decode($sizeVariantProptertyString);
          $searchPattern = "=" . $basename . "-" . $sizeVariantPropterties->suffix . "=";
          $imagesForBasenameAndSizeVariant = preg_grep($searchPattern,$artefactImages);
          $imageForBasenameAndSizeVariant = array_shift($imagesForBasenameAndSizeVariant);

          $data[$sizeVariantName]["dimensions"] = $this->getImageDimensions($imageForBasenameAndSizeVariant);
          $maxWidth = $data[$sizeVariantName]["dimensions"]["width"] > $maxWidth 
            ? $data[$sizeVariantName]["dimensions"]["width"]
            : $maxWidth;
          $maxHeight = $data[$sizeVariantName]["dimensions"]["height"] > $maxHeight 
            ? $data[$sizeVariantName]["dimensions"]["height"]
            : $maxHeight;

          preg_match("=.*/(.*?)/(.*)=", $imageForBasenameAndSizeVariant, $res);
          $data[$sizeVariantName]["path"] = $res[1];
          $data[$sizeVariantName]["src"] = $res[2];
        } 

        array_push($sizeVariantsForImagesOfType, $data);
      }

      $maxDimensions = array(
        "width" => $maxWidth,
        "height" => $maxHeight,
      );

      return array(
        "maxDimensions" => $maxDimensions,
        "images" => $sizeVariantsForImagesOfType
      );
    }

    private function createImageStack(Array $imagesByType, Array $artefactImages):Array{
      
      $imageStack = [];

      foreach($imagesByType as $typeName=>$images){
        $imageStack[$typeName] = $this->getSizeVariantsForImagesOfType($images, $artefactImages);
      }

      return $imageStack;
    }

    private function removeSelectedItems(String $pattern, Array $artefactImages):Array{
      return preg_grep("=". $pattern ."=", $artefactImages, PREG_GREP_INVERT);
    }

    private function writeJson(String $artefactFolder, Array $artefactData):void{
      $target = $this->target . "/" . $artefactFolder . "/" . $this->config->MISC["json-filename"];
      file_put_contents($target, json_encode($artefactData));
      print "schreibe $target\n";
    }

    private function getImagesForArtefact(String $artefactId):array{
      $pattern = "=" . $artefactId . "=";
      $imagesWithFullPath = preg_grep($pattern, $this->images);

      $pattern = "=" .  $this->source . "/=";
      $imagesWithRelativePath = preg_replace($pattern, "", $imagesWithFullPath);

      return $imagesWithRelativePath;
    }

    private function stripArtefactIds():array{
      $pattern = "=". $this->source ."/=";
      $artefactIds = [];

      foreach($this->images as $imagePath){
        $imagePathWithoutBasePath = preg_replace($pattern, "", $imagePath);
        $segments = explode("/", $imagePathWithoutBasePath);
        $artefactId = $segments[0];

        if(!in_array($artefactId, $artefactIds)){
          array_push($artefactIds, $artefactId);
        }
      }

      return $artefactIds;
    }

    private function getImagesForOrigin(Array $images, Object $params):Array{

      $imagesForOrigin = preg_grep("=\-origin\.=", $images);
      $sortedImagesForOrigin = $this->sortImages($imagesForOrigin, $params->fragment);

      return $sortedImagesForOrigin;
    }

    private function sortImages(Array $imagesForVariant, String $limiter):Array{

      $pattern = "=.*_".$limiter."\-=";
      $sortHelper = [];
      $sortedImages = [];

      foreach($imagesForVariant as $image){
        $imageSortFragment = preg_replace($pattern, "", $image);
        $sortHelper[$imageSortFragment] = $image;
      }

      $sortFragments = array_keys($sortHelper);
      $sortFramentsWithTrailingChars = preg_grep("=^[a-zA-Z]=", $sortFragments);
      $sortFramentsWithTrailingNumbers = preg_grep("=^[0-9]=", $sortFragments);

      sort($sortFramentsWithTrailingChars);
      sort($sortFramentsWithTrailingNumbers);

      foreach($sortFramentsWithTrailingChars as $sortFragment){
        array_push($sortedImages, $sortHelper[$sortFragment]);
      }

      foreach($sortFramentsWithTrailingNumbers as $sortFragment){
        array_push($sortedImages, $sortHelper[$sortFragment]);
      }
      
      return $sortedImages;
    }

    private function getArtefactImagesByType(Array $artefactImages):array{
      $imageStack = [];

      foreach($this->config->TYPES as $typeName=>$typeData){
        $params = json_decode($typeData);
        $pattern = "=" . $params->sort . "_" . $params->fragment . "=";
        $imagesForType = preg_grep($pattern, $artefactImages);
        $originImagesForType= $this->getImagesForOrigin($imagesForType, $params);

        if(sizeof($originImagesForType) === 0) continue;
        $imageStack[$typeName] = $originImagesForType;

      }

      return $imageStack;
    }

    private function getImageVariants():void{

      $patterns = explode("|", $this->pattern);

      foreach($patterns as $pattern){
        $cmd = "find " . $this->source . " -name '" . $pattern . "' ";
        $files = [];
        exec($cmd, $files);
    
        foreach($files as $file){ 
          switch(true){
            case preg_match("=RKD=", $file):
              array_push($this->rkdImages, $file);
            default:
            array_push($this->images, $file);
          }
        }    
      }
    }
}