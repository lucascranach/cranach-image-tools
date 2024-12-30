<?php

error_reporting(E_ALL);

########################################################################  */

$config = (object) [];
$config->LOCALCONFIG = getConfigFile();
$paths = array();
$config->PATHS = $paths;


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

function exitScript()
{
    print "\nfertig :)\n\n";
    exit;
}

function getFilenameFromPath($path)
{
    preg_match("=.*\/(.*)$=", $path, $res);
    return isset($res[1]) ? $res[1] : false;
}


function moveSourceFiles($params)
{
    print "----------\n";
    print "Soll der Inhalt von folgendem Verzeichnis ". $params["sourceBasePath"] . " in das Verzeichnis " . $params["targetBasePath"] . " kopiert werden?";
    print "\n[j,n] ";
    $choice = rtrim(fgets(STDIN));
    if ($choice !== 'j') {
        return;
    }

    $cmd = "find " . $params["sourceBasePath"] . "  -maxdepth 1 -mindepth 1 -type d";
    exec($cmd, $dirs);

    foreach ($dirs as $dir) {
        print "----------\n$dir\n";
        $cmd = "rsync -av " . $dir . " " . $params["targetBasePath"] . "/";
        exec($cmd, $output);
        print implode("\n", $output);
    }

    print "\n\n";

    return;
}

function equalizeTifSuffix($config)
{
    $sourcePath = $config->LOCALCONFIG->sourcePath;
    $cmd1 = "find " . $sourcePath . " -type f -name '*.TIF' -exec rename 's/\.TIF$/.tif/' {} \;";
    $cmd2 = "find " . $sourcePath . " -type f -name '*.TIFF' -exec rename 's/\.TIFF$/.tif/' {} \;";
    $cmd3 = "find " . $sourcePath . " -type f -name '*.tiff' -exec rename 's/\.tiff$/.tif/' {} \;";

    print "----------\n";
    print "Folgender Commands werden ausgeführt:\n";
    print $cmd1 . "\n";
    print $cmd2 . "\n";
    print $cmd3 . "\n";


    print "\nPasst das so? [j,n] ";
    $choice = rtrim(fgets(STDIN));

    if ($choice !== 'j') {
        exitScript();
    }

    exec($cmd1, $output);
    print implode("\n", $output);

    exec($cmd2, $output);
    print implode("\n", $output);

    exec($cmd3, $output);
    print implode("\n", $output);
}


function moveArtefacts($args)
{

    $originals = $args["originals"];
    $sourcePath = $args["sourcePath"];
    $mergedSourcePath = $args["mergedSourcePath"];
    $from = $args["from"];

    $cmd = "find " . $from . " -maxdepth 1 -type d -exec basename {} \;";

    print "----------\n";
    print "Folgender Command wird ausgeführt:\n";
    print $cmd . "\n";

    print "\nPasst das so? [j,n] ";
    $choice = rtrim(fgets(STDIN));

    if ($choice !== 'j') {
        exitScript();
    }

    exec($cmd, $artefacts);

    foreach ($artefacts as $artefact) {
        $cmd = "find " . $originals . " -maxdepth 2 -type d -name " . $artefact;
        $output = [];
        print "$cmd\n";
        exec($cmd, $output);

        if (count($output) == 0) {
            print "Kein Ordner für " . $artefact . " gefunden.\n";
            print "----------\n";
            continue;
        }

        $cmd = "mv " . $output[0] . " " . $mergedSourcePath . "/";
        exec($cmd, $output);

        print implode("\n\n", $output);
    }
}

function removeItemsThatWillBeReplacedLater($config)
{

    $toBeMergedSubfolder = $config->LOCALCONFIG->toBeMergedSubfolder;
    $removeItemList = $config->LOCALCONFIG->removeItemList;
    $mergedSourcePath = $config->LOCALCONFIG->mergedSourcePath;

    $removeItemListPath = $config->LOCALCONFIG->sourcePath . "/" . $removeItemList;
    $itemList = file_get_contents($removeItemListPath);
    $items = explode("\n", $itemList);

    foreach ($items as $item) {
        $item = trim($item);
        if (empty($item)) {
            continue;
        }
        $cmd = "find " . $mergedSourcePath . " -name '" . $item . ".tif' -type f -exec rm {} \;";
        print "----------\n";
        print "Folgender Command wird ausgeführt:\n";
        print $cmd . "\n";

        print "\nPasst das so? [j,n] ";
        $choice = rtrim(fgets(STDIN));

        if ($choice !== 'j') {
            exitScript();
        }

        exec($cmd, $output);
        print implode("\n", $output);
    }

}

