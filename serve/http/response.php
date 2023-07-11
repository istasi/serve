<?php

declare(strict_types=1);

namespace serve\http;

use serve\http\one\writer;

class response
{
    private writer $writer;
    private int $state = 0;

    private array $headers = [
        'connection' => ['keep-alive'],
        'content-type' => ['text/html; charset=utf8']
    ];

    public int $code = 200;


    public function sent(): bool
    {
        return $this->state > 0;
    }

    public function setWriter(writer $writer)
    {
        $this->writer = $writer;
    }

    public function header(string $key, mixed $value): bool
    {
        if ($this->state > 1) {
            return false;
        }

        $key = strtolower($key);
        if (isset($this->headers [ $key ]) === false) {
            $this->headers [ $key ] = [];
        }

        $once = ['content-type', 'content-length', 'date', 'location', 'cache-control', 'server'];
        if (in_array(haystack: $once, needle: $key) === true) {
            $this->headers [ $key ] = [];
        }

        $this->headers [ $key ][] = $value;
        return true;
    }

    public function cookie(string $key, mixed $value, bool $httpOnly = false, string $path = '/', int $time = null): bool
    {
        if ($this->state > 1) {
            return false;
        }
        $value = urlencode($value);

        if ($httpOnly === true) {
            $value .= ';HttpOnly';
        }

        $value .= ';Path='. $path;

        if ($time !== null) {
            $time = gmdate('D, d M Y H:i:s \G\M\T', time() + $time);
            $value .= ';Expires='. $time;
        }


        $this->header('Set-Cookie', $key .'='. $value);
        return true;
    }

    public function redirect(string $url, int $code = 302)
    {
        $this->code = $code;
        $this->headers ['Location'] = [$url];
    }

    public function send(string $body): void
    {
        switch ($this->state) {
            case 0:
                $this->writer->response($this->code);
                $this->state = 1;
                // no break
            case 1:
                $this->writer->headers($this->headers);
                $this->state = 2;
                // no break
            case 2:
                $this->writer->content($body);
                $this->state = 3;

                $this->headers = [];
                $this->code = 200;
        }
    }
}
