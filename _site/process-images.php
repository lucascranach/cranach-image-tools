<?php

error_reporting(E_ALL);

require './classes/ImageOperations.php';
require './classes/ImageCollection.php';
require './classes/JsonOperations.php';
require './classes/Metadata.php';

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

$sizes = array();
$sizes["xsmall"] = '{ "suffix": "xs",     "width": 200,    "quality": 70, "sharpen": "1.5x1.2+1.0+0.10", "watermark": false, "metadata": false }';
$sizes["small"] = '{ "suffix": "s",      "width": 400,    "quality": 80, "sharpen": "1.5x1.2+1.0+0.10", "watermark": false, "metadata": true }';
$sizes["medium"] = '{ "suffix": "m",      "width": 600,    "quality": 80, "sharpen": "1.5x1.2+1.0+0.10", "watermark": false, "metadata": true }';
$sizes["origin"] = '{ "suffix": "origin", "width": "auto", "quality": 95, "sharpen": false,              "watermark": true,  "metadata": true }';
$sizes["tiles"] = '{ "type": "dzi", "suffix": "dzi"}';
$config->SIZES = $sizes;

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
$types["transmitted-light"] = '{ "fragment":"Transmitted-light", "sort": "13" }';
$config->TYPES = $types;

// $types["rkd"] = '{ "fragment":"RKD", "sort": "11" }';
$misc = array();
$misc["json-filename"] = "imageData-v1.3.json";
$misc["metadata-filename"] = "metaData-v1.0.json";
$misc["rkdFragment"] = "11_RKD";
$misc["koeFragment"] = "12_KOE";
$config->MISC = $misc;

// $typesArchivals = array();
// $typesArchivals["singleOverall"] = '{ "fragment": false, "sort": false }';

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

function getTypeSubfolderName(String $typeName)
{
    global $config;
    $typeDataJSON = json_decode($config->TYPES[$typeName]);
    $folderName = (isset($typeDataJSON->sort) && isset($typeDataJSON->fragment)) ? $typeDataJSON->sort . "_" . $typeDataJSON->fragment : "";
    return $folderName;
}

function getTypeFilenamePattern(String $typeName)
{
    global $config;
    $typeDataJSON = json_decode($config->TYPES[$typeName], true);
    return (isset($typeDataJSON->fn_pattern)) ? $typeDataJSON->fn_pattern : "";
}

function convertImages($collection, $imageOperations, $config)
{
    $stackSize = $collection->getSize();
    $count = 0;

    $loggingPath = $config->PATHS["convertLog"];
    unlink($loggingPath);

    foreach ($collection->images as $asset) {
        $count++;
        print "\nAsset $count from $stackSize // $asset: ";

        $recipeTitles = array_keys($config->SIZES);
        sort($recipeTitles);
        foreach ($recipeTitles as $recipeTitle) {
            $recipe = $config->SIZES[$recipeTitle];
            $recipeData = json_decode($recipe);

            // if (isset($recipeData->type) && $recipeData->type == "dzi") {
            //     continue;
            // }

            print $recipeData->suffix . ", ";
            $imageOperations->processImage($asset, $recipeTitle, $recipeData);
        }
    }
}

function createImageTiles($collection, $imageOperations, $config)
{
    $stackSize = $collection->getSize();
    $count = 0;

    $loggingPath = $config->PATHS["convertLog"];
    unlink($loggingPath);

    foreach ($collection->images as $asset) {
        $count++;
        print "\nAsset $count from $stackSize // $asset: ";
        $imageOperations->generateTiles($asset);
        // file_put_contents($loggingPath, "$asset\n", FILE_APPEND);
    }
}

function getCliOptions()
{
    $ret = [];
    $options = getopt("p:d:o:t:");
    if (isset($options["p"])) {$ret["pattern"] = $options["p"];}
    if (isset($options["d"])) {$ret["dir"] = $options["d"];}
    if (isset($options["o"])) {$ret["overwrite"] = true;}
    if (isset($options["t"])) {$ret["period"] = $options["t"];}

    return $ret;
}

function confirmParams($params)
{
    print "----------\n";
    print "Quellverzeichnis: " . $params["source"] . "\n";
    print "Zielverzeichnis: " . $params["target"] . "\n";
    print "Pattern: " . $params["pattern"] . "\n";
    print "Zeitspanne: " . $params["period"] . "\n";
    print "Overwrite: " . $params["overwrite"] . "\n";

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
        case "a":
            var_dump($imageCollection->files);
            getImageCollection($params);
            break;
        case "green":
            echo "Your favorite color is green!";
            break;
        default:
            echo "Deine Wahl: $choice\nNa gut, dann eben nicht :)\n";
            exitScript();
    }
}

