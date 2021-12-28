<?php

error_reporting(E_ALL);

########################################################################  */

# AA_, AR_, AT_, AU_, BE_, BR_, CA_, CDN_, CH_, CU_, CZ_, DE_, DK_, E, F

$config = (object) [];
$config->LOCALCONFIG = getConfigFile();

$paths = array();
$paths["font"] = $config->LOCALCONFIG->pathAssets . "/assets/IBMPlexSans-Bold.ttf";
$paths["watermark"] = $config->LOCALCONFIG->pathAssets . "/assets/watermark-bw.svg";
$paths["tempFolder"] = $config->LOCALCONFIG->pathAssets . "/tmp";
$paths["watermark-temp"] = $paths["tempFolder"] . "/watermark-bw.png";
$paths["colormap"] = $paths["tempFolder"] . "/colormap.png";
$paths["convertLog"] = $config->LOCALCONFIG->pathAssets . "/logs/converted-variants.log";
$paths["rawLog"] = $config->LOCALCONFIG->pathAssets . "/logs/converted-raws.log";
$paths["watermark-dynamic"] = $paths["tempFolder"] . "/watermark-dynamic.png";
$config->PATHS = $paths;

$dimensions = array();
$dimensions["qualityDefault"] = 100;
$dimensions["imageWidthDefault"] = 2000;
$config->DIMENSIONS = $dimensions;

$recipes = array();
# $recipes["xsmall"] = '{ "suffix": "xs",     "width": 200,    "quality": 70, "sharpen": "1.5x1.2+1.0+0.10", "watermark": false, "metadata": true }';
# $recipes["small"] = '{ "suffix": "s",      "width": 400,    "quality": 80, "sharpen": "1.5x1.2+1.0+0.10", "watermark": false, "metadata": true }';
# $recipes["medium"] = '{ "suffix": "m",      "width": 600,    "quality": 80, "sharpen": "1.5x1.2+1.0+0.10", "watermark": false, "metadata": true }';
$recipes["origin"] = '{ "suffix": "origin", "width": "auto", "quality": 95, "sharpen": false,              "watermark": true,  "metadata": true }';
# $recipes["tiles"] = '{ "suffix": "origin", "format": "dzi"}';
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
$types["transmitted-light"] = '{ "fragment":"Transmitted-light", "sort": "13" }';
$config->TYPES = $types;

$typesArchivals = array();
$typesArchivals["singleOverall"] = '{ "fragment": false, "sort": false }';

/* Functions
############################################################################ */

function getConfigFile()
{
    $config_file = './image-tools-config.json';
    if (!file_exists($config_file)) {
        print "--------------------\n";
        print "Keine image-tools-config.json gefunden :(\n";
        exit;
    }

    $config = file_get_contents($config_file);
    return json_decode(trim($config));
}

function getTypeSubfolderName($typeName)
{
    global $config;
    $typeDataJSON = json_decode($config->TYPES[$typeName]);
    $folderName = (isset($typeDataJSON->sort) && isset($typeDataJSON->fragment)) ? $typeDataJSON->sort . "_" . $typeDataJSON->fragment : "";
    return $folderName;
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

    public function __construct($params)
    {
        $this->params = $params;
        $cmd = "find " . $this->params["source"] . " -maxdepth 5 -mtime " . $this->params["period"] . " -name '" . $this->params["pattern"] . "' ";
        exec($cmd, $this->images);
    }

    public function getSize()
    {
        return count($this->images);
    }
}

class ArchivalCollection
{

    public $images = array();

    public function __construct($config)
    {
        $this->config = $config;
        $this->type = "ArchivalCollection";

        $cmd = "find " . $this->config->SOURCE . " -name '" . $this->config->PATTERN . "' "; // -mtime -120
        exec($cmd, $this->files);

        $pattern = "=" . $this->config->SOURCE . "/=";
        $this->files = preg_replace($pattern, "", $this->files);

        $assets = array();
        foreach ($this->files as $file) {
            $artefaktBase = $this->getBasePath($file);
            $assets[$artefaktBase][] = "/$file";
            $this->createTargetFolder($artefaktBase);
        }

        foreach (array_keys($assets) as $assetBasePath) {
            $res = array("name" => $assetBasePath);

            foreach ($this->config->TYPES as $typeName => $typeData) {
                $typeFiles = $assets[$assetBasePath];
                /*if($this->config->MODE === "only-dzi-files"){
                $typeFiles = preg_grep("=$searchPattern=", $this->files);
                }*/
                $res["data"][$typeName] = $typeFiles;
            }
            array_push($this->images, $res);
        }
    }

    private function createTargetFolder($artefaktBase)
    {
        $folderPath = $this->config->TARGET . "/$artefaktBase/";
        if (file_exists($folderPath)) {
            return false;
        }

        mkdir($folderPath, 0775);
    }

