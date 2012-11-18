<?php

/**
 * This file is part of the ParParse package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author     Jordan Halterman <jordan.halterman@gmail.com>
 * @copyright  2012 Jordan Halterman
 * @license    MIT License
 */

/**
 * Core PhpARgumentsPARSEr.
 *
 * @author Jordan Halterman <jordan.halterman@gmail.com>
 */
class ParParse {

  /**
   * The program description. This is used in generating usage text.
   *
   * @var string
   */
  private $description = '';

  /**
   * Optional text describing the usage of the command. If the usage text
   * is empty then the parser will generate usage text.
   *
   * @var string|null
   */
  private $usage = NULL;

  /**
   * An assiciative array of parsable element definitions, keyed by element type.
   *
   * @var array
   */
  private $elements = array();

  /**
   * An associative array of unique parser names. This is used to ensure
   * machine-names are not duplicated.
   *
   * @var array
   */
  private $uniqueNames = array();

  /**
   * Constructor.
   *
   * @param string $description
   *   The program description, used in generating usage text.
   * @param string|null $usage
   *   Optional usage text overriding the generated usage text.
   */
  public function __construct($description = '', $usage = NULL) {
    $this->description = $description;
    $this->usage = $usage;
  }

  /**
   * Adds a new parsable element to the parser.
   *
   * @param ParParseElement $element
   *   The parsable element definition object.
   *
   * @return ParParseElement
   *   The added element.
   */
  public function addElement(ParParseElementInterface $element) {
    if (array_search($element->getName(), $this->uniqueNames) !== FALSE) {
      throw new ParParseException('Cannot duplicate element name '. $element->getName() .'. An element with that name already exists.');
    }
    $this->elements[] = $element;
    $this->uniqueNames[] = $element->getName();
    return $element;
  }

  /**
   * Adds an argument definition to the argument parser.
   *
   * @param string $name
   *   The argument's machine-name, used to reference the argument value.
   * @param int $arity
   *   The number of arguments expected by the argument. Defaults to 1.
   * @param array $options
   *   An optional associative array of additional argument options.
   */
  public function argument($name, $arity = 1, array $options = array()) {
    $options += array('arity' => $arity);
    return $this->addElement(new ParParseArgument($name, $options));
  }

  /**
   * Adds a flag definition to the argument parser.
   *
   * @param string $long
   *   The flag long name.
   * @param string|null $short
   *   The flag short name.
   * @param array $options
   *   An optional associative array of additional flag options.
   */
  public function flag($long, $short = NULL, array $options = array()) {
    return $this->option($long, $short, $options)->arity(0);
  }

  /**
   * Adds an option definition to the argument parser.
   *
   * @param string $long
   *   The option's long machine-name identifier, with or without two dashes.
   * @param string|null $short
   *   The option's short name identifier, with or without one dash.
   * @paam array $options
   *   An associative array of additional option options.
   */
  public function option($long, $short = NULL, array $options = array()) {
    return $this->addElement(new ParParseOption($long, $short, $options));
  }

  /**
   * Parses command-line arguments.
   *
   * @param array|null $args
   *   An array of command line arguments. If no arguments are given
   *   then the default $argv arguments are used.
   *
   * @return ParParseResult
   *   A result instance from which result values can be retrieved.
   */
  public function parse(array $args = NULL) {
    if (!isset($args)) {
      global $argv;
      if (!isset($argv)) {
        throw new ArgParserException('Cannot parse arguments. No arguments given.');
      }
      else {
        $args = $argv;
      }
    }

    $command = $args[0];
    $args = array_slice($args, 1);

    // First check for the special '--help' option.
    if (in_array('--help', $args) || in_array('-h', $args)) {
      $this->printHelp($command);
      exit(0);
    }

    // Note that the order in which elements are parsed is essential to
    // ensure conflicts do not arise. We have to parse options and then
    // arguments, so we separate the elements and process them separately.
    try {
      $results = $options = $arguments = array();
      foreach ($this->elements as $element) {
        if ($element instanceof ParParseArgumentInterface) {
          $arguments[] = $element;
        }
        else if ($element instanceof ParParseOptionInterface) {
          $options[] = $element;
        }
      }

      foreach ($options as $option) {
        $args = array_values($args);
        $results[$option->getName()] = $option->parse($args);
      }

      // Positional arguments are parsed a little differently. Here we
      // pass an additional argument indicating whether this is the last
      // positional argument to be parsed. This is because only the last
      // positional argument can have default values.
      $num_args = count($arguments);
      for ($i = 0; $i < $num_args; $i++) {
        $arg = $arguments[$i];
        $args = array_values($args);
        $last_arg = !isset($arguments[$i+1]);
        $arguments[$i]->parse($args, $last_arg);
      }
      return new ParParseResult($results);
    }
    catch (ParParseException $e) {
      echo $e->getMessage() . "\n\n";
      $this->printHelp($command, $e);
      exit(1);
    }
  }

