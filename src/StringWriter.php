<?php

namespace Jcof;

class StringWriter
{
    public $str;

    public $maybeNextSep;

    public $prevCh;

    public function __construct()
    {
        $this->str = '';
        $this->maybeNextSep = null;
        $this->prevCh = null;
    }

    public function write($s)
    {
        if ($this->maybeNextSep) {
            if (! $this->isSep($this->prevCh) && ! $this->isSep(substr($s, 0, 1))) {
                $this->str .= $this->maybeNextSep;
            }
            $this->maybeNextSep = null;
        }

        $this->str .= $s;
        $this->prevCh = substr($s, strlen($s) - 1, 1);
    }

    public function maybeSep($sep)
    {
        if ($this->maybeNextSep) {
            $this->write($this->maybeNextSep);
        }

        $this->maybeNextSep = $sep;
    }

    public function isSep($ch)
    {
        return $ch == '[' || $ch == ']' || $ch == '{' || $ch == '}' ||
            $ch == '(' || $ch == ')' || $ch == ',' || $ch == ':' || $ch == '"';
    }
}