function getArtefactsForUpdate($config)
{
    $originals = $config->LOCALCONFIG->originals->base;
    $sourcePath = $config->LOCALCONFIG->sourcePath;
    $mergedSourcePath = $config->LOCALCONFIG->mergedSourcePath;
    $toBeMergedSubfolder = $config->LOCALCONFIG->toBeMergedSubfolder;

    $args = [
        "originals" => $originals,
        "sourcePath" => $sourcePath,
        "mergedSourcePath" => $mergedSourcePath,
        "from" => $sourcePath . "/" . $toBeMergedSubfolder->renameOrDeleteFirst
    ];
    moveArtefacts($args);

    $args["from"] = $sourcePath . "/" . $toBeMergedSubfolder->addImages;
    moveArtefacts($args);

}

function rsyncFiles($params)
{
    print "----------\n";
    print "Soll der Inhalt von folgendem Verzeichnis ". $params["sourceBasePath"] . " in das Verzeichnis " . $params["targetBasePath"] . " kopiert werden? Das kann eine Weile dauern.";
    print "\n[j,n] ";
    $choice = rtrim(fgets(STDIN));
    if ($choice !== 'j') {
        return;
    }

    $cmd = "rsync -av " . $params["sourceBasePath"] . " " . $params["targetBasePath"] . "/";
    exec($cmd, $output);
    print implode("\n", $output);
    print "\n\n";

    return;
}

function subivideArchivalsIntoSubfolder($config)
{

    $sourcePath = $config->LOCALCONFIG->sourcePathArchivals;
    $sourcePathData = $config->LOCALCONFIG->sourcePathData;
    $targetPath = $config->LOCALCONFIG->sourcePathArchivalsWithSubfolder;
    $archivalsListSrc = $sourcePathData . "/". $config->LOCALCONFIG->archivalsItemList;

    $archivalsList = explode("\n", file_get_contents($archivalsListSrc));



    $cmd = "find " . $sourcePath . "/ -maxdepth 1 -type f -name '*.tif' -exec basename {} \;";
    exec($cmd, $itemsToBeImported);

    foreach ($archivalsList as $archivalItem) {
        $archival = trim($archivalItem);
        if (empty($archivalItem)) {
            continue;
        }

        foreach ($itemsToBeImported as $item) {
            $item = trim($item);
            if (empty($item)) {
                continue;
            }

            $itemBasename = preg_replace("/\.tif/", "", $item);

            $archivalItem = preg_replace("/\s/", "", $archivalItem);
            $archivalItem = preg_replace("/\//", "", $archivalItem);
            $archivalItem = preg_replace("/\./", "", $archivalItem);

            if (strpos($itemBasename, $archivalItem) !== false) {
                $cmd = "mkdir -p " . $targetPath . "/" . $archivalItem .
                    " && mkdir -p " . $targetPath . "/" . $archivalItem . "/01_Overall" .
                    " && mv " . $sourcePath . "/" . $item . " " . $targetPath . "/" . $archivalItem . "/01_Overall/" . $item;
                exec($cmd, $output);
                print implode("\n", $output);
            }
        }
    }
    exit;
    foreach ($res as $item) {
        $item = trim($item);
        if (empty($item)) {
            continue;
        }

        $itemBasename = preg_replace("/\.tif/", "", $item);
        $tempArchivalsList = $archivalsList;

        var_dump(array_filter($tempArchivalsList, function ($archival) {
            global $itemBasename;
            var_dump($archival, $itemBasename);
        }, ARRAY_FILTER_USE_BOTH));

        //var_dump($basename);
    }

    exit;
    var_dump($sourcePath, $targetPath);
    exit;

}

function addImagesToArtefacts($config)
{
    $sourcePath = $config->LOCALCONFIG->sourcePath;
    $addImagesFolder = $sourcePath ."/". $config->LOCALCONFIG->toBeMergedSubfolder->addImages;
    $renameOrDeleteFirstFolder = $sourcePath ."/". $config->LOCALCONFIG->toBeMergedSubfolder->renameOrDeleteFirst;
    $newObjectsFolder = $sourcePath ."/". $config->LOCALCONFIG->toBeMergedSubfolder->newObjects;
    $mergedSourcePath = $config->LOCALCONFIG->mergedSourcePath;

    $params = [
        "sourceBasePath" => $addImagesFolder . "/",
        "targetBasePath" => $mergedSourcePath . "/"
    ];
    rsyncFiles($params);

    $params = [
        "sourceBasePath" => $renameOrDeleteFirstFolder . "/",
        "targetBasePath" => $mergedSourcePath . "/"
    ];
    rsyncFiles($params);

    $params = [
        "sourceBasePath" => $newObjectsFolder . "/",
        "targetBasePath" => $mergedSourcePath . "/"
    ];
    rsyncFiles($params);
}

