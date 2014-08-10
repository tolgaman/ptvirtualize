<?php

Namespace Model;

class Box extends BaseModelFactory {

    public static function getModel($params) {
        $thisModule = substr(get_called_class(), 6) ;
        $model = \Model\SystemDetectionFactory::getCompatibleModel($thisModule, "Initializer", $params);
        return $model;
    }

}