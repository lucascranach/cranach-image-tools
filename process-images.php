<?php

// find . -name "*.jpg" -exec rename 's/\.tif-0//' '{}' ';'

define("BASEPATH_ASSETS", "/Users/cnoss/git/lucascranach/image-tools");
define("BASEPATH", "/Volumes/cranach-data");
//define("SOURCE", BASEPATH . "/images/src");
define("SOURCE", BASEPATH."/IIPIMAGES");
//define("TARGET", BASEPATH . "/images/dist");
define("TARGET", BASEPATH."/dist");

//define("SOURCE", "/Volumes/cn-extern-lacie-4tb/cranach/webserver/home/mkpacc/IIPIMAGES");
//define("TARGET", "/Volumes/cn-extern-lacie-4tb/cranach/jpgs");
define("PATTERN", "*.tif");

$paths = array();
$paths["watermarkSingle"] = BASEPATH_ASSETS . "/assets/watermark.png";
$paths["watermarkImage"] = BASEPATH_ASSETS . "/assets/stichedWatermark.png";
$paths["tempFolder"] = BASEPATH_ASSETS . "/tmp";
define("PATHS", $paths);

$dimensions = array();
$dimensions["imageWidth"] = 5600;
$dimensions["numberOfWatermarks"] = 20;
$dimensions["qualityDefault"] = 100;
define("DIMENSIONS", $dimensions);

$recipes = array();
$recipes["large"] = '{ "suffix": "l",  "width": 1200, "quality": 75, "sharpen": false,               "watermark": true,  "metadata": true }';
$recipes["xsmall"] = '{ "suffix": "xs", "width": 200,  "quality": 70, "sharpen": "1.5x1.2+1.0+0.10", "watermark": false, "metadata": false }';
$recipes["small"] = '{ "suffix": "s",  "width": 300,  "quality": 95, "sharpen": "1.5x1.2+1.0+0.10",  "watermark": false, "metadata": false }';
$recipes["medium"] = '{ "suffix": "m",  "width": 800,  "quality": 90, "sharpen": "1.5x1.2+1.0+0.10", "watermark": false, "metadata": true }';
$recipes["xlarge"] = '{ "suffix": "xl", "width": "auto", "quality": 75, "sharpen": false,              "watermark": true,  "metadata": true }';
define("RECIPES", $recipes);

class ImageCollection
{

    public $images = array();

    public function __construct()
    {
        $cmd = "find " . SOURCE . " -name '" . PATTERN . "'";

        exec($cmd, $files);
        print "$cmd";
        foreach ($files as $file) {
            $pattern = "=" . SOURCE . "=";
            $fn = preg_replace($pattern, "", $file);
            array_push($this->images, $fn);
        }
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
}

class ImageOperations
{
    public function __construct()
    {

        if (!file_exists(PATHS["watermarkImage"])) {
            $this->stitchWatermark();
        }
    }

    private function stitchWatermark()
    {

        print "stitchWatermark: ";

        /* Create empty image as background layer */
        $stichedImage = PATHS["watermarkImage"];
        $cmd = "convert  -layers flatten -size " . DIMENSIONS["imageWidth"] . "x" . DIMENSIONS["imageWidth"] . " xc:transparent " . $stichedImage;
        shell_exec($cmd);

        /* Resize watermark */
        $tempWatermark = PATHS["tempFolder"] . "/tempWatermark.png";
        $size = DIMENSIONS["imageWidth"] / DIMENSIONS["numberOfWatermarks"];
        $cmd = "convert  -layers flatten -resize $size " . PATHS["watermarkSingle"] . " " . $tempWatermark;
        shell_exec($cmd);

        /* Stich watermarks */
        $steps = array();
        $steps["x"] = DIMENSIONS["imageWidth"] / DIMENSIONS["numberOfWatermarks"];
        $steps["y"] = $steps["x"];
        $iterations = DIMENSIONS["numberOfWatermarks"] * $steps["x"];

        for ($x = 0; $x < $iterations; $x += $steps["x"]) {
            for ($y = 0; $y < $iterations; $y += $steps["y"]) {
                $cmd = "composite -compose screen -gravity NorthWest -geometry +" . $x . "+" . $y . " " . $tempWatermark . " " . $stichedImage . " " . $stichedImage;
                shell_exec($cmd);
                print ".";
            }
        }
    }

    public function engraveWatermark($target, $targetData, $recipeData)
    {
        print "engraveWatermark\n";
        print " target-> $target\n";
        // $this->resizeImage($source, $target, DIMENSIONS["imageWidth"], DIMENSIONS["imageWidth"]);

        /* Print Watermark */
        $watermark = PATHS["watermarkImage"];
        $tempWatermark = PATHS["tempFolder"] . "/tmp-watermark.png";
        $cmd = "convert -resize " .  $targetData["dimensions"]["width"] . " " .$watermark . " " . $tempWatermark;
        shell_exec($cmd);
        
        print " watermark -> $tempWatermark\n";
        $cmd = "composite -compose screen -blend 20 -gravity NorthWest -geometry +5+5 " . $tempWatermark . " " . $target . " " . $target;
        shell_exec($cmd);

        return $target;
    }

