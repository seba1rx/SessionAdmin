<?php

namespace App;

class Request
{
    protected array $payload;
    protected array $cookies;
    protected array $queryParams;
    protected string $uri;

    public function __construct()
    {
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->cookies = $_COOKIE ?? [];
        $this->payload = [];
        $this->queryParams = [];

        // detect and decode the payload (JSON, form-data or raw)
        $input = file_get_contents('php://input');
        $decoded = json_decode($input, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            $this->payload = $decoded;
        } elseif (!empty($_POST)) {
            $this->payload = $_POST;
        } else {
            // if there is no JSON nor POST, try to pass raw data
            parse_str($input, $this->payload);
        }

        // get query param from uri
        $queryString = parse_url($this->uri, PHP_URL_QUERY);
        parse_str($queryString ?? '', $this->queryParams);
    }

    /**
     * returns the whole payload as array
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * returns a value from payload
     */
    public function getFromPayload(string $item): mixed
    {
        return $this->payload[$item] ?? null;
    }

    /**
     * returns all the cookies from the equest
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    /**
     * returns a sin gle cookie value or null if it does not exist
     */
    public function getFromCookies(string $cookie): mixed
    {
        return $this->cookies[$cookie] ?? null;
    }

    /**
     * returns a param value from the uri
     */
    public function getParamFromUri(string $paramName): mixed
    {
        return $this->queryParams[$paramName] ?? null;
    }

    /**
     * gets the whole uri
     */
    public function getUri(): string
    {
        return $this->uri;
    }
}