<?php namespace Cubettech\Lacassa\Schema;

use Closure;
use Cubettech\Lacassa\Connection;

class Builder extends \Illuminate\Database\Schema\Builder
{
    /**
     * @inheritdoc
     */
    public function __construct(Connection $connection)
    {
//        die("asdad");
        $this->connection = $connection;
        $this->grammar = $connection->getSchemaGrammar();
    }


  
    /**
     * @inheritdoc
     */
    protected function createBlueprint($table, Closure $callback = null)
    {
        return new Blueprint($this->connection, $table);
    }


    public function getTables()
    {
//        dd($this);
        
        return $this->connection->getPostProcessor()->processTables(
            $this->connection->selectFromWriteConnection($this->grammar->compileTables('qr'))
        );
    }
}
