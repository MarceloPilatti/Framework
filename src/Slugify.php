<?php

namespace Framework;

abstract class Slugify
{
    public static function get($str)
    {
        if (is_string($str)) {
            $str = strtolower(trim(utf8_decode($str)));
            $before = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿRr';
            $after = 'aaaaaaaceeeeiiiidnoooooouuuuybsaaaaaaaceeeeiiiidnoooooouuuyybyRr';
            $str = strtr($str, utf8_decode($before), $after);
            $replace = array('/[^a-z0-9.-]/' => '-', '/-+/' => '-', '/\-{2,}/' => '');
            $str = preg_replace(array_keys($replace), array_values($replace), $str);
        }
        return $str;
    }
}