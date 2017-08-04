<?php

define('_PS_ROOT_DIR_', dirname(__DIR__));

require_once 'cwgecommerce.php';

class Module
{
    public function __construct()
    {
    }

    public function l($text)
    {
        return $text;
    }

    public function install()
    {
        return true;
    }

    public function uninstall()
    {
        return true;
    }

    public function display($template_path, $template_name, $id_cache)
    {
        return '';
    }

    public function getCacheId($name = null)
    {
        return $name ?? $this->name;
    }
}

class Cookie
{
    public function __unset($name)
    {
    }
}

class Tools
{
    public function getValue($value, $default)
    {
        return $_GET[$value] ?? $default;
    }

    public function getIsset($value)
    {
        return isset($_GET[$value]);
    }

    public function jsonEncode($data)
    {
        return json_encode($data);
    }

    public function jsonDecode($data, $assoc)
    {
        return json_decode($data, $assoc);
    }

    public function safeOutput($string)
    {
        return htmlentities($string, ENT_QUOTES);
    }
}