  /**
   * Prints command-line help text.
   */
  private function printHelp($command) {
    if (!is_null($this->usage)) {
      echo $this->usage;
    }

    $help = array();
    $usage = 'Usage: '. $command;
    foreach ($this->elements as $element) {
      $usage .= ' '. $element->printUsage();
    }

    $usage .= "\n";
    $help[] = $usage;

    $help[] = '-h|--help Display command usage information.'."\n";

    $arguments = $options = array();
    foreach ($this->elements as $element) {
      if ($element instanceof ParParseArgumentInterface) {
        $arguments[] = $element;
      }
      else if ($element instanceof ParParseOptionInterface) {
        $options[] = $element;
      }
    }

    $help[] = "\n";
    $indent = '  ';
    $help[] = 'Arguments:'."\n";
    foreach ($arguments as $arg) {
      $help[] = $arg->printHelp($indent) . "\n";
    }

    $help[] = "\n";
    $help[] = 'Options:'."\n";
    foreach ($options as $option) {
      $help[] = $option->printHelp($indent) . "\n";
    }
    echo implode('', $help);
  }

}

/**
 * Interface for parsable elements.
 *
 * @author Jordan Halterman <jordan.halterman@gmail.com>
 */
interface ParParseElementInterface {

  /**
   * Returns the element's command-line identifier.
   *
   * @return string
   *   The element's command-line identifier.
   */
  public function getName();

  /**
   * Sets the help text for the element.
   *
   * @param string $help
   *   The element's help text.
   *
   * @return ParParseElementInterface
   *   The called object.
   */
  public function setHelp($help);

  /**
   * Prints inline usage information for the sample command string.
   *
   * @return string
   *   Inline command usage.
   */
  public function printUsage();

  /**
   * Prints help text for the element.
   *
   * @param string $indent
   *   The indent string to use for the argument.
   *
   * @return string
   *   The help text for the element.
   */
  public function printHelp($indent = '');

}

/**
 * Interface for positional arguments.
 */
interface ParParseArgumentInterface extends ParParseElementInterface {

  /**
   * Parses positional arguments from command-line arguments.
   *
   * @param array $args
   *   An array of command-line arguments.
   * @param bool $last_arg
   *   Indicates whether this is the last positional argument. Defaults to FALSE.
   *
   * @return mixed
   *   The element value.
   */
  public function parse(array &$args, $last_arg = FALSE);

}

/**
 * Interface for optional arguments.
 */
interface ParParseOptionInterface extends ParParseElementInterface {

  /**
   * Parses elements from command-line arguments.
   *
   * @param array $args
   *   An array of command-line arguments.
   *
   * @return mixed
   *   The element value.
   */
  public function parse(array &$args);

}

/**
 * Base class for all parsable command-line elements.
 *
 * @author Jordan Halterman <jordan.halterman@gmail.com>
 */
abstract class ParParseElement implements ParParseElementInterface {

  /**
   * Indicates unlimited arity.
   *
   * @var int
   */
  const ARITY_UNLIMITED = -1;

  /**
   * Constants representing the relationship between string names
   * and the datatypes supported by PHP's settype() function.
   */
  const DATATYPE_BOOLEAN = 'bool';
  const DATATYPE_INTEGER = 'int';
  const DATATYPE_FLOAT = 'float';
  const DATATYPE_STRING = 'string';
  const DATATYPE_ARRAY = 'array';
  const DATATYPE_OBJECT = 'object';
  const DATATYPE_NULL = 'null';

