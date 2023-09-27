<?php

namespace Jcof;

class Jcof
{
    private const B62_ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private const B62_ALPHABET_NUM = [
        '0' => 0,
        '1' => 1,
        '2' => 2,
        '3' => 3,
        '4' => 4,
        '5' => 5,
        '6' => 6,
        '7' => 7,
        '8' => 8,
        '9' => 9,
        'a' => 10,
        'b' => 11,
        'c' => 12,
        'd' => 13,
        'e' => 14,
        'f' => 15,
        'g' => 16,
        'h' => 17,
        'i' => 18,
        'j' => 19,
        'k' => 20,
        'l' => 21,
        'm' => 22,
        'n' => 23,
        'o' => 24,
        'p' => 25,
        'q' => 26,
        'r' => 27,
        's' => 28,
        't' => 29,
        'u' => 30,
        'v' => 31,
        'w' => 32,
        'x' => 33,
        'y' => 34,
        'z' => 35,
        'A' => 36,
        'B' => 37,
        'C' => 38,
        'D' => 39,
        'E' => 40,
        'F' => 41,
        'G' => 42,
        'H' => 43,
        'I' => 44,
        'J' => 45,
        'K' => 46,
        'L' => 47,
        'M' => 48,
        'N' => 49,
        'O' => 50,
        'P' => 51,
        'Q' => 52,
        'R' => 53,
        'S' => 54,
        'T' => 55,
        'U' => 56,
        'V' => 57,
        'W' => 58,
        'X' => 59,
        'Y' => 60,
        'Z' => 61,
    ];

    public static function parse($str)
    {
        $self = new self();
        $r = new StringReader($str);
        $stringTable = $self->parseStringTable($r);
        $r->skip(';');
        $objectShapeTable = $self->parseObjectShapeTable($r, $stringTable);
        $r->skip(';');

        return $self->parseValue($r, $stringTable, $objectShapeTable);
    }

    public static function stringify($value)
    {
        $self = new self();
        $w = new StringWriter();
        $meta = $self->analyze($value);

        $self->stringifyStringTable($w, $meta);
        $w->write(';');
        $self->stringifyObjectShapeTable($w, $meta);
        $w->write(';');
        $self->stringifyValue($w, $meta, $value);

        return $w->str;
    }

    public function arrayIsList(array $arr)
    {
        if ($arr === []) {
            return true;
        }

        return array_keys($arr) === range(0, count($arr) - 1);
    }

    public function analyzeValue($value, &$strings, &$objectShapes)
    {
        if (is_array($value) && $this->arrayIsList($value)) {
            foreach ($value as $v) {
                $this->analyzeValue($v, $strings, $objectShapes);
            }
        } elseif (is_array($value) && ! $this->arrayIsList($value)) {
            $keys = array_keys($value);
            sort($keys);

            if (count($keys) > 1) {
                $shapeHash = json_encode($keys);
                if (! isset($objectShapes[$shapeHash])) {
                    $objectShapes[$shapeHash] = ['count' => 1, 'keys' => $keys];
                } else {
                    $objectShapes[$shapeHash]['count'] += 1;
                }
            } elseif (count($keys) == 1) {
                if (! isset($strings[$keys[0]])) {
                    $strings[$keys[0]] = ['count' => 1];
                } else {
                    $strings[$keys[0]]['count'] += 1;
                }
            }

            foreach ($keys as $key) {
                $this->analyzeValue($value[$key], $strings, $objectShapes);
            }
        } elseif (is_string($value) && strlen($value) > 1) {
            if (! isset($strings[$value])) {
                $strings[$value] = ['count' => 1];
            } else {
                $strings[$value]['count'] += 1;
            }
        }
    }

    public function analyze($value)
    {
        $strings = [];
        $objectShapes = [];
        $this->analyzeValue($value, $strings, $objectShapes);

        foreach ($objectShapes as $hash => $shape) {
            if ($shape['count'] == 1) {
                unset($objectShapes[$hash]);
            }

            foreach ($shape['keys'] as $key) {
                if (! isset($strings[$key])) {
                    $strings[$key] = ['count' => 1];
                } else {
                    $strings[$key]['count'] += 1;
                }
            }
        }

        foreach ($strings as $string => $s) {
            if ($s['count'] == 1) {
                unset($strings[$string]);
            }
        }

        $stringList = array_keys($strings);
        usort($stringList, function ($a, $b) use ($strings) {
            return $strings[$b]['count'] - $strings[$a]['count'];
        });

        $stringIds = [];
        for ($id = 0; $id < count($stringList); $id++) {
            $stringIds[$stringList[$id]] = $id;
        }

        $objectShapeList = [];
        $objectShapeIds = [];
        foreach ($objectShapes as $hash => $shape) {
            $objectShapeIds[$hash] = count($objectShapeList);
            $objectShapeList[] = $shape['keys'];
        }

        return [
            'stringList' => $stringList,
            'stringIds' => $stringIds,
            'objectShapeList' => $objectShapeList,
            'objectShapeIds' => $objectShapeIds,
        ];
    }