function renameItems($config)
{
    $toBeMergedSubfolder = $config->LOCALCONFIG->toBeMergedSubfolder;
    $renameItemList = $config->LOCALCONFIG->renameItemList;
    $mergedSourcePath = $config->LOCALCONFIG->mergedSourcePath;

    $renameItemListPath = $config->LOCALCONFIG->sourcePath . "/" . $renameItemList;
    $itemList = file_get_contents($renameItemListPath);
    $rows = explode("\n", $itemList);


    foreach ($rows as $row) {
        $items = explode(";", trim($row));

        list($oldItemName, $newItemName) = $items;

        if (empty($oldItemName)) {
            continue;
        }
        if (empty($newItemName)) {
            continue;
        }


        //$cmd = "find " . $mergedSourcePath . " -name '" . $item . ".tif' -type f -exec rename 's/\.tif$/.old.tif/' {} \;";
        $cmd = "find " . $mergedSourcePath . " -name '" . $oldItemName . ".tif' -type f ";
        exec($cmd, $output);

        if (count($output) == 0) {
            print "----------\n";
            print "Keine Datei für " . $oldItemName . " gefunden.\n";
            continue;
        }

        $cmd = "mv " . $output[0] . " " . $mergedSourcePath . "/" . $newItemName . ".tif";

        print "----------\n";
        print "Folgender Command wird ausgeführt:\n";
        print $cmd . "\n";

        print "\nPasst das so? [j,n] ";
        $choice = rtrim(fgets(STDIN));

        if ($choice !== 'j') {
            exitScript();
        }

        exec($cmd, $output);

    }
}

function showMainMenu($config)
{
    $actions = [
        "subdivide-archivals-into-subfolder" => "Step 1: Archivalien in Unterordner aufteilen.",
        "equalize-tif-suffix" => "Step 2: Suffix von TIF Dateien angleichen. Diese sind mal in Versalien und mal nicht.",
        "get-artefacts-for-update" => "Step 3: Ordner/ Artefakte holen, die aktualisiert werden sollen.",
        "remove-items" => "Step 4: Dateien löschen, die später ersetzt werden.",
        "rename-items" => "Step 5: Dateien umbenennen, wo später neue Abbildungen eingeschoben werden.",
        "add-images-to-artefacts" => "Step 6: Artefakte um Abbildungen ergaenzen.",
        "exit" => "Skript beenden",
    ];

    print "\n#############################################################################\n";
    print "Cranach Update Images\n\n";
    print <<<EOT
Hier werden einige Funktionen angeboten, um Abbildungen zu aktualisieren. Mehr Infos dazu unter:
https://lucascranach.org/intern/blog/2024/04/04/update-von-abbildungen.html

Die Abbildungen werden hier erwartet: 
EOT;

    print $config->LOCALCONFIG->sourcePath . "\n\n";

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
    if (!array_key_exists($choice, $options)) {
        print "\nDiese Aktion ist nicht verfügbar.\n\n";
        exit;
    }

    switch ($options[$choice]) {

        case "subdivide-archivals-into-subfolder":
            print "\nArchivalien in Unterordner aufteilen.\n";
            subivideArchivalsIntoSubfolder($config);
            exitScript();
            break;

        case "equalize-tif-suffix":
            print "\nSuffix von TIF Dateien angleichen …\n";
            equalizeTifSuffix($config);
            exitScript();
            break;

        case "get-artefacts-for-update":
            print "\nOrdner/ Artefakte holen, die aktualisiert werden sollen …\n";
            getArtefactsForUpdate($config);
            exitScript();
            break;

        case "remove-items":
            print "\nDateien löschen, die später ersetzt werden …\n";
            removeItemsThatWillBeReplacedLater($config);
            exitScript();
            break;

        case "rename-items":
            print "\nDateien umbenennen, woe später neue Abbildungen eingeschoben werden …\n";
            renameItems($config);
            exitScript();
            break;

        case "add-images-to-artefacts":
            print "\nArtefakte um Abbildungen ergaenzen …\n";
            addImagesToArtefacts($config);
            exitScript();
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