  /**
   * A list of available data types for settype().
   *
   * @var array
   */
  private static $dataTypes = array(
    ParParseElement::DATATYPE_BOOLEAN,
    ParParseElement::DATATYPE_INTEGER,
    ParParseElement::DATATYPE_FLOAT,
    ParParseElement::DATATYPE_STRING,
    ParParseElement::DATATYPE_ARRAY,
    ParParseElement::DATATYPE_OBJECT,
    ParParseElement::DATATYPE_NULL,
  );

  /**
   * The element's unique machine name.
   *
   * @var string
   */
  protected $name;

  /**
   * The number of command line arguments expected by this element.
   *
   * @var int
   */
  protected $arity = 1;

  /**
   * The argument's data type.
   *
   * @var string|null
   */
  protected $dataType = NULL;

  /**
   * The element's command-line help text.
   *
   * @var string
   */
  protected $helpText = '';

  /**
   * The argument's default value.
   *
   * Note that only the last argument in a set of arguments can have
   * a default value, otherwise an exception will be thrown by the parser.
   *
   * @var string|null
   */
  protected $defaultValue = NULL;

  /**
   * Constructor.
   *
   * @param string $name
   *   The element's unique machine-name.
   * @param array $options
   *   An associative array of additional element options.
   */
  public function __construct($name, array $options = array()) {
    $this->name = $name;
    foreach ($options as $option => $value) {
      $this->setOption($option, $value);
    }
  }

  /**
   * Magic method: Calls setter methods for internal attributes.
   */
  public function __call($method, $args) {
    if (method_exists($this, 'set'. $method)) {
      return $this->setOption($method, $args[0]);
    }
    else if (method_exists($this, 'add'. $method)) {
      return call_user_func_array(array($this, 'add'. $method), $args);
    }
  }

  /**
   * Magic method: Gets an arbitrary element option.
   */
  public function __get($option) {
    try {
      return $this->getOption($option);
    }
    catch (InvalidArgumentException $e) {
      // Do nothing. Allow the option to be improperly accessed, which
      // should throw an error anyways.
    }
  }

  /**
   * Gets an arbitrary element option.
   *
   * @param string $option
   *   The name of the option to return.
   *
   * @return mixed
   *   The option value.
   *
   * @throws InvalidArgumentException
   *   If the given argument does not exist.
   */
  public function getOption($option) {
    if (strpos($option, '_') !== FALSE) {
      $option = implode('', array_map('ucfirst', explode('_', $option)));
    }
    $method = 'get'. ucfirst($option);
    if (!method_exists($this, $method)) {
      throw new InvalidArgumentException('Invalid option '. $option .'.');
    }
    return $this->{$method}();
  }

  /**
   * Magic method: Sets an arbitrary element option.
   */
  public function __set($option, $value) {
    return $this->setOption($option, $value);
  }

  /**
   * Sets an arbitrary element option.
   *
   * Note that setting values is done through setter methods, so only
   * options that have unique setter methods can be accessed.
   *
   * @param string $option
   *   The option to set.
   * @param mixed $value
   *   The option value to set.
   *
   * @return ParParseElement
   *   The called object.
   */
  public function setOption($option, $value) {
    if (strpos($option, '_') !== FALSE) {
      $option = implode('', array_map('ucfirst', explode('_', $option)));
    }
    $method = 'set'. ucfirst($option);
    if (!method_exists($this, $method)) {
      throw new InvalidArgumentException('Invalid option '. $option .'.');
    }
    return $this->{$method}($value);
  }

  /**
   * Returns the element's machine name.
   *
   * @return string
   *   The element's machine name.
   */
  public function getName() {
    return $this->name;
  }

  /**
   * Sets the help text for the element.
   *
   * @param string $help
   *   The element's help text.
   *
   * @return ParParseElement
   *   The called object.
   */
  public function setHelp($help) {
    if (!is_string($help)) {
      throw new InvalidArgumentException('Invalid help text for element '. $this->name .'. Help text must be a string.');
    }
    $this->helpText = $help;
    return $this;
  }

  /**
   * Sets the element's arity.
   *
   * @param int $arity
   *   The element's arity.
   *
   * @return ParParseElement
   *   The called object.
   */
  public function setArity($arity) {
    if (!is_numeric($arity)) {
      throw new InvalidArgumentException('Invalid arity '. $arity .'. Arity must be numeric.');
    }
    $this->arity = (int) $arity;
    return $this;
  }

