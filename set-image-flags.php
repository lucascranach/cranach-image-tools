<?php

/**
 * set-image-flags.php
 *
 * Setzt die IPTC-Flags für "Download" und "Wasserzeichen" für alle TIFF-Dateien
 * in einem Verzeichnis und allen Unterverzeichnissen.
 *
 * Die Flags werden im Projekt – vgl. ImageOperations::parseSpecialInstructions() –
 * als kommaseparierte Tokens im Feld IPTC:SpecialInstructions abgelegt:
 *
 *   download      => Bild darf heruntergeladen werden      (hasDownload = true)
 *   no-watermark  => Bild bekommt KEIN Wasserzeichen       (noWatermark = true)
 *
 * Aufruf:
 *   php set-image-flags.php -d /pfad/zum/verzeichnis
 *   php set-image-flags.php -d /pfad -p '*.tif' --download=true --watermark=false -n
 *
 * Optionen:
 *   -d, --dir=PFAD        Quellverzeichnis (Pflicht), wird rekursiv durchsucht
 *   -p, --pattern=GLOB    Dateimuster, mehrere kommasepariert. Default: *.tif,*.tiff
 *       --download=BOOL   Download-Flag setzen. Default: true
 *       --watermark=BOOL  Wasserzeichen. Default: false (= Token 'no-watermark' wird gesetzt)
 *   -r, --replace         SpecialInstructions komplett überschreiben statt zu ergänzen
 *   -n, --dry-run         Nur anzeigen, was passieren würde – nichts schreiben
 *   -y, --yes             Ohne Rückfrage durchlaufen
 *   -b, --backup          Originaldateien als *_original behalten
 *   -h, --help            Diese Hilfe
 */

error_reporting(E_ALL);

const TOKEN_DOWNLOAD     = 'download';
const TOKEN_NO_WATERMARK = 'no-watermark';

$params = getCliOptions();

if ($params['help'] || $params['dir'] === null) {
    printUsage();
    exit($params['help'] ? 0 : 1);
}

if (!is_dir($params['dir'])) {
    print "\nFehler: '" . $params['dir'] . "' ist kein Verzeichnis.\n\n";
    exit(1);
}

checkExiftool();

$images = getImages($params['dir'], $params['pattern']);

if (sizeof($images) === 0) {
    print "\nKeine passenden Dateien gefunden.\n\n";
    exit(0);
}

confirmParams($params, sizeof($images));

$jobs = buildJobs($images, $params);
writeJobs($jobs, $params);

exitScript();

/* -------------------------------------------------------------------------- */

function getCliOptions()
{
    $options = getopt(
        "d:p:rnybh",
        ["dir:", "pattern:", "download:", "watermark:", "replace", "dry-run", "yes", "backup", "help"]
    );

    $ret = [
        "dir"       => firstOption($options, ["d", "dir"]),
        "pattern"   => firstOption($options, ["p", "pattern"]) ?? "*.tif,*.tiff",
        "download"  => toBool(firstOption($options, ["download"]), true),
        "watermark" => toBool(firstOption($options, ["watermark"]), false),
        "replace"   => hasOption($options, ["r", "replace"]),
        "dryRun"    => hasOption($options, ["n", "dry-run"]),
        "yes"       => hasOption($options, ["y", "yes"]),
        "backup"    => hasOption($options, ["b", "backup"]),
        "help"      => hasOption($options, ["h", "help"]),
    ];

    if ($ret["dir"] !== null) {
        $ret["dir"] = rtrim($ret["dir"], "/");
    }

    return $ret;
}

function firstOption($options, $keys)
{
    foreach ($keys as $key) {
        if (isset($options[$key])) {
            return is_array($options[$key]) ? end($options[$key]) : $options[$key];
        }
    }
    return null;
}

function hasOption($options, $keys)
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $options)) {
            return true;
        }
    }
    return false;
}

function toBool($value, $default)
{
    if ($value === null) {
        return $default;
    }
    return in_array(strtolower(trim($value)), ["true", "1", "yes", "y", "j", "ja"], true);
}

function printUsage()
{
    print "\nsetzt die IPTC-Flags 'download' und 'no-watermark' rekursiv für TIFF-Dateien.\n\n";
    print "  php set-image-flags.php -d /pfad/zum/verzeichnis [Optionen]\n\n";
    print "  -d, --dir=PFAD        Quellverzeichnis (Pflicht), rekursiv\n";
    print "  -p, --pattern=GLOB    Dateimuster, kommasepariert. Default: *.tif,*.tiff\n";
    print "      --download=BOOL   Download-Flag. Default: true\n";
    print "      --watermark=BOOL  Wasserzeichen. Default: false\n";
    print "  -r, --replace         SpecialInstructions überschreiben statt ergänzen\n";
    print "  -n, --dry-run         Nur anzeigen, nichts schreiben\n";
    print "  -y, --yes             Ohne Rückfrage durchlaufen\n";
    print "  -b, --backup          Originale als *_original behalten\n";
    print "  -h, --help            Diese Hilfe\n\n";
}

function checkExiftool()
{
    $ret = shell_exec("command -v exiftool");
    if ($ret === null || trim((string) $ret) === '') {
        print "\nFehler: exiftool ist nicht installiert bzw. nicht im PATH.\n\n";
        exit(1);
    }
}

