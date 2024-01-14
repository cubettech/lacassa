<?php

namespace Cubettech\Lacassa;

use Illuminate\Database\Connection as BaseConnection;

use Cassandra;
use Cassandra\Request\Request as CassReq;

class Connection extends BaseConnection {
    public const DEFAULT_TIMEOUT = 30;
    public const DEFAULT_CONNECT_TIMEOUT = 5.0;
    public const DEFAULT_REQUEST_TIMEOUT = 12.0;
    public const DEFAULT_ALLOW_FILTERING = false;
    public const DEFAULT_PAGE_SIZE = 500;
    public const DEFAULT_CONSISTENCY = Cassandra\Request\Request::CONSISTENCY_LOCAL_ONE;

    /**
     * The Cassandra connection handler.
     *
     * @var CassandraConnection
     */
    protected $connection;
    protected $config;
    protected $db;
    protected $allowFiltering;

    /**
     * Create a new database connection instance.
     *
     * @param array $config
     */
    public function __construct(array $config) {
        $this->config = $config;
        // Create the connection
        $this->db = $config['keyspace'];
        $this->connection = $this->createConnection($config);
        $this->useDefaultPostProcessor();

        $this->useDefaultSchemaGrammar();
        $this->useDefaultQueryGrammar();
    }

    /**
     * Dynamically pass methods to the connection.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters) {
        return call_user_func_array([$this->connection, $method], $parameters);
    }

    /**
     * Begin a fluent query against a database collection.
     *
     * @param string $collection
     * @return Query\Builder
     */
    public function collection($collection) {
        $query = new Query\Builder($this->connection);

        return $query->from($collection);
    }

    /**
     * Begin a fluent query against a database collection.
     *
     * @param string $table
     * @param  ?string $as
     * @return Query\Builder
     */
    public function table($table, $as = null) {
        return $this->collection($table);
    }

    /**
     * @inheritdoc
     */
    public function getSchemaBuilder() {
        return new Schema\Builder($this);
    }

    /**
     * [getSchemaGrammar returns the connection grammer]
     * @return [Schema\Grammar] [description]
     */
    public function getSchemaGrammar() {
        return new Schema\Grammar;
    }

    /**
     * return Cassandra object.
     *
     * @return \Cassandra\DefaultSession
     */
    public function getCassandraConnection() {
        return $this->connection;
    }

    public function getConsistency() {
        return $this->config['consistency'] ?? self::DEFAULT_CONSISTENCY;
    }


    /**
     * @inheritdoc
     */
    public function disconnect() {
        unset($this->connection);
    }

    /**
     * @inheritdoc
     */
    public function getElapsedTime($start) {
        return parent::getElapsedTime($start);
    }

    /**
     * @inheritdoc
     */
    public function getDriverName() {
        return 'Cassandra';
    }

    /**
     * Call an CQL statement and return the boolean result.
     *
     * @param string $query
     * @param array $bindings
     * @param bool $useReadPdo
     * @return bool
     */
    public function select($query, $bindings = [], $useReadPdo = true) {
        return $this->statement($query, $bindings, true);
    }

    /**
     * Execute an CQL statement and return the boolean result.
     *
     * @param string $query
     * @param array $bindings
     * @return bool
     */
    public function statement($query, $bindings = [], $isSelect = false) {
        if ($this->allowFiltering) {
            $query .= ' ALLOW FILTERING';
        }

        foreach ($bindings as $binding) {
            $value = 'string' == strtolower(gettype($binding)) ? "'" . $binding . "'" : $binding;
            $query = preg_replace('/\?/', $value, $query, 1);
        }

        $builder = new Query\Builder($this, $this->getPostProcessor());

        return $builder->executeCql($query);
    }

    /**
     * Run an CQL statement and get the number of rows affected.
     *
     * @param string $query
     * @param array $bindings
     * @return int
     */
    public function affectingStatement($query, $bindings = []) {
        // For update or delete statements, we want to get the number of rows affected
        // by the statement and return that back to the developer. We'll first need
        // to execute the statement and then we'll use PDO to fetch the affected.
        foreach ($bindings as $binding) {
            $value = $value = 'string' == strtolower(gettype($binding)) ? "'" . $binding . "'" : $binding;
            $query = preg_replace('/\?/', $value, $query, 1);
        }
        $builder = new Query\Builder($this, $this->getPostProcessor());

        return $builder->executeCql($query);
    }

    /**
     * Execute an raw CQL statement and return the boolean result.
     *
     * @param string $query
     * @param array $bindings
     * @return bool
     */
    public function raw($query) {
        $builder = new Query\Builder($this, $this->getPostProcessor());
        $result = $builder->executeCql($query);

        return $result;
    }

    /**
     * Get nodes configs
     *
     * @param array $config
     * @return array
     */
    protected function getNodes(array $config) {
        $nodes = [];
        $this->allowFiltering = $config['allow_filtering'] ?? self::DEFAULT_ALLOW_FILTERING;

        $hosts = explode(',', $config['host']);
        $config['port'] = $config['port'] ?? [];

        if (count($hosts) < 1) {
            throw new Cassandra\Exception('DB hostname is not found, please check your DB hostname');
        }

        if ($config['port']) {
            $ports = explode(',', $config['port']);
        }


        foreach ($hosts as $index => $host) {
            $node = [
                'host' => $host,
                'port' => (int) $ports[$index],
                'username' => $config['username'],
                'password' => $config['password'],
                'timeout' => $config['timeout'] ?? self::DEFAULT_TIMEOUT,
                'connect_timeout' => $config['connect_timeout'] ?? self::DEFAULT_CONNECT_TIMEOUT,
                'request_timeout' => $config['request_timeout'] ?? self::DEFAULT_REQUEST_TIMEOUT,
                'page_size' => $config['page_size'] ?? self::DEFAULT_PAGE_SIZE,
            ];


            $nodes[] = $node;
        }

        return $nodes;
    }


    /**
     * Create a new Cassandra connection.
     *
     * @param array $config
     * @return CassandraConnection
     */
    protected function createConnection(array $config) {
        $nodes = $this->getNodes($config);
        $connection = new Cassandra\Connection($nodes, $config['keyspace']);

        try {
            $connection->connect();
        } catch (Cassandra\Exception $e) {
            echo 'Caught exception: ', $e->getMessage(), "\n";
        }

        return $connection;
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultPostProcessor() {
        return new Query\Processor();
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultQueryGrammar() {
        return new Query\Grammar();
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultSchemaGrammar() {
        return new Schema\Grammar();
    }
}
