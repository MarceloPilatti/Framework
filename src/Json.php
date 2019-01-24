<?php

namespace Framework;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class Json
{
    private $params;

    function __construct($params = [])
    {
        $this->params = $params;
        $session = new Session();
        if ($session->has('errors')) {
            $errors = $session->get('errors');
            $session->remove('errors');
            $this->params['errors'] = $errors;
        }

        if ($session->has('inputs')) {
            $inputs = array();
            $formInputs = $session->get('inputs');
            foreach ($formInputs as $key => $value) {
                $inputs[$key . 'Input'] = $value;
            }
            $session->remove('inputs');
            $this->params['inputs'] = $inputs;
        }

        $flashMessages = $session->getFlashBag()->all();
        if ($flashMessages) {
            $count = 0;
            $flashMessagesArray = array();
            foreach ($flashMessages as $type => $messages) {
                foreach ($messages as $message) {
                    $flashMessagesArray[$count]['message'] = $message;
                    $flashMessagesArray[$count]['type'] = $type;
                    $count++;
                }
            }
            $this->params['flashMessages'] = $flashMessagesArray;
        }
    }

    public function encode()
    {
        return new Response(json_encode($this->params));
    }

    public function decode()
    {
        return new Response(json_decode($this->params));
    }
}