<?php

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