<?php namespace Cubettech\Lacassa\Query;

use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;
use Illuminate\Database\Query\Builder as BaseBuilder;

class Grammar extends BaseGrammar
{
    /**
     * [compileSelect compiles the cql select]
     * @param  BaseBuilder $query [description]
     * @return [type]             [description]
     */
    public function compileSelect(BaseBuilder $query)
    {

        // If the query does not have any columns set, we'll set the columns to the
        // * character to just get all of the columns from the database. Then we
        // can build the query and concatenate all the pieces together as one.
        $original = $query->columns;

        if (is_null($query->columns)) {
            $query->columns = ['*'];
        }

        // To compile the query, we'll spin through each component of the query and
        // see if that component exists. If it does we'll just call the compiler
        // function for the component which is responsible for making the CQL.
        $cql = trim(
            $this->concatenate(
                $this->compileComponents($query)
            )
        );
        $query->columns = $original;

        return $cql;
    }

    /**
      * Compile an insert statement into CQL.
      *
      * @param  Cubettech\Lacassa\Query $query
      * @param  array               $values
      * @return string
      */
    public function compileInsert(BaseBuilder $query, array $values)
    {
        // Essentially we will force every insert to be treated as a batch insert which
        // simply makes creating the CQL easier for us since we can utilize the same
        // basic routine regardless of an amount of records given to us to insert.
        $table = $this->wrapTable($query->from);
        if (! is_array(reset($values))) {
            $values = [$values];
        }
				$insertCollections = collect($query->bindings['insertCollection']);

				$insertCollectionArray = $insertCollections->mapWithKeys(function($collectionItem){
        return [$collectionItem['column'] => $this->compileCollectionValues($collectionItem['type'], $collectionItem['value'])];
				})->all();

        $columns = $this->columnize(array_keys(reset($values)));
        $collectionColumns = $this->columnize(array_keys($insertCollectionArray));
        if($collectionColumns){
          $columns = $columns ? $columns .', '. $collectionColumns:$collectionColumns;
        }
        $collectionParam = $this->buildInsertCollectionParam($insertCollections);
        // We need to build a list of parameter place-holders of values that are bound
        // to the query. Each insert should have the exact same amount of parameter
        // bindings so we will loop through the record and parameterize them all.
        $parameters = collect($values)->map(
            function ($record) {
                return $this->parameterize($record);
            }
        )->implode(', ');
        if($collectionParam){
          $parameters = $parameters ? $parameters .', '. $collectionParam : $collectionParam;
        }

        return "insert into $table ($columns) values ($parameters)";
    }

    /**
     * [buildInsertCollectionParam description]
     * @param  [type] $collection [description]
     * @return [type]             [description]
     */
    public function buildInsertCollectionParam($collection){
      return $collection->map(function($collectionItem){
        return $this->compileCollectionValues($collectionItem['type'], $collectionItem['value']);
      })->implode(', ');
    }

    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param  string $value
     * @return string
     */
    protected function wrapValue($value)
    {
        if ($value !== '*') {
            return str_replace('"', '""', $value);
        }

        return $value;
    }

    /**
     * Compile a delete statement into CQL.
     *
     * @param  Cubettech\Lacassa\Query $query
     * @return string
     */
    public function compileDelete(BaseBuilder $query)
    {
        $delColumns = "";
        if(isset($query->delParams)) {
            $delColumns = implode(", ", $query->delParams);
        }

        $wheres = is_array($query->wheres) ? $this->compileWheres($query) : '';
        return trim("delete ".$delColumns." from {$this->wrapTable($query->from)} $wheres");
    }

    /**
     * Compile an update statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  array                              $values
     * @return string
     */
    public function compileUpdate(BaseBuilder $query, $values)
    {
        $table = $this->wrapTable($query->from);
        // Each one of the columns in the update statements needs to be wrapped in the
        // keyword identifiers, also a place-holder needs to be created for each of
        // the values in the list of bindings so we can make the sets statements.
        $columns = collect($values)->map(
            function ($value, $key) {
                return $this->wrap($key).' = '.$this->parameter($value);
            }
        )->implode(', ');

        // Of course, update queries may also be constrained by where clauses so we'll
        // need to compile the where clauses and attach it to the query so only the
        // intended records are updated by the SQL statements we generate to run.
        $wheres = $this->compileWheres($query);
        $upateCollections = $this->compileUpdateCollections($query);
        if($upateCollections)
        {
          $upateCollections = $columns ? ', '.$upateCollections : $upateCollections;
        }

        return trim("update {$table} set $columns $upateCollections $wheres");
    }

    /**
     * [compileUpdateCollections compiles the udpate collection methods]
     * @param  BaseBuilder $query [description]
     * @return [type]             [description]
     */
    public function compileUpdateCollections(BaseBuilder $query)
    {
        $updateCollections = collect($query->bindings['updateCollection']);

        $updateCollectionCql = $updateCollections->map(
            function ($collection, $key) {
                if($collection['operation']) {
                    return $collection['column'] . '=' . $collection['column'] . $collection['operation'] . $this->compileCollectionValues($collection['type'], $collection['value']);
                }else{
                    return $collection['column'] . '=' . $this->compileCollectionValues($collection['type'], $collection['value']);
                }
            }
        )->implode(', ');
        return $updateCollectionCql;

    }

    /**
     * [compileCollectionValues compiles the values assigned to collections]
     * @param  [type] $type  [description]
     * @param  [type] $value [description]
     * @return [type]        [description]
     */
    public function compileCollectionValues($type, $value)
    {
        if(is_array($value)) {

            if('set' == $type) {
                $collection = "{".$this->buildCollectionString($type, $value)."}";
            }
            elseif ('list' == $type) {
                $collection = "[".$this->buildCollectionString($type, $value)."]";
            }
            elseif ('map' == $type) {
                $collection = "{".$this->buildCollectionString($type, $value)."}";
            }

            return $collection;
        }

    }

    /**
     * [buildCollectionString builds the insert string]
     * @param  [type] $type  [description]
     * @param  [type] $value [description]
     * @return [type]        [description]
     */
    public function buildCollectionString($type, $value)
    {
        $isAssociative = false;
        if(count(array_filter(array_keys($value), 'is_string')) > 0) {
            $isAssociative = true;
        }
        if(is_array($value)) {
            if('set' == $type || 'list' == $type) {
                $collection = collect($value)->map(
                    function ($item, $key) {
                        return 'string' == strtolower(gettype($item)) ? "'" . $item . "'" : $item;
                    }
                )->implode(', ');
            }
            elseif('map' == $type) {
                $collection = collect($value)->map(
                    function ($item, $key) use ($isAssociative){
                        if($isAssociative === true) {
                            $key = 'string' == strtolower(gettype($key)) ? "'" . $key . "'" : $key;
                            $item = 'string' == strtolower(gettype($item)) ? "'" . $item . "'" : $item;
                            return   $key . ':'. $item;
                        }else{
                            return is_numeric($item) ? $item : "'".$item."'";
                        }

                    }
                )->implode(', ');
            }

        }
        return $collection;
    }

    /**
     * [compileIndex description]
     * @param  [type] $query   [description]
     * @param  [type] $columns [description]
     * @return [type]          [description]
     */
    public function compileIndex($query, $columns)
    {
      $table = $this->wrapTable($query->from);
      $value = implode(", ",$columns);
      return "CREATE INDEX IF NOT EXISTS ON ". $table ."(".  $value .")";
    }

}
