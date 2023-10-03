# JCOF (PHP) JSON Compact Object Format

A more efficient way to represent JSON-style objects.

## About
JCOF tries to be a drop-in replacement for JSON, with most of the same semantics, but with a much more compact representation of objects. The main way it does this is to introduce a string table at the beginning of the object, and then replace all strings with indexes into that string table. It also employs a few extra tricks to make objects as small as possible, without losing the most important benefits of JSON. Most importantly, it remains a text-based, schemaless format.

Example JSON object (299 byte):****
```json
{
	"people": [
		{"first-name": "Bob", "age": 32, "occupation": "Plumber", "full-time": true},
		{"first-name": "Alice", "age": 28, "occupation": "Programmer", "full-time": true},
		{"first-name": "Bernard", "age": 36, "occupation": null, "full-time": null},
		{"first-name": "El", "age": 57, "occupation": "Programmer", "full-time": false}
	]
}
```

Coverted JCOF object (134 byte) 55% lower :

```
Programmer;"age""first-name""full-time""occupation";
{"people"[(0,iw"Bob"b"Plumber")(0,is"Alice"b,s0)(0,iA"Bernard"n,n)(0,iV"El"B,s0)]}
```

### Benchmark
<img src="https://github.com/Arif-un/jcof-php/blob/main/benchmark.png?raw=true" alt="json-vs-jcof-benchmark"/>


### Install
You can install Slugify through [Composer](https://getcomposer.org/):

```shell
composer require arif-un/jcof
```

### Usage
stringify PHP associative array (json_encode)
```php
use Jcof;

$jsonString = file_get_contents(__DIR__ . '/madrid.json');

$dataAssocArray = json_decode($jsonString, true); // covert to an associative array

$jcofCompressedStr = Jcof::stringify($dataAssocArray); // compressed string
```

convert back to JSON

```php
use Jcof;

$dataAssocArray = Jcof::parse($jcofCompressedStr); // covert jcof compressed string to associative array

$jsonData = json_encode($dataAssocArray); // coverted json
```

### Other language implementation

- JCOF Javascript [NPM](https://www.npmjs.com/package/jcof)


<br>
<br>
#### This repository is php implementation of [javascript version](https://github.com/mortie/jcof).