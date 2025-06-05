<?php
// public/index.php

/**
 * Front controller : on inclut simplement le routeur et on le lance.
 */
require_once  'app/Routes/Router.php';

$router = new Router();
$router->handleRequest();
