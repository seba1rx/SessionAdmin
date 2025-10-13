<?php

namespace App;

use App\TemplateEngine;
use App\Request;

class Controller
{
    private $response;

    public function __construct()
    {
        $this->response = new \stdClass;
    }

    public function start():string
    {
        $html = TemplateEngine::render(tpl_dir("main.php"));
        return $html;
    }

    public function hello(): object
    {
        $dialog = new \stdClass;
        $dialog->title = "hello";
        $dialog->html = "Hello from backend!";

        $this->response->dialog = $dialog;
        return $this->response;
    }

    public function demoData(): object
    {
        $data = new \stdClass;
        $data->foo = "foo";
        $data->bar = "bar";
        $data->baz = "baz";

        $dialog = new \stdClass;
        $dialog->title = "demoData";
        $html = nl2br(json_encode($data, JSON_PRETTY_PRINT));
        $dialog->html = wrapInLeftAlignedDiv($html);

        $this->response->dialog = $dialog;

        return $this->response;
    }

    public function showLogin(): object
    {
        $content = TemplateEngine::render(tpl_dir("form.php"));
        $html = new \stdClass;
        $html->id = "content";
        $html->content = $content;

        $this->response->html = $html;
        return $this->response;
    }

    /**
     * reloads the div showing the session data
     *
     * @return object
     */
    public function reloadSessionData(): object
    {
        $content = json_encode($_SESSION ?? [], JSON_PRETTY_PRINT);

        $html = new \stdClass;
        $html->id = "session_data";
        $html->content = $content;

        $this->response->html = $html;
        return $this->response;
    }

    public function logout(): object
    {
        sessionAdmin()->terminate();
        return $this->response;
    }

    public function addVarToSession(Request $request): object
    {
        $payload = $request->getPayload();
        $_SESSION[$payload['varname']] = $payload['value'];
        return $this->reloadSessionData();
    }

}