<?php

namespace Jcof;

class ParseErrors extends \Exception
{
    public $index;

    public function __construct($msg, $index)
    {
        parent::__construct($msg);
        $this->message = 'ParseError';
        $this->index = $index;
    }
}
