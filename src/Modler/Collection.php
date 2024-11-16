<?php

namespace Modler;

use Countable;
use Iterator;
use ArrayAccess;

class Collection implements Countable, Iterator, ArrayAccess
{
    public const SORT_ASC = 'asc';
    public const SORT_DESC = 'desc';

    /**
     * Current set of data for collection
     *
     * @var array
     */
    private array $data = array();

    /**
     * Current position in data (used in Iterator)
     *
     * @var integer
     */
    private int $position = 0;

    public function __construct(array $data = array())
    {
        if (!empty($data)) {
            $this->setData($data);
        }
    }

    /**
     * Magic method to catch get* calls to fetch a set of property values
     *
     * @param string $name Function name called
     * @param array  $args Arguments
     *
     * @return null|array Results if found, null if not
     */
    public function __call(string $name, array $args)
    {
        // If it starts with get*
        if (strpos($name, 'get') === 0) {
            $property = lcfirst(str_replace('get', '', $name));
            $results = array();
            foreach ($this->data as $item) {
                $results[] = $item->$property;
            }
            return $results;
        }
    }

    /**
     * Set the current data for the collection
     *
     * @param array $data Data to assign to the collection
     *
     * @return void
     */
    private function setData(array $data): void
    {
        $this->data = $data;
    }

    // For Countable interface
    /**
     * Return a count of the current data
     *
     * @return integer Count result
     */
    public function count(): int
    {
        return count($this->data);
    }

    // For Iterator
    /**
     * Return the current item in the set
     *
     * @return mixed Current data item
     */
    public function current(): mixed
    {
        return $this->data[$this->position];
    }

    /**
     * Return the current key (position) value
     *
     * @return integer Position value
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * Get the next position value
     *
     * @return integer Next position
     */
    #[\ReturnTypeWillChange]
    public function next(): int
    {
        return ++$this->position;
    }

    /**
     * Rewind to the beginning of the set (position = 0)
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * See if the requested position exists in the data
     *
     * @return boolean Exists/doesn't exist
     */
    public function valid(): bool
    {
        return isset($this->data[$this->position]);
    }

    /**
     * Check to see if an offset exists (ArrayAccess)
     *
     * @param integer|string $offset Offset
     *
     * @return boolean Offset exists
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    /**
     * Get the value for the offset (ArrayAccess)
     *
     * @param integer|string $offset Offset
     *
     * @return mixed Offset value
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset];
    }

    /**
     * Set a value on the given offset
     *
     * @param integer|seting $offset Offset
     * @param mixed          $value  Value to set to offset
     *
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[$offset] = $value;
    }

    /**
     * Unset an offset if it exists
     *
     * @param integer|seting $offset Offset
     *
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        if (isset($this->data[$offset])) {
            unset($this->data[$offset]);
        }
    }

    /**
     * Add an item to the collection
     *
     * @param mixed $data Data item to add
     *
     * @return void
     */
    public function add(mixed $data): void
    {
        $this->data[] = $data;
    }

    /**
     * Remove an item from the collection by index ID
     *
     * @param integer $dataId Item ID
     *
     * @return void
     */
    public function remove(int $dataId): void
    {
        if (array_key_exists($dataId, $this->data)) {
            unset($this->data[$dataId]);
        }
    }

    /**
     * Output the current collection data as an array
     *     If the "expand" is defined, toArray is called on sub-objects too
     *
     * @param boolean $expand Expand sub-elements with toArray too
     *
     * @return array Data set as array output
     */
    public function toArray(bool $expand = false): array
    {
        if ($expand === true) {
            $result = array();
            foreach ($this->data as $value) {
                if (is_object($value) && method_exists($value, 'toArray')) {
                    $result[] = $value->toArray();
                } else {
                    $result[] = $value;
                }
            }
            return $result;
        } else {
            return $this->data;
        }
    }

    /**
     * Filter the collection by the boolean return values
     *     in a callable function
     *
     * @param callable $function Filtering function
     *
     * @return \Modler\Collection instance
     */
    public function filter(callable $function): self
    {
        $class = get_class();
        $self = new $class();

        foreach ($this->data as $data) {
            if ($function($data) === true) {
                $self->add($data);
            }
        }
        return $self;
    }

    /**
     * Slice and return certain items in the set
     *     If no # of items is specificed, the rest of the data is returned
     *
     * @param integer $start Start index
     * @param integer $items Number of items to return
     *
     * @return array Sliced set of data
     */
    public function slice(int $start, ?int $items = null): array
    {
        $end = ($items !== null) ? $items : count($this->data) - 1;
        return array_slice($this->data, $start, $end);
    }

    /**
     * Check to see if the collection contains the given data
     *
     * @param mixed $data Data to locate
     *
     * @return boolean Found/not found result
     */
    public function contains(mixed $data): bool
    {
        foreach ($this->data as $item) {
            if ($item == $data) {
                return true;
            }
        }
        return false;
    }

    /**
     * Alias to the "slice" function but a bit simpler
     *
     * @param integer $limit Number of times to reduce down to
     *
     * @return \Modeler\Collection Collection instance
     */
    public function take(int $limit): self
    {
        $data = $this->slice(0, $limit);
        $collection = new Collection($data);
        return $collection;
    }

    /**
     * Sort the collection based on the value of the property defined
     *     and the direction given
     *
     * @param string $direction Direction constant value (SORT_ASC/SORT_DESC)
     * @param string $property  Property name
     *
     * @return object Modler\Collection instance
     */
    public function order(?string $direction = null, ?string $property = null): self
    {
        $direction = ($direction === null || $direction === self::SORT_DESC)
            ? self::SORT_DESC : self::SORT_ASC;
        $data = $this->data;

        if ($property !== null) {
            // sort by the property value
            $values = array();
            foreach ($data as $index => $item) {
                $values[$index] = $item->$property;
            }
            ($direction === self::SORT_DESC) ? asort($values) : arsort($values);
            $sorted = array();
            foreach ($values as $index => $item) {
                $sorted[] = $data[$index];
            }
            $data = $sorted;
        } else {
            // sort as strings
            ($direction === self::SORT_DESC) ? sort($data) : rsort($data);
        }
        $this->setData($data);
        return $this;
    }

    /**
     * Find match(es) in the current set of data based on
     *     the provided proprty and value
     *
     * @param string  $property Proprty name to match on
     * @param mixed   $value    Value to match
     * @param boolean $all      Return all matches or just single (default is single)
     *
     * @return mixed Match(es) if found, null if not
     */
    public function find(string $property, mixed $value, bool $all = false): mixed
    {
        $match = null;
        $matches = array();
        foreach ($this->data as $item) {
            if (is_object($item) && $item instanceof \Modler\Model) {
                if ($item->isProperty($property)) {
                    $match = $item->$property;
                }
            } elseif (is_object($item)) {
                if (isset($item->$property)) {
                    $match = $item->$property;
                }
            } elseif (is_array($item)) {
                $match = $item[$property];
            } else {
                $match = $item;
            }
            if ($match == $value) {
                if ($all === true) {
                    $matches[] = $item;
                } else {
                    return $item;
                }
            }
        }
        return ($all === true) ? $matches : null;
    }
}
