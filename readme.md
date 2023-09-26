# JCOF (PHP) JSON Compact Object Format

A more efficient way to represent JSON-style objects.

## About
JCOF tries to be a drop-in replacement for JSON, with most of the same semantics, but with a much more compact representation of objects. The main way it does this is to introduce a string table at the beginning of the object, and then replace all strings with indexes into that string table. It also employs a few extra tricks to make objects as small as possible, without losing the most important benefits of JSON. Most importantly, it remains a text-based, schemaless format.

### Stringify
```php
Jcof::stringify(...)
```

### Parse
```php
Jcof::parse(...)
```