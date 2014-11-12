<?php

Namespace Model;

class StatusAllOS extends BaseLinuxApp {

    // Compatibility
    public $os = array("any") ;
    public $linuxType = array("any") ;
    public $distros = array("any") ;
    public $versions = array("any") ;
    public $architectures = array("any") ;

    // Model Group
    public $modelGroup = array("Default") ;

    protected $phlagrantfile;
    protected $papyrus ;

    public function __construct($params) {
        parent::__construct($params);
        $this->initialize();
    }

    public function statusShow() {
        $this->loadFiles();
        $command = VBOXMGCOMM." showvminfo \"{$this->phlagrantfile->config["vm"]["name"]}\"  " ;
        $out = $this->executeAndLoad($command);
        $outLines = explode("\n", $out);
        foreach ($outLines as $outLine) {
            if (strpos($outLine, "State:") !== false) {
                return $outLine."\n" ; } }
        return null ;
    }

    public function statusFull() {
        $this->loadFiles();
        $command = VBOXMGCOMM." showvminfo \"{$this->phlagrantfile->config["vm"]["name"]}\" " ;
        $status = $this->executeAndLoad($command) ;
        return $status ;
    }

    protected function loadFiles() {
        $this->phlagrantfile = $this->loadPhlagrantFile();
        $this->papyrus = $this->loadPapyrusLocal();
    }

    protected function loadPhlagrantFile() {
        $prFactory = new \Model\PhlagrantRequired();
        $phlagrantFileLoader = $prFactory->getModel($this->params, "PhlagrantFileLoader") ;
        return $phlagrantFileLoader->load() ;
    }

    protected function loadPapyrusLocal() {
        $prFactory = new \Model\PhlagrantRequired();
        $papyrusLocalLoader = $prFactory->getModel($this->params, "PapyrusLocalLoader") ;
        return $papyrusLocalLoader->load($this->phlagrantfile) ;
    }

}