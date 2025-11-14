<?php
class RestException extends Exception
{
    public function __construct($status = 500, $message = '', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, (int) $status, $previous);
    }
}
