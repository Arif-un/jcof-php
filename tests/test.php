
<?php

$b62alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
$b62alphanum = [];
for ($i = 0; $i < strlen($b62alphabet); $i++) {
    $b62alphanum[$b62alphabet[$i]] = $i;
}

function isSep($ch)
{
    return $ch == '[' || $ch == ']' || $ch == '{' || $ch == '}' ||
        $ch == '(' || $ch == ')' || $ch == ',' || $ch == ':' || $ch == '"';
}

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
            if (! isSep($this->prevCh) && ! isSep($s[0])) {
                $this->str .= $this->maybeNextSep;
            }
            $this->maybeNextSep = null;
        }

        $this->str .= $s;
        $this->prevCh = $s[strlen($s) - 1];
    }

    public function maybeSep($sep)
    {
        if ($this->maybeNextSep) {
            $this->write($this->maybeNextSep);
        }

        $this->maybeNextSep = $sep;
    }
}

if (! function_exists('array_is_list')) {
    function array_is_list(array $arr)
    {
        if ($arr === []) {
            return true;
        }

        return array_keys($arr) === range(0, count($arr) - 1);
    }
}

function analyzeValue($value, &$strings, &$objectShapes)
{
    if (is_array($value) && array_is_list($value)) {
        foreach ($value as $v) {
            analyzeValue($v, $strings, $objectShapes);
        }
    } elseif (is_array($value) && ! array_is_list($value)) {
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
            analyzeValue($value[$key], $strings, $objectShapes);
        }
    } elseif (is_string($value) && strlen($value) > 1) {
        if (! isset($strings[$value])) {
            $strings[$value] = ['count' => 1];
        } else {
            $strings[$value]['count'] += 1;
        }
    }
}

