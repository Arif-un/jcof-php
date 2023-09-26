<?php

namespace Jcof;

class StringReader
{
    public $str;

    public $index;

    public function __construct($str)
    {
        $this->str = $str;
        $this->index = 0;
    }

    public function peek()
    {
        if ($this->index >= strlen($this->str)) {
            return null;
        } else {
            return substr($this->str, $this->index, 1);
        }
    }

    public function consume()
    {
        $this->index += 1;
    }

    public function skip($ch)
    {
        $peeked = $this->peek();
        if ($peeked != $ch) {
            $this->error("Unexpected char: Expected '".$ch."', got '".$peeked."'");
        }

        $this->consume();
    }

    public function maybeSkip($ch)
    {
        if ($this->peek() == $ch) {
            $this->consume();
        }
    }

    public function error($msg)
    {
        throw new ParseErrors($msg, $this->index);
    }
}
