<?php

namespace App;

use App\TemplateEngine;
use App\Request;

class Controller
{
    private object $response;

    public function __construct()
    {
        $this->response = new \stdClass;
    }

    public function start(): string
    {
        return TemplateEngine::render(tpl_dir('main.php'));
    }

    public function hello(): object
    {
        $dialog        = new \stdClass;
        $dialog->title = 'hello';
        $dialog->html  = 'Hello from backend!';

        $this->response->dialog = $dialog;
        return $this->response;
    }

    public function demoData(): object
    {
        $data      = new \stdClass;
        $data->foo = 'foo';
        $data->bar = 'bar';
        $data->baz = 'baz';

        $dialog        = new \stdClass;
        $dialog->title = 'demoData';
        $dialog->html  = wrapInLeftAlignedDiv(nl2br(json_encode($data, JSON_PRETTY_PRINT)));

        $this->response->dialog = $dialog;
        return $this->response;
    }

    public function showLogin(): object
    {
        $html          = new \stdClass;
        $html->id      = 'content';
        $html->content = TemplateEngine::render(tpl_dir('form.php'));

        $this->response->html = $html;
        return $this->response;
    }

    public function reloadSessionData(): object
    {
        if ($_SESSION['sessionadmin']['isUser'] ?? false) {
            $html          = new \stdClass;
            $html->id      = 'session_data';
            $html->content = json_encode($_SESSION, JSON_PRETTY_PRINT);

            $this->response->html = $html;
        } else {
            $this->response->eval = 'window.location.reload()';
        }
        return $this->response;
    }

    public function logout(): object
    {
        sessionAdmin()->terminate();
        $this->response->eval = 'window.location.reload()';
        return $this->response;
    }

    public function addVarToSession(Request $request): object
    {
        $payload = $request->getPayload();
        $_SESSION[$payload['varname']] = $payload['value'];
        return $this->reloadSessionData();
    }
}
