ParParse
========

**MIT License**

ParParse is a command-line arguments parser written in PHP. It is both
lightweight and feature rich, supporting a range of argument types -
from positional arguments to boolean flags and named options -
automatically generates usage/help text, and provides many
additional features like long and short named arguments (most of
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
$parser->addArgument('foo')
  ->setType('int')
  ->setDefault(1)
  ->setValidator('my_validation_callback')
  ->setHelpText('Foo does bar.');

// Or, using alternate syntax (I prefer the alternate syntaxt, see below).
$parser->argument('foo')
  ->type('int')
  ->default(1)
  ->validate('my_validation_callback')
  ->help('Foo does bar.');

$results = $parser->parse();
```
We can execute this command using:

`myscript.php 1`

```php
print $results->foo;
```

```
1
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
$parser->addArgument('city')
  ->setArity(ParParseArgument::ARITY_UNLIMITED) // Can also use -1
  ->setHelpText('A random list of cities.');

// Setting the type to 'string' is not really necessary here since
// all command line arguments are strings.
$parser->addOption('alpha', 'a')
  ->setTyoe('string')
  ->setDefault('')
  ->setHelpText('A simple string.');

// Setting the default value to 0 will apply 0 to *each* of
// the option's two required arguments.
$parser->addOption('beta', 'b')
  ->setArity(2)
  ->setType('int')
  ->setDefault(0)
  ->setHelpText('A list of numbers.');

// Here we set an option with a minimum of two and maximum of
// three arguments using the min(2) method with an arity of 3.
$parser->addOption('foo', 'f')
  ->setArity(3)
  ->setDefault(array('a', 'b', 'c'))
  ->setMin(2)
  ->setHelpText('A minimum of two and maximum of three strings.');
```

The `arity()` property indicates the number of arguments expected,
and the `min()` property indicates the minimum number of arguments
required. In cases where multiple arguments are expected, the option
can accept an array of default values rather than a single default
for each argument.

Note the array of three default values in the last option. Look at
the result if we run the script using the example command:

`myscript.php la ny -a bar -b 1 2 -f 3 4`

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
  [0] => "la"
  [1] => "ny"
)
"bar"
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
setting up of boolean type switches.

#### Examples

`myscript.php foo -a b -c`

```php
$parser = new ParParse();
$parser->addArgument('first')->setDefault(NULL)->setHelpText('Some help you are!');

$parser->addOption('alpha', 'a')->setDefault(FALSE)->setHelpText('A simple option.');

// Here's where we get to the flag.
$parser->addFlag('charlie', 'c')->setHelpText('Foo.');
$parser->addFlag('delta', 'd')->setHelpText('Bar.');

// Alternatively, we could define the flag like this...
$parser->addOption('charlie', 'c')->setArity(0)->setType('bool')->setHelpText('Foo.');
// Or this...
$parser->option('charlie', 'c')->arity(0)->type('bool')->help('Foo.');
// Or a mixture of either.
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
"foo"
"b"
true
false
```

### Argument Validation
All command line element types available in ParParse can be validated
using custom validation callbacks. Simply call the `setValidator()` or
`validate()` (alternate syntax) method on the element.

```php
// The value will be passed as the indicated data type, in this case int.
function validate_under_100($value) {
  return $value < 100;
}

$parser->addArgument('foo')
  ->setType('int')
  ->setArity(2)
  ->setDefault(10)
  ->setValidator('validate_under_100')
  ->setHelpText('A bunch of numbers.');
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

### More examples
The following script can interpret each of these commands with the same results.

`myscript.php one two --three=four,five --five 1.01 --seven`

`myscript.php -t four five one two --five=1.01 -s`

`myscript.php -s one --three four five two -f 1.01`

```php
$parser = new ParParse();
$parser->addArgument('aaa')->setHelpText('First argument (a string).');
$parser->addArgument('bbb')->setHelpText('Second argument (a string).');
$parser->addArgument('ccc')->setDefault(0)->setType('int')->setHelpText('Third argument (a number, optional).');

$parser->addOption('three', 't')->setArity(2)->setDefault(array('foo', 'bar'))->setHelpText('First option (a string).');
$parser->addOption('five', 'f')->setDefault('')->setHelpText('Second option (a float).');

$parser->addFlag('seven', 's')->setHelpText('First flag.');
$parser->addFlag('eight', 'e')->setHelpText('Second flag (not used).');
```

```php
$results = $parser->parse();
print $results->aaa;
print $results->bbb;
print $results->ccc;
print_r($results->three);
print $results->five;
print $results->seven;
```

```
"one"
"two"
0
Array
(
  [0] => "four"
  [1] => "five"
)
1.01
true
false
```

#### Alternate Syntax
The syntax demonstrated in this documentation is really only one way out
of a few different ways to accomplish the same tasks. ParParse also supports
short versions of all public setters. Internally, it uses a magic `__call()`
method that simply calls `ParParseElement::setOption()` for unknown method calls.

```php
$parser = new ParParse();
$parser->argument('foo')
  ->arity(2)
  ->default(array(1, 2))
  ->validate('my_validation_callback')
  ->type('int')
  ->help('Foo does bar.');
```

Alternatively, you can construct a `ParParseArgument` or `ParParseOption`
object completely separate from the `ParParse` class API and call the
`ParParse::addElement()` method to add the element to the parser.

```php
$parser = new ParParse();
$arg = new ParParseArgument('foo');
$arg->setType('int')->setArity(2)->setDefault(array(1, 2))->setHelp('A couple numbers.');
$parser->addElement($arg);
```

### ParParse DSL
ParParse now features a simple and limited [Domain-specific language](http://en.wikipedia.org/wiki/Domain-specific_language)
that was implemented as an alternative to building argument definitions via the
traditional object-oriented API. The ParParse language is purposely designed
with a commonly understood syntax that generally follows the command-line usage
information format. Behind the scenes, the parser simply converts the argument
string and returns a `ParParseArgument` or `ParParseOption` object appropriately
configured according to the definition syntax. This ensures all standard
ParParse argument methods are still accessible after the argument is defined.

While this feature is still in development, here are a few examples of how
the ParParse argument definition language is used as of now.

#### Positional Arguments

As you'll see, arguments are denoted with surrounding brackets `<` and `>`.
Optional arguments will have additional square brackets `[` and `]`.
Unlimited arguments will be followed by three dots `...`.

```php
$parser = new ParParse();

// This is a simple positional argument with an arity of 1.
// Note that we define the help text after the two dashes.
$parser->add('<foo> -- "Foo does bar."');

// This is how we can access the argument.
echo $parser->parse()->foo;

// This positional argument has an arity of 1 but is not required.
$parser->add('[<foo>] -- "Foo does bar, but is not required."');

// This positional argument has an arity of 3.
// All three arguments are required.
$parser->add('<foo> <foo> <foo> -- "Foo does bar."');

// This positional argument has an arity of 3 with only 1 required argument.
$parser->add('<foo> [<foo>] [<foo>] -- "Foo does bar with at least 1 argument."');

// This positional argument has an unlimited arity and no minimum.
$parser->add('[<foo>...] -- "Foo does bar with unlimited arguments."');

// This positional argument has unlimited arity with a minimum of one argument.
$parser->add('<foo> [<foo> ...] -- "Foo does bar with unlimited arguments (at least 1)."');

// Use the standard API on top of the language.
$parser->add('<foo> <foo> -- "Foo does bar, twice."')->setType('string')->setDefault(array('Hello', 'world!'));
```

#### Optional Arguments

Optional arguments are defined a little differently than positional
arguments. The first argument will be the optional argument's switch,
either a long flag prefixed by `--` or a short flag prefixed by `-`.
If the option takes arguments then arguments follow. Long options
with one argument can use an equals `=` sign and the argument name
again in brackets `<` and `>`. Short options as usual do not use
equals signs.

```php
$parser = new ParParse();

// Boolean flags.
$parser->add('--bar -- "A simple boolean flag."');
$parser->add('--bar|-b -- "A simple boolean flag with a short alias."');

// Access the boolean flag.
echo $parser->parse()->bar;

// Single value options.
$parser->add('--bar|-b <foo> -- "A single value option."');
$parser->add('--bar=<foo> -- "A single value option."');

// Multi value options.
$parser->add('--bar|-b <foo> <foo> -- "An option with two values."');
$parser->add('--bar|-b <foo> [<foo>] -- "An option with two values, one required."');

// Unlimited value options.
$parser->add('--bar|-b <foo> [<foo>...] -- "An option with unlimited values, one required."');
$parser->add('--bar|-b <foo> <foo> <foo> [<foo>...] -- "An option with unlimited values, three required."');

// Using the API on top of the argument definition language.
// Get a float value and validate that it's less than 100.
$parser->add('--bar|-b <value> -- "A single value option."')->setType('float')->validate(create_function('$value', 'return $value < 100;'));
```