    public function stringifyStringTable($w, $meta)
    {
        if (count($meta['stringList']) == 0) {
            return;
        }

        $this->stringifyString($w, $meta['stringList'][0]);
        for ($i = 1; $i < count($meta['stringList']); $i++) {
            $w->maybeSep(',');
            $this->stringifyString($w, $meta['stringList'][$i]);
        }
    }

    public function stringifyString($w, $string)
    {
        if (preg_match('/^[a-zA-Z0-9]+$/', $string)) {
            $w->write($string);
        } else {
            $w->write(json_encode($string));
        }
    }

    public function stringifyObjectShapeTable($w, $meta)
    {
        if (count($meta['objectShapeList']) == 0) {
            return;
        }

        $this->stringifyObjectShape($w, $meta, $meta['objectShapeList'][0]);
        for ($i = 1; $i < count($meta['objectShapeList']); $i++) {
            $w->write(',');
            $this->stringifyObjectShape($w, $meta, $meta['objectShapeList'][$i]);
        }
    }

    public function stringifyObjectShape($w, $meta, $shape)
    {
        $this->stringifyObjectKey($w, $meta, $shape[0]);
        for ($i = 1; $i < count($shape); $i++) {
            $w->maybeSep(':');
            $this->stringifyObjectKey($w, $meta, $shape[$i]);
        }
    }

    public function stringifyObjectKey($w, $meta, $key)
    {
        $id = isset($meta['stringIds'][$key]) ? $meta['stringIds'][$key] : null;
        if ($id === null) {
            $w->write(json_encode($key));
        } else {
            $this->stringifyBase62($w, $id);
        }
    }

    public function stringifyBase62($w, $num)
    {
        $str = '';
        do {
            $str .= self::B62_ALPHABET[$num % 62];
            $num = intval($num / 62);
        } while ($num > 0);

        for ($i = strlen($str) - 1; $i >= 0; $i--) {
            $w->write(substr($str, $i, 1));
        }
    }

    public function stringifyValue($w, $meta, $value)
    {
        if (is_array($value) && $this->arrayIsList($value)) {
            $w->write('[');
            if (count($value) == 0) {
                $w->write(']');

                return;
            }
            // print_r($value);
            $this->stringifyValue($w, $meta, $value[0]);
            for ($i = 1; $i < count($value); $i++) {
                $w->maybeSep(',');
                $this->stringifyValue($w, $meta, $value[$i]);
            }

            $w->write(']');
        } elseif (is_array($value) && ! $this->arrayIsList($value)) {
            $keys = array_keys($value);
            sort($keys);
            $hash = json_encode($keys);
            $shapeId = isset($meta['objectShapeIds'][$hash]) ? $meta['objectShapeIds'][$hash] : null;
            if ($shapeId === null) {
                $this->stringifyKeyedObjectValue($w, $meta, $value, $keys);
            } else {
                $this->stringifyShapedObjectValue($w, $meta, $value, $keys, $shapeId);
            }
        } elseif (is_numeric($value) && ! is_string($value)) {
            if ($value == intval($value) && ($value < 0 || $value > 10)) {
                $w->write($value < 0 ? 'I' : 'i');
                $this->stringifyBase62($w, abs($value));
            } elseif (is_infinite($value) || is_nan($value)) {
                $w->write('n');
            } else {
                $w->write(json_encode($value));
            }
        } elseif (is_string($value)) {
            $stringId = isset($meta['stringIds'][$value]) ? $meta['stringIds'][$value] : null;
            if ($stringId === null) {
                $w->write(json_encode($value));
            } else {
                $w->write('s');
                $this->stringifyBase62($w, $stringId);
            }
        } elseif (is_bool($value)) {
            $w->write($value ? 'b' : 'B');
        } elseif ($value === null) {
            $w->write('n');
        } else {
            throw new Exception("Can't serialize value: ".$value);
        }
    }

    public function stringifyShapedObjectValue($w, $meta, $value, $keys, $shapeId)
    {
        $w->write('(');
        $this->stringifyBase62($w, $shapeId);
        if (count($keys) == 0) {
            $w->write(')');

            return;
        }

        foreach ($keys as $key) {
            $w->maybeSep(',');
            $this->stringifyValue($w, $meta, $value[$key]);
        }

        $w->write(')');
    }