  /**
   * Sets the element's default value.
   *
   * @param mixed $default
   *   The default value.
   *
   * @return ParParseElement
   *   The called object.
   */
  public function setDefault($default) {
    $this->defaultValue = $default;
    return $this;
  }

  /**
   * Sets the argument's data type.
   *
   * @param string $data_type
   *   The data type.
   *
   * @return ParParseArgument
   *   The called object.
   */
  public function setType($data_type) {
    if (!in_array($data_type, self::$dataTypes) && !class_exists($data_type)) {
      throw new InvalidArgumentException('Invalid data type '. $data_type .'. Data type must be a PHP type or available class.');
    }
    $this->dataType = $data_type;
    return $this;
  }

  /**
   * Applies the current data type to the given value.
   *
   * @param string $value
   *   The value to which to apply the data type.
   *
   * @return mixed
   *   The new value with the data type applied.
   */
  protected function applyDataType($value) {
    if (!isset($this->dataType)) {
      return $value;
    }
    if (in_array($this->dataType, self::$dataTypes)) {
      settype($value, $this->dataType);
      return $value;
    }
    else {
      if (!class_exists($this->dataType)) {
        throw new InvalidArgumentException('Invalid data type '. $this->dataType .'. Data type must be a PHP type or available class.');
      }
      return new $this->dataType($value);
    }
  }

}

/**
 * Represents an argument.
 *
 * @author Jordan Halterman <jordan.halterman@gmail.com>
 */
class ParParseArgument extends ParParseElement implements ParParseArgumentInterface {

  /**
   * Indicates whether the argument has a default value.
   *
   * @var bool
   */
  private $hasDefault = FALSE;

  /**
   * Parses positional arguments from command-line arguments.
   *
   * @param array $args
   *   An array of command-line arguments.
   * @param bool $last_arg
   *   Indicates whether this is the last positional argument.
   *
   * @return mixed
   *   The argument value.
   */
  public function parse(array &$args, $last_arg = FALSE) {
    $args = array_values($args);
    $num_args = count($args);

    // Get only valid arguments to validate that the proper arguments exist.
    $valid_args = array();
    foreach ($args as $arg) {
      if (strpos($arg, '-') === 0) {
        continue;
      }
      $valid_args[] = $arg;
    }

    // Ensure the correct number of arguments even exist.
    // Default values can only be applied to the last positional argument definition.
    $num_valid_args = count($valid_args);
    if (($num_valid_args === 0 || $num_valid_args < $this->arity) && (!$last_arg || !$this->hasDefault)) {
      throw new ParParseMissingArgumentException('Missing argument '. $this->name .'.');
    }

    switch ($this->arity) {
      case 1:
        for ($i = 0; $i < $num_args; $i++) {
          if (strpos($args[$i], '-') === 0) {
            continue;
          }
          else {
            $value = $args[$i];
            unset($args[$i]);
            return $this->applyDataType($value);
          }
        }
        return $this->defaultValue;
      default:
        if (is_array($this->defaultValue)) {
          $values = $this->defaultValue;
        }
        else {
          for ($i = 0; $i < $this->arity; $i++) {
            $values[] = $this->defaultValue;
          }
        }

        $position = 0;
        for ($i = 0; $i < $num_args; $i++) {
          if (strpos($args[$i], '-') === 0) {
            continue;
          }
          else {
            $values[$position++] = $this->applyDataType($args[$i]);
            unset($args[$i]);
          }

          if ($this->arity !== self::ARITY_UNLIMITED && $position == $this->arity) {
            break;
          }
        }
        return $values;
    }
  }

  /**
   * Prints inline usage information for the sample command string.
   *
   * @return string
   *   Inline command usage.
   */
  public function printUsage() {
    $usage = '';
    if ($this->arity == self::ARITY_UNLIMITED) {
      $usage .= ' '. $this->name .' ['. $this->name .' ...]';
    }
    else {
      for ($i = 0; $i < $this->arity; $i++) {
        $usage .= ' '. $this->name;
      }
    }
    return trim($usage);
  }

  /**
   * Prints help text for the argument.
   *
   * @return string
   *   A help text string.
   */
  public function printHelp($indent = '') {
    return $indent . $this->name . $indent . $this->help;
  }

