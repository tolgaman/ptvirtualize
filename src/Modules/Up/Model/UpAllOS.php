<?php

Namespace Model;

class UpAllOS extends BaseFunctionModel {

    // Compatibility
    public $os = array("any") ;
    public $linuxType = array("any") ;
    public $distros = array("any") ;
    public $versions = array("any") ;
    public $architectures = array("any") ;

    // Model Group
    public $modelGroup = array("Default") ;

    protected $source ;

    public function __construct($params) {
        parent::__construct($params);
        $this->initialize();
    }

    public function doUp() {
        $loggingFactory = new \Model\Logging();
        $logging = $loggingFactory->getModel($this->params) ;
        $res = $this->loadFiles();
        if ($res === false) {
            $logging->log("Up module was unable to load a Virtufile", $this->getModuleName(), LOG_FAILURE_EXIT_CODE) ;
            return false ; }
        $this->findProvider("UpOther");
        $this->setLogSource();
        $o = $this->virtufile ;
        if (property_exists($o, "files")) { $res = $this->doMultiUp() ; }
        else { $res = $this->doSingleUp() ; }
        return $res ;
    }

    public function setLogSource() {
        $this->loadFiles();
        $this->findProvider("UpOther");
        $this->source = (isset($this->params["pfile"]) && $this->params["pfile"] != "Virtufile" ) ? $this->params["pfile"] : "" ;
    }

    protected function doSingleUp() {
        $loggingFactory = new \Model\Logging();
        $logging = $loggingFactory->getModel($this->params) ;

        if (!$this->vmExistsInProvider()) {

            if ($this->isSavedInPapyrus()) {
                $logging->log(
                    "This VM is saved in your Papyrus file, but does not exist in your provider...",
                    $this->getModuleName() ) ; }

            $logging->log("Non existent machine. Creating from scratch.", $this->getModuleName());
            $this->deleteFromPapyrus();
            $res = $this->completeBuildUp();
            if ($res==true) { return true ; }
            else {
                $logging->log("Up module failed.", $this->getModuleName(), LOG_FAILURE_EXIT_CODE) ;
                return false ; } }

        if ($this->isSavedInPapyrus() && $this->vmExistsInProvider()) {
            $logging->log("This VM is saved in your Papyrus file, trying...", $this->getModuleName());
            if ($this->vmIsRunning()) {
                $logging->log("This VM is already up and running.", $this->getModuleName());
                return true ; }
            else {
                $logging->log("This VM is not running.", $this->getModuleName()); } }

        if ($this->vmExistsInProvider()) {
            $logging->log("This VM exists in your provider, trying...", $this->getModuleName());
            if ($this->vmIsRunning()) {
                $logging->log("This VM is already up and running.", $this->getModuleName());
                return true ; }
            else {
                $logging->log("This VM is not running.", $this->getModuleName()); }  }

        $smp = $this->startModPro() ;

        return $smp ;

    }

    protected function startModPro() {
        $loggingFactory = new \Model\Logging() ;
        $logging = $loggingFactory->getModel($this->params) ;
        $logging->log("Virtualize will start and optionally modify and provision your existing VM.", $this->getModuleName());
        $res = $this->runHook("up", "pre") ;
        if (in_array(false, $res)) { return false ; }
        $logging->log("This VM has been deleted outside of Virtualize. Re-creating from scratch.", $this->getModuleName());
        $this->modifyVm(true);
        $this->startVm();
        $this->provisionVm(true);
        $res = $this->runHook("up", "post") ;
        if (in_array(false, $res)) { return false ; }
        $this->postUpMessage();
        return true ;
    }


