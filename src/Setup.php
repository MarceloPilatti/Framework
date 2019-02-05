<?php

namespace Framework;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

abstract class Setup
{
    public static function run()
    {
        setlocale(LC_ALL, "pt_BR", "pt_BR.iso-8859-1", "pt_BR.utf-8", "portuguese");
        date_default_timezone_set('America/Sao_Paulo');

        $request = Request::createFromGlobals();
        $session = new Session();
        $request->setSession($session);

        $version = Config::getVersion();
        $updatedAt = Config::getUpdatedAt();
        $dBName = Config::getDBName();

        $env = getenv('APPLICATION_ENV');
        $session->set('minify', '');
        if ($env != "development") {
            $session->set('minify', '.min');
        }
        $session->set('version', $version);
        $session->set('updatedAt', $updatedAt);
        $session->set('dBName', $dBName);

        $routes = include dirname(__DIR__, 4) . "/app/config/routes.php";

        $app = new Application($routes);
        try {
            return $app->handle($request)->send();
        } catch (\Throwable $t) {
            return ApplicationError::showError($t, ErrorType::ERROR);
        }
    }
}