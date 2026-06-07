<?php

namespace App;

class Response
{
    public static function send(mixed $data): void
    {
        $request_type = self::detectRequestType();

        http_response_code(200);
        if ($request_type == "xhr" || $request_type == "fetch") {
            // JSON response
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        } else {
            // HTML response
            header('Content-Type: text/html; charset=utf-8');
            if (is_array($data) || is_object($data)) {
                echo "<pre>" . htmlspecialchars(print_r($data, true)) . "</pre>";
            } else {
                echo $data;
            }
        }
    }

    private static function detectRequestType(): string
    {
        // XHR (classic XMLHttpRequest)
        if (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) {
            return 'xhr';
        }

        // Fetch (if personalized header "X-Fetch-Request": "true" is included in the JS request)
        if (!empty($_SERVER['HTTP_X_FETCH_REQUEST']) && $_SERVER['HTTP_X_FETCH_REQUEST'] === 'true') {
            return 'fetch';
        }

        // Any other (normal browser, curl, etc.)
        return 'http';
    }
}



