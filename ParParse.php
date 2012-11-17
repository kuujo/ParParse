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
   * An associative array of unique parser identifiers. This is used to ensure
   * machine-names are not duplicated.
   *
   * @var array
   */
  private $uniqueIdentifiers = array();

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
   * @param ParParseParsableElement $element
   *   The parsable element definition object.
   *
   * @return ParParseParsableElement
   *   The added element.
   */
  public function addElement(ParParseParsableElementInterface $element) {
    if (array_search($element->getIdentifier(), $this->uniqueIdentifiers) !== FALSE) {
      throw new ParParseException('Cannot duplicate element identifier '. $element->getIdentifier() .'. An element with that identifier already exists.');
    }
    $this->elements[] = $element;
    $this->uniqueIdentifiers[] = $element->getIdentifier();
    return $element;
  }

  /**
   * Adds an argument definition to the argument parser.
   *
   * @param string $identifier
   *   The argument's machine-name, used to reference the argument value.
   * @param int $cardinality
   *   The number of arguments expected by the argument. Defaults to 1.
   * @param array $options
   *   An optional associative array of additional argument options.
   */
  public function addArgument($identifier, $cardinality = 1, array $options = array()) {
    if (strpos($identifier, '--') === 0) {
      return $this->addElement(new ParParseOption($identifier, $options));
    }
    else {
      return $this->addElement(new ParParseArgument($identifier, $options));
    }
    $options += array('cardinality' => $cardinality);
    return $this->addElement(new ParParseArgument($identifier, $options));
  }

  /**
   * Adds an option definition to the argument parser.
   *
   * @param string $identifier
   *   The option's machine-name identifier, with or without two dashes.
   * @param string|null $alias
   *   The option's alias, prefixed with one dash.
   */
  public function addOption($identifier, $alias = NULL, $default = NULL, array $options = array()) {
    $options += array('alias' => $alias, 'default_value' => $default);
    return $this->addElement(new ParParseOption($identifier, $options));
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
      $this->printHelpText($command);
      exit(0);
    }

    // Note that the order in which elements are parsed is essential to
    // ensure conflicts do not arise. We have to parse options and then
    // arguments, so we separate the elements and process them separately.
    try {
      $results = $options = $arguments = array();
      foreach ($this->elements as $element) {
        $element_type = $element->getType();
        if (isset(${$element_type.'s'})) {
          ${$element_type.'s'}[] = $element;
        }
      }

      foreach ($options as $option) {
        $args = array_values($args);
        $results[$option->getIdentifier()] = $option->parse($args);
      }

      // Arguments are treated a little differently. Only the last argument
      // in the set can have missing or default values. This is because
      // missing arguments earlier in the process would ruin the order.
      $num_args = count($arguments);
      for ($i = 0; $i < $num_args; $i++) {
        $arg = $arguments[$i];
        $args = array_values($args);
        try {
          $results[$arg->getIdentifier()] = $arg->parse($args);
        }
        catch (ParParseMissingArgumentException $e) {
          if (isset($arguments[$i+1])) {
            throw $e;
          }
          else {
            try {
              $results[$arg->getIdentifier()] = $arg->getDefaultValue();
            }
            catch (ParParseException $e) {
              throw new ParParseMissingArgumentException('Missing argument '. $arg->getIdentifier() .'.');
            }
          }
        }
      }
      return new ParParseResult($results);
    }
    catch (ParParseException $e) {
      $this->printHelpText($command);
      exit(1);
    }
  }

  /**
   * Prints command-line help text.
   */
  private function printHelpText($command) {
    if (!is_null($this->usage)) {
      echo $this->usage;
    }

    $help = array();
    $usage = 'Usage: '. $command;
    foreach ($this->elements as $element) {
      switch ($element->getType()) {
        case 'argument':
          $usage .= $this->printArgument($element);
          break;
        case 'option':
          $usage .= $this->printOption($element);
          break;
      }
    }

    $usage .= "\n";
    $help[] = $usage;

    $help[] = "\n";
    $help[] = '-h|--help Display command usage information.'."\n";

    $arguments = $options = array();
    foreach ($this->elements as $element) {
      if ($element->getType() === 'argument') {
        $arguments[] = $element;
      }
      else {
        $options[] = $element;
      }
    }

    $help[] = "\n";
    $indent = '  ';
    $help[] = 'Arguments:'."\n";
    foreach ($arguments as $arg) {
      $help[] = $indent . $arg->getIdentifier() . $indent . $arg->getHelpText() . "\n";
    }

    $help[] = "\n";
    $help[] = 'Options:'."\n";
    foreach ($options as $option) {
      $opt_text = '--'. $option->getIdentifier();
      if ($option->getAlias()) {
        $opt_text = '-'. $option->getAlias() .' '. $opt_text;
      }
      if ($option->getValueDescriptor()) {
        $opt_text .= ' '. $option->getValueDescriptor();
      }
      $opt_text .= $indent . $option->getHelpText();
      $opt_text .= "\n";
      $help[] = $indent . $opt_text;
    }
    echo implode('', $help);
  }

  /**
   * Returns command-line help text for an argument.
   */
  private function printArgument(ParParseArgument $arg) {
    $usage = '';
    $cardinality = $arg->getCardinality();
    if ($cardinality == ParParseArgument::UNLIMITED) {
      $usage .= ' '. $arg->getIdentifier() .' ['. $arg->getIdentifier() .' ...]';
    }
    else {
      for ($i = 0; $i < $cardinality; $i++) {
        $usage .= ' '. $arg->getIdentifier();
      }
    }
    return $usage;
  }

  /**
   * Returns command-line help text for an option.
   */
  private function printOption(ParParseOption $option) {
    if ($option->getAlias()) {
      if ($option->getValueDescriptor()) {
        return ' ['. $option->getAlias() .'|'. $option->getIdentifier() .'=<'. $option->getValueDescriptor() .'>]';
      }
      else {
        return ' ['. $option->getAlias() .'|'. $option->getIdentifier() .']';
      }
    }
    return ' ['. $param->getIdentifier() .'=<value>]';
  }

}

