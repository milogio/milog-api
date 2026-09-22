<?php

namespace App\Exceptions;

use RuntimeException;

class UiAuthenticationException extends RuntimeException
{
    protected $errorCode;

    protected $status;

    protected $details;

    public function __construct($errorCode, $message, $status = 401, array $details = [])
    {
        parent::__construct($message);

        $this->errorCode = $errorCode;
        $this->status = $status;
        $this->details = $details;
    }

    public function errorCode()
    {
        return $this->errorCode;
    }

    public function status()
    {
        return $this->status;
    }

    public function details()
    {
        return $this->details;
    }
}
