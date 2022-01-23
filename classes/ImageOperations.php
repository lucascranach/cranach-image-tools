<?php

class ImageOperations
{
    public function __construct($config, $params)
    {
        $this->config = $config;
        $this->params = $params;
    }

    private function map($value, $valueRangeStart, $valueRangeEnd, $newRangeStart, $newRangeEnd)
    {
        return $newRangeStart + ($newRangeEnd - $newRangeStart) * (($value - $valueRangeStart) / ($valueRangeEnd - $valueRangeStart));
    }

    private function getColorMap($source, $cols, $rows)
    {

        $mapCols = $cols;
        $mapRows = $rows;

        $colorMapPath = $this->config->PATHS["colormap"];

        $cmd = "convert -resize " . $mapCols . "x$mapRows -set colorspace sRGB $source txt:";
        exec($cmd, $data);

        // $cmd = "convert -resize " . $mapCols. "x$mapRows -colorspace Gray $source $colorMapPath";
        // exec($cmd);

        $map = [];
        foreach ($data as $row) {
            preg_match("=(.*?),(.*?):.*\((.*?)\,(.*?)\,(.*?)\,=", $row, $res);
            if (!isset($res[1])) {
                continue;
            }

            $x = intval($res[1]);
            $y = intval($res[2]);
            $r = intval($res[3]);
            $g = intval($res[4]);
            $b = intval($res[5]);

            $reduce = 50;
            $r = $r > 130 ? $r - $reduce : $r;
            $g = $g > 130 ? $g - $reduce : $g;
            $b = $b > 130 ? $b - $reduce : $b;

            $add = 50;
            $r = $r < 120 ? $r + $add : $r;
            $g = $g < 120 ? $g + $add : $g;
            $b = $b < 120 ? $b + $add : $b;

            $map[$x][$y] = [
                "color" => "$r, $g, $b",
                "lightnessRaw" => $res[3],
                "row" => $row,

            ];
        }

        return $map;
    }

    private function createWatermarkData($source)
    {

        $dynamic_watermark = $this->config->PATHS["watermark-dynamic"];
        $font = $this->config->PATHS["font"];

        $dimensions = $this->getDimensions($source);
        $width = $dimensions['width'];
        $height = $dimensions['height'];

        if ($width >= $height) {
            $ratio = $width / $height;
            $tileAmount = 4 + floor($width / 3000);
            $tileSize = round($height / $tileAmount);

        } else {
            $ratio = $height / $width;
            $tileAmount = 4 + floor($height / 3000);
            $tileSize = round($width / $tileAmount);
        }

        $cols = round($width / $tileSize);
        $rows = round($height / $tileSize);

        $colorMap = $this->getColorMap($source, $cols, $rows);

        $watermarkdata = [];
        $baseFontSize = round($tileSize / 30);

        for ($col = 0; $col <= $cols; $col++) {
            for ($row = 0; $row <= $rows; $row++) {
                $skip = rand(0, 10);
                if ($skip > 7) {
                    continue;
                }

                $pointsize = rand($baseFontSize, $baseFontSize * 5);
                $opacity = rand(3, 6) / 10;

                $xRand = rand(0, round($tileSize / 4));
                $x = ($col * $tileSize) + $xRand;
                $yRand = rand(0, round($tileSize / 2));
                $y = ($row * $tileSize) + $pointsize + $yRand;
                if (!isset($colorMap[$col][$row])) {
                    continue;
                }

                $color = $colorMap[$col][$row]["color"];
                array_push($watermarkdata, " -pointsize $pointsize -fill 'rgba($color, $opacity)' -annotate +$x+$y 'cda_'");
            }
        }

        return "-font $font " . implode(' ', $watermarkdata);
    }

    public function generateTiles($recipeData, $targetData)
    {

        $path = preg_replace("=pyramid=", "", $targetData['path']);

        //$suffix = ($this->config->MODE !== "dzi-only" && !preg_match("=tif$=", $this->config->PATTERN)) ? '-' . $recipeData->suffix : "";
        $suffix = (preg_match("=tif$=", $this->config->PATTERN)) ? '-' . $recipeData->suffix : "-origin";
        $source = $this->config->TARGET . $path . '/' . $targetData['basefilename'] . $suffix . ".jpg";

        $basefilenameTarget = (preg_match("=\-origin=", $targetData['basefilename'])) ? preg_replace("=\-origin=", "", $targetData['basefilename']) : $targetData['basefilename'];
        $target = $this->config->TARGET . $path . '/' . $basefilenameTarget;
        $dzi = $target . '.dzi';
        $files = $target . '_files';

        /*if (file_exists($files) && $this->config->MODE !== "json-only") {
        $cmd = 'rm -Rf ' . $files;
        shell_exec($cmd);
        }*/

        if (file_exists($target . '.dzi') && $this->config->MODE !== "dzi-only") {
            echo "Skip " . $target . '.dzi' . " already exists.\n";
            return;
        }
        $cmd = 'vips dzsave ' . $source . ' ' . $target . ' --suffix .jpg[Q=95]';

        shell_exec($cmd);
        chmod($target . '.dzi', 0755);

        $cmd = 'chmod -R 755 ' . $target . '_files';
        shell_exec($cmd);
    }

    public function manageTargetPath($source, $recipeData)
    {
        $pattern = "=" . $this->params["source"] . "=";
        $targetPath = preg_replace($pattern, $this->params["target"], $source);
        $pattern = "=\..*?$=";
        return preg_replace($pattern, "-" . $recipeData->suffix . ".jpg", $targetPath);
    }

    public function processImage($image, $recipeTitle, $recipeData)
    {
        $source = $image;
        $target = $this->manageTargetPath($source, $recipeData);

        if (file_exists($target)) {
          print "-";
          return;
      }
        createRecursiveFolder($target);

        $watermarkData = isset($recipeData->watermark) && $recipeData->watermark === true ? $this->createWatermarkData($source, $target) : false;
        $this->resizeImage($source, $target, $recipeData, $watermarkData);

        return true;

    }

    public function getDimensions($src)
    {
        $cmd = "identify -quiet $src";
        $ret = explode(" ", shell_exec($cmd));

        list($width, $height) = explode("x", $ret[2]);
        return array('width' => $width, 'height' => $height);
    }

    public function resizeImage($source, $target, $recipeData, $watermarkData)
    {

        $sharpen = (isset($recipeData->sharpen)) ? $recipeData->sharpen : false;
        $quality = (isset($recipeData->quality)) ? $recipeData->quality : $this->config->DIMENSIONS["qualityDefault"];
        $width = (isset($recipeData->width)) ? $recipeData->width : $this->config->DIMENSIONS["imageWidthDefault"];
        $height = (isset($recipeData->height)) ? $recipeData->height : $this->config->DIMENSIONS["imageWidthDefault"];
        $metadata = (isset($recipeData->metadata)) ? $recipeData->metadata : false;

        $source .= "[0]";
        $handleMetadata = ($metadata === false) ? "+profile iptc,8bim" : "";
        $sharpen = ($sharpen !== false) ? "-unsharp $sharpen" : "";
        $resize = ($width == "auto") ? "" : " -resize " . $width . "x" . $height;
        $cmd = "convert -interlace plane -quiet $handleMetadata $watermarkData -strip -quality $quality " . $resize . " $sharpen $source $target";
        shell_exec($cmd);

        // chmod($target, 0755);

        return true;
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
}