/**
 * Interface for parsable elements.
 *
 * @author Jordan Halterman <jordan.halterman@gmail.com>
 */
interface ParParseParsableElementInterface {

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

  /**
   * Returns the element type.
   *
   * @return string
   *   The element type.
   */
  public function getType();

  /**
   * Returns the element's command-line identifier.
   *
   * @return string
   *   The element's command-line identifier.
   */
  public function getIdentifier();

  /**
   * Returns help text for the element.
   *
   * @return string
   *   The element's command-line help text.
   */
  public function getHelpText();

  /**
   * Sets the help text for the element.
   *
   * @param string $help
   *   The element's help text.
   *
   * @return ParParseParsableElementInterface
   *   The called object.
   */
  public function setHelpText($help);

}

/**
 * Interface for elements that support aliases.
 *
 * @author Jordan Halterman <jordan.halterman@gmail.com>
 */
interface ParParseAliasableInterface {

  /**
   * Returns the element's short alias.
   *
   * @return string
   *   The element's short alias.
   */
  public function getAlias();

  /**
   * Sets the element's short alias.
   *
   * @param string $alias
   *   The element's short alias.
   *
   * @return ParParseElement
   *   The called object.
   */
  public function setAlias($alias);

}

/**
 * Interface for elements that can convert values by data types.
 *
 * @author Jordan Halterman <jordan.halterman@gmail.com>
 */
interface ParParseTypeableInterface {

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
   * Sets the argument's data type.
   *
   * @param string $data_type
   *   The data type.
   *
   * @return ParParseArgument
   *   The called object.
   */
  public function setDataType($data_type);

}

/**
 * Interface for elements that support default values.
 *
 * @author Jordan Halterman <jordan.halterman@gmail.com>
 */
interface ParParseDefaultableInterface {

  /**
   * Returns the element's default value.
   *
   * @return mixed
   *   The element's default value.
   */
  public function getDefaultValue();

  /**
   * Sets the element's default value.
   *
   * @param mixed $default
   *   The default value.
   *
   * @return ParParseElement
   *   The called object.
   */
  public function setDefaultValue($default);

}

/**
 * Base class for all parsable command-line elements.
 *
 * @author Jordan Halterman <jordan.halterman@gmail.com>
 */
abstract class ParParseParsableElement implements ParParseParsableElementInterface, ParParseTypeableInterface, ParParseDefaultableInterface {

  /**
   * The parsable element's type. This should be set in the class definition
   * and is used to determine the order in which parsers are executed.
   *
   * @var string
   */
  protected $type = '';

  /**
   * The element's unique machine identifier.
   *
   * @var string
   */
  protected $identifier;

  /**
   * The argument's data type.
   *
   * @var string|null
   */
  protected $dataType = NULL;

