<?php

use Illuminate\Routing\Router;

$router->post('/workshop/modules/{module}/publish', ['as' => 'api.workshop.module.publish', 'uses' => 'ModulesController@publishAssets']);
