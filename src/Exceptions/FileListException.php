<?php
namespace carry0987\FileList\Exceptions;

class FileListException extends \Exception
{
    private $errorInfo;

    // Override constructor to pass error information
    public function __construct(string $message, $code = 0, $errorInfo = [])
    {
        parent::__construct($message, (int) $code);
        $this->errorInfo = $errorInfo;
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }

    public function getErrorInfo()
    {
        return $this->errorInfo;
    }
}
