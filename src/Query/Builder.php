<?php namespace Cubettech\Lacassa\Query;

use Closure;
use DateTime;
use InvalidArgumentException;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Cubettech\Lacassa\Connection;
use Cassandra;




class Builder extends BaseBuilder
{
    /**
     * The current query value bindings.
     *
     * @var array
     */
    public $bindings = [
        'select' => [],
        'join'   => [],
        'where'  => [],
        'having' => [],
        'order'  => [],
        'union'  => [],
        'updateCollection' => [],
        'insertCollection' => []
    ];
    /**
     * The where constraints for the query.
     *
     * @var array
     */
    public $updateCollections;

    public $insertCollections;

    /**
     * The database collection.
     *
     * @var CassandraCollection
     */
    protected $collection;

    /**
     * The column projections.
     *
     * @var array
     */
    public $projections;

    /**
     * The cursor timeout value.
     *
     * @var int
     */
    public $timeout;

    /**
     * The cursor hint value.
     *
     * @var int
     */
    public $hint;

    /**
     * Custom options to add to the query.
     *
     * @var array
     */
    public $options = [];

    /**
     * Indicate if we are executing a pagination query.
     *
     * @var bool
     */
    public $paginating = false;

    /**
     * All of the available clause operators.
     *
     * @var array
     */
    public $operators = [
        '=',
        '<',
        '>',
        '<=',
        '>=',
        '<>',
        '!=',
        'like',
        'not like',
        'between',
        'ilike',
        '&',
        '|',
        '^',
        '<<',
        '>>',
        'rlike',
        'regexp',
        'not regexp',
        'exists',
        'type',
        'mod',
        'where',
        'all',
        'size',
        'regex',
        'text',
        'slice',
        'elemmatch',
        'geowithin',
        'geointersects',
        'near',
        'nearsphere',
        'geometry',
        'maxdistance',
        'center',
        'centersphere',
        'box',
        'polygon',
        'uniquedocs',
        'contains',
        'contains key',
    ];

    /**
     * Operator conversion.
     *
     * @var array
     */
    protected $conversion = [
        '=' => '=',
        '!=' => '$ne',
        '<>' => '$ne',
        '<' => '$lt',
        '<=' => '$lte',
        '>' => '$gt',
        '>=' => '$gte'
    ];

    /**
     * Check if we need to return Collections instead of plain arrays (laravel >= 5.3 )
     *
     * @var boolean
     */
    protected $useCollections;

    /**
     * [$collectionTypes description]
     * @var [array]
     */
    public $collectionTypes = ['set', 'list', 'map'];

    /**
     * @inheritdoc
     */
    public function __construct(Connection $connection)
    {
        $this->grammar = new Grammar;
        $this->connection = $connection;
        // $this->processor = $processor;
        //$this->useCollections = $this->shouldUseCollections();
    }

