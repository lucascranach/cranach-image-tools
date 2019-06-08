<?php

define("BASEPATH", "/Users/cnoss/git/cranach-image-tools");
define("SOURCE", BASEPATH . "/images/src");
define("TARGET", BASEPATH . "/images/dist");

$paths = array();
$paths["watermarkSingle"] = BASEPATH . "/assets/watermark-white.png";
$paths["watermarkImage"] = BASEPATH . "/assets/stichedWatermark.png";
$paths["tempFolder"] = BASEPATH . "/tmp";
define("PATHS", $paths);

$dimensions = array();
$dimensions["imageWidth"] = 1800;
$dimensions["numberOfWatermarks"] = 20;
$dimensions["qualityDefault"] = 100;
define("DIMENSIONS", $dimensions);

$recipes = array();
$recipes["xsmall"] = '{ "suffix": "xs", "width": 200,  "quality": 70, "sharpen": "1.5x1.2+1.0+0.10", "watermark": false, "metadata": false }';
$recipes["small"] = '{ "suffix": "s",  "width": 300,  "quality": 95, "sharpen": "1.5x1.2+1.0+0.10", "watermark": false, "metadata": false }';
$recipes["medium"] = '{ "suffix": "m",  "width": 800,  "quality": 90, "sharpen": "1.5x1.2+1.0+0.10", "watermark": false, "metadata": true }';
$recipes["large"] = '{ "suffix": "l",  "width": 1200, "quality": 85, "sharpen": false,              "watermark": true,  "metadata": true }';
define("RECIPES", $recipes);

class ImageCollection
{

    public $images = array();

    public function __construct()
    {

        $cmd = "find " . SOURCE . " -name *.tif*";
        exec($cmd, $files);
        foreach ($files as $file) {
            $pattern = "=" . SOURCE . "=";
            $fn = preg_replace($pattern, "", $file);
            array_push($this->images, $fn);
        }
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
        $cmd = "convert -size " . DIMENSIONS["imageWidth"] . "x" . DIMENSIONS["imageWidth"] . " xc:transparent " . $stichedImage;
        shell_exec($cmd);

        /* Resize watermark */
        $tempWatermark = PATHS["tempFolder"] . "/tempWatermark.png";
        $size = DIMENSIONS["imageWidth"] / DIMENSIONS["numberOfWatermarks"];
        $cmd = "convert -resize $size " . PATHS["watermarkSingle"] . " " . $tempWatermark;
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

    public function convertToJPEG()
    {}

    public function engraveWatermark($source, $target)
    {
        print "engraveWatermark\n";

        $this->resizeImage($source, $target, DIMENSIONS["imageWidth"], DIMENSIONS["imageWidth"]);

        /* Print Watermark */
        $watermark = PATHS["watermarkImage"];
        $cmd = "composite -compose screen -blend 50 -gravity NorthWest -geometry +5+5 " . $watermark . " " . $target . " " . $target;
        shell_exec($cmd);

        return $target;
    }

    private function manageTargetPath($image, $suffix = false)
    {
        $target = TARGET . $image;
        $targetPath = $this->getDirectoryFromPath($target);
        $targetFile = $this->getFilenameFromPath($target);

        if (isset($suffix)) {
            preg_match("=(.*?)\.(.*)=", $targetFile, $res);
            $targetFile = $res[1] . "-" . $suffix . "." . $res[2];
        }

        return BASEPATH . $this->checkPathSegements($targetPath) . $targetFile;
    }

    public function processImage($image, $recipeData)
    {
        print "processImage: $image\n";

        $source = SOURCE . $image;
        $target = $this->manageTargetPath($image, $recipeData->suffix);
        $watermark = $recipeData->watermark;

        if ($watermark !== false) {
            $source = $this->engraveWatermark($source, $target, $recipeData);
        }

        $this->resizeImage($source, $target, $recipeData);
    }

    public function resizeImage($source, $target, $data)
    {
        $sharpen = (isset($data->sharpen)) ? $data->sharpen : false;
        $quality = (isset($data->quality)) ? $data->quality : DIMENSIONS["qualityDefault"];
        $width = (isset($data->width)) ? $data->width : DIMENSIONS["imageWidth"];
        $height = (isset($data->height)) ? $data->height : DIMENSIONS["imageWidth"];
        $metadata = (isset($data->metadata)) ? $data->metadata : false;

        $handleMetadata = ($metadata === false) ? "+profile iptc,8bim" : "";
        $sharpen = ($sharpen !== false) ? "-unsharp $sharpen" : "";
        $cmd = "convert $handleMetadata -strip -quality $quality -resize " . $width . "x" . $height . " $sharpen $source $target";
        shell_exec($cmd);
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

function convertToJPEG($imageCollection, $imageOperations)
{
    foreach ($imageCollection->images as $image) {
        if (!preg_match("=\.jpg$=", $image)) {
            print "TBD! convert: $image\n";
        }
    }
}

function convertImages($imageCollection, $imageOperations)
{
    foreach ($imageCollection->images as $image) {
        foreach (RECIPES as $recipe) {
            $recipeData = json_decode($recipe);
            $imageOperations->processImage($image, $recipeData);
        }
    }
}

$imageCollection = new ImageCollection;
$imageOperations = new imageOperations;
// convertToJPEG($imageCollection, $imageOperations);
convertImages($imageCollection, $imageOperations);
