<?php

Namespace Model;

class BoxUpImportMac extends BaseFunctionModel {

    // Compatibility
    public $os = array("Darwin") ;
    public $linuxType = array("any") ;
    public $distros = array("any") ;
    public $versions = array("any") ;
    public $architectures = array("any") ;

    // Model Group
    public $modelGroup = array("UpImport") ;

    //@todo need windows version
    public function import($file, $ostype, $name) {
        $command  = VBOXMGCOMM."import {$file} --vsys 0 --ostype {$ostype}" ;
        $command .= " --vmname {$name}" ;
        $ret = $this->executeAndGetReturnCode($command);
//        $command = "echo $?" ;
//        $ret = $this->executeAndLoad($command);
        return (in_array($ret, array("0","1"))) ? true : false ;
    }

    public function getResumableStates() {
        return array("paused") ;
    }

}