    protected function doMultiUp() {
        $loggingFactory = new \Model\Logging() ;
        $logging = $loggingFactory->getModel($this->params) ;
        $logging->log("Using multiple Virtufile setup", $this->getModuleName());
        // Please enter the following...
        // All boxes ?
        if (!isset($this->params["up-all"])) {
            $question = "Bring all Boxes up?" ;
            $this->params["up-all"] = self::askYesOrNo($question) ; }
        // Which boxes? (if yes above ignore this)
        if ($this->params["up-all"] == false && !isset($this->params["pfiles"])) {
            $question = "Which Virtufile/s should I execute (Comma-Separated)?" ;
            $this->params["pfiles"] = self::askForInput($question) ; }
        if ($this->params["up-all"] == true) { $pfilesToExecute = $this->virtufile->files ; }
        else { $pfilesToExecute = $this->params["pfiles"] ; }
        // @todo should we just have parallax in ptv, otherwise we are creating an unneccessary dependency
        $ptconfigureCommand = PTCCOMM." parallax cli --yes --guess " ;
        for ($i = 0; $i < count($pfilesToExecute); $i++) {
            $cnum = $i + 1 ;
            $ptconfigureCommand .= "--command-{$cnum}=\"ptvirtualize up now --yes --guess --pfile={$this->virtufile->files[$i]} \" " ; }
        echo $ptconfigureCommand."\n" ;
        $res = self::executeAndOutput($ptconfigureCommand) ;
        if ($res==0) { return true ; }
        else {
            $logging->log("Up module failed while bringing up multiple boxes", $this->getModuleName(), LOG_FAILURE_EXIT_CODE) ;
            return false ; }
    }

    public function doReload() {
        $this->loadFiles();
        $loggingFactory = new \Model\Logging();
        $logging = $loggingFactory->getModel($this->params) ;
        $logging->log("Halting Machine...", $this->getModuleName());
        $haltFactory = new \Model\Halt() ;
        $halt = $haltFactory->getModel($this->params) ;
        $halt->haltNow();
        $logging->log("Bringing Machine up with Modifications and Provisioning...", $this->getModuleName());
        $this->doUp();
    }

    protected function postUpMessage() {
        $loggingFactory = new \Model\Logging();
        $logging = $loggingFactory->getModel($this->params) ;
        $logging->log("{$this->virtufile->config["vm"]["post_up_message"]}", $this->getModuleName());
    }

    protected function completeBuildUp() {
        $loggingFactory = new \Model\Logging();
        $logging = $loggingFactory->getModel($this->params) ;
        $this->runHook("up", "pre") ;
        $res = $this->importBaseBox();
        if ($res == false) {
            \Core\BootStrap::setExitCode(1);
            $logging->log("Importing Base Box Failed", $this->getModuleName());
            return false; }
        $res = $this->modifyVm();
        if ($res == false) {
            \Core\BootStrap::setExitCode(1);
            $logging->log("Modifying VM Failed", $this->getModuleName());
            return false; }
        $res = $this->startVm();
        if ($res == false) {
            \Core\BootStrap::setExitCode(1);
            $logging->log("Starting Virtual Machine Failed", $this->getModuleName());
            return false; }
        $res = $this->provisionVm();
        if ($res == false) {
            \Core\BootStrap::setExitCode(1);
            $logging->log("Provisioning Virtual Machine Failed", $this->getModuleName());
            return false; }
        $this->runHook("up", "post") ;
        $this->postUpMessage();
    }

    protected function isSavedInPapyrus() {
        if ( count($this->papyrus)>1 ) { return true ; }
        return false ;
    }

    protected function vmExistsInProvider() {
        return $this->provider->vmExists($this->virtufile->config["vm"]["name"]);
    }

    protected function vmIsRunning() {
        return $this->provider->vmIsRunning($this->virtufile->config["vm"]["name"]);
    }

    protected function importBaseBox() {
        $upFactory = new \Model\Up();
        $importBox = $upFactory->getModel($this->params, "ImportBaseBox") ;
        $importBox->papyrus = $this->papyrus ;
        $importBox->virtufile = $this->virtufile ;
        return $importBox->performImport() ;
    }