    public function getSize()
    {
        return count($this->images);
    }

    private function getBasePath($path)
    {
        return preg_replace("=(.*?)\.tif=", '${1}', $path);
    }
}

class ImageOperations
{
    public function __construct($config, $params)
    {
        $this->config = $config;
        $this->params = $params;
    }

    private function map($value, $valueRangeStart, $valueRangeEnd, $newRangeStart, $newRangeEnd) {
      return $newRangeStart + ($newRangeEnd - $newRangeStart) * (($value - $valueRangeStart) / ($valueRangeEnd - $valueRangeStart));
    }

    private function getColorMap($source, $cols, $rows){

      $mapCols = $cols;
      $mapRows = $rows;

      $colorMapPath = $this->config->PATHS["colormap"];

      $cmd = "convert -resize " . $mapCols. "x$mapRows -set colorspace sRGB $source txt:";
      exec($cmd, $data);

      // $cmd = "convert -resize " . $mapCols. "x$mapRows -colorspace Gray $source $colorMapPath";
      // exec($cmd);

      $map = [];
      foreach($data as $row){
        preg_match("=(.*?),(.*?):.*\((.*?)\,(.*?)\,(.*?)\,=", $row, $res);
        if(!isset($res[1])) continue;
        $x = intval($res[1]);
        $y = intval($res[2]);
        $r = intval($res[3]);
        $g = intval($res[4]);
        $b = intval($res[5]);
        //$lightness = floor($this->map(intval($res[3]), 6, 170, 0, 255));
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
        $tileSize = $this->config->LOCALCONFIG->tileSize;
        
        $cols = floor($width / $tileSize);
        $rows = floor($height / $tileSize);

        $colorMap = $this->getColorMap($source, $cols, $rows);

        $watermarkdata = [];
        $baseFontSize = $tileSize / 40;
        $lightnessSplitPoint = 122;

        for ($col = 0; $col < $cols; $col++) {
          for ($row = 0; $row < $rows; $row++) {
            $skip = rand(0,10);
            if($skip > 8) continue;
            $pointsize = rand($baseFontSize*2, $baseFontSize * 5);
            $opacity = rand(2, 5) / 10;
            
            $xRand = rand(0, $tileSize/4);
            $x = ($col * $tileSize) + $xRand;
            $yRand = rand(0, $tileSize/2);
            $y = ($row * $tileSize) + $pointsize + $yRand;
            if(!isset($colorMap[$col][$row])) continue;
            $color =  $colorMap[$col][$row]["color"];
            array_push($watermarkdata, " -pointsize $pointsize -fill 'rgba($color, $opacity)' -annotate +$x+$y 'cda_'");
          }
        }

        return  "-font $font " . implode(' ', $watermarkdata);
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
        createRecursiveFolder($target);
        
        $watermarkData = isset($recipeData->watermark) ? $this->createWatermarkData($source, $target) : false;
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

    public function __checkPathSegements($path)
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

function convertImages($collection, $imageOperations, $config)
{
    $stackSize = $collection->getSize();
    $count = 0;

    $loggingPath = $config->PATHS["convertLog"];
    unlink($loggingPath);

    foreach ($collection->images as $asset) {
        $count++;
        print "\Asset $count from $stackSize // $asset:";

        $recipeTitles = array_keys($config->RECIPES);
        sort($recipeTitles);
        foreach ($recipeTitles as $recipeTitle) {
            print "\n->$recipeTitle\n";
            $recipe = $config->RECIPES[$recipeTitle];
            $recipeData = json_decode($recipe);
            $imageOperations->processImage($asset, $recipeTitle, $recipeData);
        }

        file_put_contents($loggingPath, "$asset\n", FILE_APPEND);
    }
}

function getCliOptions()
{
    $ret = [];
    $options = getopt("p:d:");
    if (isset($options["p"])) {$ret["pattern"] = $options["p"];}
    if (isset($options["d"])) {$ret["dir"] = $options["d"];}

    return $ret;
}

function confirmParams($params)
{
    print "----------\n";
    print "Quellverzeichnis: " . $params["source"] . "\n";
    print "Zielverzeichnis: " . $params["target"] . "\n";
    print "Pattern: " . $params["pattern"] . "\n";
    print "Zeitspanne: " . $params["period"] . "\n";

    print "\nAlle Angaben in Ordnung? [j,n] ";
    $choice = rtrim(fgets(STDIN));

    if ($choice !== 'j') {exitScript();}
    return true;
}

function exitScript()
{
    print "\nfertig :)\n\n";
    exit;
}

function getImageCollection($params)
{
    $imageCollection = new ImageCollection($params);

    print "----------\n";
    print "Sollen " . sizeof($imageCollection->images) . " Bilder verarbeitet werden? [j,n,(a)nzeigen] ";
    $choice = rtrim(fgets(STDIN));

    switch ($choice) {
        case "j":
            return $imageCollection;
            break;
        case "a":
            var_dump($imageCollection->files);
            getImageCollection($params);
            break;
        case "green":
            echo "Your favorite color is green!";
            break;
        default:
            exitScript();
    }
}

function getFilenameFromPath($path)
{
    preg_match("=.*\/(.*)$=", $path, $res);
    return isset($res[1]) ? $res[1] : false;
}

function removePyramidDoubles($files, $config)
{
    $res = [];
    foreach ($files as $path) {
        $filename = getFilenameFromPath($path);
        if (!$filename) {continue;}
        $pattern = "=$filename=";
        $in_array = preg_grep($pattern, $res);
        if (sizeof($in_array) === 0) {array_push($res, $path);}
    }

    return $res;
}

function createRecursiveFolder($path)
{
    preg_match("=(.*)\/=", $path, $res);
    $segments = explode("/", $res[1]);

    $growingPath = [];
    foreach ($segments as $segment) {
        array_push($growingPath, $segment);
        $newPath = implode("/", $growingPath);
        if (preg_match("=[a-zA-Z]=", $newPath) && !file_exists($newPath)) {mkdir($newPath, 0775);}
    }
    return;
}

function cleanUpPyramidTiffs($filename)
{
    $from = preg_replace("=\.png=", "-0.png", $filename);
    if (!file_exists($from)) {
        return;
    }

    rename($from, $filename);

    $levels = [1, 2, 3, 4, 5, 6, 7, 8, 9];
    foreach ($levels as $level) {
        $deletePath = preg_replace("=\.png=", "-$level.png", $filename);
        if (!file_exists($deletePath)) {
            continue;
        }

        unlink($deletePath);
    }
    return;
}

function createRawImages($imageType, $config)
{

    function getParamsForRawConvertion($imageType, $config)
    {

        switch ($imageType) {
            case "paintings":
                $pattern = preg_replace("=\|=", "\|", $config->LOCALCONFIG->unconvertedImageParams->paintings->pattern);

                return [
                    "source" => $config->LOCALCONFIG->unconvertedImageParams->paintings->path,
                    "target" => $config->LOCALCONFIG->preparedImageParams->paintings->path,
                    "suffixPattern" => $pattern,
                ];
                break;
        }
    }

    $supportedImageTypes = [
        "paintings",
    ];

    $pattern = "=$imageType=";
    if (!preg_grep($pattern, $supportedImageTypes)) {
        print "\nFalscher Bildtyp. Es werden nur folgende Typen unterstützt:\n";
        foreach ($supportedImageTypes as $type) {
            print "$type\n";
        }
        showMainMenu($config);
        return;
    }

    print "----------\n";
    print "Raw Version erzeugen von: $imageType\n";

    $cliOptions = getCliOptions();
    $params = getParamsForRawConvertion($imageType, $config);

    $startDirectory = isset($cliOptions["dir"]) ? $params['source'] . "/" . $cliOptions["dir"] : $params['source'];
    $cmd = "find $startDirectory " . " -regex '" . $params['suffixPattern'] . "' ";
    print "$cmd\n";
    exec($cmd, $files);

    $files = removePyramidDoubles($files, $config);

    print "Es werden " . sizeof($files) . " bearbeitet …\n";

    foreach ($files as $file) {
        $pathWithoutPyramid = preg_replace("=pyramid/=", "", $file);
        $pattern = "=" . $params['source'] . "=";
        $target = preg_replace($pattern, $params['target'], $pathWithoutPyramid);
        
        $suffixPattern = "=" . $params["suffixPattern"] . "=";        
        $suffixPattern = preg_replace("=\\\=", "", $suffixPattern);
        $suffixPattern = preg_replace("=\\.=", "\.", $suffixPattern);
        $target = preg_replace($suffixPattern, ".png", $target);

        createRecursiveFolder($target);
        $cmd = "convert $file $target";
        print "erzeuge $target\n";
        
        exec($cmd);
        cleanUpPyramidTiffs($target);
    }

}

function removeTargetContents($config)
{
    print "----------\n";
    print "Soll der Inhalt von folgendem Verzeichnis gelöscht werden?\n" . $config->TARGET;
    print "\n[j,n] ";
    $choice = rtrim(fgets(STDIN));
    if ($choice !== 'j') {
        return;
    }

    recursiveRemoveDirectory($config->TARGET);
    print "Inhalte wurden gelöscht.\n";
    return;
}

function recursiveRemoveDirectory($path, $isSubDir = false)
{
    $files = glob($path . '/*');
    foreach ($files as $item) {
        is_dir($item) ? recursiveRemoveDirectory($item, true) : unlink($item);
    }
    if ($isSubDir) {rmdir($path);}
    return;
}

function chooseImageType($config)
{

    $imageTypes = [
        "paintings" => "Gemälde",
        "graphics" => "Grafiken",
    ];

    print "----------\n";

    $count = 0;
    $options = [];

    foreach ($imageTypes as $key => $value) {
        $count++;
        print "[$count] $value\n";
        $options[$count] = $key;
    }

    print "Für welchen Bildtyp sollen Derivate erzeugt werden? ";
    $choice = intval(rtrim(fgets(STDIN)));
    if (!array_key_exists($choice, $options)) {
        print "\nDieser Bildtyp ist nicht verfügbar.\n\n";
        showMainMenu($config);
        return;
    }

    return $options[$choice];
}

function showMainMenu($config)
{

    $actions = [
        "create-raw-paintings" => "Raw Version der Gemälde erzeugen",
        "generate-images" => "Derivate erzeugen",
        "remove-target-contents" => "Zielverzeichnis löschen",
        "exit" => "Skript beenden",
    ];

    $params = [
        "-p" => "Übergibt ein File-Pattern, welches das Pattern der Config überschreibt, z.B. -p \"G_*\"",
        "-d" => "Übergibt ein optionales Startverzeichnis, z.B. -d \"PRIVATE_NONE-P200_FR058\"",
    ];

    print "\n#############################################################################\n";
    print "Cranach Image Tools\n\n";
    print "Das Skript kann mit folgenden Parametern aufgerufen werden:\n";
    foreach ($params as $key => $value) {
        print "$key\t$value\n";
    }
    print "\nFolgende Aktionen sind verfügbar:\n";

    $count = 0;
    $options = [];

    foreach ($actions as $key => $value) {
        $count++;
        print "[$count] $value\n";
        $options[$count] = $key;
    }

    print "\nWas soll gemacht werden? ";
    $choice = intval(rtrim(fgets(STDIN)));
    if (!array_key_exists($choice, $options)) {print "\nDiese Aktion ist nicht verfügbar.\n\n";exit;}

    switch ($options[$choice]) {
        case "generate-images":
            print "\nBilder generieren …\n";

            $cliOptions = getCliOptions($config);
            $type = chooseImageType($config);

            $sourceBasePath = $config->LOCALCONFIG->preparedImageParams->$type->path;
            $source = isset($cliOptions["dir"])
            ? $sourceBasePath . '/' . $cliOptions["dir"]
            : $sourceBasePath;

            $targetBasePath = $config->LOCALCONFIG->targetPath;
            $target = isset($cliOptions["dir"])
            ? $targetBasePath . '/' . $cliOptions["dir"]
            : $targetBasePath;

            $pattern = isset($cliOptions["pattern"])
            ? $cliOptions["pattern"]
            : $config->LOCALCONFIG->preparedImageParams->$type->pattern;

            $period = isset($cliOptions["period"])
            ? $cliOptions["period"]
            : $config->LOCALCONFIG->defaultPeriod;

            $params = [
                "source" => $source,
                "target" => $target,
                "pattern" => $pattern,
                "period" => $period,
            ];

            confirmParams($params);

            $imageCollection = getImageCollection($params);
            $imageOperations = new ImageOperations($config, $params);
            convertImages($imageCollection, $imageOperations, $config);
            exitScript();
            break;

        case "remove-target-contents":
            removeTargetContents($config);
            showMainMenu($config);
            break;

        case "create-raw-paintings":
            createRawImages('paintings', $config);
            break;

        case "exit":
            exitScript();
            break;

        default:
            print "Na gut, dann eben nix.\n";
            exit;
    }

}

/* Main
############################################################################ */

showMainMenu($config);
exitScript();

$newSession = "\n#######################################################\n" . date("d.m.Y, H:i:s", time());

if ($config->MODE === "dzi-only") {
    $recipes = [];
    $recipes["tiles"] = $config->RECIPES["tiles"];
    $config->RECIPES_ORIG = $config->RECIPES;
    $config->RECIPES = $recipes;
}

$imageCollection = new ImageCollection($config);
$imageOperations = new ImageOperations($config);
convertImages($imageCollection, $imageOperations, $config);

# Archivalien
/*$config->SOURCE = $local_config->SOURCE_ARCHIVALS;
$config->TARGET = $local_config->TARGET_ARCHIVALS;
$config->TYPES = $typesArchivals;

$archivalCollection = new ArchivalCollection($config);
$imageOperations = new ImageOperations($config);
convertImages($archivalCollection, $imageOperations, $config); */