  /**
   * Sets the argument's default value.
   *
   * Note that only the last argument in a set of arguments can have
   * a default value, otherwise an exception will be thrown by the parser.
   *
   * @param mixed $default
   *   The default value.
   *
   * @return ParParseArgument
   *   The called object.
   */
  public function setDefault($default) {
    $this->hasDefault = TRUE;
    $this->defaultValue = $default;
    return $this;
  }

}

/**
 * Represents an option - prefixed with -- or -.
 *
 * @author Jordan Halterman <jordan.halterman@gmail.com>
 */
class ParParseOption extends ParParseElement implements ParParseOptionInterface {

  /**
   * Indicates that no name should be used.
   *
   * @var null
   */
  const NAME_NONE = NULL;

  /**
   * The option's long name. Defaults to the option name upon construction.
   *
   * @var string
   */
  private $long = NULL;

  /**
   * The option's short name. Defaults to NULL.
   *
   * @var string|null
   */
  private $short = NULL;

  /**
   * An array of option aliases.
   *
   * @var array
   */
  private $aliases = array();

  /**
   * Constructor.
   *
   * @param string $long
   *   The element's unique long machine-name.
   * @param string|null $short
   *   The element's unique short machine-name.
   * @param array $options
   *   An associative array of additional option options.
   */
  public function __construct($long, $short = NULL, array $options = array()) {
    $this->setLong($long);
    $options += array('short' => $short);
    parent::__construct($long, $options);
  }

  /**
   * Prints inline usage information for the sample command string.
   *
   * @return string
   *   Inline command usage.
   */
  public function printUsage() {
    $usage = '[';
    if ($this->short) {
      $usage .= '-'. $this->short;
    }
    else {
      $usage .= '--'. $this->long;
    }

    if ($this->arity == 1) {
      $usage .= ' <'. $this->name .'>';
    }
    else {
      $num_args = $this->arity;
      if ($this->arity == self::ARITY_UNLIMITED) {
        $num_args = 3;
      }
      for ($i = 0; $i < $num_args; $i++) {
        $usage .= ' <'. $this->name . $i .'>';
      }
    }
    $usage .= ']';
    return $usage;
  }

  /**
   * Prints help text for the option.
   */
  public function printHelp($indent = '') {
    $opt_text = $indent;
    if ($this->long) {
      $opt_text .= '--'. $this->long;
      if ($this->short) {
        $opt_text .= ' | -'. $this->short;
      }
    }
    else if ($this->short) {
      $opt_text .= '-'. $this->short;
    }

    $opt_text .= $indent . $this->help;
    return $opt_text;
  }

  /**
   * Parses options from command-line arguments.
   *
   * @param array $args
   *   An array of command-line arguments.
   *
   * @return mixed
   *   The option value, or default value if the option wasn't found.
   */
  public function parse(array &$args) {
    $num_args = count($args);

    // If the option's aurity is 0 then this is a boolean flag.
    // If the option's aurity is unlimited then get all argument after.

    if (isset($this->long)) {
      $long_id = '--'. $this->long;
    }
    if (isset($this->short)) {
      $short_id = '-'. $this->short;
    }
    $alias_ids = array();
    foreach ($this->aliases as $alias) {
      $alias_ids[] = '--'. $alias;
    }

    if ($this->arity == 0) {
      return $this->parseFlag($args);
    }
    else {
      return $this->parseOption($args);
    }

    for ($i = 0; $i < $num_args; $i++) {
      if (isset($this->long)) {
        $result = $this->checkArg($args, $i, $long_id);
        if (isset($result)) {
          return $result;
        }
      }
      if (isset($this->short)) {
        $result = $this->checkArg($args, $i, $short_id);
        if (isset($result)) {
          return $result;
        }
      }
      foreach ($alias_ids as $alias_id) {
        $result = $this->checkArg($args, $i, $alias_id);
        if (isset($result)) {
          return $result;
        }
      }
    }
    return $this->defaultValue;
  }

  /**
   * Parses command line arguments as a boolean flag.
   */
  private function parseFlag(array &$args) {
    $num_args = count($args);
    for ($i = 0; $i < $num_args; $i++) {
      if (isset($this->long) && $args[$i] == '--'. $this->long) {
        unset($args[$i]);
        return $this->applyDataType(TRUE);
      }
      else if (isset($this->short) && $args[$i] == '-'. $this->short) {
        unset($args[$i]);
        return $this->applyDataType(TRUE);
      }
      foreach ($this->aliases as $alias) {
        if ($args[$i] == '--'. $alias) {
          unset($args[$i]);
          return $this->applyDataType(TRUE);
        }
      }
    }
    return $this->defaultValue;
  }

