ParParse
========

**MIT License**

ParParse is a command-line arguments parser written in PHP. It is both
lightweight and feature rich, supporting a range of argument types -
from positional arguments to boolean flags and named options - as well
as many additional features like long and short named arguments (most of
the focus is on conventional Unix syntax), default values, multiple
arguments, enforced data types, data validation, all in a single
include file. To get started all you need to do is copy _ParParse.php_
to your project directory and use `require 'path/to/ParParse.php`.

Usage
-----

### The Hello World! Example

#### Positional Arguments

This is a breif example on how we can parse a simple command with only
a single positional argument:

`myscript.php "Hello world!"`

To parse the script, we simply create a new `ParParse` object, call the
`ParParse::argument()` method, and add some basic options to the argument
object. Upon instantiation, arguments and options have basic settings that
are ideal in most cases. I'll get into the available settings further below.

```php
require 'path/to/ParParse.php';
$parser = new ParParse();
$parser->argument('text')->help('A string of text to print.');
$results = $parser->parse();
echo $results->text;
```

This is a pretty simple script. We simply add a _positional argument_ to
the parser named `text` and add some helpful help text to the argument.
Help text is used in generating usage information for display on the command
line. For example, if we simple type `myscript.php` we'll see something
like this:

```
Missing argument 'text'.
Usage: myscript.php text
-h|--help        Display command usage information.

Arguments:
  text:          A string of text to print.
```
Note that you can also pass custom usage text as the second argument
to the parser's constructor.

Alternatively, if we type the command `myscript.php "Hello world!"` we get:

```
Hello world!
```

#### Boolean Flags
Of course, there is a lot more we can do with positional arguments. But
I'll get back to the advanced features down below. For now, let's look at
the next supported argument type, boolean flags.

What if we want to do the _Hello world_ example using a boolean flag
and a command that looks something like this?

`myscript.php "Hello world" --exclaim

```php
$parser = new ParParse();
$parser->argument('text')->help('A string of text to print.');
$parser->flag('exclaim', 'x')->help('Use an exclamation point.');
$results = $parser->parse();
if ($results->exclaim) {
  echo $results->text . '!';
}
```
Note here the second argument to the flag. It's the short name of the flag,
so this flag can be used via `--exclaim` or `-x`.

#### Named Options
Finally, using the command line with only positional arguments and switches
can be very limiting. When it comes to building a really useful command line
tool we often need to be able to explicitly set values. Of course that can
be done with ParParse as well.

What if we change our _Hello world!_ example to use some optional arguments?
Maybe we want a flag to print it in all caps and be able to optionally append
a name to the positional argument's text.

`myscript.php Hello -c --name James -s=!`

Here we still have the same positional argument as before, but with a boolean
flag for capitalizing the text `-c`, an option for a name `--name`, and another
option for the suffix of the string.

```php
$parser->argument('text')->help('A string of text to print.');
$parser->flag('caps', 'c')->help('Capitalize text.');
$parser->option('name')->short('n')->help('An optional name.');
$results = $parser->parse();
$text = $results->text;
if ($results->name) {
  $text .= $results->name;
}
if ($results->caps) {
  $text = strtoupper($text);
}
```

### Advanced Features

Now, it looks like some useful features could cut down on the use of code.
Time for another program - a math program. We want to be able to do basic
calculations using a simple command.

`mathscript.php 1.2 3.4 4.5 --calc=sum --round`

```php
$parser->argument('numbers')
  ->arity(ParParseArgument::ARITY_UNLIMITED)
  ->type(ParParseArgument::DATATYPE_FLOAT)
  ->help('A list of numbers to calculate.');

$parser->option('calc', 'c')
  ->default(NULL)
  ->help('The function to use to perform calculations.');

$parser->flag('round', 'r')->help('Indicates that the result should be rounded to the nearest zero.');
$results = $parser->parse();
echo $results->numbers; // array(1.2, 3.4, 4.5);
// Calculate the result.
```

Note here that I used a couple new methods. The arity setting indicates how
many instanves of the argument are _allowed_. `ArgParseElement::arity()` is
available on all types of elements - the `ArgParseElement` class is the base
of both `ArgParseArgument` and `ArgParseOption`, and the flag element is
simply an `ArgParseOption` object with an arity of `0`. The arity of all
elements always defaults to 0, except for the special case of flags. Note
that there is a separate method for setting the _minimum_ number of arguments
required in options (minimums do not work with positional arguments because
of their nature).

Also, this snippet introduces the data type settings as well. Data type
constants simply indicate the data type string required by PHP's `settype()`
function, so you can simply pass a string matching the appropriate data
type as well. _The data type will be applied to each value_, not the entire
set of values.

#### Default values

Finally, we set a simple default value on the `calc` option. Note that there
are some important caveats to using default values. When working with positional
arguments, default values can only be applied to the last positional argument.
When setting the default value of an element that supports multiple values (has
an arity > 1) you can either set a non-array value that will apply to all elements
of the resulting array of an array of default values. Take this example:

```php
$parser->option('foo')->arity(3)->min(1)
  ->default(array(3, 2, 1))->type('int');
```
If we use the command `myscript.php --foo 1` the result will be:
```php
$results = $parser->parse();
print $results->foo; // array(1, 2, 1);
```
Since the command met the minimum limit of one but not the arity, the rest
of the default values were applied. Note that array defaults *must* match the
arity number unless the arity is unlimited.

#### Validation

In the case of the last example, we need a way to validate that the calculation
function being passed is one that is acceptable for various reasons, one of
which might be security in some cases.

```php
function validate_calc($calc) {
  return $calc == 'sum';
}

$parser->option('calc', 'c')
  ->validate('validate_calc')
  ->type('string')
  ->help('A calculation function.');
```

#### A final note on syntax
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
