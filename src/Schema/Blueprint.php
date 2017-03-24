<?php namespace Cubettech\Lacassa\Schema;

use Illuminate\Database\Connection;
use \Illuminate\Database\Schema\Grammars\Grammar as BaseGrammar;


class Blueprint extends \Illuminate\Database\Schema\Blueprint
{
    /**
     * The Cassandra object for this blueprint.
     *
     * @var MongoConnection
     */
    protected $connection;

    protected $primary;

    /**
     * @inheritdoc
     */
    public function __construct(Connection $connection, $table)
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    /**
     * Get the columns on the blueprint that should be added.
     *
     * @return array
     */
    public function getAddedColumns()
    {
        return array_filter(
            $this->columns, function ($column) {
                return ! $column->change;
            }
        );
    }
    /**
     * Get the raw SQL statements for the blueprint.
     *
     * @param  \Illuminate\Database\Connection              $connection
     * @param  \Illuminate\Database\Schema\Grammars\Grammar $grammar
     * @return array
     */
    public function toSql(Connection $connection, BaseGrammar $grammar)
    {
        $this->addImpliedCommands();

        $statements = [];
        // Each type of command has a corresponding compiler function on the schema
        // grammar which is used to build the necessary SQL statements to build
        // the blueprint element, so we'll just call that compilers function.
        foreach ($this->commands as $command) {
            $method = 'compile'.ucfirst($command->name);

            if (method_exists($grammar, $method)) {
                if (! is_null($sql = $grammar->$method($this, $command, $connection))) {
                    $statements = array_merge($statements, (array) $sql);
                }
            }
        }
        return $statements;
    }

    /**
     * Specify the primary key(s) for the table.
     *
     * @param  string|array $columns
     * @param  string       $name
     * @param  string|null  $algorithm
     * @return \Illuminate\Support\Fluent
     */
    public function primary($columns, $name = null, $algorithm = null)
    {
        $columns = (array) $columns;
        //$index = $index ?: $this->createIndexName($type, $columns);
        $this->primary = $command = $this->createCommand('primary', compact('columns', 'algorithm'));
        return $command;
    }
    public function compilePrimary()
    {
        $primaryKey = $this->primary;
        if($primaryKey) {
            if('primary' == $primaryKey->name) {
                return sprintf('primary key (%s) ', implode(', ', $primaryKey->columns));
            }
        }
        return;
    }
    /**
     * Create a new ascii column on the table.
     *
     * @param  string $column
     * @return \Illuminate\Support\Fluent
     */
    public function ascii($column)
    {
        return $this->addColumn('ascii', $column);
    }

    /**
     * Create a new bigint column on the table.
     *
     * @param  string $column
     * @return \Illuminate\Support\Fluent
     */
    public function bigint($column)
    {
        return $this->addColumn('bigint', $column);
    }

    /**
     * Create a new blob column on the table.
     *
     * @param  string $column
     * @return \Illuminate\Support\Fluent
     */
    public function blob($column)
    {
        return $this->addColumn('blob', $column);
    }

    /**
     * Create a new boolean column on the table.
     *
     * @param  string $column
     * @return \Illuminate\Support\Fluent
     */
    public function boolean($column)
    {
        return $this->addColumn('boolean', $column);
    }

    /**
     * Create a new counter column on the table.
     *
     * @param  string $column
     * @return \Illuminate\Support\Fluent
     */
    public function counter($column)
    {
        return $this->addColumn('counter', $column);
    }

    /**
     * Create a new frozen column on the table.
     *
     * @param  string $column
     * @return \Illuminate\Support\Fluent
     */
    public function frozen($column)
    {
        return $this->addColumn('frozen', $column);
    }

    /**
     * Create a new inet column on the table.
     *
     * @param  string $column
     * @return \Illuminate\Support\Fluent
     */
    public function inet($column)
    {
        return $this->addColumn('inet', $column);
    }

    /**
     * Create a new int column on the table.
     *
     * @param  string $column
     * @return \Illuminate\Support\Fluent
     */
    public function int($column)
    {
        return $this->addColumn('int', $column);
    }

    /**
     * Create a new list column on the table.
     *
     * @param  string $column
     * @param  string $collectionType
     * @return \Illuminate\Support\Fluent
     */
    public function listCollection($column, $collectionType)
    {
        return $this->addColumn('list', $column, compact('collectionType'));
    }

    /**
     * Create a new map column on the table.
     *
     * @param  string $column
     * @param  string $collectionType1
     * @param  string $collectionType2
     * @return \Illuminate\Support\Fluent
     */
    public function mapCollection($column, $collectionType1, $collectionType2)
    {
        return $this->addColumn('map', $column, compact('collectionType1', 'collectionType2'));
    }

    /**
     * Create a new set column on the table.
     *
     * @param  string $column
     * @param  string $collectionType
     * @return \Illuminate\Support\Fluent
     */
    public function setCollection($column, $collectionType)
    {
        return $this->addColumn('set', $column, compact('collectionType'));
    }

    /**
     * Create a new timestamp column on the table.
     *
     * @param  string $column
     * @return \Illuminate\Support\Fluent
     */
    public function timestamp($column)
    {
        return $this->addColumn('timestamp', $column);
    }

    /**
     * Create a new timeuuid column on the table.
     *
     * @param  string $column
     * @return \Illuminate\Support\Fluent
     */
    public function timeuuid($column)
    {
        return $this->addColumn('timeuuid', $column);
    }

    /**
     * Create a new tuple column on the table.
     *
     * @param  string $column
     * @param  string $tuple1type
     * @param  string $tuple2type
     * @param  string $tuple3type
     * @return \Illuminate\Support\Fluent
     */
    public function tuple($column, $tuple1type, $tuple2type, $tuple3type)
    {
        return $this->addColumn('tuple', $column, compact('tuple1type', 'tuple2type', 'tuple3type'));
    }

    /**
     * Create a new uuid column on the table.
     *
     * @param  string $column
     * @return \Illuminate\Support\Fluent
     */
    public function uuid($column)
    {
        return $this->addColumn('uuid', $column);
    }

    /**
     * Create a new varchar column on the table.
     *
     * @param  string $column
     * @return \Illuminate\Support\Fluent
     */
    public function varchar($column)
    {
        return $this->addColumn('varchar', $column);
    }

    /**
     * Create a new varint column on the table.
     *
     * @param  string $column
     * @return \Illuminate\Support\Fluent
     */
    public function varint($column)
    {
        return $this->addColumn('varint', $column);
    }

}