    public function stringifyKeyedObjectValue($w, $meta, $value, $keys)
    {
        $w->write('{');
        if (count($keys) == 0) {
            $w->write('}');

            return;
        }

        $this->stringifyKeyValuePair($w, $meta, $keys[0], $value[$keys[0]]);
        for ($i = 1; $i < count($keys); $i++) {
            $w->maybeSep(',');
            $this->stringifyKeyValuePair($w, $meta, $keys[$i], $value[$keys[$i]]);
        }

        $w->write('}');
    }

    public function stringifyKeyValuePair($w, $meta, $key, $val)
    {
        $this->stringifyObjectKey($w, $meta, $key);
        $w->maybeSep(':');
        $this->stringifyValue($w, $meta, $val);
    }

    public function parseStringTable($r)
    {
        if ($r->peek() == ';') {
            return [];
        }

        $strings = [];
        while (true) {
            $strings[] = $this->parseString($r);
            $ch = $r->peek();
            if ($ch == ';') {
                return $strings;
            } elseif ($ch == ',') {
                $r->consume();
            }
        }
    }

    public function parseString($r)
    {
        if ($r->peek() == '"') {
            return $this->parseJsonString($r);
        } elseif (preg_match('/[a-zA-Z0-9]/', $r->peek())) {
            return $this->parsePlainString($r);
        } else {
            $r->error('Expected plain string or JSON string');
        }
    }

    public function parsePlainString($r)
    {
        $str = $r->peek();
        $r->consume();

        while (true) {
            $ch = $r->peek();
            if (! preg_match('/[a-zA-Z0-9]/', $ch)) {
                return $str;
            }

            $str .= $ch;
            $r->consume();
        }
    }

    public function parseJsonString($r)
    {
        $start = $r->index;
        $r->skip('"');
        while (true) {
            $ch = $r->peek();
            $r->consume();
            if ($ch == '"') {
                break;
            } elseif ($ch == '\\') {
                $r->consume();
            } elseif ($ch === null) {
                $r->error('Unexpected EOF');
            }
        }

        $substring = substr($r->str, $start, $r->index - $start);

        return json_decode($substring, true);
    }

    public function parseObjectShapeTable($r, $stringTable)
    {
        if ($r->peek() == ';') {
            return [];
        }

        $shapes = [];
        while (true) {
            $shapes[] = $this->parseObjectShape($r, $stringTable);
            $ch = $r->peek();
            if ($ch == ';') {
                return $shapes;
            } elseif ($ch == ',') {
                $r->consume();
            }
        }
    }

    public function parseObjectShape($r, $stringTable)
    {
        $shape = [];
        while (true) {
            $shape[] = $this->parseObjectKey($r, $stringTable);
            $ch = $r->peek();
            if ($ch == ',' || $ch == ';') {
                return $shape;
            } elseif ($ch == ':') {
                $r->consume();
            }
        }
    }

    public function parseObjectKey($r, $stringTable)
    {
        if ($r->peek() == '"') {
            return $this->parseJsonString($r);
        } else {
            $id = $this->parseBase62($r);
            if ($id >= count($stringTable)) {
                $r->error('String ID '.$id.' out of range');
            }

            return $stringTable[$id];
        }
    }

    public function parseBase62($r)
    {
        if (! preg_match('/[0-9a-zA-Z]/', $r->peek())) {
            $r->error('Expected base62 value');
        }

        $num = 0;
        while (true) {
            $num *= 62;
            $num += self::B62_ALPHABET_NUM[$r->peek()];
            $r->consume();
            if (! preg_match('/[0-9a-zA-Z]/', $r->peek())) {
                return $num;
            }
        }
    }

    public function parseValue($r, $stringTable, $objectShapeTable)
    {
        $ch = $r->peek();
        if ($ch == '[') {
            return $this->parseArrayValue($r, $stringTable, $objectShapeTable);
        } elseif ($ch == '(') {
            return $this->parseShapedObjectValue($r, $stringTable, $objectShapeTable);
        } elseif ($ch == '{') {
            return $this->parseKeyedObjectValue($r, $stringTable, $objectShapeTable);
        } elseif (preg_match('/[iIf0-9\-]/', $ch)) {
            return $this->parseNumberValue($r);
        } elseif ($ch == 's' || $ch == '"') {
            return $this->parseStringValue($r, $stringTable);
        } elseif ($ch == 'b') {
            $r->consume();

            return true;
        } elseif ($ch == 'B') {
            $r->consume();

            return false;
        } elseif ($ch == 'n') {
            $r->consume();

            return null;
        } else {
            $r->error("Expected value, got '".$ch."'");
        }
    }

