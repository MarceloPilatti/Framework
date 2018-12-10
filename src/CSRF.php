<?php

namespace Framework;

use Symfony\Component\HttpFoundation\Session\Session;

abstract class CSRF
{
    public static function generateFormToken()
    {
        $session = new Session();
        $token = password_hash(uniqid(time(), true), PASSWORD_BCRYPT);
        $session->set('csrf_token', $token);
        return $token;
    }

    public static function verifyFormToken($formToken)
    {
        $session = new Session();
        $sessionToken = $session->get('csrf_token');
        if (!isset($sessionToken) || !isset($formToken) || $sessionToken !== $formToken) {
            return false;
        }
        return true;
    }

    public static function getToken()
    {
        $session = new Session();
        return $session->get('csrf_token');
    }
}