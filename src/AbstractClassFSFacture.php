<?php

namespace Finespirits\FsFacture;

if (!defined('ABSPATH')) {
    exit;
}

abstract class AbstractClassFSFacture {
    public function get($key = '') {
        if(!empty($key)) {
            if(isset($_GET[$key]) && !empty($_GET[$key])) {
                return $_GET[$key];
            } else return false;
        } else return $_GET;
    }

    public function post($key = '') {
        if(!empty($key)) {
            if(isset($_POST[$key]) && !empty($_POST[$key])) {
                return $_POST[$key];
            } else return false;
        } else return $_POST;
    }
}