    protected function modifyVm($onlyIfRequestedByParam = false) {
        if ($onlyIfRequestedByParam == true) {
            if ($this->getParamBySynonym("modify") !== true ) {
                $loggingFactory = new \Model\Logging();
                $logging = $loggingFactory->getModel($this->params) ;
                $logging->log("Not modifying as modify parameter not set", $this->getModuleName());
                return true ; } }
        $upFactory = new \Model\Up();
        $modifyVM = $upFactory->getModel($this->params, "ModifyVM") ;
        $modifyVM->papyrus = $this->papyrus ;
        $modifyVM->virtufile = $this->virtufile ;
        return $modifyVM->performModifications() ;
    }

    protected function startVm() {
        $loggingFactory = new \Model\Logging();
        $logging = $loggingFactory->getModel($this->params) ;
        if (isset($this->virtufile->config["vm"]["gui_mode"])) {
            $logging->log("Using {$this->virtufile->config["vm"]["gui_mode"]} GUI mode specified in Virtufile", $this->getModuleName());
            $guiMode = $this->virtufile->config["vm"]["gui_mode"] ; }
        else {
            if (isset($this->params["guess"])) {
                $logging->log("No GUI mode explicitly set, Guess parameter set, defaulting to headless GUI mode...", $this->getModuleName());
                $guiMode = "headless" ; }
            else {
                $logging->log("No GUI mode or Guess parameter set, defaulting to headless GUI mode...", $this->getModuleName());
                $guiMode = "headless" ; } }
        $logging->log("Starting Virtual Machine", $this->getModuleName());
        $command = VBOXMGCOMM." startvm {$this->virtufile->config["vm"]["name"]} --type $guiMode" ;
        $res = $this->executeAndGetReturnCode($command, true, true);
        return ($res["rc"] == 0) ? true : false ;
    }

    protected function provisionVm($onlyIfRequestedByParam = false) {
        $loggingFactory = new \Model\Logging();
        $logging = $loggingFactory->getModel($this->params) ;
        if ($onlyIfRequestedByParam == true) {
            if ($this->getParamBySynonym("provision") == true ) {
                $logging->log("Not provisioning as provision parameter not set", $this->getModuleName());
                return true; } }
        if (isset($this->params["hooks"])) {
            $logging->log("Specific execution hooks requested, {$this->params["hooks"]}", $this->getModuleName());
            $hooks = $this->getParameterHooks();
            $pns = array();
            foreach ($hooks as $hook) {
                $pns[] = $this->runHook("up", $hook); }
            return (in_array(false, $pns)) ? false : true ; }
        $pn = $this->runHook("up", "default");
        return $pn ;
    }

    protected function getParamBySynonym($param) {
        $ray["modify"] = array("modify", "mod") ;
        $ray["provision"] = array("provision", "pro") ;
        foreach($ray[$param] as $entry) {
            if (isset($this->params[$entry])) {
                $this->params[$param] = true ; } }
        return $this->params[$param] ;
    }


    protected function getParameterHooks() {
        if (!isset($this->params["hooks"])) { return array() ; }
        $tags = explode(",", $this->params["hooks"]) ;
        return $tags ;
    }

    protected function deleteFromPapyrus() {
        \Model\AppConfig::deleteProjectVariable($this->virtufile->config["vm"]["name"], null, null, true) ;
    }

    protected function runHook($hook, $type) {
        $loggingFactory = new \Model\Logging();
        $logging = $loggingFactory->getModel($this->params) ;
        if (isset($this->params["ignore-hooks"]) ) {
            $logging->log("Not provisioning $hook $type hooks as ignore hooks parameter is set", $this->getModuleName());
            return true ; }
        $logging->log("Provisioning $hook $type hooks", $this->getModuleName());
        $provisionFactory = new \Model\Provision();
        $provision = $provisionFactory->getModel($this->params) ;
        return $provision->provisionHook($hook, $type);
    }

}