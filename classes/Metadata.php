<?php

class Metadata
{
    public function __construct($config, $params)
    {
        $this->config = $config;
        $this->source = $params['source'];
        $this->target = $params['target'];
        $this->pattern = $params['pattern'];
        $this->images = $this->getImages();

    }

    public function extractMetadata(){
      
      $size = sizeof($this->images);
      $count = 0;
      $errorLog = [];

      foreach($this->images as $image){
        $count++;

        print "$count/$size $image\n";
        
        $cmd = "exiftool $image";
        exec($cmd, $data);
        $metadata = $this->stripData($data);
        list($metaDataObject, $checksum) = $this->createMetaDataObject($metadata);
        
        if(strlen($checksum) === 0){
          print "Keine Metadaten für $image\n";
          array_push($errorLog, $image);
          continue;
        } 

        
        $artefactId = $this->extractArtefactId($image);
        $filename = $this->extractFilename($image);
        $this->createMetadataJson($artefactId, $filename, $metaDataObject);
      }

      file_put_contents("metadata-log.txt", join("\n", $errorLog));
    }

    private function createMetadataJson($artefactId, $filename, $metaDataObject){

      $filenameWithoutSuffix = preg_replace("=\.tif=", "", $filename);
      $target = $this->target . "/" . $artefactId . "/". $filenameWithoutSuffix . "-" . $this->config->MISC["metadata-filename"];
      createRecursiveFolder($target);
      file_put_contents($target, json_encode($metaDataObject));
      chmod($target, 0755);
    }

    private function createMetaDataObject($metadata){

      $metaDataObject = [];
      $log = [];

      $params = [
        "sourceFields" => ["xp-comment"],
        "data" => $metadata
      ];
      list($metaDataObject['image-description-de'], $metaDataObject['image-description-en']) = $this->addToMetaDataObject($params);
      array_push($log, $metaDataObject['image-description-de']); 
      
      $params = [
        "sourceFields" => ["xp-subject"],
        "data" => $metadata
      ];
      list($metaDataObject['file-type-de'], $metaDataObject['file-type-en']) = $this->addToMetaDataObject($params);
      array_push($log, $metaDataObject['file-type-de']); 

      /* Artist */
      $params = [
        "sourceFields" => ["artist"],
        "data" => $metadata
      ];
      list($metaDataObject['image-created-de'], $metaDataObject['image-created-en']) = $this->addToMetaDataObject($params);
      array_push($log, $metaDataObject['image-created-de']); 

      $params = [
        "sourceFields" => ["copyright-notice"],
        "data" => $metadata
      ];
      list($metaDataObject['image-source-de'], $metaDataObject['image-source-en']) = $this->addToMetaDataObject($params);
      array_push($log, $metaDataObject['image-source-de']); 

      $params = [
        "sourceFields" => ["date-created"],
        "data" => $metadata
      ];
      list($metaDataObject['image-date-de'], $metaDataObject['image-date-en']) = $this->addToMetaDataObject($params);
      $metaDataObject['image-date-de'] = str_replace(":", "-", $metaDataObject['image-date-de']);
      $metaDataObject['image-date-en'] = str_replace(":", "-", $metaDataObject['image-date-en']);
      array_push($log, $metaDataObject['image-date-de']); 

      return [$metaDataObject, join("", $log)];
    }

    private function getFieldContent($params){
      $sourceFields = $params["sourceFields"];
      $data = $params["data"];
      foreach($sourceFields as $sourceField){
        if(isset($data[$sourceField])) return $data[$sourceField];
      }
    }

    private function addToMetaDataObject($params){
      $content = $this->getFieldContent($params);
      if(!$content) return ['',''];
      if(!preg_match("=#=", $content)) return [trim($content), trim($content)];
      
      list($de, $en) = explode("#", $content);
      return [trim($de), trim($en)];
    }

    private function extractArtefactId($image){
      $path = preg_replace("=". $this->source ."/=", "", $image);
      $artefactId = preg_replace("=/.*?$=", "", $path);
      return $artefactId;
    }

    private function extractFilename($image){  
      preg_match("=.*/(.*)=", $image, $res);
      $filename = $res[1];
      return $filename;
    }

    private function slugify($string){
      return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string), '-'));
    }

    private function stripData($data){
      $structuredData = [];
      foreach($data as $line){
        preg_match("=(.*?)\:(.*)=", $line, $res);
        $key = $this->slugify($res[1]);
        $value = trim($res[2], " ");
        $structuredData[$key] = $value;
      }
      return $structuredData;
    }
    
    private function getImages(){
      $cmd = "find " . $this->source . " -type f -name '" . $this->pattern . "'";
      exec($cmd, $images);
      return $images;
    }
}