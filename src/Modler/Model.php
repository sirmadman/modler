<?php

namespace Modler;

use InvalidArgumentException;

class Model
{
    /**
     * Current model properties
     *
     * @var array
     */
    protected array $properties = array(
    );

    /**
     * Current model values
     *
     * @var array
     */
    protected array $values = array();

    /**
     * Error messages for the current model
     *
     * @var array
     */
    protected array $messages = [];

    /**
     * Init the object and load data if given
     *
     * @param array $data Data to load [optional]
     */
    public function __construct(array $data = array())
    {
        if (!empty($data)) {
            $this->load($data);
        }
    }

    /**
     * Checks if a given property is set on the model.
     *
     * @param string $name Property name
     *
     * @return bool
     */
    public function __isset(string $name)
    {
        return array_key_exists($name, $this->properties);
    }

    /**
     * Set the value of a property
     *
     * @param string $name  Property name
     * @param mixed  $value Property value
     *
     * @throws \InvalidArgumentException If property doesn't exist
     * @return void
     */
    public function __set(string $name, mixed $value)
    {
        if (!$this->isProperty($name)) {
            throw new InvalidArgumentException(
                'Property name "' . $name . '" not found'
            );
        }
        $property = $this->properties[$name];
        if (
            !isset($property['guarded'])
            || (isset($property['guarded']) && $property['guarded'] === false)
        ) {
            $this->values[$name] = $value;
        }
    }

    /**
     * Get the value of a current property
     *
     * @param string $name Property name
     *
     * @return mixed The property value if found, null if not
     */
    public function __get(string $name)
    {
        $property = $this->getProperty($name);

        if ($property == null) {
            throw new InvalidArgumentException('Property "' . $name . '" is invalid');
        }

        // See if it's a relation
        if (
            isset($property['type'])
            && strtolower($property['type']) == 'relation'
        ) {
            return $this->handleRelation($property);
        }

        // If not, probably just a value - return that (or null)
        return (array_key_exists($name, $this->values))
        ? $this->values[$name] : null;
    }

    /**
     * Set an error message for a given field
     *
     * @param string $field   Field name
     * @param string $message Error message
     *
     * @return void
     */
    public function setMessage(string $field, string $message): void
    {
        $this->messages[$field] = $message;
    }

    /**
     * Get the current set of error messages
     *
     * @return array Current error message set
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get the mesage for a provided field
     *
     * @param string $field Field name
     *
     * @return mixed Either the string message or null if not found
     */
    public function getMessage(string $field): ?string
    {
        return (isset($this->messages[$field])) ? $this->messages[$field] : null;
    }

    /**
     * Handle a relational mapping in a model
     *
     * @param array $property Property configuration
     *
     * @return object Instance of relation object (model/collection)
     */
    public function handleRelation(array $property): mixed
    {
        $model = $property['relation']['model'];
        $method = $property['relation']['method'];
        $local = $property['relation']['local'];

        if (!class_exists($model)) {
            throw new InvalidArgumentException('Model "' . $model . '" does not exist');
        }

        $instance = $this->makeModelInstance($model);
        if (!method_exists($instance, $method)) {
            throw new InvalidArgumentException(
                'Method "' . $method . '" does not exist on model ' . get_class($instance)
            );
        }
        $params = array(
            (isset($this->values[$local])) ? $this->values[$local] : null
        );
        $result = call_user_func_array(array($instance, $method), $params);

        if (
            isset($property['relation']['return'])
            && $property['relation']['return'] === 'value'
        ) {
            return $result;
        } else {
            return $instance;
        }
    }

    /**
     * Make a new model instance
     *
     * @param string $model Model namespace "path"
     *
     * @return object Model instance
     */
    public function makeModelInstance(string $model): object
    {
        $instance = new $model();
        return $instance;
    }

    /**
     * Handle the get* method calls
     *
     * @param string $name Function name called
     * @param array  $args Arguments given
     *
     * @return mixed Value if found, null if not
     */
    public function __call(string $name, array $args)
    {
        if (substr($name, 0, 3) == 'get') {
            $property = strtolower(str_replace('get', '', $name));
            if ($this->isProperty($property)) {
                return $this->getValue($property);
            }
        }
        return null;
    }

