<?php

error_reporting(E_ALL);

$local_config = getConfig();
$config = (object)[];

// Soll viel in die Console geschrieben werden oder nicht(false);
setConfigValue('CACKLING', false);

// Bestehende Bilder überschreiben?
setConfigValue('FORCE', false);

// create-images, json-only, create-images
setConfigValue('MODE', 'json-only'); 

// Pfade und so
setConfigValue('BASEPATH_ASSETS','./');
setConfigValue('BASEPATH', './data');
setConfigValue('SOURCE', '/IIPIMAGES');
setConfigValue('TARGET', '/dist');
setConfigValue('JSON_OUTPUT_FN', 'imageData-1.1.json');
setConfigValue('MAGICK_SLICER_PATH', './libs/MagickSlicer-master/magick-slicer.sh');
setConfigValue('MAGICK_COMMAND', 'magick convert');


// Nach welchem Pattern soll gesucht werden?
setConfigValue('PATTERN', '*.tif');

$paths = array();
$paths["watermark"] = $config->BASEPATH_ASSETS . "/assets/watermark-shadow.svg";
$paths["tempFolder"] = $config->BASEPATH_ASSETS . "/tmp";
$paths["watermark-temp"] = $paths["tempFolder"] . "/watermark-tmp.png";
$config->PATHS = $paths;

$dimensions = array();
$dimensions["qualityDefault"] = 100;
$dimensions["imageWidthDefault"] = 2000;
$config->DIMENSIONS = $dimensions;

$recipes = array();
$recipes["xsmall"] = '{ "suffix": "xs",     "width": 200,    "quality": 70, "sharpen": "1.5x1.2+1.0+0.10", "watermark": false, "metadata": true }';
$recipes["small"] =  '{ "suffix": "s",      "width": 400,    "quality": 80, "sharpen": "1.5x1.2+1.0+0.10", "watermark": false, "metadata": true }';
$recipes["medium"] = '{ "suffix": "m",      "width": 600,    "quality": 80, "sharpen": "1.5x1.2+1.0+0.10", "watermark": false, "metadata": true }';
#$recipes["large"] =  '{ "suffix": "l",      "width": 1200,   "quality": 85, "sharpen": false,              "watermark": true,  "metadata": true }';
#$recipes["xlarge"] = '{ "suffix": "xl",     "width": "1800", "quality": 85, "sharpen": false,              "watermark": true,  "metadata": true }';
$recipes["origin"] = '{ "suffix": "origin", "width": "auto", "quality": 95, "sharpen": false,              "watermark": true,  "metadata": true }';
$recipes["tiles"]  = '{ "suffix": "origin", "format": "dzi"}';
$config->RECIPES = $recipes;

$types = array();
$types["overall"] = '{ "fragment":"Overall", "sort": "01" }';
$types["reverse"] = '{ "fragment":"Reverse", "sort": "02" }';
$types["irr"] = '{ "fragment":"IRR", "sort": "03" }';
$types["x-radiograph"] = '{ "fragment":"X-radiograph", "sort": "04" }';
$types["uv-light"] = '{ "fragment":"UV-light", "sort": "05" }';
$types["detail"] = '{ "fragment":"Detail", "sort": "06" }';
$types["photomicrograph"] = '{ "fragment":"Photomicrograph", "sort": "07" }';
$types["conservation"] = '{ "fragment":"Conservation", "sort": "08" }';
$types["other"] = '{ "fragment":"Other", "sort": "09" }';
$types["analysis"] = '{ "fragment":"Analysis", "sort": "10" }';
$types["rkd"] = '{ "fragment":"RKD", "sort": "11" }';
$types["koe"] = '{ "fragment":"KOE", "sort": "12" }';
$types["reflected-light"] = '{ "fragment":"Reflected-light", "sort": "13" }';
$types["transmitted-light"] = '{ "fragment":"Transmitted-light", "sort": "14" }';
$config->TYPES = $types;