    public function manageTargetPath($image, $suffix = false)
    {
        $target = TARGET . $image;
        $targetPath = $this->getDirectoryFromPath($target);
        $targetFile = $this->getFilenameFromPath($target);

        if ($suffix !== false) {
            preg_match("=(.*?)\.(.*)=", $targetFile, $res);
            $targetFile = $res[1] . "-" . $suffix . "." . $res[2];
        }
        return BASEPATH . $this->checkPathSegements($targetPath) . $targetFile;
    }

    public function processImage($image, $recipeData, &$imageBundle)
    {
        print "processImage: $image\n";

        $source = preg_quote(SOURCE . $image);
        $target = $this->manageTargetPath($image, $recipeData->suffix);

        if (!preg_match("=\.jpg$=", $target)) {
            $target = preg_replace("=\.tif$=", ".jpg", $target);
        }
        
        $targetData = $this->resizeImage($source, $target, $recipeData, $imageBundle);

        $watermark = $recipeData->watermark;
        if ($watermark !== false) {
          $this->engraveWatermark($target, $targetData, $recipeData);
        }

        return $targetData;

    }

    public function getDimensions($src)
    {
        $cmd = "identify $src";
        $ret = explode(" ", shell_exec($cmd));
        list($width, $height) = explode("x", $ret[2]);
        return array('width' => $width, 'height' => $height);
    }

    public function resizeImage($source, $target, $data, &$imageBundle)
    {
        $sharpen = (isset($data->sharpen)) ? $data->sharpen : false;
        $quality = (isset($data->quality)) ? $data->quality : DIMENSIONS["qualityDefault"];
        $width = (isset($data->width)) ? $data->width : DIMENSIONS["imageWidth"];
        $height = (isset($data->height)) ? $data->height : DIMENSIONS["imageWidth"];
        $metadata = (isset($data->metadata)) ? $data->metadata : false;
        $source .= "[0]";

        $handleMetadata = ($metadata === false) ? "+profile iptc,8bim" : "";
        $sharpen = ($sharpen !== false) ? "-unsharp $sharpen" : "";
        $resize = ($width == "auto") ? "" : " -resize " . $width . "x" . $height;
        if ($width == "auto") {$imageBundle["maxDimensions"] = $this->getDimensions($source);}
        $cmd = "convert $handleMetadata -strip -quality $quality " . $resize . " $sharpen $source $target";

        shell_exec($cmd);
        preg_match("=.*/(.*?)$=", $target, $res);
        $fn = $res[1];
        return array('dimensions' => $this->getDimensions($target), 'src' => $fn);
    }

    public function getType($image)
    {
        preg_match("=.*/(.*?)/.*?$=", $image, $res);
        return $res[1];
    }
    public function getJsonPath($image)
    {
        return preg_replace("=(.*)/.*?/.*?$=", '${1}/imageData.json', $this->manageTargetPath($image));
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
        $targetPath = BASEPATH . $targetPath;
        mkdir($targetPath, 0775);
    }

    public function checkPathSegements($path)
    {
        $pattern = "=" . BASEPATH . "=";
        $workingPath = preg_replace($pattern, "", $path);
        $pathSegments = explode("/", $workingPath);

        /* Gunnar hat in der Verzeichnisstruktur immer noch das Haus. Das brauchen wir aber nicht. */
        unset($pathSegments[3]);
        $targetPath = "/" . array_shift($pathSegments);
        foreach ($pathSegments as $segment) {
            $targetPath .= "$segment/";
            if (!file_exists(BASEPATH . $targetPath)) {
                $this->createDirectory($targetPath);
            }
        }

        return $targetPath;
    }

}

function convertImages($imageCollection, $imageOperations)
{

    foreach ($imageCollection->images as $image) {
        $imageBundle = new ImageBundle;
        $imageType = $imageOperations->getType($image);
        $imageBundle->addSubStack($imageType);

        foreach (RECIPES as $recipe) {

            $recipeData = json_decode($recipe);
            $imageData = $imageOperations->processImage($image, $recipeData, $imageBundle->imageStack[$imageType]);
            $imageBundle->imageStack[$imageType]["images"][$recipeData->suffix] = array('dimensions' => $imageData["dimensions"], 'src' => $imageData["src"]);
        }

        $jsonPath = $imageOperations->getJsonPath($image);
        file_put_contents($jsonPath, json_encode($imageBundle));

    }
}

$imageCollection = new ImageCollection;
$imageOperations = new ImageOperations;

convertImages($imageCollection, $imageOperations);
