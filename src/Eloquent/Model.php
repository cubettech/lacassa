<?php 

namespace Cubettech\Lacassa\Eloquent;

use Carbon\Carbon;
use DateTime;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Cubettech\Lacassa\Query\Builder as QueryBuilder;
use Cassandra\Timestamp;
use ReflectionMethod;

abstract class Model extends BaseModel
{

    /**
     * The collection associated with the model.
     *
     * @var string
     */
    protected $collection;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = '_id';

    /**
     * The parent relation instance.
     *
     * @var Relation
     */
    protected $parentRelation;

    /**
     * Custom accessor for the model's id.
     *
     * @param  mixed  $value
     * @return mixed
     */
    public function getIdAttribute($value)
    {
        // If we don't have a value for 'id', we will use the Cassandra '_id' value.
        // This allows us to work with models in a more sql-like way.
        if (! $value and array_key_exists('_id', $this->attributes)) {
            $value = $this->attributes['_id'];
        }

        return $value;
    }

    /**
     * Get the table qualified key name.
     *
     * @return string
     */
    public function getQualifiedKeyName()
    {
        return $this->getKeyName();
    }

    /**
     * Convert a DateTime to a storable UTCDateTime object.
     *
     * @param  DateTime|int  $value
     * @return UTCDateTime
     */
    public function fromDateTime($value)
    {
        // If the value is already a UTCDateTime instance, we don't need to parse it.
        if ($value instanceof Timestamp) {
            return $value;
        }

        // Let Eloquent convert the value to a DateTime instance.
        if (! $value instanceof DateTime) {
            $value = parent::asDateTime($value);
        }

        return new Timestamp($value->getTimestamp() * 1000);
    }

    /**
     * Return a timestamp as DateTime object.
     *
     * @param  mixed  $value
     * @return DateTime
     */
    protected function asDateTime($value)
    {
        // Convert UTCDateTime instances.
        if ($value instanceof Timestamp) {
            return Carbon::createFromTimestamp($value->time());
        }

        return parent::asDateTime($value);
    }

    /**
     * Get the format for database stored dates.
     *
     * @return string
     */
    protected function getDateFormat()
    {
        return $this->dateFormat ?: 'Y-m-d H:i:s';
    }

    /**
     * Get a fresh timestamp for the model.
     *
     * @return Timestamp
     */
    public function freshTimestamp()
    {
        return new Timestamp(round(microtime(true) * 1000));
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return $this->collection ?: parent::getTable();
    }

    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        // Check if the key is an array dot notation.
        if (str_contains($key, '.') and array_has($this->attributes, $key)) {
            return $this->getAttributeValue($key);
        }

        $camelKey = camel_case($key);

        // If the "attribute" exists as a method on the model, it may be an
        // embedded model. If so, we need to return the result before it
        // is handled by the parent method.
        if (method_exists($this, $camelKey)) {
            $method = new ReflectionMethod(get_called_class(), $camelKey);
        }

