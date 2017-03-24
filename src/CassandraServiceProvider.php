<?php

namespace Cubettech\Lacassa;

use Illuminate\Support\ServiceProvider;
use Cassandra;

class CassandraServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        require __DIR__ . '/../vendor/autoload.php';
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        // Add database driver.
        $this->app->resolving('db', function ($db) {
            $db->extend('Cassandra', function ($config, $name) {
                $config['name'] = $name;
                return new Connection($config);
            });
        });

        // Add connector for queue support.
        // $this->app->resolving('queue', function ($queue) {
        //     $queue->addConnector('mongodb', function () {
        //         return new MongoConnector($this->app['db']);
        //     });
        // });
    }
}