    /**
     * Set the table which the query is targeting.
     *
     * @param  string $table
     * @return $this
     */
    public function from($collection)
    {
        return parent::from($collection);
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array $columns
     * @return Cassandra\Rows
     */
    public function get($columns = ['*'])
    {
        if(is_null($this->columns)) {
            $this->columns = $columns;
        }
        $cql = $this->toCql();
        $cql = $this->bindQuery($cql);
        $result = $this->executeCql($cql);
        return $result;
    }

    /**
     * Bind the query with its parameters.
     *
     * @param  object $cql
     * @return $cql
     */
    public function bindQuery($cql)
    {
        foreach($this->getBindings() as $binding)
        {
            $value = is_numeric($binding) ? $binding : "'".$binding."'";
            $cql = preg_replace('/\?/', $value, $cql, 1);
        }
        return $cql;
    }

    /**
     * Execute the CQL query.
     *
     * @param  object $cql
     * @return Cassandra\Rows
     */
    public function executeCql($cql)
    {   //dd($cql);
        $statement = new Cassandra\SimpleStatement($cql);
        $future    = $this->connection->getCassandraConnection()->executeAsync($statement);
        $result    = $future->get();
        return $result;
    }
    /**
     * Delete a record from the database.
     *
     * @param  mixed $id
     * @return Cassandra\Rows
     */
    public function deleteRow()
    {
        $query = $this->grammar->compileDelete($this);
        $cql = $this->bindQuery($query);
        $result = $this->executeCql($cql);
        return $result;
    }

    /**
     * Delete a column from the database.
     *
     * @param  mixed $columns
     * @return Cassandra\Rows
     */
    public function deleteColumn($columns)
    {
        $this->delParams = $columns;
        $query = $this->grammar->compileDelete($this);
        $cql = $this->bindQuery($query);
        $result = $this->executeCql($cql);
        return $result;
    }

    /**
     * Get the CQL representation of the query.
     *
     * @return string
     */
    public function toCql()
    {
        return $this->grammar->compileSelect($this);
    }

    /**
     * Retrieve the "count" result of the query.
     *
     * @param  string $columns
     * @return Cassandra\Rows
     */
    public function count($columns = '*')
    {
        $result = $this->get();
        return (int) $result->count();
    }

    /**
     * [updateCollection used to update the colletions like set, list and map]
     * @param  [string] $type      [description]
     * @param  [string] $column    [description]
     * @param  [string] $operation [description]
     * @param  [string] $value     [description]
     * @return [string]            [description]
     */
    public function updateCollection($type, $column, $operation = null, $value = null)
    {
        //Check if the type is anyone in SET, LIST or MAP. else throw ERROR.
        if (! in_array(strtolower($type), $this->collectionTypes)) {
            throw new InvalidArgumentException("Invalid binding type: {$type}, Should be any one of ".implode(', ', $this->collectionTypes));
        }
        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        if(func_num_args() == 3)
        {
          $value = $operation;
          $operation = null;
        }
        $updateCollection = compact('type', 'column', 'value', 'operation');
        $this->updateCollections[] = $updateCollection;
        $this->addCollectionBinding($updateCollection, 'updateCollection');
        return $this;
    }

    /**
     * Add a binding to the query.
     *
     * @param  mixed  $value
     * @param  string $type
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function addCollectionBinding($value, $type = 'updateCollection')
    {
        if (! array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException("Invalid binding type: {$type}.");
        }
      $this->bindings[$type][] = $value;
        return $this;
    }

    /**
     * Update a record in the database.
     *
     * @param  array $values
     * @return int
     */
        public function update(array $values = [])
        {
            $cql = $this->grammar->compileUpdate($this, $values);
            return $this->connection->update(
                $cql, $this->cleanBindings(
                    $this->grammar->prepareBindingsForUpdate($this->bindings, $values)
                )
            );
        }

      /**
       * Insert a new record into the database.
       *
       * @param  array  $values
       * @return bool
       */
      public function insert(array $values = [])
      {
          $insertCollectionArray = [];
          // Since every insert gets treated like a batch insert, we will make sure the
          // bindings are structured in a way that is convenient when building these
          // inserts statements by verifying these elements are actually an array.
          if (empty($values)) {
              return true;
          }

          if (! is_array(reset($values))) {
              $values = [$values];
          }

  				if (! is_array(reset($values))) {
              $values = [$values];
          }


          // Here, we will sort the insert keys for every record so that each insert is
          // in the same order for the record. We need to make sure this is the case
          // so there are not any errors or problems when inserting these records.
          else {
              foreach ($values as $key => $value) {
                  ksort($value);
                  $values[$key] = $value;
              }
          }
          // Finally, we will run this query against the database connection and return
          // the results. We will need to also flatten these bindings before running
          // the query so they are all in one huge, flattened array for execution.
          return $this->connection->insert(
              $this->grammar->compileInsert($this, $values),
              $this->cleanBindings(Arr::flatten($values, 1))
          );
      }

      /**
       * [insertCollection insert a colletion type in cassandra]
       * @param  [type] $type   [description]
       * @param  [type] $column [description]
       * @param  [type] $value  [description]
       * @return [type]         [description]
       */
      public function insertCollection($type, $column, $value)
      {
        $insertCollection = compact('type', 'column', 'value');
        $this->insertCollections[] = $insertCollection;
        $this->addCollectionBinding($insertCollection, 'insertCollection');
        return $this;
      }

      /**
       * [index description]
       * @param  [type] $columns [description]
       * @return [type]          [description]
       */
      public function index($columns = [])
      {
        $cql = $this->grammar->compileIndex($this, $columns);
        $result = $this->executeCql($cql);
        return $result;
      }
}