function getImages($dir, $pattern)
{
    $patterns = array_filter(array_map('trim', explode(',', $pattern)));

    $nameExpressions = [];
    foreach ($patterns as $singlePattern) {
        $nameExpressions[] = "-iname " . escapeshellarg($singlePattern);
    }

    $cmd = "find " . escapeshellarg($dir) . " -type f \\( " . implode(' -o ', $nameExpressions) . " \\)";

    $images = [];
    exec($cmd, $images);
    sort($images);

    return $images;
}

function confirmParams($params, $imageCount)
{
    print "----------\n";
    print "Verzeichnis: " . $params["dir"] . "\n";
    print "Pattern: " . $params["pattern"] . "\n";
    print "Download: " . ($params["download"] ? "true" : "false") . "\n";
    print "Wasserzeichen: " . ($params["watermark"] ? "true" : "false")
        . ($params["watermark"] ? "" : " (Token '" . TOKEN_NO_WATERMARK . "' wird gesetzt)") . "\n";
    print "Modus: " . ($params["replace"] ? "überschreiben" : "ergänzen") . "\n";
    print "Backup: " . ($params["backup"] ? "ja (*_original)" : "nein") . "\n";
    print "Dry-Run: " . ($params["dryRun"] ? "ja" : "nein") . "\n";
    print "Gefundene Dateien: " . $imageCount . "\n";

    if ($params["yes"]) {
        return true;
    }

    print "\nSollen diese Dateien verarbeitet werden? [j,n] ";
    $choice = rtrim(fgets(STDIN));

    if ($choice !== 'j') {
        exitScript();
    }
    return true;
}

/**
 * Liest die vorhandenen SpecialInstructions in einem einzigen exiftool-Aufruf.
 * Rückgabe: [ absoluterPfad => vorhandenerWert ]
 */
function readSpecialInstructions($images)
{
    $argFile = writeArgFile($images);
    $cmd     = "exiftool -charset utf8 -j -IPTC:SpecialInstructions -@ " . escapeshellarg($argFile) . " 2>/dev/null";
    $json    = shell_exec($cmd);
    unlink($argFile);

    $current = [];
    $data    = json_decode((string) $json, true);

    if (!is_array($data)) {
        return $current;
    }

    foreach ($data as $entry) {
        if (!isset($entry["SourceFile"])) {
            continue;
        }
        $current[$entry["SourceFile"]] = isset($entry["SpecialInstructions"])
            ? trim((string) $entry["SpecialInstructions"])
            : "";
    }

    return $current;
}

/**
 * Ermittelt pro Datei den neuen Feldinhalt und gruppiert die Dateien
 * nach identischem Zielwert, damit exiftool im Batch schreiben kann.
 */
function buildJobs($images, $params)
{
    $current = readSpecialInstructions($images);
    $jobs    = [];
    $skipped = 0;

    foreach ($images as $image) {
        $existing = isset($current[$image]) ? $current[$image] : "";
        $value    = buildSpecialInstructions($existing, $params);

        if (strtolower($existing) === strtolower($value)) {
            $skipped++;
            continue;
        }

        if (!isset($jobs[$value])) {
            $jobs[$value] = [];
        }
        array_push($jobs[$value], $image);
    }

    print "----------\n";
    print "Bereits korrekt gesetzt: $skipped\n";
    print "Zu ändern: " . (sizeof($images) - $skipped) . "\n\n";

    return $jobs;
}

/**
 * Baut den neuen Feldinhalt. Bereits vorhandene, fremde Tokens bleiben erhalten
 * (ausser im Replace-Modus); die beiden gesteuerten Tokens werden neu gesetzt.
 */
function buildSpecialInstructions($existing, $params)
{
    $tokens = [];

    if (!$params["replace"] && $existing !== "") {
        foreach (explode(',', $existing) as $token) {
            $token = trim($token);
            $lower = strtolower($token);
            if ($token === "" || $lower === TOKEN_DOWNLOAD || $lower === TOKEN_NO_WATERMARK) {
                continue;
            }
            array_push($tokens, $token);
        }
    }

    if ($params["download"]) {
        array_push($tokens, TOKEN_DOWNLOAD);
    }

    if (!$params["watermark"]) {
        array_push($tokens, TOKEN_NO_WATERMARK);
    }

    return implode(', ', $tokens);
}

function writeJobs($jobs, $params)
{
    if (sizeof($jobs) === 0) {
        print "Nichts zu tun.\n";
        return;
    }

    foreach ($jobs as $value => $images) {
        $label = $value === "" ? "<leer, Feld wird gelöscht>" : $value;
        print "----------\n";
        print sizeof($images) . " Datei(en) => IPTC:SpecialInstructions = \"$label\"\n";

        if ($params["dryRun"]) {
            foreach ($images as $image) {
                print "\t[dry-run] $image\n";
            }
            continue;
        }

        $argFile   = writeArgFile($images);
        $overwrite = $params["backup"] ? "" : "-overwrite_original";

        $cmd = "exiftool $overwrite -charset utf8 -codedcharacterset=utf8"
            . " -iptc:SpecialInstructions=" . escapeshellarg($value)
            . " -@ " . escapeshellarg($argFile);

        $result = shell_exec($cmd . " 2>&1");
        unlink($argFile);

        print trim((string) $result) . "\n";
    }
}

/**
 * Schreibt die Dateiliste in ein Argument-File für exiftool (-@),
 * damit auch sehr grosse Mengen ohne ARG_MAX-Probleme verarbeitet werden.
 */
function writeArgFile($images)
{
    $argFile = tempnam(sys_get_temp_dir(), "cda-flags-");
    file_put_contents($argFile, implode("\n", $images) . "\n");
    return $argFile;
}

function exitScript()
{
    print "\nfertig :)\n\n";
    exit;
}