function analyze($value)
{
    $strings = [];
    $objectShapes = [];
    analyzeValue($value, $strings, $objectShapes);

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

function stringify($value)
{
    $w = new StringWriter();
    $meta = analyze($value);

    stringifyStringTable($w, $meta);
    $w->write(';');
    stringifyObjectShapeTable($w, $meta);
    $w->write(';');
    stringifyValue($w, $meta, $value);

    return $w->str;
}

function stringifyStringTable($w, $meta)
{
    if (count($meta['stringList']) == 0) {
        return;
    }

    stringifyString($w, $meta['stringList'][0]);
    for ($i = 1; $i < count($meta['stringList']); $i++) {
        $w->maybeSep(',');
        stringifyString($w, $meta['stringList'][$i]);
    }
}

function stringifyString($w, $string)
{
    if (preg_match('/^[a-zA-Z0-9]+$/', $string)) {
        $w->write($string);
    } else {
        $w->write(json_encode($string));
    }
}

function stringifyObjectShapeTable($w, $meta)
{
    if (count($meta['objectShapeList']) == 0) {
        return;
    }

    stringifyObjectShape($w, $meta, $meta['objectShapeList'][0]);
    for ($i = 1; $i < count($meta['objectShapeList']); $i++) {
        $w->write(',');
        stringifyObjectShape($w, $meta, $meta['objectShapeList'][$i]);
    }
}

function stringifyObjectShape($w, $meta, $shape)
{
    stringifyObjectKey($w, $meta, $shape[0]);
    for ($i = 1; $i < count($shape); $i++) {
        $w->maybeSep(':');
        stringifyObjectKey($w, $meta, $shape[$i]);
    }
}

function stringifyObjectKey($w, $meta, $key)
{
    $id = isset($meta['stringIds'][$key]) ? $meta['stringIds'][$key] : null;
    if ($id === null) {
        $w->write(json_encode($key));
    } else {
        stringifyBase62($w, $id);
    }
}

function stringifyBase62($w, $num)
{
    global $b62alphabet;
    $str = '';
    do {
        $str .= $b62alphabet[$num % 62];
        $num = intval($num / 62);
    } while ($num > 0);

    for ($i = strlen($str) - 1; $i >= 0; $i--) {
        $w->write($str[$i]);
    }
}

function stringifyValue($w, $meta, $value)
{
    if (is_array($value) && array_is_list($value)) {
        $w->write('[');
        if (count($value) == 0) {
            $w->write(']');

            return;
        }
        // print_r($value);
        stringifyValue($w, $meta, $value[0]);
        for ($i = 1; $i < count($value); $i++) {
            $w->maybeSep(',');
            stringifyValue($w, $meta, $value[$i]);
        }

        $w->write(']');
    } elseif (is_array($value) && ! array_is_list($value)) {
        $keys = array_keys($value);
        sort($keys);
        $hash = json_encode($keys);
        $shapeId = isset($meta['objectShapeIds'][$hash]) ? $meta['objectShapeIds'][$hash] : null;
        if ($shapeId === null) {
            stringifyKeyedObjectValue($w, $meta, $value, $keys);
        } else {
            stringifyShapedObjectValue($w, $meta, $value, $keys, $shapeId);
        }
    } elseif (is_numeric($value)) {
        if ($value == intval($value) && ($value < 0 || $value > 10)) {
            $w->write($value < 0 ? 'I' : 'i');
            stringifyBase62($w, abs($value));
        } elseif (is_infinite($value) || is_nan($value)) {
            $w->write('n');
        } else {
            $w->write(strval($value));
        }
    } elseif (is_string($value)) {
        $stringId = isset($meta['stringIds'][$value]) ? $meta['stringIds'][$value] : null;
        if ($stringId === null) {
            $w->write(json_encode($value));
        } else {
            $w->write('s');
            stringifyBase62($w, $stringId);
        }
    } elseif (is_bool($value)) {
        $w->write($value ? 'b' : 'B');
    } elseif ($value === null) {
        $w->write('n');
    } else {
        throw new Exception("Can't serialize value: ".$value);
    }
}

function stringifyShapedObjectValue($w, $meta, $value, $keys, $shapeId)
{
    $w->write('(');
    stringifyBase62($w, $shapeId);
    if (count($keys) == 0) {
        $w->write(')');

        return;
    }

    foreach ($keys as $key) {
        $w->maybeSep(',');
        stringifyValue($w, $meta, $value[$key]);
    }

    $w->write(')');
}

function stringifyKeyedObjectValue($w, $meta, $value, $keys)
{
    $w->write('{');
    if (count($keys) == 0) {
        $w->write('}');

        return;
    }

    stringifyKeyValuePair($w, $meta, $keys[0], $value[$keys[0]]);
    for ($i = 1; $i < count($keys); $i++) {
        $w->maybeSep(',');
        stringifyKeyValuePair($w, $meta, $keys[$i], $value[$keys[$i]]);
    }

    $w->write('}');
}

function stringifyKeyValuePair($w, $meta, $key, $val)
{
    stringifyObjectKey($w, $meta, $key);
    $w->maybeSep(':');
    stringifyValue($w, $meta, $val);
}

class ParseErrors extends Exception
{
    public $index;

    public function __construct($msg, $index)
    {
        parent::__construct($msg);
        $this->name = 'ParseError';  // In PHP, Exception has a 'message' property but not a 'name' property.
        $this->index = $index;
    }
}

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
            return $this->str[$this->index];
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

function parse($str)
{
    $r = new StringReader($str);
    $stringTable = parseStringTable($r);
    $r->skip(';');
    $objectShapeTable = parseObjectShapeTable($r, $stringTable);
    $r->skip(';');

    return parseValue($r, $stringTable, $objectShapeTable);
}

function parseStringTable($r)
{
    if ($r->peek() == ';') {
        return [];
    }

    $strings = [];
    while (true) {
        $strings[] = parseString($r);
        $ch = $r->peek();
        if ($ch == ';') {
            return $strings;
        } elseif ($ch == ',') {
            $r->consume();
        }
    }
}

function parseString($r)
{
    if ($r->peek() == '"') {
        return parseJsonString($r);
    } elseif (preg_match('/[a-zA-Z0-9]/', $r->peek())) {
        return parsePlainString($r);
    } else {
        $r->error('Expected plain string or JSON string');
    }
}

function parsePlainString($r)
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

function parseJsonString($r)
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

function parseObjectShapeTable($r, $stringTable)
{
    if ($r->peek() == ';') {
        return [];
    }

    $shapes = [];
    while (true) {
        $shapes[] = parseObjectShape($r, $stringTable);
        $ch = $r->peek();
        if ($ch == ';') {
            return $shapes;
        } elseif ($ch == ',') {
            $r->consume();
        }
    }
}

function parseObjectShape($r, $stringTable)
{
    $shape = [];
    while (true) {
        $shape[] = parseObjectKey($r, $stringTable);
        $ch = $r->peek();
        if ($ch == ',' || $ch == ';') {
            return $shape;
        } elseif ($ch == ':') {
            $r->consume();
        }
    }
}

function parseObjectKey($r, $stringTable)
{
    if ($r->peek() == '"') {
        return parseJsonString($r);
    } else {
        $id = parseBase62($r);
        if ($id >= count($stringTable)) {
            $r->error('String ID '.$id.' out of range');
        }

        return $stringTable[$id];
    }
}

function parseBase62($r)
{
    global $b62alphanum;

    if (! preg_match('/[0-9a-zA-Z]/', $r->peek())) {
        $r->error('Expected base62 value');
    }

    $num = 0;
    while (true) {
        $num *= 62;
        $num += $b62alphanum[$r->peek()];
        $r->consume();
        if (! preg_match('/[0-9a-zA-Z]/', $r->peek())) {
            return $num;
        }
    }
}

function parseValue($r, $stringTable, $objectShapeTable)
{
    $ch = $r->peek();
    if ($ch == '[') {
        return parseArrayValue($r, $stringTable, $objectShapeTable);
    } elseif ($ch == '(') {
        return parseShapedObjectValue($r, $stringTable, $objectShapeTable);
    } elseif ($ch == '{') {
        return parseKeyedObjectValue($r, $stringTable, $objectShapeTable);
    } elseif (preg_match('/[iIf0-9\-]/', $ch)) {
        return parseNumberValue($r);
    } elseif ($ch == 's' || $ch == '"') {
        return parseStringValue($r, $stringTable);
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

function parseArrayValue($r, $stringTable, $objectShapeTable)
{
    $r->skip('[');
    if ($r->peek() == ']') {
        $r->consume();

        return [];
    }

    $arr = [];
    while (true) {
        $arr[] = parseValue($r, $stringTable, $objectShapeTable);
        $ch = $r->peek();
        if ($ch == ']') {
            $r->consume();

            return $arr;
        } elseif ($ch == ',') {
            $r->consume();
        }
    }
}

function parseShapedObjectValue($r, $stringTable, $objectShapeTable)
{
    $r->skip('(');
    $shapeId = parseBase62($r);
    if ($shapeId >= count($objectShapeTable)) {
        $r->error('Shape ID '.$shapeId.' out of range');
    }

    $shape = $objectShapeTable[$shapeId];
    $obj = [];
    foreach ($shape as $key) {
        if ($r->peek() == ',') {
            $r->consume();
        }
        $obj[$key] = parseValue($r, $stringTable, $objectShapeTable);
    }

    $r->skip(')');

    return $obj;
}

function parseKeyedObjectValue($r, $stringTable, $objectShapeTable)
{
    $r->skip('{');
    if ($r->peek() == '}') {
        $r->consume();

        return [];
    }

    $obj = [];
    while (true) {
        $key = parseObjectKey($r, $stringTable);
        if ($r->peek() == ':') {
            $r->consume();
        }

        $obj[$key] = parseValue($r, $stringTable, $objectShapeTable);
        $ch = $r->peek();
        if ($ch == ',') {
            $r->consume();
        } elseif ($ch == '}') {
            $r->consume();

            return $obj;
        }
    }
}

function parseNumberValue($r)
{
    $ch = $r->peek();
    if ($ch == 'i') {
        $r->consume();

        return parseBase62($r);
    } elseif ($ch == 'I') {
        $r->consume();

        return -parseBase62($r);
    } else {
        return parseFloatValue($r);
    }
}

function parseFloatValue($r)
{
    // Here, we read the float, but then use PHP's float parser,
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

        if ($str[strlen($str) - 1] == '.') {
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

        if (! preg_match('/[0-9]/', $str[strlen($str) - 1])) {
            $r->error('Zero-length exponential part in float literal');
        }
    }

    return floatval($str);
}

function parseStringValue($r, $stringTable)
{
    if ($r->peek() == '"') {
        return parseJsonString($r);
    } else {
        $r->skip('s');
        $id = parseBase62($r);
        if ($id >= count($stringTable)) {
            $r->error('String ID '.$id.' out of range');
        }

        return $stringTable[$id];
    }
}

// print_r(
//   stringify(
//     // json_encode(
//       parse('Programmer;"age""first-name""full-time""occupation";{"people"[(0,iw"Bob"b"Plumber")(0,is"Alice"b,s0)(0,iA"Bernard"n,n)(0,iV"El"B,s0)]}')
//     // )
//   )
// );

print_r(
    parse('Programmer;"age""first-name""full-time""occupation";{"people"[(0,iw"Bob"b"Plumber")(0,is"Alice"b,s0)(0,iA"Bernard"n,n)(0,iV"El"B,s0)]}')
);
