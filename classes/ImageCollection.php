<?php

class ImageCollection
{
    public $images = array();

    public function __construct(Array $params)
    {
        $this->params = $params;
        $cmd = "find " . $this->params["source"] . " -maxdepth 6 -mtime " . $this->params["period"] . " \( -not -path '*_files/*' -and -name '" . $this->params["pattern"] . "' \) ";
        exec($cmd, $this->images);
    }

    public function getSize()
    {
        return count($this->images);
    }
}