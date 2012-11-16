ParParse
========

**MIT License**

ParParse is a command-line arguments parser written in PHP. It both
lightweight and feature rich, with all classes contained in a single file.
So, to get started all you need to do is copy _ParParse.php_ to your
project directory and use `require 'path/to/ParParse.php`.

Usage
-----

```php
require 'ParParse.php';

// Create a new ParParse object.
$parser = new ParParse();
```

ParParse defines three different types of arguments, positional arguments,
optional flags, and optional parameters.

### Position Arguments
Positional arguments are mostly self explanitory. The order in which you
define positional arguments is important. Note that only the last argument
in a set of positional arguments can use a default value for logical reasons.

```php
// Add a first positional argument that expects an integer.
$parser->addArgument('foo')
  ->setDataType('int'); // Ensure we get an integer rather than a string.

// Add a second argument expecting an unlimited number of strings. Note
// that cardinality (the number of arguments expected) defaults to one.
$parser->addArgument('bar')
  ->setCardinality(ParParseArgument::CARDINALITY_UNLIMITED)
  ->setDataType('string')
  // Help text is used by the parser to display command help.
  ->setHelpText('Bar does foo.');

// Alternatively, we could pass the cardinality as the second
// argument to the ParParse::addArgument() method.
$parser->addArgument('bar', ParParseArgument::CARDINALITY_UNLIMITED);
```

### Optional Flags
Flags represent boolean options, meaning if the flag is present then
the value is `TRUE`, otherwise the value is `FALSE`. Also, we can invoke
custom actions when flags are present.

```php
// The second argument to ParParse::addFlag() is a flag alias.
// In this case the flag can be used via --baz or -b.
$parser->addFlag('baz', 'b');

// We could also use the ParParseFlag::setAlias() method.
$flag = $parser->addFlag('baz')
  ->setAlias('b')
  ->setHelpText('Baz does foo.');

// Let's add an action to the flag.
function baz_action() {
  print "baz is present!";
}
$flag->addAction('baz_action');
```

Examples: `myscript --baz` `myscript -b`

### Optional Parameters
Finally, the last element type supported in ParParse - parameters - represent
options that can have dynamic values. For example, `--foo=bar` or `--foo bar`
or `-f=bar`, etc. Note that parameters *must* have a default value (which
itself defaults to `NULL`).

```php
$parser->addParameter('foo')
  ->setAlias('f')
  ->setDataType('int')
  ->setDefaultValue(0)
  ->setHelpText('Foo does bar.');
```

Examples: `myscript --foo=1` `myscript -f 1`

### Processing results
All types of elements support arbitrary data processing callbacks.
Simply use the `addCallback()` method. When a valid value is found the
callbacks will be executed in the order in which they were added. Note
that callbacks are *not* executed for default values.

```php
// Callbacks should return the processed value. This allows us to use
// many standard PHP functions as well.
function round_foo($value) {
  return round($value, 2);
}

$parser->addArgument('foo')
  ->setDataType('float')
  ->addCallback('round_foo');

$parser->addArgument('bar')
  ->setDataType('string')
  ->addCallback('ucfirst');
```

Example: `myscript.php 1.2345 baz`

```php
$results = $parser->parse();
echo $results->get('foo'); // 1.23
echo $results->get('bar'); // Baz
```

### Accessing results

```php
// Once we've defined all our command-line elements, we can run the parser.
// Note that if we don't pass any arguments to the ParParse::parse()
// method the default PHP $argv array will be used.
$results = $parser->parse();

$foo = $results->get('foo');
// or...
$foo = $results->foo;
```

Note that if the command-line arguments given do not meet the parser's
criteria it will automatically display help text. Also, the `--help`
flag and `-h` alias are reserved as the user can use those flags to
request help text as well.
