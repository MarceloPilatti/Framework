<?php

namespace Lib;

abstract class Filter
{
    public static function filterString($var)
    {
        return filter_var(trim($var), FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
    }

    public static function filterHtml($var)
    {
        return filter_var(trim($var), FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW);
    }

    public static function filterUrl($var)
    {
        return filter_var(trim($var), FILTER_SANITIZE_URL);
    }

    public static function filterEmail($var)
    {
        return filter_var(trim($var), FILTER_SANITIZE_EMAIL);
    }

    public static function filterRegex($name, $regex)
    {
        $matches = array();
        $validatedName = preg_match($regex, trim($name), $matches);
        if ($validatedName == 1) {
            return $matches[0];
        }
        return $name;
    }
}