/* Functions
############################################################################ */

function getConfig(){
  $config_file = './image-tools.config';
  if(!file_exists($config_file)) return false;
  $config = file_get_contents($config_file);
  return json_decode(trim($config));
}

function setConfigValue($key, $default_value){
  global $config, $local_config;
  $config->$key = isset($local_config->$key) ? $local_config->$key : $default_value;
}

function getTypeSubfolderName($typeName)
{
    global $config;
    $typeDataJSON = json_decode($config->TYPES[$typeName]);
    return $typeDataJSON->sort . "_" . $typeDataJSON->fragment;
}

function getTypeFilenamePattern($typeName)
{
    global $config;
    $typeDataJSON = json_decode($config->TYPES[$typeName], true);
    return (isset($typeDataJSON->fn_pattern)) ? $typeDataJSON->fn_pattern : "";
}


class ImageCollection
{

    public $images = array();

    public function __construct($config)
    {
      $this->config = $config;

        $cmd = "find " . $this->config->SOURCE . " -name '" . $this->config->PATTERN . "' "; // -mtime -120
        exec($cmd, $this->files);
        $this->sortFiles();

        $pattern = "=" . $this->config->SOURCE . "=";
        $this->files = preg_replace($pattern, "", $this->files);

        $assets = array();
        foreach ($this->files as $file) {
            $assets[$this->getBasePath($file)] = 0;
        }

        foreach (array_keys($assets) as $assetBasePath) {
            $res = array("name" => $assetBasePath);

            foreach ($this->config->TYPES as $typeName => $typeData) {
                $typePattern = getTypeSubfolderName($typeName);
                $filenamePattern = getTypeFilenamePattern($typeName);
                $searchPattern = (isset($filenamePattern)) ? $typePattern . "/" . $filenamePattern : $typePattern;
                $typeFiles = preg_grep("=/$assetBasePath/$searchPattern=", $this->files);
                $res["data"][$typeName] = $typeFiles;
            }
            array_push($this->images, $res);
        }
    }

    public function sortFiles(){
      
      $images_with_trailing_chars = preg_grep("=[a-zA-Z]\.tif$=", $this->files);
      $images_with_trailing_numbers = preg_grep("=[0-9]\.tif$=", $this->files);
      
      sort($images_with_trailing_chars);
      sort($images_with_trailing_numbers);
      
      $this->files = array_merge($images_with_trailing_chars, $images_with_trailing_numbers);
    }

    public function getSize()
    {
        return count($this->images);
    }

    private function getBasePath($path)
    {
        return preg_replace("=/(.*?)/.*=", '${1}', $path);
    }
}

class ImageBundle
{
    public function __construct()
    {
        $this->imageStack = [];
    }

    public function addSubStack($type)
    {
        $this->imageStack[$type] = array('maxDimensions' => [], 'images' => []);
    }

    public function flattenRepresentative()
    {
        $this->imageStack["representative"]["images"] = $this->imageStack["representative"]["images"][0];
    }
}

class ImageOperations
{
    public function __construct($config)
    {
      $this->config = $config;
    }

    public function engraveWatermark($target, $targetData, $recipeData)
    {
        if ($this->config->CACKLING) {print "engraveWatermark\n";}
        if ($this->config->CACKLING) {print " target-> $target\n";}

        if ($this->config->MODE === "create-images") {
            $watermark = $this->config->PATHS["watermark"];
            $watermark_temp = $this->config->PATHS["watermark-temp"];
            $watermark_size = $targetData["dimensions"]["width"] * 0.2;

            if ($this->config->CACKLING) {print " watermark -> $tempWatermark\n";}
            $cmd = $this->config->MAGICK_COMMAND . " convert -background transparent -resize $watermark_size $watermark $watermark_temp && ".$this->config->MAGICK_COMMAND." composite -compose difference -tile -blend 15 " . $watermark_temp . " " . $target . " " . $target;

            shell_exec($cmd);

        }
        return $target;
    }

