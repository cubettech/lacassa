<?php
namespace Cubettech\Lacassa;

use Illuminate\Database\Connection as BaseConnection;
use Cassandra;

class Connection extends BaseConnection
{
    /**
     * The Cassandra connection handler.
     *
     * @var \Cassandra\DefaultSession
     */
    protected $connection;

    /**
     * Create a new database connection instance.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        // Create the connection
        $this->db = $config['keyspace'];
        $this->connection = $this->createConnection($config);
        $this->useDefaultPostProcessor();
    }

    /**
     * Begin a fluent query against a database collection.
     *
     * @param  string $collection
     * @return Query\Builder
     */
    public function collection($collection)
    {
        $query = new Query\Builder($this);
        return $query->from($collection);
    }

    /**
     * Begin a fluent query against a database collection.
     *
     * @param  string $table
     * @return Query\Builder
     */
    public function table($table)
    {
        return $this->collection($table);
    }

    /**
     * @inheritdoc
     */
    public function getSchemaBuilder()
    {
        return new Schema\Builder($this);
    }
    /**
     * [getSchemaGrammar returns the connection grammer]
     * @return [Schema\Grammar] [description]
     */
    public function getSchemaGrammar()
    {
        return new Schema\Grammar;
    }

    /**
     * return Cassandra object.
     *
     * @return \Cassandra\DefaultSession
     */
    public function getCassandraConnection()
    {
        return $this->connection;
    }

    /**
     * Create a new Cassandra connection.
     *
     * @param  array $config
     * @return \Cassandra\DefaultSession
     */
    protected function createConnection(array $config)
    {
        $cluster   = Cassandra::cluster()->build();
        $keyspace  = $config['keyspace'];
        $connection   = $cluster->connect($keyspace);
        return $connection;
    }

    /**
     * @inheritdoc
     */
    public function disconnect()
    {
        unset($this->connection);
    }

    /**
     * @inheritdoc
     */
    public function getElapsedTime($start)
    {
        return parent::getElapsedTime($start);
    }

    /**
     * @inheritdoc
     */
    public function getDriverName()
    {
        return 'Cassandra';
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultPostProcessor()
    {
        return new Query\Processor();
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultQueryGrammar()
    {
        return new Query\Grammar();
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultSchemaGrammar()
    {
        return new Schema\Grammar();
    }

    /**
     * Execute an CQL statement and return the boolean result.
     *
     * @param  string $query
     * @param  array  $bindings
     * @return bool
     */
    public function statement($query, $bindings = [])
    {
        foreach($bindings as $binding)
          {
            $value = 'string' == strtolower(gettype($binding)) ? "'" . $binding . "'" : $binding;
            $query = preg_replace('/\?/', $value, $query, 1);
          }
          $builder = new Query\Builder($this, $this->getPostProcessor());
          return $builder->executeCql($query);
    }

    /**
     * Run an CQL statement and get the number of rows affected.
     *
     * @param  string $query
     * @param  array  $bindings
     * @return int
     */
    public function affectingStatement($query, $bindings = [])
    {
            // For update or delete statements, we want to get the number of rows affected
            // by the statement and return that back to the developer. We'll first need
            // to execute the statement and then we'll use PDO to fetch the affected.
        foreach($bindings as $binding)
            {
            $value = $value = 'string' == strtolower(gettype($binding)) ? "'" . $binding . "'" : $binding;
            $query = preg_replace('/\?/', $value, $query, 1);
        }
            $builder = new Query\Builder($this, $this->getPostProcessor());

            return $builder->executeCql($query);
    }

    /**
     * Execute an CQL statement and return the boolean result.
     *
     * @param  string $query
     * @param  array  $bindings
     * @return bool
     */
    public function raw($query)
    {
        $builder = new Query\Builder($this, $this->getPostProcessor());
        $result = $builder->executeCql($query);
        return $result;
    }

    /**
     * Dynamically pass methods to the connection.
     *
     * @param  string $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->connection, $method], $parameters);
    }
}
