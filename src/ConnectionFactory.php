<?php

namespace Framework;

use PDO;

class ConnectionFactory
{
    private static $factory;
    private $pdo;

    public static function getFactory()
    {
        if (!self::$factory) {
            self::$factory = new ConnectionFactory();
        }
        return self::$factory;
    }

    public function getConnection()
    {
        try {
            $dBName=Config::getDBName();
            $config = Config::getConfig();
            if (!$this->pdo) {
                $this->pdo = new \PDO(sprintf("mysql:host=%s;dbname=%s", $config["db"]["server"], $dBName), $config["db"]["user"], $config["db"]["password"]);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdo->exec("set names utf8");
                return $this->pdo;
            }
            return $this->pdo;
        } catch (\PDOException $e) {
            Logger::log("Erro ao conectar no banco de dados: " . $e->getMessage());
            return null;
        }
    }
}
