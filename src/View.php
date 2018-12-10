<?php

namespace Framework;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class View
{
    private $viewPath;
    private $layoutPath;

    function __construct($viewPath = null, $params = null, $layoutPath = null)
    {
        if ($layoutPath == null) {
            $layoutPath = __DIR__ . '/../app/view/layout.phtml';
        }
        $viewPath = __DIR__ . '/../app/view/' . $viewPath . '.phtml';
        if (!isset($layoutPath) || !file_exists($layoutPath)) {
            throw new \Exception('View file ' . $layoutPath . ' not found');
        }
        if (!isset($viewPath) || !file_exists($viewPath)) {
            throw new \Exception('View file ' . $viewPath . ' not found');
        }
        $this->layoutPath = $layoutPath;
        $this->viewPath = $viewPath;

        if (isset($params)) {
            foreach ($params as $key => $value) {
                $this->$key = $value;
            }
        }
        $session = new Session();
        if ($session->has('errors')) {
            $errors = $session->get('errors');
            foreach ($errors as $key => $error) {
                $this->{$key . 'Error'} = "<label class='text-danger'>" . $error . "</label>";
            }
            $session->remove('errors');
        }
        if ($session->has('inputs')) {
            $inputs = $session->get('inputs');
            foreach ($inputs as $key => $input) {
                $this->{$key . 'Input'} = $input;
            }
            $session->remove('inputs');
        }
        $flashDivs = '';
        $flashMessages = $session->getFlashBag()->all();
        if ($flashMessages) {
            foreach ($flashMessages as $type => $messages) {
                foreach ($messages as $message) {
                    $flashDivs .= '<div class="alert alert-' . $type . '">' . $message . '</div>';
                }
            }
        }
        $this->flashMessages = $flashDivs;
        $this->version = $session->get('version');
        $this->min = $session->get('min');
        $this->csrf_token = "<input type='hidden' name='csrf_token' value='" . CSRF::generateFormToken() . "' />";
    }

    public function render()
    {
        require_once $this->layoutPath;
        return new Response();
    }

    public function content()
    {
        return require_once $this->viewPath;
    }
}