    public function generateTiles( $recipeData, $targetData){

      $source = $this->config->TARGET . $targetData['path'] . '/'. $targetData['basefilename'] .'-' . $recipeData->suffix . ".jpg";
      $target = $this->config->TARGET . $targetData['path'] . '/'. $targetData['basefilename'];
      $dzi = $target . '.dzi';

      if(file_exists($target . '.dzi')){
        echo "Skip ". $target . '.dzi' . " already exists.\n";
        return;
      }
      $cmd = 'vips dzsave ' . $source .' ' . $target .' --suffix .jpg[Q=95]';
      shell_exec($cmd);
    }

    public function manageTargetPath($image, $recipeTitle, $suffix = false, $typeName)
    {
        $target = $this->config->TARGET . $image;
        $targetPath = $this->getDirectoryFromPath($target);
        $targetFile = $this->getFilenameFromPath($target);
        $typeFolder = getTypeSubfolderName($typeName);

        if ($suffix !== false) {
            preg_match("=(.*?)\.(.*)=", $targetFile, $res);
            $targetFile = $res[1] . "-" . $suffix . "." . $res[2];
        }

        $targetPath = $this->config->BASEPATH . $this->checkPathSegements($targetPath);
        $pattern = '/'.$typeFolder.'\//';
        $targetPath = (preg_match($pattern, $targetPath)) ? $targetPath : $targetPath . $typeFolder;
        if (!is_dir($targetPath)) {mkdir($targetPath);}

        return $targetPath . "/" . $targetFile;
    }

    public function processImage($image, $recipeTitle, $recipeData, &$imageBundle, $typeName)
    {
        if ($this->config->CACKLING) {print "processImage: $image\n";}

        $source = preg_quote($this->config->SOURCE . $image);
        $target = $this->manageTargetPath($image, $recipeTitle, $recipeData->suffix, $typeName);

        if (!preg_match("=\.jpg$=", $target)) {
            $target = preg_replace("=\.tif$=", ".jpg", $target);
        }

        if($recipeTitle === "tiles"){
          $tileSource = $image;
          preg_match("=(.*)\/(.*)\.=", $tileSource, $res);
          $path = $res[1];
          $basefilename = $res[2];
          $targetData['path'] = $path;
          $targetData['basefilename'] = $basefilename;
          $targetData['src'] = $basefilename . '.dzi';

          $this->generateTiles($recipeData, $targetData);
          
        }else{
          $targetData = $this->resizeImage($source, $target, $recipeData, $imageBundle);
          $watermark = $recipeData->watermark;
          if ($watermark !== false) {
            $this->engraveWatermark($target, $targetData, $recipeData);
          }
        }

        return $targetData;

    }

    public function getDimensions($src)
    {
        $cmd = "identify -quiet $src";
        $ret = explode(" ", shell_exec($cmd));
        list($width, $height) = explode("x", $ret[2]);
        return array('width' => $width, 'height' => $height);
    }

    public function resizeImage($source, $target, $data, &$imageBundle)
    {

        $sharpen = (isset($data->sharpen)) ? $data->sharpen : false;
        $quality = (isset($data->quality)) ? $data->quality : $this->config->DIMENSIONS["qualityDefault"];
        $width = (isset($data->width)) ? $data->width : $this->config->DIMENSIONS["imageWidthDefault"];
        $height = (isset($data->height)) ? $data->height : $this->config->DIMENSIONS["imageWidthDefault"];
        $metadata = (isset($data->metadata)) ? $data->metadata : false;
        $imageBundle["maxDimensions"] = $this->getDimensions($source);

        $source .= "[0]";
        if ($this->config->MODE === "create-images") {

            $handleMetadata = ($metadata === false) ? "+profile iptc,8bim" : "";
            $sharpen = ($sharpen !== false) ? "-unsharp $sharpen" : "";
            $resize = ($width == "auto") ? "" : " -resize " . $width . "x" . $height;
            $cmd = "convert -interlace plane -quiet $handleMetadata -strip -quality $quality " . $resize . " $sharpen $source $target";

            shell_exec($cmd);
        }

        preg_match("=.*/(.*?)$=", $target, $res);
        $fn = $res[1];
        return array('dimensions' => $this->getDimensions($target), 'src' => $fn);
    }

