<?php

Namespace Model;

class BaseVirtualboxAllOS extends Base {

    // Compatibility
    public $os = array("any") ;
    public $linuxType = array("any") ;
    public $distros = array("any") ;
    public $versions = array("any") ;
    public $architectures = array("any") ;

    // Model Group
    public $modelGroup = array("Base") ;

    public function isVMInStatus($vm, $statusRequested) {
        $command = VBOXMGCOMM." showvminfo \"{$vm}\" " ;
        $out = $this->executeAndLoad($command);
        $outLines = explode("\n", $out);
        $outStr = "" ;
        foreach ($outLines as $outLine) {
            if (strpos($outLine, "State:") !== false) {
                $outStr .= $outLine."\n" ;
                break; } }
        if (!is_array($statusRequested)) {$statusRequested = array($statusRequested);}
        foreach ($statusRequested as $stat) {
            if (strpos($outStr, strtolower($stat))==true) {
                return true ; } }
        return false ;
    }

}