<?php namespace Cubettech\Lacassa\Schema;

use Illuminate\Database\Schema\Grammars\Grammar as BaseGrammar;
use Cubettech\Lacassa\Schema\Blueprint as Blueprint;
use \Illuminate\Support\Fluent;
use Cubettech\Lacassa\Connection;
use \Illuminate\Database\Schema\Blueprint as BaseBlueprint;

class Grammar extends BaseGrammar
{
  /**
    * The possible column modifiers.
    *
    * @var array
    */
   protected $modifiers = [
       'VirtualAs', 'StoredAs', 'Unsigned', 'Charset', 'Collate', 'Nullable',
       'Default', 'Increment', 'Comment', 'After', 'First',
   ];

   /**
    * The possible column serials.
    *
    * @var array
    */
   protected $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];

   /**
    * Compile the query to determine the list of tables.
    *
    * @return string
    */
   public function compileTableExists()
   {
       return 'select * from system_schema.tables where table_schema = ? and table_name = ?';
   }


// Todos
//    public function compileTables($keyspace)
//    {
//        // Query to fetch table names from a specific keyspace in Cassandra
//        return "select * from system_schema.tables where keyspace_name = '$keyspace' ALLOW FILTERING";
//    }

   /**
    * Compile the query to determine the list of columns.
    *
    * @return string
    */
   public function compileColumnListing()
   {
       return 'select column_name from information_schema.columns where table_schema = ? and table_name = ?';
   }

   /**
    * Compile a create table command.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $command
    * @param  \Illuminate\Database\Connection  $connection
    * @return string
    */
   public function compileCreate(Blueprint $blueprint, Fluent $command, Connection $connection)
   {
       $sql = $this->compileCreateTable(
           $blueprint, $command, $connection
       );

       // Once we have the primary SQL, we can add the encoding option to the SQL for
       // the table.  Then, we can check if a storage engine has been supplied for
       // the table. If so, we will add the engine declaration to the SQL query.
      //  $sql = $this->compileCreateEncoding(
      //      $sql, $connection, $blueprint
      //  );

       //Once we have the sql query we add the indexes - primary key to the query
      //  $sql = $this->compilePrimary($blueprint, $command);

       // Finally, we will append the engine configuration onto this SQL statement as
       // the final thing we do before returning this finished SQL. Once this gets
       // added the query will be ready to execute against the real connections.
       return $this->compileCreateEngine(
           $sql, $connection, $blueprint
       );
   }

   /**
    * Create the main create table clause.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $command
    * @param  \Illuminate\Database\Connection  $connection
    * @return string
    */
   protected function compileCreateTable($blueprint, $command, $connection)
   {
       return sprintf('%s table %s (%s, %s)',
           'create',
           $this->wrapTable($blueprint),
           implode(', ', $this->getColumns($blueprint)),
           $this->compilePrimary($blueprint, $command)
       );
   }

   /**
    * Append the character set specifications to a command.
    *
    * @param  string  $sql
    * @param  \Illuminate\Database\Connection  $connection
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @return string
    */
   protected function compileCreateEncoding($sql, Connection $connection, Blueprint $blueprint)
   {
       // First we will set the character set if one has been set on either the create
       // blueprint itself or on the root configuration for the connection that the
       // table is being created on. We will add these to the create table query.
       if (isset($blueprint->charset)) {
           $sql .= ' default character set '.$blueprint->charset;
       } elseif (! is_null($charset = $connection->getConfig('charset'))) {
           $sql .= ' default character set '.$charset;
       }

       // Next we will add the collation to the create table statement if one has been
       // added to either this create table blueprint or the configuration for this
       // connection that the query is targeting. We'll add it to this SQL query.
       if (isset($blueprint->collation)) {
           $sql .= ' collate '.$blueprint->collation;
       } elseif (! is_null($collation = $connection->getConfig('collation'))) {
           $sql .= ' collate '.$collation;
       }

       return $sql;
   }

   /**
    * Append the engine specifications to a command.
    *
    * @param  string  $sql
    * @param  \Illuminate\Database\Connection  $connection
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @return string
    */
   protected function compileCreateEngine($sql, Connection $connection, Blueprint $blueprint)
   {
       if (isset($blueprint->engine)) {
           return $sql.' engine = '.$blueprint->engine;
       } elseif (! is_null($engine = $connection->getConfig('engine'))) {
           return $sql.' engine = '.$engine;
       }

       return $sql;
   }

   /**
    * Compile an add column command.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $command
    * @return string
    */
   public function compileAdd(Blueprint $blueprint, Fluent $command)
   {
       $columns = $this->prefixArray('add', $this->getColumns($blueprint));

       return 'alter table '.$this->wrapTable($blueprint).' '.implode(', ', $columns);
   }

   /**
    * Compile a primary key command.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $command
    * @return string
    */
   public function compilePrimary(Blueprint $blueprint, Fluent $command)
   {
       return $blueprint->compilePrimary();
       return $this->compileKey($blueprint, $command, 'primary key');
   }

   /**
    * Compile a unique key command.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $command
    * @return string
    */
   public function compileUnique(Blueprint $blueprint, Fluent $command)
   {
       return $this->compileKey($blueprint, $command, 'unique');
   }

   /**
    * Compile a plain index key command.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $command
    * @return string
    */
   public function compileIndex(Blueprint $blueprint, Fluent $command)
   {
       return $this->compileKey($blueprint, $command, 'index');
   }

   /**
    * Compile an index creation command.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $command
    * @param  string  $type
    * @return string
    */
   protected function compileKey(Blueprint $blueprint, Fluent $command, $type)
   {
       return sprintf('alter table %s add %s %s%s(%s)',
           $this->wrapTable($blueprint),
           $type,
           $this->wrap($command->index),
           $command->algorithm ? ' using '.$command->algorithm : '',
           $this->columnize($command->columns)
       );
   }

   /**
    * Compile a drop table command.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $command
    * @return string
    */
   public function compileDrop(Blueprint $blueprint, Fluent $command)
   {
       return 'drop table '.$this->wrapTable($blueprint);
   }

   /**
    * Compile a drop table (if exists) command.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $command
    * @return string
    */
   public function compileDropIfExists(Blueprint $blueprint, Fluent $command)
   {
       return 'drop table if exists '.$this->wrapTable($blueprint);
   }

   /**
    * Compile a drop column command.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $command
    * @return string
    */
   public function compileDropColumn(Blueprint $blueprint, Fluent $command)
   {
       $columns = $this->prefixArray('drop', $this->wrapArray($command->columns));

       return 'alter table '.$this->wrapTable($blueprint).' '.implode(', ', $columns);
   }

   /**
    * Compile a drop primary key command.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $command
    * @return string
    */
   public function compileDropPrimary(Blueprint $blueprint, Fluent $command)
   {
       return 'alter table '.$this->wrapTable($blueprint).' drop primary key';
   }

   /**
    * Compile a drop unique key command.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $command
    * @return string
    */
   public function compileDropUnique(Blueprint $blueprint, Fluent $command)
   {
       $index = $this->wrap($command->index);

       return "alter table {$this->wrapTable($blueprint)} drop index {$index}";
   }

   /**
    * Compile a drop index command.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $command
    * @return string
    */
   public function compileDropIndex(Blueprint $blueprint, Fluent $command)
   {
       $index = $this->wrap($command->index);

       return "alter table {$this->wrapTable($blueprint)} drop index {$index}";
   }

   /**
    * Compile a drop foreign key command.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $command
    * @return string
    */
   public function compileDropForeign(Blueprint $blueprint, Fluent $command)
   {
       $index = $this->wrap($command->index);

       return "alter table {$this->wrapTable($blueprint)} drop foreign key {$index}";
   }

   /**
    * Compile a rename table command.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $command
    * @return string
    */
   public function compileRename(Blueprint $blueprint, Fluent $command)
   {
       $from = $this->wrapTable($blueprint);

       return "rename table {$from} to ".$this->wrapTable($command->to);
   }

   /**
    * Compile the command to enable foreign key constraints.
    *
    * @return string
    */
   public function compileEnableForeignKeyConstraints()
   {
       return 'SET FOREIGN_KEY_CHECKS=1;';
   }

   /**
    * Compile the command to disable foreign key constraints.
    *
    * @return string
    */
   public function compileDisableForeignKeyConstraints()
   {
       return 'SET FOREIGN_KEY_CHECKS=0;';
   }

   /**
    * Create the column definition for a char type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeChar(Fluent $column)
   {
       return "char({$column->length})";
   }

   /**
    * Create the column definition for a string type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeString(Fluent $column)
   {
       return "varchar({$column->length})";
   }

   /**
    * Create the column definition for a text type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeText(Fluent $column)
   {
       return 'text';
   }

   /**
    * Create the column definition for a text type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeBigint(Fluent $column)
   {
       return 'bigint';
   }

   /**
    * Create the column definition for a blob type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeBlob(Fluent $column)
   {
       return 'blob';
   }

   /**
    * Create the column definition for a counter type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeCounter(Fluent $column)
   {
       return 'counter';
   }

   /**
    * Create the column definition for a frozen type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeFrozen(Fluent $column)
   {
       return 'frozen';
   }

   /**
    * Create the column definition for a inet type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeInet(Fluent $column)
   {
       return 'inet';
   }

  /**
    * Create the column definition for a int type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeInt(Fluent $column)
   {
       return 'int';
   }

   /**
    * Create the column definition for a list type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeList(Fluent $column)
   {
       return 'list<'.$column->collectionType.'>';
   }

   /**
    * Create the column definition for a map type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeMap(Fluent $column)
   {
       return 'map<'.$column->collectionType1.', '.$column->collectionType2.'>';
   }

  /**
    * Create the column definition for a set type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeSet(Fluent $column)
   {
       return 'set<'.$column->collectionType.'>';
   }

   /**
    * Create the column definition for a timeuuid type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeTimeuuid(Fluent $column)
   {
       return 'timeuuid';
   }

   /**
    * Create the column definition for a tuple type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeTuple(Fluent $column)
   {
       return 'tuple<'.$column->tuple1type.', '.$column->tuple2type.', '.$column->tuple3type.'>';
   }

   /**
    * Create the column definition for a varchar type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeVarchar(Fluent $column)
   {
       return 'varchar';
   }

   /**
    * Create the column definition for a varint type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeVarint(Fluent $column)
   {
       return 'varint';
   }

   /**
    * Create the column definition for a medium text type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeMediumText(Fluent $column)
   {
       return 'mediumtext';
   }

   /**
    * Create the column definition for a long text type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeLongText(Fluent $column)
   {
       return 'longtext';
   }

   /**
    * Create the column definition for a big integer type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeBigInteger(Fluent $column)
   {
       return 'bigint';
   }

   /**
    * Create the column definition for an integer type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeInteger(Fluent $column)
   {
       return 'int';
   }

   /**
    * Create the column definition for a medium integer type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeMediumInteger(Fluent $column)
   {
       return 'mediumint';
   }

   /**
    * Create the column definition for a tiny integer type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeTinyInteger(Fluent $column)
   {
       return 'tinyint';
   }

   /**
    * Create the column definition for a small integer type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeSmallInteger(Fluent $column)
   {
       return 'smallint';
   }

   /**
    * Create the column definition for a float type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeFloat(Fluent $column)
   {
       return $this->typeDouble($column);
   }

   /**
    * Create the column definition for a double type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeDouble(Fluent $column)
   {
       return 'double';
   }

   /**
    * Create the column definition for a decimal type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeDecimal(Fluent $column)
   {
       return "decimal";
   }

   /**
    * Create the column definition for a boolean type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeBoolean(Fluent $column)
   {
       return 'boolean';
   }

  /**
    * Create the column definition for a boolean type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeAscii(Fluent $column)
   {
       return 'ascii';
   }
   /**
    * Create the column definition for an enum type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeEnum(Fluent $column)
   {
       return "enum('".implode("', '", $column->allowed)."')";
   }

   /**
    * Create the column definition for a json type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeJson(Fluent $column)
   {
       return 'json';
   }

   /**
    * Create the column definition for a jsonb type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeJsonb(Fluent $column)
   {
       return 'json';
   }

   /**
    * Create the column definition for a date type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeDate(Fluent $column)
   {
       return 'date';
   }

   /**
    * Create the column definition for a date-time type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeDateTime(Fluent $column)
   {
       return 'datetime';
   }

   /**
    * Create the column definition for a date-time type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeDateTimeTz(Fluent $column)
   {
       return 'datetime';
   }

   /**
    * Create the column definition for a time type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeTime(Fluent $column)
   {
       return 'time';
   }

   /**
    * Create the column definition for a time type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeTimeTz(Fluent $column)
   {
       return 'time';
   }

   /**
    * Create the column definition for a timestamp type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeTimestamp(Fluent $column)
   {
       if ($column->useCurrent) {
           return 'timestamp default CURRENT_TIMESTAMP';
       }

       return 'timestamp';
   }

   /**
    * Create the column definition for a timestamp type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeTimestampTz(Fluent $column)
   {
       if ($column->useCurrent) {
           return 'timestamp default CURRENT_TIMESTAMP';
       }

       return 'timestamp';
   }

   /**
    * Create the column definition for a binary type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeBinary(Fluent $column)
   {
       return 'blob';
   }

   /**
    * Create the column definition for a uuid type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeUuid(Fluent $column)
   {
       return 'uuid';
   }

   /**
    * Create the column definition for an IP address type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeIpAddress(Fluent $column)
   {
       return 'varchar(45)';
   }

   /**
    * Create the column definition for a MAC address type.
    *
    * @param  \Illuminate\Support\Fluent  $column
    * @return string
    */
   protected function typeMacAddress(Fluent $column)
   {
       return 'varchar(17)';
   }

   /**
    * Get the SQL for a generated virtual column modifier.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $column
    * @return string|null
    */
   protected function modifyVirtualAs(Blueprint $blueprint, Fluent $column)
   {
       if (! is_null($column->virtualAs)) {
           return " as ({$column->virtualAs})";
       }
   }

   /**
    * Get the SQL for a generated stored column modifier.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $column
    * @return string|null
    */
   protected function modifyStoredAs(Blueprint $blueprint, Fluent $column)
   {
       if (! is_null($column->storedAs)) {
           return " as ({$column->storedAs}) stored";
       }
   }

   /**
    * Get the SQL for an unsigned column modifier.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $column
    * @return string|null
    */
   protected function modifyUnsigned(Blueprint $blueprint, Fluent $column)
   {
       if ($column->unsigned) {
           return ' unsigned';
       }
   }

   /**
    * Get the SQL for a character set column modifier.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $column
    * @return string|null
    */
   protected function modifyCharset(Blueprint $blueprint, Fluent $column)
   {
       if (! is_null($column->charset)) {
           return ' character set '.$column->charset;
       }
   }

   /**
    * Get the SQL for a collation column modifier.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $column
    * @return string|null
    */
   protected function modifyCollate(Blueprint $blueprint, Fluent $column)
   {
       if (! is_null($column->collation)) {
           return ' collate '.$column->collation;
       }
   }

   /**
    * Get the SQL for a nullable column modifier.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $column
    * @return string|null
    */
   protected function modifyNullable(Blueprint $blueprint, Fluent $column)
   {
       if (is_null($column->virtualAs) && is_null($column->storedAs)) {
           return $column->nullable ? ' null' : ' not null';
       }
   }

   /**
    * Get the SQL for a default column modifier.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $column
    * @return string|null
    */
   protected function modifyDefault(Blueprint $blueprint, Fluent $column)
   {
       if (! is_null($column->default)) {
           return ' default '.$this->getDefaultValue($column->default);
       }
   }

   /**
    * Get the SQL for an auto-increment column modifier.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $column
    * @return string|null
    */
   protected function modifyIncrement(Blueprint $blueprint, Fluent $column)
   {
       if (in_array($column->type, $this->serials) && $column->autoIncrement) {
           return ' auto_increment primary key';
       }
   }

   /**
    * Get the SQL for a "first" column modifier.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $column
    * @return string|null
    */
   protected function modifyFirst(Blueprint $blueprint, Fluent $column)
   {
       if (! is_null($column->first)) {
           return ' first';
       }
   }

   /**
    * Get the SQL for an "after" column modifier.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $column
    * @return string|null
    */
   protected function modifyAfter(Blueprint $blueprint, Fluent $column)
   {
       if (! is_null($column->after)) {
           return ' after '.$this->wrap($column->after);
       }
   }

   /**
    * Get the SQL for a "comment" column modifier.
    *
    * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
    * @param  \Illuminate\Support\Fluent  $column
    * @return string|null
    */
   protected function modifyComment(Blueprint $blueprint, Fluent $column)
   {
       if (! is_null($column->comment)) {
           return " comment '".$column->comment."'";
       }
   }

   /**
    * Wrap a single string in keyword identifiers.
    *
    * @param  string  $value
    * @return string
    */
   protected function wrapValue($value)
   {
       if ($value !== '*') {
           return str_replace('`', '``', $value);
       }

       return $value;
   }

   /**
   * Compile the blueprint's column definitions.
   *
   * @param  \Illuminate\Database\Schema\Blueprint $blueprint
   * @return array
   */
  protected function getColumns(BaseBlueprint $blueprint)
  {
      $columns = [];

      foreach ($blueprint->getAddedColumns() as $column) {
          // Each of the column types have their own compiler functions which are tasked
          // with turning the column definition into its SQL format for this platform
          // used by the connection. The column's modifiers are compiled and added.
          $sql = $this->wrap($column).' '.$this->getType($column);

          $columns[] = $sql;
      }

      return $columns;
  }

}
