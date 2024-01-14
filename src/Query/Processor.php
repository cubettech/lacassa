<?php namespace Cubettech\Lacassa\Query;

use Illuminate\Database\Query\Processors\Processor as BaseProcessor;
use Illuminate\Database\Query\Builder;

class Processor extends BaseProcessor
{

    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {

//        dd($query);
//        dd($sql);
//        dd($values);
////        dd($query->getConnection()->);
//

//        dd(debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
//        dd($sequence);
//        dd($sequence);
        $query->getConnection()->insert($sql, $values);
        return null;


        $id = $query->getConnection()->lastInsertId($sequence);
//        dd($id);

//        $id = "44";
        return is_numeric($id) ? (int) $id : $id;

    }

    /**
     * Process the results of a tables query.
     *
     * @param  array  $results
     * @return array
     */
    public function processTables($results)
    {
//        dd($results);

        return [];


//        return array_map(function ($result) {
//            $result = (object) $result;
//
//            return [
//                'name' => $result->name,
//                'schema' => $result->schema ?? null, // PostgreSQL and SQL Server
//                'size' => isset($result->size) ? (int) $result->size : null,
//                'comment' => $result->comment ?? null, // MySQL and PostgreSQL
//                'collation' => $result->collation ?? null, // MySQL only
//                'engine' => $result->engine ?? null, // MySQL only
//            ];
//        }, $results);
    }

}