        return parent::getAttribute($key);
    }

    /**
     * Get an attribute from the $attributes array.
     *
     * @param  string  $key
     * @return mixed
     */
    protected function getAttributeFromArray($key)
    {
        // Support keys in dot notation.
        if (str_contains($key, '.')) {
            $attributes = array_dot($this->attributes);

            if (array_key_exists($key, $attributes)) {
                return $attributes[$key];
            }
        }

        return parent::getAttributeFromArray($key);
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed   $value
     */
    public function setAttribute($key, $value)
    {
        // Convert _id to ObjectID.
        if ($key == '_id' and is_string($value)) {
            $builder = $this->newBaseQueryBuilder();

            $value = $builder->convertKey($value);
        } // Support keys in dot notation.
        elseif (str_contains($key, '.')) {
            if (in_array($key, $this->getDates()) && $value) {
                $value = $this->fromDateTime($value);
            }

            array_set($this->attributes, $key, $value);

            return;
        }

        parent::setAttribute($key, $value);
    }

    /**
     * Convert the model's attributes to an array.
     *
     * @return array
     */
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        // Convert dot-notation dates.
        foreach ($this->getDates() as $key) {
            if (str_contains($key, '.') and array_has($attributes, $key)) {
                array_set($attributes, $key, (string) $this->asDateTime(array_get($attributes, $key)));
            }
        }

        return $attributes;
    }

    /**
     * Get the casts array.
     *
     * @return array
     */
    public function getCasts()
    {
        return $this->casts;
    }

    /**
     * Determine if the new and old values for a given key are numerically equivalent.
     *
     * @param  string  $key
     * @return bool
     */
    protected function originalIsNumericallyEquivalent($key)
    {
        $current = $this->attributes[$key];
        $original = $this->original[$key];

        // Date comparison.
        if (in_array($key, $this->getDates())) {
            $current = $current instanceof Timestamp ? $this->asDateTime($current) : $current;
            $original = $original instanceof Timestamp ? $this->asDateTime($original) : $original;

            return $current == $original;
        }

        return parent::originalIsNumericallyEquivalent($key);
    }

    /**
     * Remove one or more fields.
     *
     * @param  mixed  $columns
     * @return int
     */
    public function drop($columns)
    {
        if (! is_array($columns)) {
            $columns = [$columns];
        }

        // Unset attributes
        foreach ($columns as $column) {
            $this->__unset($column);
        }

        // Perform unset only on current document
        return $this->newQuery()->where($this->getKeyName(), $this->getKey())->unset($columns);
    }

    /**
     * Append one or more values to an array.
     *
     * @return mixed
     */
    public function push()
    {
        if ($parameters = func_get_args()) {
            $unique = false;

            if (count($parameters) == 3) {
                list($column, $values, $unique) = $parameters;
            } else {
                list($column, $values) = $parameters;
            }

            // Do batch push by default.
            if (! is_array($values)) {
                $values = [$values];
            }

            $query = $this->setKeysForSaveQuery($this->newQuery());

            $this->pushAttributeValues($column, $values, $unique);

            return $query->push($column, $values, $unique);
        }

        return parent::push();
    }

    /**
     * Remove one or more values from an array.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @return mixed
     */
    public function pull($column, $values)
    {
        // Do batch pull by default.
        if (! is_array($values)) {
            $values = [$values];
        }

        $query = $this->setKeysForSaveQuery($this->newQuery());

        $this->pullAttributeValues($column, $values);

        return $query->pull($column, $values);
    }

    /**
     * Append one or more values to the underlying attribute value and sync with original.
     *
     * @param  string  $column
     * @param  array   $values
     * @param  bool    $unique
     */
    protected function pushAttributeValues($column, array $values, $unique = false)
    {
        $current = $this->getAttributeFromArray($column) ?: [];

        foreach ($values as $value) {
            // Don't add duplicate values when we only want unique values.
            if ($unique and in_array($value, $current)) {
                continue;
            }

            array_push($current, $value);
        }

        $this->attributes[$column] = $current;

        $this->syncOriginalAttribute($column);
    }

    /**
     * Remove one or more values to the underlying attribute value and sync with original.
     *
     * @param  string  $column
     * @param  array   $values
     */
    protected function pullAttributeValues($column, array $values)
    {
        $current = $this->getAttributeFromArray($column) ?: [];

        foreach ($values as $value) {
            $keys = array_keys($current, $value);

            foreach ($keys as $key) {
                unset($current[$key]);
            }
        }

        $this->attributes[$column] = array_values($current);

        $this->syncOriginalAttribute($column);
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \sonvq\Cassandra\Query\Builder $query
     * @return \sonvq\Cassandra\Eloquent\Builder|static
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }

    /**
     * Get a new query builder instance for the connection.
     *
     * @return Builder
     */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new QueryBuilder($connection, $connection->getPostProcessor());
    }

    /**
     * Handle dynamic method calls into the method.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        // Unset method
        if ($method == 'unset') {
            return call_user_func_array([$this, 'drop'], $parameters);
        }

        return parent::__call($method, $parameters);
    }
}
