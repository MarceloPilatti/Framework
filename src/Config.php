<?php

namespace Framework;

abstract class Config
{
    public static function getDBName()
    {
        $config = self::getConfig();
        $env = getenv('APPLICATION_ENV');
        $dBName = null;
        if ($env == 'production') {
            $dBName = $config['db']['dBNameProduction'];
        } else {
            if ($env == 'test') {
                $dBName = $config["db"]["dBNameTest"];
            } else {
                $dBName = $config['db']['dBName'];
            }
        }
        return $dBName;
    }

    public static function getConfig()
    {
        return include dirname(__DIR__, 4) .'/config/config.php';
    }

    public static function getVersion()
    {
        $config = self::getConfig();
        $version = $config['version'];
        return $version;
    }

    public static function getUpdatedAt()
    {
        $config = self::getConfig();
        $updatedAt = $config['updatedAt'];
        return $updatedAt;
    }

    public static function getMaintenance()
    {
        $config = self::getConfig();
        $maintenance = $config['maintenance'];
        return $maintenance;
    }

    public static function getBackTime()
    {
        $config = self::getConfig();
        $backTime = $config['backTime'];
        return $backTime;
    }
}