function getFilenameFromPath($path)
{
    preg_match("=.*\/(.*)$=", $path, $res);
    return isset($res[1]) ? $res[1] : false;
}

function removePyramidDoubles($files)
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

                return [
                    "source" => $config->LOCALCONFIG->unconvertedImageParams->paintings->path,
                    "target" => $config->LOCALCONFIG->preparedImageParams->paintings->path,
                    "searchPattern" => '\( -name  \*.tif -o -name \*.tiff -o -name \*.TIF -o -name \*.TIFF \)',
                ];

            case "graphics":

                return [
                    "source" => $config->LOCALCONFIG->unconvertedImageParams->graphics->path,
                    "target" => $config->LOCALCONFIG->preparedImageParams->graphics->path,
                    "searchPattern" => '\( -name  \*.tif -o -name \*.tiff -o -name \*.TIF -o -name \*.TIFF \)',
                ];
        }
    }

    $supportedImageTypes = [
        "paintings",
        "graphics",
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

    $loggingPath = $config->PATHS["rawLog"];
    if (file_exists($loggingPath)) {unlink($loggingPath);}

    print "----------\n";
    print "Raw Version erzeugen von: $imageType\n";

    $cliOptions = getCliOptions();
    $params = getParamsForRawConvertion($imageType, $config);

    $startDirectory = isset($cliOptions["dir"]) ? $params['source'] . "/" . $cliOptions["dir"] . "/" : $params['source'];
    $cmd = "find $startDirectory " . $params['searchPattern'];
    exec($cmd, $files);

    $files = removePyramidDoubles($files, $config);

    print "Es werden " . sizeof($files) . " bearbeitet …\n";

    $count = 1;
    foreach ($files as $file) {
        $pathWithoutPyramid = preg_replace("=/pyramid/=", "/", $file);
        $pattern = "=" . $params['source'] . "=";
        $target = preg_replace($pattern, $params['target'], $pathWithoutPyramid);
        $target = preg_replace("=\..*?$=", ".tif", $target);
        print "$count von " . sizeof($files) . "\n";
        $count++;

        if (file_exists($target)) {
            print "Datei existiert bereits\n";
            continue;
        }

        createRecursiveFolder($target);
        $cmd = "magick $file\[0] +repage -compress lzw $target";
        print "erzeuge $target\n";

        exec($cmd);
        // cleanUpPyramidTiffs($target);
        // file_put_contents($loggingPath, "$file\n", FILE_APPEND);
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
        "archivals" => "Archivalien",
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

function getConvertionParams($cliOptions, $params)
{

    $sourceBasePath = $params["sourceBasePath"];
    $source = isset($cliOptions["dir"])
    ? $sourceBasePath . '/' . $cliOptions["dir"]
    : $sourceBasePath;

    $targetBasePath = $params["targetBasePath"];
    $target = isset($cliOptions["dir"])
    ? $targetBasePath . '/' . $cliOptions["dir"]
    : $targetBasePath;

    $defaultPattern = gettype($params["pattern"]) === "array" ? implode("|", $params["pattern"]) : $params["pattern"];
    $pattern = isset($cliOptions["pattern"])
    ? $cliOptions["pattern"]
    : $defaultPattern;

    $period = isset($cliOptions["period"])
    ? $cliOptions["period"]
    : $params["defaultPeriod"];

    $overwrite = isset($cliOptions["overwrite"])
    ? $cliOptions["overwrite"]
    : "nein";

    $params = [
        "sourceBasePath" => $sourceBasePath,
        "source" => $source,
        "targetBasePath" => $targetBasePath,
        "target" => $target,
        "pattern" => $pattern,
        "period" => $period,
        "overwrite" => $overwrite,
    ];

    return $params;
}

function showMainMenu($config)
{

    $actions = [
        "create-raw-paintings" => "Raw Version der Gemälde erzeugen",
        "create-raw-graphics" => "Raw Version der Grafiken erzeugen",
        "generate-variants" => "Derivate erzeugen",
        "generate-tiles" => "DZI Tiles erzeugen",
        "generate-json" => "JSON Dateien erzeugen",
        "generate-json-archivals" => "JSON Dateien für Archivalien erzeugen",
        "extract-metadata" => "Metadaten extrahieren",
        "remove-target-contents" => "Zielverzeichnis löschen",
        "exit" => "Skript beenden",
    ];

    $params = [
        "-p" => "Übergibt ein File-Pattern, welches das Pattern der Config überschreibt, z.B. -p \"G_*\"",
        "-d" => "Übergibt ein optionales Startverzeichnis, z.B. -d \"PRIVATE_NONE-P200_FR058\"",
        "-o ja" => "Damit werden bestehende Daten überschrieben.",
        "-t" => "Übergibt ein Periode, z.B. -2 findet nur Dateien, die in den letzten 2 Tagen geändert wurden.",
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
        case "generate-variants":
            print "\nBilder generieren …\n";

            $cliOptions = getCliOptions($config);
            $entityType = chooseImageType($config);
            $params = getConvertionParams($cliOptions, [
                "sourceBasePath" => $config->LOCALCONFIG->preparedImageParams->$entityType->path,
                "targetBasePath" => $config->LOCALCONFIG->targetPath,
                "pattern" => $config->LOCALCONFIG->preparedImageParams->$entityType->pattern,
                "defaultPeriod" => $config->LOCALCONFIG->defaultPeriod,
            ]);

            confirmParams($params);

            $imageCollection = getImageCollection($params);
            $imageOperations = new ImageOperations($config, $params);

            convertImages($imageCollection, $imageOperations, $config);
            exitScript();
            break;

        case "generate-tiles":
            print "\nImage Tiles generieren …\n";

            $cliOptions = getCliOptions($config);
            $entityType = chooseImageType($config);
            $params = getConvertionParams($cliOptions, [
                "sourceBasePath" => $config->LOCALCONFIG->targetPath,
                "targetBasePath" => $config->LOCALCONFIG->targetPath,
                "pattern" => $config->LOCALCONFIG->tileSourcePattern,
                "defaultPeriod" => $config->LOCALCONFIG->defaultPeriod,
            ]);

            confirmParams($params);
            print "Generate Tiles\n";
            $imageCollection = getImageCollection($params);
            print "imageCollection\n";
            $imageOperations = new ImageOperations($config, $params);

            createImageTiles($imageCollection, $imageOperations, $config);
            exitScript();
            break;

        case "generate-json":
            print "\nJSON Dateien generieren …\n";

            $cliOptions = getCliOptions($config);
            $params = getConvertionParams($cliOptions, [
                "sourceBasePath" => $config->LOCALCONFIG->targetPath,
                "targetBasePath" => $config->LOCALCONFIG->targetPath,
                "pattern" => ["*.jpg", "*.dzi"],
                "defaultPeriod" => $config->LOCALCONFIG->defaultPeriod,
            ]);

            confirmParams($params);
            $jsonOperations = new JsonOperations($config, $params);
            $jsonOperations->createJSONS();
            exitScript();
            break;

        case "generate-json-archivals":
            print "\nJSON Dateien für Archivalien generieren …\n";

            $cliOptions = getCliOptions($config);
            $params = getConvertionParams($cliOptions, [
                "sourceBasePath" => $config->LOCALCONFIG->targetPath,
                "targetBasePath" => $config->LOCALCONFIG->targetPath,
                "pattern" => ["*.jpg", "*.dzi"],
                "defaultPeriod" => $config->LOCALCONFIG->defaultPeriod,
            ]);

            $params["modus"] = "archivals";

            confirmParams($params);
            $jsonOperations = new JsonOperations($config, $params);
            $jsonOperations->createJSONS();
            exitScript();
            break;

        case "extract-metadata":
            print "\nMetadaten extrahieren …\n";

            $cliOptions = getCliOptions($config);
            $params = getConvertionParams($cliOptions, [
                "sourceBasePath" => $config->LOCALCONFIG->metaDataSource,
                "targetBasePath" => $config->LOCALCONFIG->metaDataTarget,
                "pattern" => [".*\.tif"],
                "defaultPeriod" => $config->LOCALCONFIG->defaultPeriod,
            ]);

            confirmParams($params);
            $metadataService = new Metadata($config, $params);
            $metadataService->extractMetadata();
            exitScript();
            break;

        case "remove-target-contents":
            removeTargetContents($config);
            showMainMenu($config);
            break;

        case "create-raw-paintings":
            createRawImages('paintings', $config);
            break;

        case "create-raw-graphics":
            createRawImages('graphics', $config);
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
