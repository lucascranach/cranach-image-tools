<?php

class JsonOperations
{
    public function __construct($config, $params)
    {
        $this->config = $config;
        $this->sourceBasePath = $params['sourceBasePath'];
        $this->source = $params['source'];
        $this->targetBasePath = $params['targetBasePath'];
        $this->target = $params['target'];
        $this->pattern = $params['pattern'];
        $this->period = $params['period'];
        $this->images = [];
        $this->rkdImages = [];
    }
    
    public function createJSONS(){
      $this->getImageVariants();
      $artefactIds = $this->stripArtefactIds();
      foreach($artefactIds as $artefactId){
        $artefactImages = $this->getImagesForArtefact($artefactId);
        $artefactImagesByType = $this->getArtefactImagesByType($artefactImages);
        $imageStack = $this->createImageStack($artefactImagesByType, $artefactImages);
        $this->writeJson($artefactId, $imageStack);
      }
    }

    private function getImageDimensions($imagePath){
      print ".";
      $path = $this->config->LOCALCONFIG->targetPath . "/" . $imagePath;
      $cmd = "identify -quiet $path";
      $ret = explode(" ", shell_exec($cmd));
      list($width, $height) = explode("x", $ret[2]);
      return array('width' => $width, 'height' => $height);
    }

    private function getFragment($preSegment){
      $rkdFragment = $this->config->MISC["rkdFragment"];
      $koeFragment = $this->config->MISC["koeFragment"];
      $fragment = preg_match("=rkd=i", $preSegment) ? $rkdFragment : $koeFragment;
      return $fragment;
    }

    private function getSizeVariantsForImagesOfType($images, $artefactImages){

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
          $suffix = isset($sizeVariantPropterties->type) ? '.' . $sizeVariantPropterties->type : '-'.$sizeVariantPropterties->suffix;
          $searchPattern = "=" . $basename . $suffix . "=";
          $imagesForBasenameAndSizeVariant = preg_grep($searchPattern,$artefactImages);
          $imageForBasenameAndSizeVariant = array_shift($imagesForBasenameAndSizeVariant);

          preg_match("=(.*)/(.*?)/(.*)=", $imageForBasenameAndSizeVariant, $res);
          $preSegment = $res[1];
          $segment = $res[2];

          $data[$sizeVariantName]["src"] = $res[3];
          $data[$sizeVariantName]["path"] = preg_match("=rkd|koe=i", $preSegment) 
            ? $this->getFragment($preSegment) . "/" . $segment : $segment;
          $data[$sizeVariantName]["type"] = isset($sizeVariantPropterties->type) ? $sizeVariantPropterties->type : 'img';
          
          if($data[$sizeVariantName]["type"] === 'dzi'){
            $data[$sizeVariantName]["src"] = preg_replace("=\-dzi\.jpg=", ".dzi", $data[$sizeVariantName]["src"] );
          }
          if($data[$sizeVariantName]["type"] !== 'img') continue;
          $data[$sizeVariantName]["dimensions"] = $this->getImageDimensions($imageForBasenameAndSizeVariant);
          $maxWidth = $data[$sizeVariantName]["dimensions"]["width"] > $maxWidth 
            ? $data[$sizeVariantName]["dimensions"]["width"]
            : $maxWidth;
          $maxHeight = $data[$sizeVariantName]["dimensions"]["height"] > $maxHeight 
            ? $data[$sizeVariantName]["dimensions"]["height"]
            : $maxHeight;

          
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

    private function createImageStack($imagesByType, $artefactImages){
      
      $imageStack = [];

      foreach($imagesByType as $typeName=>$images){
        $imageStack[$typeName] = $this->getSizeVariantsForImagesOfType($images, $artefactImages);
      }

      return ['imageStack'=>$imageStack];
    }

    private function removeSelectedItems($pattern, $artefactImages){
      return preg_grep("=". $pattern ."=", $artefactImages, PREG_GREP_INVERT);
    }

    private function writeJson($artefactFolder, $artefactData){
      $target = $this->targetBasePath . "/" . $artefactFolder . "/" . $this->config->MISC["json-filename"];

      file_put_contents($target, json_encode($artefactData));
      chmod($target, 0755);
      print "schreibe $target\n";
    }

    private function getImagesForArtefact($artefactId){
      $pattern = "=/" . $artefactId . "_=";
      $imagesWithFullPath = preg_grep($pattern, $this->images);

      $pattern = "=" .  $this->sourceBasePath . "/=";
      $imagesWithRelativePath = preg_replace($pattern, "", $imagesWithFullPath);
      
      return $imagesWithRelativePath;
    }

    private function stripArtefactIds(){
      $pattern = "=". $this->sourceBasePath ."/=";
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

    private function getImagesForOrigin( $images, $params){
      $imagesForOrigin = preg_grep("=\-origin\.=", $images);   
      $sortedImagesForOrigin = $this->sortImages($imagesForOrigin, $params->fragment);
      return $sortedImagesForOrigin;
    }

    private function sortImages($imagesForVariant, $limiter){

      $pattern = "=.*_".$limiter.".=";
      $sortHelper = [];
      $sortedImages = [];

      foreach($imagesForVariant as $image){
        $imageSortFragment = preg_replace($pattern, "", $image);
        $filename = preg_replace("=.*/=", "", $image);
        if(preg_match("=rkd=i", $image)){ $imageSortFragment = "02-rkd-$imageSortFragment"; }
        if(preg_match("=koe=i", $image)){ $imageSortFragment = "01-koe-$imageSortFragment"; }
        if(!preg_match("=$limiter=", $filename)){ $imageSortFragment = "noType--$imageSortFragment"; }
        $imageSortFragment = strtolower($imageSortFragment);
        $sortHelper[$imageSortFragment] = $image;
      }

      $sortFragments = array_keys($sortHelper);
      $sortFramentsWithoutType = preg_grep("=notype=", $sortFragments);
      $sortFramentsWithType = array_diff($sortFragments, $sortFramentsWithoutType);

      $sortFramentsWithTrailingChars = preg_grep("=^[a-zA-Z]=", $sortFramentsWithType);
      $sortFramentsWithTrailingNumbers = preg_grep("=^[0-9]=", $sortFramentsWithType);
      
      sort($sortFramentsWithTrailingChars);
      sort($sortFramentsWithTrailingNumbers);
      
      foreach($sortFramentsWithTrailingChars as $sortFragment){
        array_push($sortedImages, $sortHelper[$sortFragment]);
      }

      foreach($sortFramentsWithTrailingNumbers as $sortFragment){
        array_push($sortedImages, $sortHelper[$sortFragment]);
      }

      foreach($sortFramentsWithoutType as $sortFragment){
        array_push($sortedImages, $sortHelper[$sortFragment]);
      }
      
      return $sortedImages;
    }

    private function getArtefactImagesByType($artefactImages){
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

    private function getImageVariants(){

      $patterns = explode("|", $this->pattern);

      foreach($patterns as $pattern){
        $cmd = "find " . $this->source . " \( -not -path '*_files/*' -and -name '" . $pattern . "' \) ";
        $files = [];
        exec($cmd, $this->images);   
      }
    }
}