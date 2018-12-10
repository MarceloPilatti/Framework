<?php

namespace Lib;

abstract class Logger
{
    public static function log($msg, $level = LogLevel::ERROR, $file = false)
    {
        $levelStr = '';
        if ($level == LogLevel::INFO) {
            $levelStr = 'INFO';
        } elseif ($level == LogLevel::WARNING) {
            $levelStr = 'WARNING';
        } elseif ($level == LogLevel::ERROR) {
            $levelStr = 'ERROR';
        }
        $date = date('Y-m-d H:i:s');
        $msg = sprintf("[%s] [%s]: %s%s", $date, $levelStr, $msg, PHP_EOL);
        if ($file) {
            file_put_contents($file, $msg, FILE_APPEND);
        } else {
            error_log($msg, 0);
        }
    }
}