    /**
     * See if a property is valid
     *
     * @param string $name Property name
     *
     * @return boolean Valid/invalid property
     */
    public function isProperty(string $name): bool
    {
        return array_key_exists($name, $this->properties);
    }

    /**
     * Set a value on the current model
     *
     * @param string $name  Property name
     * @param mixed  $value Property value
     *
     * @return void
     */
    public function setValue(string $name, mixed $value): void
    {
        $this->values[$name] = $value;
    }

    /**
     * Get a value from the current set. If not found,
     *     null is returned
     *
     * @param string $name Property name
     *
     * @return mixed Either the property value or null
     */
    public function getValue(string $name): mixed
    {
        return (isset($this->values[$name]))
        ? $this->values[$name] : null;
    }

    /**
     * Get the full current property set
     *
     * @return array Property set
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Get the configuration for a property. If it doesn't
     *     exist, null is returned
     *
     * @param string $name Property name
     *
     * @return array|null The configuration if found, null otherwise
     */
    public function getProperty(string $name): ?array
    {
        return (array_key_exists($name, $this->properties))
        ? $this->properties[$name] : null;
    }

    /**
     * Add a property to the list
     *     If exists and override is not set, an exception is thrown
     *
     * @param string  $name     Property name
     * @param array   $config   Property configuration
     * @param boolean $override Override existing pproperty [optional]
     *
     * @return void
     * @throws \InvalidArgumentException If property exists and it not overridden
     */
    public function addProperty(
        string $name,
        array $config,
        bool $override = false
    ): void {
        if (array_key_exists($name, $this->properties) && $override === false) {
            throw new InvalidArgumentException(
                'Property name "' . $name . '" already exists'
            );
        }
        $this->properties[$name] = $config;
    }

    /**
     * Load the given data into the model, checking properties
     *
     * @param array $data         Data to load
     * @param bool  $enforceGuard Ensures that guarded values are not overwritten
     *
     * @return void
     */
    public function load(array $data, bool $enforceGuard = true): void
    {
        foreach ($data as $name => $value) {
            if (array_key_exists($name, $this->properties)) {
                $property = $this->properties[$name];

                $method = 'load' . ucwords($name);
                if (method_exists($this, $method) == true) {
                    $value = $this->$method($value);
                }

                if ($enforceGuard === true) {
                    if (
                        !isset($property['guarded'])
                        || (isset($property['guarded'])
                        && $property['guarded'] === false)
                    ) {
                        $this->setValue($name, $value);
                    }
                } else {
                    $this->setValue($name, $value);
                }
            }
        }
    }

    /**
     * Return the current set of values in an array
     *
     * @param array $filter Values to filter from the return
     *
     * @return array Current values
     */
    public function toArray(array $filter = array()): array
    {
        $values = $this->values;
        foreach ($filter as $name) {
            if (isset($values[$name])) {
                unset($values[$name]);
            }
        }
        return $values;
    }

    /**
     * Verify that all required values are set
     *
     * @param array $ignore Ignore properties list
     *
     * @throws \InvalidArgumentException If required permission is not set
     * @return boolean True if verification is successful
     */
    public function verify(array $ignore = array()): bool
    {
        $properties = $this->getProperties();
        foreach ($properties as $name => $config) {
            if (in_array($name, $ignore)) {
                continue;
            }
            if (
                (isset($config['required']) && $config['required'] === true)
                && !isset($this->values[$name])
            ) {
                throw new InvalidArgumentException(
                    'Property "' . $name . '" is required!'
                );
            }
            $validateMethod = 'validate' . ucwords(strtolower($name));
            if (
                method_exists($this, $validateMethod)
                && isset($this->values[$name])
            ) {
                if ($this->$validateMethod($this->values[$name]) === false) {
                    // See if we have a custom message
                    $msg = $this->getMessage($name);
                    if ($msg === null) {
                        $msg = 'Invalid value for property "' . $name . '"!';
                    }
                    throw new InvalidArgumentException($msg);
                }
            }
        }
        return true;
    }
}