  /**
   * Parses command line arguments for option with values.
   */
  private function parseOption(array &$args) {
    $num_args = count($args);
    $option_ids = array();
    if (isset($this->long)) {
      $option_ids[] = '--'. $this->long;
    }
    if (isset($this->short)) {
      $option_ids[] = '-'. $this->short;
    }
    foreach ($this->aliases as $alias) {
      $alias_ids[] = '--'. $alias;
    }
    foreach ($option_ids as $option_id) {
      for ($i = 0; $i < $num_args; $i++) {
        if ($args[$i] == $option_id) {
          return $this->getValueFromNextArg($args, $i);
        }
        foreach (array('=', ':', '') as $separator) {
          if (strpos($args[$i], $option_id.$separator) === 0) {
            return $this->getValueFromArg($args, $i, $option_id.$separator);
          }
        }
      }
    }
    return $this->defaultValue;
  }

  /**
   * Attempts to get the option value from the command. This is used
   * in cases where an equals sign was used for the option value.
   *
   * @param array $args
   *   An array of command line arguments.
   * @param int $position
   *   The position of this argument.
   * @param string $prefix
   *   The prefix to remove from the argument.
   *
   * @return mixed
   *   The processed argument.
   */
  private function getValueFromArg(array &$args, $position, $prefix) {
    $arg = $args[$position];
    unset($args[$position]);
    $value = substr($arg, strlen($prefix));
    // If the arity is greater than one then return this as an array.
    if ($this->arity == self::ARITY_UNLIMITED || $this->arity > 1) {
      // defaults = array(1, 2, 3)
      // values = array(1, 2)
      $default_values = $this->defaultValue;
      $values = array_map('trim', explode(',', trim($value, '"')));
      if (is_array($default_values)) {
        if (count($values) < $this->arity && count($default_values) < $this->arity) {
          throw new ParParseMissingArgumentException($this->name .' requires '. $this->arity .' arguments.');
        }
        for ($i = 0, $value_count = count($values); $i < $value_count; $i++) {
          $default_values[$i] = $this->applyDataType($values[$i]);
        }
        return $default_values;
      }
      else {
        if (count($values) < $this->arity) {
          throw new ParParseMissingArgumentException($this->name .' requires '. $this->arity .' arguments.');
        }
        $return_values = array();
        foreach ($values as $value) {
          $return_values[] = $this->applyDataType($value);
        }
        return $return_values;
      }
    }
    return $this->applyDataType($value);
  }

  /**
   * Attempts to get the option value from the next command line argument.
   *
   * @param array $args
   *   An array of command line arguments.
   * @param int $position
   *   The position of this option in arguments.
   *
   * @return mixed
   *   The processed option value.
   */
  private function getValueFromNextArg(array &$args, $position) {
    unset($args[$position]);
    $position++;

    // If this argument has unlimited arity then get all valid
    // arguments up to the next option.
    if ($this->arity == self::ARITY_UNLIMITED) {
      $values = array();
      // If default value is an array then apply the given arguments
      // over the array of default values.
      if (is_array($this->defaultValue)) {
        $values = $this->defaultValue;
      }
      for ($i = $position, $index = 0, $num_args = count($args); $i < $num_args; $i++) {
        if (strpos($args[$i], '-') !== 0) {
          $values[$index++] = $this->applyDataType($args[$i]);
          unset($args[$i]);
        }
        else {
          break;
        }
      }
      return $values;
    }
    else {
      // First get all the values up to the next argument or the arity is reached.
      $values = array();
      for ($i = $position; $i < $position + $this->arity; $i++) {
        if (!isset($args[$i]) || strpos($args[$i], '-') === 0) {
          break;
        }
        else {
          $values[] = $this->applyDataType($args[$i]);
        }
      }

      // If the number of values given were less than the arity then try to
      // apply default values.
      if (count($values) < $this->arity) {
        if (is_array($this->defaultValue) && count($this->defaultValue) > $count(values)) {
          $values = array_values($values) + array_values($this->defaultValue);
        }
        if (count($values) < $this->arity) {
          throw new ParParseMissingArgumentException($this->name .' expects '. $this->arity .' arguments.');
        }
      }
      return $values;
    }
  }

