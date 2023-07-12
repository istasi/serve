<?php

declare(strict_types=1);

namespace serve\connections\http;

use Fiber;
use serve\connections\tcp;
use serve\http;
use serve\log;

class client extends tcp\base
{
    private $state = 0;

    private int $reader = 1;
    private array $readers = [];

    private int $writer = 1;
    private array $writers = [];
    protected $readBuffer = '';

    public function __construct(mixed $stream, public readonly string $address)
    {
        parent::__construct($stream);

        $this->readers [1] = new http\one\reader();
        $this->writers [1] = new http\one\writer();
        $this->writers [1]->client($this);

        //TODO: Write this
        //$this->readers [2] = new http\two\reader ();
        //$this->writers [2] = new http\two\writer ();
    }

    public function read(int $length = 4096): string|false
    {
        $message = parent::read(4096);
        if ($this->connected === false) {
            return false;
        }

        $this->readBuffer .= $message;

        switch ($this->state) {
            case 0:
                // Determine protocol
                $first = substr($this->readBuffer, 0, -1);
                if (is_numeric($first) === true) {
                    $this->reader = 2;
                } else {
                    $this->reader = 1;
                }
                // no break
            case 1:
                $reader = $this->readers [ $this->reader ];
                $reader->address($this->address);
                $reader->text($this->readBuffer);
                foreach ($reader->parse() as $request) {
                    $response = new http\response();
                    $response->header('content-encoding', $request->header('accept-encoding'));
                    $response->setWriter($this->writers [1]);

					$this->trigger('request', ['request' => $request, 'response' => $response ]);
                }

                $this->readBuffer = $reader->text();
        }

        return false;
    }
}
