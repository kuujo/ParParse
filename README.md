ParParse
========

**MIT License**

ParParse is a command-line arguments parser written in PHP. It is both
lightweight and feature rich, supporting a range of argument types -
from positional arguments to boolean flags and named options - as well
as many additional features like long and short named arguments (most of
the focus is on conventional Unix syntax), default values, multiple
arguments, enforced data types, and data validation, all in a single
include file. To get started all you need to do is copy _ParParse.php_
to your project directory and use `require 'path/to/ParParse.php`.

Usage
-----

### Positional Arguments

ParParse fully supports positional arguments, a feature that is often
missing from other command-line argument parsers. As with all ParParse
arguments, positional arguments can be represented in multiple values,
use custom validation, and be converted to specific data types. Some
positional arguments can also be optional and support default values.

```php
$parser = new ParParse();
$parser->argument('foo')
  ->type('int')
  ->default(1)
  ->validator('my_validation_callback')
  ->help('Foo does bar.');

// Or, using alternate syntax (see below).
$parser->argument('foo')
  ->setType('int')
  ->setDefault(1)
  ->setValidator('my_validation_callback')
  ->setHelp('Foo does bar.');

$results = $parser->parse();
```
The `ParParse::parse()` method will also accept an array of arguments.
This is useful for cases where you may want to parse command-line like
information, such as from a configuration file. When no arguments are
passed to the `parse()` method the global `$argv` arguments are used.

Help text is used to automatically generate usage information. So, if
a bad command is entered we'll get a message like this:

`myscript.php`

```
Missing argument 'foo'.
Usage: myscript.php foo
-h|--help                               Display command usage information.

Arguments:
  foo                                   Foo does bar.
```

### Named Options

ParParse supports both long and short option types, as well as various
methods of defining option values. Option values can be indicated by
the following argument `--foo bar`, using an equals sign `--foo=bar`,
a colon `--foo:bar`, or short alias concatenated `-fbar`. Also, for
options with multiple values (an arity > 1) values can be separated
as normal arguments or with commas `--foo=1,2,3`.

#### Examples

`myscript.php la ny -a bar -b 1 2 -foo=bar,baz`

```php
$parser = new ParParse();
// Note that an arity of -1 indicates no limit. This means the
// arguments will be added until the next option switch is found.
$parser->argument('city')
  ->arity(ParParseArgument::ARITY_UNLIMITED) // Can also use -1
  ->help('A random list of cities.');

// Setting the type to 'string' is not really necessary here since
// all command line arguments are strings.
$parser->option('alpha', 'a')
  ->type('string')
  ->default('')
  ->help('A simple string.');

// Setting the default value to 0 will apply 0 to *each* of
// the option's two required arguments.
$parser->option('beta', 'b')
  ->arity(2)
  ->type('int')
  ->default(0)
  ->help('A list of numbers.');

// Here we set an option with a minimum of two and maximum of
// three arguments using the min(2) method with an arity of 3.
$parser->option('foo', 'f')
  ->arity(3)
  ->default(array('a', 'b', 'c'))
  ->min(2)
  ->help('A minimum of two and maximum of three strings.');
```

The `arity()` property indicates the number of arguments expected,
and the `min()` property indicates the minimum number of arguments
required. In cases where multiple arguments are expected, the option
can accept an array of default values rather than a single default
for each argument.

Note the array of three default values in the last option. Look at
the result if we enter the following command on the command line:

`myscript.php -a bar -b 1 2 -f 3 4`

```php
$results = $parser->parse();
print_r($results->city);
echo $results->alpha;
echo $results->beta;
print_r($results->foo);
```

Note that in the last option which uses an array of defaults, the
last element will retain the default value, while the first two
default values are overridden by the command line arguments.
```
Array
(
  [0] => 'la'
  [1] => 'ny'
)
bar
Array
(
  [0] => 1
  [1] => 2
)
Array
(
  [0] => 3
  [1] => 4
  [2] => 'c'
)
```

### Boolean flags

Actually, you've already seen how to implement boolean flags. Simply
set an option's arity to `0` and you have a boolean flag. However,
ParParse also provides a helper method to simplify the creating and
setting up of switches.

#### Examples

`myscript.php foo -a b -c`

```php
$parser = new ParParse();
$parser->argument('first')->default(NULL)->help('Some help you are!');

$parser->option('alpha', 'a')->default(FALSE)->help('A simple option.');

// Here's where we get to the flag.
$parser->flag('charlie', 'c')->help('Foo.');
$parser->flag('delta', 'd')->help('Bar.');

// Alternatively, we could define the flag like this...
$parser->option('charlie', 'c')->arity(0)->type('bool')->help('Foo.');
```
Now we parse the commands.
```php
$results = $parser->parse();
print $results->first;
print $results->alpha;
print $reuslts->charlie;
print $results->delta;
```

```
foo
b
true
false
```

### Argument Validation
All command line element types available in ParParse can be validated
using custom validation callbacks. Simply call the `validate()` or
`setValidator()` (alternate syntax) method on the element.

```php
// The value will be passed as the indicated data type, in this case int.
function validate_under_100($value) {
  return $value < 100;
}

$parser->argument('foo')
  ->type('int')
  ->arity(2)
  ->default(10)
  ->validate('validate_under_100')
  ->help('A bunch of numbers.');
```

What happens if we enter a bad number?

`myscript.php 12345`

```
Invalid argument(s) for 'foo'.
Usage: myscript.php foo
-h|--help                               Display command usage information.

Arguments:
  foo                                   A bunch of numbers.
```

#### Alternate Syntax
The syntax demonstrated in this documentation is really only one way out
of a few different ways to accomplish the same tasks. Internally, all the
command line element classes in ParParse use methods prefixed with 'set'
and use the magic `__call()` method to access them in the way demonstrated.
You can use either method at your discretion:
```php
$parser = new ParParse();
$parser->argument('foo')
  ->setArity(2)
  ->setDefault(FALSE)
  ->setHelp('Foo does bar.');
```
