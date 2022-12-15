<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(\L5Swagger\L5SwaggerServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
        Response::macro('send', function ($message, $code = 200, $data = [], $errors = []) {

            $response = [
              'statusCode' => $code,
              'message'=>$message,
              'data' => $data,
            ];
            return Response::json($response,$code);
        });
        Paginator::useBootstrap();
    }
}