  /**
   * Sets the option's long name.
   *
   * @param string|null $long
   *   The option's long name. If no name is given then the option
   *   will be assumed to have no long name.
   *
   * @return ParParseOption
   *   The called object.
   */
  public function setLong($long) {
    if (!is_string($long) && !is_null($long)) {
      throw new InvalidArgumentException('Invalid long name. Long name must be a string.');
    }
    if (!isset($long)) {
      $this->long = NULL;
      return $this;
    }
    if (strpos($long, '--') === 0) {
      $this->long = substr($long, 2);
    }
    else {
      $this->long = $long;
    }
    return $this;
  }

  /**
   * Sets the option's short name.
   *
   * @param string $alias
   *   The option's short name. If no name is given then the option will be
   *   assumed to have no short name.
   *
   * @return ParParseOption
   *   The called object.
   */
  public function setShort($short = NULL) {
    if (!isset($short)) {
      $this->short = NULL;
      return $this;
    }
    if (!is_string($short)) {
      throw new InvalidArgumentException('Invalid short name. Short name must be a string.');
    }
    if (strpos($short, '-') === 0) {
      $this->short = substr($short, 1);
    }
    else {
      $this->short = $short;
    }
    return $this;
  }

  /**
   * Adds an option alias.
   *
   * @param string $alias
   *   The option alias.
   *
   * @return ParParseOption
   *   The called object.
   */
  public function addAlias($alias) {
    if (!is_string($alias)) {
      throw new InvalidArgumentException('Invalid alias. Alias must be a string.');
    }
    $this->aliases[] = $alias;
    return $this;
  }

  /**
   * Sets the option's data type.
   *
   * If the data type is set to 'bool' then we assume that this option
   * is a boolean flag and set its arity to zero.
   *
   * @param string $data_type
   *   The data type.
   *
   * @return ParParseOption
   *   The called object.
   */
  public function setType($data_type) {
    if ($data_type == self::DATATYPE_BOOLEAN) {
      $this->arity = 0;
    }
    return parent::setType($data_type);
  }

  /**
   * Sets the option's arity.
   *
   * Similar to when data types are set, if the arity is set to zero
   * then we set the data type to boolean.
   *
   * @param int $arity
   *   The element's arity.
   *
   * @return ParParseOption
   *   The called object.
   */
  public function setArity($arity) {
    parent::setArity($arity);
    if ($this->arity == 0) {
      $this->dataType = self::DATATYPE_BOOLEAN;
    }
    return $this;
  }

}

/**
 * Parsed arguments results.
 *
 * @author Jordan Halterman <jordan.halterman@gmail.com>
 */
class ParParseResult {

  /**
   * Constructor.
   *
   * @param array $results
   *   An associative array of parsed results.
   */
  public function __construct(array $results) {
    $this->results = $results;
  }

  /**
   * Magic method: Gets a result value.
   */
  public function __get($name) {
    try {
      return $this->get($name);
    }
    catch (ParParseException $e) {
      // Do nothing if the result doesn't exist.
    }
  }

  /**
   * Gets a parser result.
   *
   * @param string $name
   *   The name of the element whose result to return.
   *
   * @return mixed
   *   The element result.
   */
  public function get($name) {
    if (isset($this->results[$name])) {
      return $this->results[$name];
    }
    throw new ParParseException('No result for '. $name .' exist.');
  }

}

/**
 * Base ParParse exception.
 *
 * @author Jordan Halterman <jordan.halterman@gmail.com>
 */
class ParParseException extends Exception {}

/**
 * Missing argument exception.
 *
 * @author Jordan Halterman <jordan.halterman@gmail.com>
 */
class ParParseMissingArgumentException extends ParParseException {}

$parser = new ParParse();
$parser->argument('foo')->arity(1)->help('Foo argument does bar.');

// Setting the 'type' to 'bool' automatically turns this into a switch.
// Any arguments given after the switch will be ignored.
// Also, setting the arity to 0 will convert it to a switch.
$parser->option('bar')->short('b')->type('bool');
// $parser->option('bar')->short('b')->alias('baz')->arity(0);

$parser->flag('baz')->short('ba')->type('bool');

$parser->option('boo')->short('o')->type('int');

$parser->parse();
