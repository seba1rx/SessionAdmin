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
        $dialog       = new \stdClass;
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

    /**
     * Stores a value under the current tab's session namespace.
     * Uses TabManager::set() so the value is isolated to the calling browser tab.
     */
    public function addVarToSession(Request $request): object
    {
        $payload = $request->getPayload();
        sessionAdmin()->tabManager->set($payload['varname'], $payload['value']);
        return $this->reloadSessionData();
    }

    /**
     * Returns whether the current browser tab is already indexed in the session.
     * Demonstrates TabManager::isTabIndexed().
     */
    public function tabStatus(): object
    {
        $tm      = sessionAdmin()->tabManager ?? null;
        $indexed = $tm && $tm->isTabIndexed();

        $dialog        = new \stdClass;
        $dialog->title = 'Tab status';
        $dialog->html  = $indexed
            ? '<span style="color:green;font-size:1.1em">&#10003; This tab is indexed in the session.</span>'
            : '<span style="color:#c00;font-size:1.1em">&#10007; This tab is not yet indexed (JS client may not have registered it yet).</span>';

        $this->response->dialog = $dialog;
        return $this->response;
    }
}
