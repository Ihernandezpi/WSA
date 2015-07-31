<?php
$app->post("/inicio", function() use($app) {
try{
		$usuario = $app->request->post('usuario');
		$contra = $app->request->post('contra');
		$connection = getConnection();
		$dbh = $connection->prepare("SELECT u.idUsuario, u.idPersona, p.nombre, p.apellidoP, p.apellidoM, p.fechaN, pr.idProfesor, pr.noTrabajador FROM usuarios AS u INNER JOIN persona AS p ON ( p.idPersona = u.idPersona ) INNER JOIN profesor AS pr ON ( pr.idPersona = p.idPersona ) WHERE u.usuario = '" . $usuario . "' and u.contra='" . $contra . "' and u.estado='Activo'");
		$dbh->execute();
		$personas = $dbh->fetchAll();		
		$connection = null;
		$app->response->headers->set("Content-type", "application/json");
		$app->response->status(200);
		$app->response->body(json_encode($personas));	
	}
	catch(PDOException $e)
	{
		echo "Error: " . $e->getMessage();
	}
});
?>