    public function getType($image)
    {
        preg_match("=.*/(.*?)/.*?$=", $image, $res);
        return $res[1];
    }

    private function getBasePath($path)
    {
        return preg_replace("=/(.*?)/.*=", '${1}', $path);
    }

    private function getDirectoryFromPath($path)
    {
        preg_match("=(.*)/=", $path, $targetPath);
        return $targetPath[1];
    }

    private function getFilenameFromPath($path)
    {
        return preg_replace("=.*/=", "", $path);
    }

    public function createDirectory($targetPath)
    {
        $targetPath = $this->config->BASEPATH . $targetPath;
        mkdir($targetPath, 0775);
    }

    public function checkPathSegements($path)
    {
        $pattern = "=" . $this->config->BASEPATH . "=";
        $workingPath = preg_replace($pattern, "", $path);
        $pathSegments = explode("/", $workingPath);

        /* Gunnar hat in der Verzeichnisstruktur immer noch das Haus. Das brauchen wir aber nicht. */
        unset($pathSegments[3]);
        $targetPath = "/" . array_shift($pathSegments);
        foreach ($pathSegments as $segment) {
            if ($segment !== "pyramid") {
                $targetPath .= "$segment/";
            }

            if (!file_exists($this->config->BASEPATH . $targetPath)) {
                $this->createDirectory($targetPath);
            }
        }

        return $targetPath;
    }

}

function convertImages($imageCollection, $imageOperations, $config)
{
    $stackSize = $imageCollection->getSize();
    $count = 0;

    foreach ($imageCollection->images as $asset) {

        $assetName = $asset["name"];
        $assetData = $asset["data"];
        $count++;

        print "\nAsset $count from $stackSize // $assetName:";
        $imageBundle = new ImageBundle;
        $jsonPath = $config->TARGET . "/$assetName/" . $config->JSON_OUTPUT_FN;

        if (file_exists($jsonPath) && !$config->FORCE ) {
            print "… already exists :)";
            continue;
        }

        foreach ($config->TYPES as $typeName => $typeData) {
            $imageBundle->addSubStack($typeName);
                        
            foreach ($assetData[$typeName] as $image) {            
                $assetImages = array();
                $recipeTitles = array_keys($config->RECIPES);
                sort($recipeTitles);
                foreach ($recipeTitles as $recipeTitle) {
                    print ".";
                    $recipe = $config->RECIPES[$recipeTitle];
                    $recipeData = json_decode($recipe);
                    $typeFolder = getTypeSubfolderName($typeName);
                    $imageData = $imageOperations->processImage($image, $recipeTitle, $recipeData, $imageBundle->imageStack[$typeName], $typeName);
                    if($imageData === "skip"){
                      print "Skip $image\n"; continue;
                    }
                    if($recipeTitle === "tiles"){
                      $assetImages[$recipeTitle] = array('type'=>'dzi', 'src' => $imageData["src"], 'path' => $typeFolder);
                    }else{
                      $assetImages[$recipeTitle] = array('dimensions' => $imageData["dimensions"], 'src' => $imageData["src"], 'path' => $typeFolder);
                    }
                }

                // print "\n-------------\n";
                // print "Type: $typeName"
                array_push($imageBundle->imageStack[$typeName]["images"], $assetImages);

            }
        }
        // $imageBundle->flattenRepresentative();
        file_put_contents($jsonPath, json_encode($imageBundle));
        print "written $jsonPath\n";
    }
}


/* Main
############################################################################ */

$imageCollection = new ImageCollection($config);
$imageOperations = new ImageOperations($config);

convertImages($imageCollection, $imageOperations, $config);
