<?php
  require 'Slim/Slim.php';
    \Slim\Slim::registerAutoloader();
    $app = new \Slim\Slim();
    define ("SPECIALCONSTANT",true);
    require 'utilerias/conexion.php';
    require 'api/api.php';
    require 'api/sincronizacion.php';
    require 'app/routes/admin.php';
    $app->run();
?>