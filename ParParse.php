<?php

/**
 * Core PhpARgumentsPARSEr.
 */
class ParParse {

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
   * Adds a new parsable element to the parser.
   *
   * @param ParParseParsableElement $element
   *   The parsable element definition object.
   *
   * @return ParParseParsableElement
   *   The added element.
   */
  public function addElement(ParParseParsableElement $element) {
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
   *   The argument's machine-name, used to reference the parameter value.
   * @param int $cardinality
   *   The number of arguments expected by the argument. Defaults to 1.
   * @param array $options
   *   An optional associative array of additional argument options.
   */
  public function addArgument($name, $cardinality = 1, array $options = array()) {
    $options += array('cardinality' => $cardinality);
    return $this->addElement(new ParParseArgument($name, $options));
  }

  /**
   * Adds a flag definition to the argument parser.
   *
   * @param string $name
   *   The flag's machine-name, used to reference the flag value.
   * @param string|null $alias
   *   The flag's optional short alias.
   * @param array $options
   *   An optional associative array of additional flag options.
   */
  public function addFlag($name, $alias = NULL, array $options = array()) {
    $options += array('alias' => $alias);
    return $this->addElement(new ParParseFlag($name, $options));
  }

  /**
   * Adds a parameter definition to the argument parser.
   *
   * @param string $name
   *   The parameter's machine-name, used to reference the parameter value.
   * @param string|null $alias
   *   The parameter's optional short alias.
   * @param mixed|null $default
   *   The parameter's default value. Defaults to NULL.
   * @param array $options
   *   An optional associative array of additional parameter options.
   */
  public function addParameter($name, $alias = NULL, $default = NULL, array $options = array()) {
    $options += array('alias' => $alias, 'default_value' => $default);
    return $this->addElement(new ParParseParameter($name, $options));
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
    }

    // Note that the order in which elements are parsed is essential to
    // ensure conflicts do not arise. We have to parse flags, parameters,
    // and then arguments, so we separate the elements and process separately.
    try {
      $results = $flags = $parameters = $arguments = array();
      foreach ($this->elements as $element) {
        $element_type = $element->getType();
        if (isset(${$element_type.'s'})) {
          ${$element_type.'s'}[] = $element;
        }
      }

      // Process flags.
      foreach ($flags as $flag) {
        $results[$flag->getName()] = $flag->parse($args);
      }

      // Process parameters.
      foreach ($parameters as $param) {
        $results[$param->getName()] = $param->parse($args);
      }

      // Arguments are treated a little differently. Only the last argument
      // in the set can have missing or default values. This is because
      // missing arguments earlier in the process would ruin the order.
      $num_args = count($arguments);
      for ($i = 0; $i < $num_args; $i++) {
        $arg = $arguments[$i];
        try {
          $results[$arg->getName()] = $arg->parse($args);
        }
        catch (ParParseMissingArgumentException $e) {
          if (isset($arguments[$i+1])) {
            throw $e;
          }
          else {
            try {
              $results[$arg->getName()] = $arg->getDefaultValue();
            }
            catch (ParParseException $e) {
              throw new ParParseMissingArgumentException('Missing argument '. $arg->getName() .'.');
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
    $help = array();
    $usage = 'Usage: '. $command;
    foreach ($this->elements as $element) {
      switch ($element->getType()) {
        case 'argument':
          $usage .= $this->printArgument($element);
          break;
        case 'flag':
          $usage .= $this->printFlag($element);
          break;
        case 'parameter':
          $usage .= $this->printParameter($element);
          break;
      }
    }

    $usage .= "\n";
    $help[] = $usage;

    $help[] = '-h|--help Display command usage information.';

    $arguments = $options = array();
    foreach ($this->elements as $element) {
      if ($element->getType() === 'argument') {
        $arguments[] = $element;
      }
      else {
        $options[] = $element;
      }
    }

    $indent = '  ';
    $help[] = 'Arguments:'."\n";
    foreach ($arguments as $arg) {
      $help[] = $indent . $arg->getName() . $indent . $arg->getHelpText() . "\n";
    }

    $help[] = 'Options:'."\n";
    foreach ($options as $option) {
      $opt_text = '--'. $option->getName();
      if ($option->getAlias()) {
        $opt_text = '-'. $option->getAlias() .' '. $opt_text;
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
    if ($cardinality == ParParseArgument::CARDINALITY_UNLIMITED) {
      $usage .= ' '. $arg->getName() .' ['. $arg->getName() .' ...]';
    }
    else {
      for ($i = 0; $i < $cardinality; $i++) {
        $usage .= ' '. $arg->getName();
      }
    }
    return $usage;
  }

  /**
   * Returns command-line help text for a flag.
   */
  private function printFlag(ParParseFlag $flag) {
    if ($flag->getAlias()) {
      return ' [-'. $flag->getAlias() .'|--'. $flag->getName() .']';
    }
    return ' [--'. $flag->getName() .']';
  }

  /**
   * Returns command-line help text for a parameter.
   */
  private function printParameter(ParParseParameter $param) {
    if ($param->getAlias()) {
      return ' [-'. $param->getAlias() .'|--'. $param->getName() .'=<value>]';
    }
    return ' [--'. $param->getName() .'=<value>]';
  }

}

/**
 * Interface for parsable elements.
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
   * Returns the element's machine-readable name.
   *
   * @return string
   *   The element's machine-readable name.
   */
  public function getName();

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
 */
abstract class ParParseParsableElement implements ParParseParsableElementInterface {

  /**
   * The parsable element's type. This should be set in the class definition
   * and is used to determine the order in which parsers are executed.
   *
   * @var string
   */
  protected $type = '';

  /**
   * The element's unique machine name.
   *
   * @var string
   */
  protected $name;

  /**
   * The element's command-line help text.
   *
   * @var string
   */
  protected $helpText = '';

  /**
   * An array of callbacks to invoke for the element's values.
   *
   * @var array
   */
  protected $callbacks = array();

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
   * Returns the element type.
   *
   * @return string
   *   The element type.
   */
  public function getType() {
    return $this->type;
  }

  /**
   * Returns the element's machine-readable name.
   *
   * @return string
   *   The element's machine-readable name.
   */
  public function getName() {
    return $this->name;
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
      throw new InvalidArgumentException('Invalid help text for '. $this->type .' '. $this->name .'. Help text must be a string.');
    }
    $this->helpText = $help;
    return $this;
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
        throw new InvalidArgumentException('Invalid callback '. $callback .' for '. $this-> type .' '. $this->name .'. Callbacks must be callable.');
      }
      $value = $callback($value);
    }
    return $value;
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
      $option = array_map('ucfirst', explode('_', $option));
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

}

/**
 * Represents an argument.
 */
class ParParseArgument extends ParParseParsableElement implements ParParseTypeableInterface, ParParseDefaultableInterface {

  /**
   * Indicates unlimited cardinality.
   *
   * @var int
   */
  const CARDINALITY_UNLIMITED = -1;

  /**
   * Indicates the element type.
   *
   * @var string
   */
  protected $type = 'argument';

  /**
   * The argument's data type.
   *
   * @var string|null
   */
  private $dataType = NULL;

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
   * The argument's default value.
   *
   * Note that only the last argument in a set of arguments can have
   * a default value, otherwise an exception will be thrown by the parser.
   *
   * @var string|null
   */
  private $defaultValue;

  /**
   * Parses flags from command-line arguments.
   *
   * @param array $args
   *   An array of command-line arguments.
   *
   * @return mixed
   *   The flag value.
   */
  public function parse(array &$args) {
    $args = array_values($args);
    $num_args = count($args);
    if ($num_args === 0) {
      throw new ParParseMissingArgumentException('Missing argument '. $this->name .'.');
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
        throw new ParParseMissingArgumentException('Missing argument '. $this->name .'.');
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
      case self::CARDINALITY_UNLIMITED:
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
  private function applyDataType($value) {
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
      throw new ParParseException('No default value for '. $this->name .'.');
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
 * Represents a flag.
 */
class ParParseFlag extends ParParseParsableElement implements ParParseAliasableInterface {

  /**
   * Indicates the element type.
   *
   * @var string
   */
  protected $type = 'flag';

  /**
   * The flag's short alias. Defaults to NULL.
   *
   * @var string|null
   */
  private $alias = NULL;

  /**
   * An array of actions to call when the flag is present.
   *
   * @var array
   */
  private $actions = array();

  /**
   * Parses flags from command-line arguments.
   *
   * @param array $args
   *   An array of command-line arguments.
   *
   * @return mixed
   *   The flag value.
   */
  public function parse(array &$args) {
    $args = array_values($args);
    $num_args = count($args);
    for ($i = 0; $i < $num_args; $i++) {
      if ($args[$i] == '--'. $this->name || $args[$i] == '-'. $this->alias) {
        unset($args[$i]);
        $this->invokeActions();
        return $this->executeCallbacks(TRUE);
      }
    }
    return $this->executeCallbacks(FALSE);
  }

  /**
   * Returns the flag's short alias.
   *
   * @return string
   *   The flag's short alias.
   */
  public function getAlias() {
    return $this->alias;
  }

  /**
   * Sets the flag's short alias.
   *
   * @param string $alias
   *   The flag's short alias.
   *
   * @return ParParseFlag
   *   The called object.
   */
  public function setAlias($alias) {
    if (!isset($alias)) {
      return $this;
    }
    if (!is_string($alias)) {
      throw new InvalidArgumentException('Invalid alias. Alias must be a string.');
    }
    $this->alias = $alias;
    return $this;
  }

  /**
   * Adds an action callback to be invoked when the flag is present.
   *
   * @param callable $callback
   *   A callable callback.
   *
   * @return ParParseFlag
   *   The called object.
   */
  public function addAction($callback) {
    $this->actions[] = $action;
    return $this;
  }

  /**
   * Invokes all actions when a flag is present.
   */
  private function invokeActions() {
    foreach ($this->actions as $action) {
      call_user_func($action);
    }
  }

}

/**
 * Represents a parameter.
 */
class ParParseParameter extends ParParseParsableElement implements ParParseAliasableInterface, ParParseTypeableInterface, ParParseDefaultableInterface {

  /**
   * Indicates the element type.
   *
   * @var string
   */
  protected $type = 'parameter';

  /**
   * The argument's data type.
   *
   * @var string|null
   */
  private $dataType = NULL;

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
   * The parameter's short alias. Defaults to NULL.
   *
   * @var string|null
   */
  private $alias = NULL;

  /**
   * The parameter's default value. Defaults to NULL.
   *
   * @var string|null
   */
  private $defaultValue = NULL;

  /**
   * Parses parameters from command-line arguments.
   *
   * @param array $args
   *   An array of command-line arguments.
   *
   * @return mixed
   *   The parameter value.
   */
  public function parse(array &$args) {
    $args = array_values($args);
    $num_args = count($args);
    for ($i = 0; $i < $num_args; $i++) {
      if (strpos($args[$i], '--'. $this->name) === 0) {
        $prefix = '--'. $this->name;
      }
      else if (strpos($args[$i], '-'. $this->alias) === 0) {
        $prefix = '-'. $this->alias;
      }
      else {
        continue;
      }

      if ($args[$i] == $prefix) {
        $value = $args[$i+1];
        unset($args[$i+1]);
      }
      else if (strpos($args[$i], '=') !== FALSE) {
        if (strpos($args[$i], $prefix .'=') !== 0) {
          continue;
        }
        $value = substr($args[$i], strlen($prefix) + 1);
      }
      else {
        $value = substr($args[$i], strlen($prefix));
      }
      unset($args[$i]);
      return $this->executeCallbacks($this->applyDataType($value));
    }
    return $this->defaultValue;
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
  private function applyDataType($value) {
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
   * Returns the parameter's short alias.
   *
   * @return string
   *   The parameter's short alias.
   */
  public function getAlias() {
    return $this->alias;
  }

  /**
   * Sets the parameter's short alias.
   *
   * @param string $alias
   *   The parameter's short alias.
   *
   * @return ParParseParameter
   *   The called object.
   */
  public function setAlias($alias) {
    if (!isset($alias)) {
      return $this;
    }
    if (!is_string($alias)) {
      throw new InvalidArgumentException('Invalid alias. Alias must be a string.');
    }
    $this->alias = $alias;
    return $this;
  }

  /**
   * Returns the parameter's default value.
   *
   * @return mixed
   *   The parameter's default value.
   */
  public function getDefaultValue() {
    return $this->defaultValue;
  }

  /**
   * Sets the parameter's default value.
   *
   * @param mixed $default
   *   The default value.
   *
   * @return ParParseParameter
   *   The called object.
   */
  public function setDefaultValue($default) {
    $this->defaultValue = $default;
    return $this;
  }

}

/**
 * Parsed arguments results.
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
    if (isset($this->results[$name])) {
      return $this->results[$name];
    }
  }

}

/**
 * Base ParParse exception.
 */
class ParParseException extends Exception {}

/**
 * Missing argument exception.
 */
class ParParseMissingArgumentException extends ParParseException {}
