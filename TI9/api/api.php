<?php
	
	$app->post("/actividades",function() use($app){
		$jsonIn = $app->request->post("jsonIn");
		$jsonOut = array();
		$jsonOut = json_decode($jsonIn,true);
		
		//var_dump($jsonOut);

		$s = new Sincronizacion($jsonOut);
		$s->verificarTipo();
		$s->retornoJson();
	

	});
?>