  /**
   * A list of available data types for settype().
   *
   * @var array
   */
  private static $dataTypes = array(
    ParParseTypeableInterface::DATATYPE_BOOLEAN,
    ParParseTypeableInterface::DATATYPE_INTEGER,
    ParParseTypeableInterface::DATATYPE_FLOAT,
    ParParseTypeableInterface::DATATYPE_STRING,
    ParParseTypeableInterface::DATATYPE_ARRAY,
    ParParseTypeableInterface::DATATYPE_OBJECT,
    ParParseTypeableInterface::DATATYPE_NULL,
  );

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
   * An array of callbacks to invoke for the element's values.
   *
   * @var array
   */
  protected $callbacks = array();

  /**
   * Constructor.
   *
   * @param string $identifier
   *   The element's unique machine-name.
   * @param array $options
   *   An associative array of additional element options.
   */
  public function __construct($identifier, array $options = array()) {
    $this->identifier = $identifier;
    foreach ($options as $option => $value) {
      $this->setOption($option, $value);
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
    if (method_exists($this, $method)) {
      return $this->{$method}();
    }
    throw new InvalidArgumentException('Cannot find getter method for option '. $option .'.');
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
   * @return ParParseParsableElement
   *   The called object.
   */
  public function setOption($option, $value) {
    if (strpos($option, '_') !== FALSE) {
      $option = implode('', array_map('ucfirst', explode('_', $option)));
    }
    $method = 'set'. ucfirst($option);
    if (method_exists($this, $method)) {
      $this->{$method}($value);
    }
    return $this;
  }

  /**
   * Returns the element type.
   *
   * @return string
   *   The element type.
   */
  public function getType() {
    return $this->type;
  }

  /**
   * Returns the element's command line identifier.
   *
   * @return string
   *   The element's command line identifier.
   */
  public function getIdentifier() {
    return $this->identifier;
  }

  /**
   * Returns help text for the element.
   *
   * @return string
   *   The element's command-line help text.
   */
  public function getHelpText() {
    return $this->helpText;
  }

  /**
   * Sets the help text for the element.
   *
   * @param string $help
   *   The element's help text.
   *
   * @return ParParseParsableElement
   *   The called object.
   */
  public function setHelpText($help) {
    if (!is_string($help)) {
      throw new InvalidArgumentException('Invalid help text for '. $this->type .' '. $this->identifier .'. Help text must be a string.');
    }
    $this->helpText = $help;
    return $this;
  }

  /**
   * Returns the element's default value.
   *
   * @return mixed
   *   The element's default value.
   */
  public function getDefaultValue() {
    return $this->defaultValue;
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
  public function setDefaultValue($default) {
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
  public function setDataType($data_type) {
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

  /**
   * Adds a processing callback.
   *
   * @param callable $callback
   *   The callback to invoke for element values.
   *
   * @return ParParseParsableElement
   *   The called object.
   */
  public function addCallback($callback) {
    $this->callbacks[] = $callback;
    return $this;
  }

  /**
   * Executes callbacks for the element's value.
   *
   * @param mixed $value
   *   The value to process.
   *
   * @return mixed
   *   The resulting processed value.
   */
  protected function executeCallbacks($value) {
    foreach ($this->callbacks as $callback) {
      if (!is_callable($callback)) {
        throw new InvalidArgumentException('Invalid callback '. $callback .' for '. $this-> type .' '. $this->identifier .'. Callbacks must be callable.');
      }
      $value = $callback($value);
    }
    return $value;
  }

}

/**
 * Represents an argument.
 *
 * @author Jordan Halterman <jordan.halterman@gmail.com>
 */
class ParParseArgument extends ParParseParsableElement {

  /**
   * Indicates unlimited cardinality.
   *
   * @var int
   */
  const UNLIMITED = -1;

  /**
   * Indicates the element type.
   *
   * @var string
   */
  protected $type = 'argument';

  /**
   * Indicates the number of arguments expected.
   *
   * @var int
   */
  private $cardinality = 1;

  /**
   * Indicates whether the argument has a default value.
   *
   * @var bool
   */
  private $hasDefault = FALSE;

  /**
   * Constructor.
   *
   * @param string $identifier
   *   The element's unique machine-name.
   * @param array $options
   *   An associative array of additional argument options.
   */
  public function __construct($identifier, array $options = array()) {
    if (strpos($identifier, '-') === 0) {
      throw new InvalidArgumentException($identifier .' is not a valid argument.');
    }
    parent::__construct($identifier, $options);
  }

  /**
   * Parses positional arguments from command-line arguments.
   *
   * @param array $args
   *   An array of command-line arguments.
   *
   * @return mixed
   *   The argument value.
   */
  public function parse(array &$args) {
    $args = array_values($args);
    $num_args = count($args);
    if ($num_args === 0) {
      throw new ParParseMissingArgumentException('Missing argument '. $this->identifier .'.');
    }
    else {
      $valid_args = array();
      foreach ($args as $arg) {
        if (strpos($arg, '-') === 0) {
          continue;
        }
        $valid_args[] = $arg;
      }

      if (count($valid_args) == 0) {
        throw new ParParseMissingArgumentException('Missing argument '. $this->identifier .'.');
      }
    }

    switch ($this->cardinality) {
      case 1:
        $num_args = count($args);
        for ($i = 0; $i < $num_args; $i++) {
          if (strpos($args[$i], '-') === 0) {
            continue;
          }
          else {
            $value = $args[$i];
            unset($args[$i]);
            return $this->executeCallbacks($this->applyDataType($value));
          }
        }
        break;
      case self::UNLIMITED:
        $values = array();
        for ($i = 0; $i < $num_args; $i++) {
          if (strpos($args[$i], '-') === 0) {
            continue;
          }
          else {
            $values[] = $this->executeCallbacks($this->applyDataType($args[$i]));
            unset($args[$i]);
          }
        }
        return $values;
      default:
        if (count($valid_args) > $this->cardinality) {
          return NULL;
        }

        $values = array();
        for ($i = 0; $i < $num_args; $i++) {
          if (strpos($args[$i], '-') === 0) {
            continue;
          }
          else {
            $values[] = $this->executeCallbacks($this->applyDataType($args[$i]));
            unset($args[$i]);
          }

          if (count($values) == $this->cardinality) {
            break;
          }
        }
        return $values;
    }
  }

  /**
   * Returns the argument cardinality.
   *
   * @return int
   *   The argument cardinality.
   */
  public function getCardinality() {
    return $this->cardinality;
  }

  /**
   * Sets the argument cardinality.
   *
   * @param int $cardinality
   *   The number of arguments expected.
   *
   * @return ParParseArgument
   *   The called object.
   */
  public function setCardinality($cardinality) {
    if (!is_numeric($cardinality)) {
      throw new InvalidArgumentException('Invalid cardinality. Cardinality must be numeric.');
    }
    $this->cardinality = $cardinality;
    return $this;
  }

  /**
   * Returns the argument's default value.
   *
   * Note that only the last argument in a set of arguments can have
   * a default value, otherwise an exception will be thrown by the parser.
   *
   * @return mixed
   *   The argument's default value.
   */
  public function getDefaultValue() {
    if (!$this->hasDefault) {
      throw new ParParseException('No default value for '. $this->identifier .'.');
    }
    return $this->defaultValue;
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
  public function setDefaultValue($default) {
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
class ParParseOption extends ParParseParsableElement implements ParParseAliasableInterface {

  /**
   * The element type.
   *
   * @var string
   */
  protected $type = 'option';

  /**
   * The option's short alias. Defaults to NULL.
   *
   * @var string|null
   */
  private $alias = NULL;

  /**
   * The option's default value.
   *
   * @var mixed
   */
  protected $defaultValue = FALSE;

  /**
   * An array of actions to call when the option is present.
   *
   * @var array
   */
  private $actions = array();

  /**
   * The option value descriptor, used to build help text.
   * Defaults to 'value'.
   *
   * @var string
   */
  private $valueDescriptor = FALSE;

  /**
   * Constructor.
   *
   * @param string $identifier
   *   The element's unique machine-name.
   * @param array $options
   *   An associative array of additional option options.
   */
  public function __construct($identifier, array $options = array()) {
    if (strpos($identifier, '--') === 0) {
      $identifier = substr($identifier, 2);
    }
    parent::__construct($identifier, $options);
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
    for ($i = 0; $i < $num_args; $i++) {
      if ($args[$i] == '--'. $this->identifier) {
        return $this->getValueFromNextArg($args, $i);
      }
      else if ($args[$i] == '-'. $this->alias) {
        return $this->getValueFromNextArg($args, $i);
      }
      else if (strpos($args[$i], '--'. $this->identifier .'=') === 0) {
        return $this->getValueFromArg($args, $i);
      }
      else if (strpos($args[$i], '-'. $this->alias .'=') === 0) {
        return $this->getValueFromArg($args, $i);
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
   * @param int $index
   *   The index of this argument.
   *
   * @return mixed
   *   The processed argument.
   */
  private function getValueFromArg(array &$args, $index) {
    $arg = $args[$index];
    unset($args[$index]);
    $value = substr($arg, strpos($arg, '=') + 1);
    return $this->executeCallbacks($this->applyDataType($value));
  }

  /**
   * Attempts to get the option value from the next command line argument.
   *
   * @param array $args
   *   An array of command line arguments.
   * @param int $index
   *   The index of this option in arguments.
   *
   * @return mixed
   *   The processed option value.
   */
  private function getValueFromNextArg(array &$args, $index) {
    unset($args[$index]);
    if (!isset($args[$index+1])) {
      $this->invokeActions();
      return $this->executeCallbacks($this->applyDataType(TRUE));
    }
    else {
      $next_arg = $args[$index+1];
      if (strpos($next_arg, '-') === 0) {
        $this->invokeActions();
        return $this->executeCallbacks($this->applyDataType(TRUE));
      }
      else {
        unset($args[$index+1]);
        return $this->executeCallbacks($this->applyDataType($next_arg));
      }
    }
  }

  /**
   * Returns the option's short alias.
   *
   * @return string
   *   The option's short alias.
   */
  public function getAlias() {
    return $this->alias;
  }

  /**
   * Sets the option's short alias.
   *
   * @param string $alias
   *   The option's short alias.
   *
   * @return ParParseOption
   *   The called object.
   */
  public function setAlias($alias) {
    if (!isset($alias)) {
      return $this;
    }
    if (!is_string($alias)) {
      throw new InvalidArgumentException('Invalid alias. Alias must be a string.');
    }
    if (strpos($alias, '-') === 0) {
      $this->alias = substr($alias, 1);
    }
    else {
      $this->alias = $alias;
    }
    return $this;
  }

  /**
   * Returns the value descriptor.
   *
   * @return string
   *   The value descriptor.
   */
  public function getValueDescriptor() {
    return $this->valueDescriptor;
  }

  /**
   * Sets the value descriptor, used to generate help text.
   *
   * @param string $descriptor
   *   The value descriptor.
   *
   * @return ParParseOption
   *   The called object.
   */
  public function setValueDescriptor($descriptor) {
    if (!is_string($descriptor)) {
      throw new InvalidArgumentException('Option value descriptor must be a string.');
    }
    $this->valueDescriptor = $descriptor;
    return $this;
  }

  /**
   * Adds an action callback to be invoked when the option is present.
   *
   * @param callable $callback
   *   A callable callback.
   *
   * @return ParParseOption
   *   The called object.
   */
  public function addAction($callback) {
    $this->actions[] = $action;
    return $this;
  }

  /**
   * Invokes all actions when a option is present.
   */
  private function invokeActions() {
    foreach ($this->actions as $action) {
      call_user_func($action);
    }
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
  public function __get($identifier) {
    try {
      return $this->get($identifier);
    }
    catch (ParParseException $e) {
      // Do nothing if the result doesn't exist.
    }
  }

  /**
   * Gets a parser result.
   *
   * @param string $identifier
   *   The name of the element whose result to return.
   *
   * @return mixed
   *   The element result.
   */
  public function get($identifier) {
    if (isset($this->results[$identifier])) {
      return $this->results[$identifier];
    }
    throw new ParParseException('No result for '. $identifier .' exist.');
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

$parser->addArgument('foo', 1)
  ->setHelpText('Foo argument does bar.');
$parser->addArgument('bar', 2)
  ->setDefaultValue('non-existent')
  ->setHelpText('Bar argument does baz.');

$parser->addOption('--baz', '-b')
  ->setDefaultValue('baz bitches')
  ->setHelpText('Baz is a simple boolean flag.')
  ->setDataType('string');
$parser->addOption('--boo')
  ->setAlias('-o')
  ->setDataType('int')
  ->setDefaultValue(0)
  ->setHelpText('Boo scares the shit outta you!')
  ->setValueDescriptor('number');

$results = $parser->parse();

$foo = $results->get('foo');
print 'Foo: '. $foo.PHP_EOL;

$bar = $results->get('bar');
print_r($bar);

$baz = $results->get('baz');
print 'Baz: '. $baz.PHP_EOL;

$boo = $results->get('boo');
print 'Boo: '. $boo.PHP_EOL;
