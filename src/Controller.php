<?php

namespace Framework;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

abstract class Controller
{
    public function redirectTo($uri = null, $with = [])
    {
        $session = new Session();
        $header = "Refresh: 0";
        if (count($with) > 0) {
            foreach ($with as $key => $value) {
                $session->set($key, $value);
            }
        }
        if ($uri) {
            $header = "Location: " . $uri;
        }
        header($header);
        exit();
    }

    public function showMessage(Session $session, $type, $message, $redirectTo=false)
    {
        $session->getFlashBag()->add($type, $message);
        if($redirectTo){
            $this->redirectTo($redirectTo);
        }
    }

    public function isPost(Request $request)
    {
        return $request->isMethod('POST');
    }

    public function getFormData(Request $request)
    {
        return array_merge($request->query->all(), $request->files->all(), $request->attributes->all(), $request->request->all());
    }
    public function getFormParameter(Request $request, $key)
    {
        return $this->getFormData($request)[$key];
    }
}