    public function parseArrayValue($r, $stringTable, $objectShapeTable)
    {
        $r->skip('[');
        if ($r->peek() == ']') {
            $r->consume();

            return [];
        }

        $arr = [];
        while (true) {
            $arr[] = $this->parseValue($r, $stringTable, $objectShapeTable);
            $ch = $r->peek();
            if ($ch == ']') {
                $r->consume();

                return $arr;
            } elseif ($ch == ',') {
                $r->consume();
            }
        }
    }

    public function parseShapedObjectValue($r, $stringTable, $objectShapeTable)
    {
        $r->skip('(');
        $shapeId = $this->parseBase62($r);
        if ($shapeId >= count($objectShapeTable)) {
            $r->error('Shape ID '.$shapeId.' out of range');
        }

        $shape = $objectShapeTable[$shapeId];
        $obj = [];
        foreach ($shape as $key) {
            if ($r->peek() == ',') {
                $r->consume();
            }
            $obj[$key] = $this->parseValue($r, $stringTable, $objectShapeTable);
        }

        $r->skip(')');

        return $obj;
    }

    public function parseKeyedObjectValue($r, $stringTable, $objectShapeTable)
    {
        $r->skip('{');
        if ($r->peek() == '}') {
            $r->consume();

            return [];
        }

        $obj = [];
        while (true) {
            $key = $this->parseObjectKey($r, $stringTable);
            if ($r->peek() == ':') {
                $r->consume();
            }

            $obj[$key] = $this->parseValue($r, $stringTable, $objectShapeTable);
            $ch = $r->peek();
            if ($ch == ',') {
                $r->consume();
            } elseif ($ch == '}') {
                $r->consume();

                return $obj;
            }
        }
    }

    public function parseNumberValue($r)
    {
        $ch = $r->peek();
        if ($ch == 'i') {
            $r->consume();

            return $this->parseBase62($r);
        } elseif ($ch == 'I') {
            $r->consume();

            return -$this->parseBase62($r);
        } else {
            return $this->parseFloatValue($r);
        }
    }

    public function parseFloatValue($r)
    {
        // Here, we read the float, but then use custom's float parser,
        // because making a float parser and serializer pair
        // which can round-trip any number is apparently pretty hard

        $str = '';

        if (($ch = $r->peek()) == '-') {
            $str .= $ch;
            $r->consume();
        }

        while (preg_match('/[0-9]/', $ch = $r->peek())) {
            $str .= $ch;
            $r->consume();
        }

        if ($str == '' || $str == '-') {
            $r->error('Zero-length number in float literal');
        }

        if (($ch = $r->peek()) == '.') {
            $str .= $ch;
            $r->consume();

            while (preg_match('/[0-9]/', $ch = $r->peek())) {
                $str .= $ch;
                $r->consume();
            }

            if (substr($str, strlen($str) - 1, 1) == '.') {
                $r->error('Zero-length fractional part in float literal');
            }
        }

        $ch = $r->peek();
        if ($ch == 'e' || $ch == 'E') {
            $str .= $ch;
            $r->consume();

            $ch = $r->peek();
            if ($ch == '+' || $ch == '-') {
                $str .= $ch;
                $r->consume();
            }

            while (preg_match('/[0-9]/', $ch = $r->peek())) {
                $str .= $ch;
                $r->consume();
            }

            if (! preg_match('/[0-9]/', substr($str, strlen($str) - 1, 1))) {
                $r->error('Zero-length exponential part in float literal');
            }
        }

        return $this->stringToFloat($str);
    }

    public function stringToFloat($str)
    {
        $isNegative = false;
        if (substr($str, 0, 1) === '-') {
            $isNegative = true;
            $str = substr($str, 1);
        }

        $parts = explode('.', $str);
        $decimalLength = isset($parts[1]) ? strlen($parts[1]) : 0;

        $floatValue = (float) $str;

        $formattedValue = number_format($floatValue, $decimalLength, '.', '');

        return $isNegative ? -1 * $formattedValue : $formattedValue;
    }

    public function parseStringValue($r, $stringTable)
    {
        if ($r->peek() == '"') {
            return $this->parseJsonString($r);
        } else {
            $r->skip('s');
            $id = $this->parseBase62($r);
            if ($id >= count($stringTable)) {
                $r->error('String ID '.$id.' out of range');
            }

            return $stringTable[$id];
        }
    }
}
