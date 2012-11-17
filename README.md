ParParse
========

**MIT License**

ParParse is a command-line arguments parser written in PHP. It both
lightweight and feature rich, with all classes contained in a single file.
So, to get started all you need to do is copy _ParParse.php_ to your
project directory and use `require 'path/to/ParParse.php`.

Usage
-----

#### A Complete Example
Before getting started in the specifics of ParParse, this is a fairly
complete example of how to use the API.

```php
require 'ParParse.php';

$parser = new ParParse('An application to calculate sums.');

$parser->addArgument('total', ParParseArgument::UNLIMITED)
  ->setHelpText('A list of numbers to sum.')
  ->setDataType('float')
  ->addCallback('array_sum');

$parser->addOption('--round', '-r')
  ->setHelpText('The number of decimal points by which to round.')
  ->setValueDescriptor('decimals')
  ->setDataType('int')
  ->setDefaultValue(2);

// myscript.php 1.1, 2.2, 3.3 --round 4

$results = $parser->parse();
$rounded = round($results->get('total'), $results->get('round'));
echo $rounded; // 6.6
```

### More About the API

```php
require 'ParParse.php';

// Create a new ParParse object.
$parser = new ParParse();
```

ParParse defines two element types, positional arguments and options.

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

### Options
Options are represented by being prefixed with dashes, as is common
in command line usage. Options can server as either boolean flags or
expect a value. Additionally, we can perform actions based on whether
an option is present, and as with arguments we can apply data types and
data processing callbacks to an option. Additionally, options *must*
have a default value, which defaults to `FALSE`.

```php
// The second argument to ParParse::addOption() is an alias.
$parser->addOption('--baz', '-b');

// We could also use the ParParseOption::setAlias() method.
$option = $parser->addOption('--baz')
  ->setAlias('-b')
  ->setHelpText('Baz does foo.');

// So far, what we've made is a boolean flag. If the flag isn't present
// it will return FALSE, and if it is it will return TRUE.
function baz_action() {
  print "baz is present!";
}
$option->addAction('baz_action');

// Now let's add an option that expects a value.
$parser->addOption('--foo')
  ->setAlias('-f')
  ->setDataType('string')
  ->setDefaultValue('foo/bar')
  ->setValueDescriptor('path')
  ->setHelpText('an optional path to bar');
// Note that we used another setter for a property called
// valueDescriptor. The value descriptor is used when printing
// command help text on the command line to identify the type
// of value expected by the option.

```

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
  ->setHelpText('a foo price')
  ->addCallback('round_foo');

$parser->addArgument('bar')
  ->setDataType('string')
  ->setHelpText('a bar proper name')
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
