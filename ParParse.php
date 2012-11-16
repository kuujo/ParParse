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
  private $elements = array(
    'flag' => array(),
    'parameter' => array(),
    'argument' => array(),
  );

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
    if (array_key_exists($element->getName(), $this->uniqueNames)) {
      throw new ParParseException('Cannot duplicate element name '. $element->getName() .'. An element with that name already exists.');
    }
    if (!isset($this->elements[$element->getType()])) {
      throw new ParParseException('Invalid element type '. $element->getType() .'.');
    }
    $this->elements[$element->getType()][] = $element;
    $this->uniqueNames[$element->getName()] = TRUE;
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
        throw new Exception('Cannot parse arguments. No arguments given.');
      }
      $args = array_slice($argv, 1);
    }

    $results = array();
    foreach ($this->elements['flag'] as $flag) {
      $results[$flag->getName()] = $flag->parse($args);
    }
    foreach ($this->elements['parameter'] as $param) {
      $results[$param->getName()] = $param->parse($args);
    }

    // Arguments are treated a little differently. Only the last argument
    // in the set can have missing or default values. This is because
    // missing arguments earlier in the process would ruin the order.
    $num_args = count($this->elements['argument']);
    for ($i = 0; $i < $num_args; $i++) {
      $arg = $this->elements['argument'][$i];
      try {
        $results[$arg->getName()] = $arg->parse($args);
      }
      catch (ParParseMissingArgumentException $e) {
        if (isset($this->elements['argument'][$i+1])) {
          throw $e;
        }
        else {
          $results[$arg->getName()] = $arg->getDefaultValue();
        }
      }
    }
    return new ParParseResult($results);
  }

}

/**
 * Base class for all parsable command-line elements.
 */
abstract class ParParseParsableElement {

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
   * The element's label.
   *
   * @var string
   */
  protected $label = '';

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
   * Returns the element's human-readable label.
   *
   * @return string
   *   The element's human-readable label.
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * Sets the element's human-readable label.
   *
   * @param string $label
   *   The element's human-readable label.
   *
   * @return ParParseElement
   *   The called object.
   */
  public function setLabel($label) {
    if (!is_string($label)) {
      throw new InvalidArgumentException('Invalid label for '. $this->type .' '. $this->name .'. Label must be a string.');
    }
    $this->label = $label;
    return $this;
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
    if (!is_string($label)) {
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
        throw new InvalidArgumentException('Invalid callback '. $callback ' for '. $this-> type .' '. $this->name .'. Callbacks must be callable.');
      }
      $value = $callback($value);
    }
    return $value;
  }

  /**
   * Parses command line options for the element.
   *
   * @param array $args
   *   An array of command-line arguments.
   *
   * @return mixed
   *   The argument value.
   */
  abstract public function parse(array &$args);

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
      $option = array_map('ucfirst', explode('_', $option));
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
class ParParseArgument extends ParParseParsableElement {

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
   * Indicates the number of arguments expected.
   *
   * @var int
   */
  private $cardinality = 1;

  /**
   * The argument's default value. Defaults to NULL.
   *
   * Note that only the last argument in a set of arguments can have
   * a default value, otherwise an exception will be thrown by the parser.
   *
   * @var string|null
   */
  private $defaultValue = NULL;

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
    if (!isset($args[0])) {
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
            return $this->executeCallbacks($value);
          }
        }
        break;
      case self::CARDINALITY_UNLIMITED:
        $values = array();
        $num_args = count($args);
        $i = 0;
        while ($i < count($args)) {
          if (strpos($args[$i], '-') === 0) {
            $i++;
            continue;
          }
          else {
            $values[] = $this->executeCallbacks($args[$i]);
            unset($args[$i]);
          }
        }
        return $values;
      default:
        if (count($valid_args) > $this->cardinality) {
          return NULL;
        }

        $values = array();
        while ($i < count($args)) {
          if (strpos($args[$i], '-') === 0) {
            $i++;
          }
          else {
            $values[] = $this->executeCallbacks($args[$i]);
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
    $this->defaultValue = $default;
    return $this;
  }

}

/**
 * Represents a flag.
 */
class ParParseFlag extends ParParseParsableElement {

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
    $num_args = count($args);
    for ($i = 0; $i < $num_args; $i++) {
      if ($args[$i] == '--'. $this->name || $args[$i] == '-'. $this->alias) {
        unset($args[$i]);
        $this->invokeActions();
        return TRUE;
      }
    }
    return FALSE;
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
class ParParseParameter extends ParParseParsableElement {

  /**
   * Indicates the element type.
   *
   * @var string
   */
  protected $type = 'parameter';

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
      return $this->executeCallbacks($value);
    }
    return $this->defaultValue;
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

$parser = new ParParse();
$parser->addArgument('partner')
  ->setLabel('Partner');
$parser->addArgument('categories')
  ->setLabel('Category')
  ->setCardinality(ParParse::CARDINALITY_UNLIMITED)
  ->setDefaultValue(NULL);

$parser->addOption('channel')
  ->setLabel('Channel')
  ->addAlias('c');
$parser->addOption('inbound')
  ->setLabel('Inbound address')
  ->setAlias('i');

$results = $parser->parse();

$categories = $results->categories;

