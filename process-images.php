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
define("DIMENSIONS", $dimensions);

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

    public function engraveWatermark($image)
    {   

        print "engraveWatermark: $image\n";

        $source = SOURCE . $image;
        $target = TARGET . $image;
        $targetPath = $this->getDirectoryFromPath($target);
        $targetFile = $this->getFilenameFromPath($target);
        $cleanTarget = BASEPATH . $this->checkPathSegements($targetPath) . $targetFile;

        /* Resize to specific Value */
        $cmd = "convert -resize " . DIMENSIONS["imageWidth"] . "x" . DIMENSIONS["imageWidth"] . " $source $cleanTarget";
        shell_exec($cmd);

        /* Engrave Watermark */
        $watermark = PATHS["watermarkImage"];
        $cmd = "composite -compose screen -blend 50 -gravity NorthWest -geometry +5+5 " . $watermark . " " . $cleanTarget . " " . $cleanTarget;
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

function engraveWatermarks($imageCollection, $imageOperations)
{
    foreach ($imageCollection->images as $image) {
        $imageOperations->engraveWatermark($image);
    }
}

$imageCollection = new ImageCollection;
$imageOperations = new imageOperations;
convertToJPEG($imageCollection, $imageOperations);
engraveWatermarks($imageCollection, $imageOperations);
