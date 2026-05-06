<?php

class ImageCollection
{
    public $images = array();
    public $params = array();

    public function __construct(array $params)
    {
        $this->params = $params;
        $cmd = "find " . $this->params["source"] . " -maxdepth 6 -mtime " . $this->params["period"] . " \( -not -path '*_files/*' -and -name '" . $this->params["pattern"] . "' \) ";
        var_dump($cmd);
        exec($cmd, $this->images);
    }

    public function getSize()
    {
        return count($this->images);
    }
}
