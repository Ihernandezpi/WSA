<?php
	class Sincronizacion
	{
		var $jsonOut;
		var $jsonRespuesta;
		var $hoy;
		function Sincronizacion($json){
			$this->jsonOut = $json;
			$this->jsonRespuesta = array();
			$this->hoy  = date("Y-m-d H:i:s");
		}
		public function verificarTipo(){
			for ($a=0; $a < count($this->jsonOut) ; $a++) { 
			switch ($this->jsonOut[$a]["tipoAccion"]) {
					case 'actualizar':
						$this->verificarID($this->jsonOut[$a]["idActividades"],$this->jsonOut[$a]);
						break;
					case 'eliminar':
						$this->cambiarEstatus($this->jsonOut[$a]["idActividades"], $this->jsonOut[$a]["estado"]);	
						break;
					case 'dispositivo':
						$this->verificarFechaDispositivo($this->jsonOut[$a]);
						break;
					case 'inicio':
							$this->extraerInformacion($this->jsonOut[$a]["idProfesor"]);
						break;	
					default:
						# code...
						break;
				}
			}
		

		}

		public function verificarID($idActividad,$numeroArray){
			$connection = getConnection();
			$dbh = $connection->prepare("SELECT * FROM actividades WHERE idActividades = ?");
			$dbh->bindParam(1,$idActividad);
			$dbh->execute();
			$actividad = $dbh->fetchAll();
			$connection = null;
			if(count($actividad) != 0){
				//Actualizacion si existe el registro en la base de datos entonces
				//verificar la fecha del servidor con el paquete json recibido  
				$this->validarActualizacion($idActividad,$numeroArray["fechaCreacion"],$numeroArray);
			}
			else{
				//Insertar el registro son existe en la base de datos del servidor
				if($this->insertarActividad($idActividad,$numeroArray) == true){
					//$this->jsonRespuesta = array();
				

				}
				$arrayInsertar = array(	'tipoAccion' => '1');
				array_push($this->jsonRespuesta, $arrayInsertar);
			
				
			}
			
		}

		public function insertarActividad($idActividad,$numeroArray){
				$resultado = false;
				try{
					$connection = getConnection();
					$dbh = $connection->prepare("INSERT INTO `actividades`(`idActividades`,`idAsignacion`, `nombre`, `descripcion`, `fechaCreacion`, `fechaRealizacion`, `fechaActualizacion`, `estado`, `tipo`, `color`) VALUES (?,?,?,?,?,?,(select now()),?,?,?);");
					$dbh->bindParam(1,$numeroArray["idActividades"]);
					$dbh->bindParam(2,$numeroArray["idAsignacion"]);
					$dbh->bindParam(3,$numeroArray["nombre"]);
					$dbh->bindParam(4,$numeroArray["descripcion"]);
					$dbh->bindParam(5,$numeroArray["fechaCreacion"]);
					$dbh->bindParam(6,$numeroArray["fechaRealizacion"]);
//******************$dbh->bindParam(7,$numeroArray["fechaActualizacion"]);
					$dbh->bindParam(7,$numeroArray["estado"]);
					$dbh->bindParam(8,$numeroArray["tipo"]);
					$dbh->bindParam(9,$numeroArray["color"]);
					$dbh->execute();
					
					$connection = null;
					//Asignarlo al paquete de respuesta 
					$resultado = true;
				}	
				catch(PDOException $e){
					echo "Error al Insertar: ".$e->getMessage();
				}

				return $resultado;

		}
		
		public function validarActualizacion($idActividad,$fechaJson, $numeroArray){
			try{
				//Consulta de fechaModificacion del servidor
				$connection = getConnection();
				$dbh = $connection->prepare("SELECT fechaActualizacion FROM actividades WHERE idActividades = ?");
				$dbh->bindParam(1,$idActividad);
				$dbh->execute();
				$fechaServidor = $dbh->fetchAll();
				$connection = null;
				
				//Fecha recortada del paquete JSON
				$fechaDivididaJson = explode("-", $fechaJson);
				$anioJson = $fechaDivididaJson[0];
				$mesJson = $fechaDivididaJson[1];
				$diaJson = $fechaDivididaJson[2];
				
				//Fecha recortada del servidor
				$fechaDivididaServidor = explode("-", $fechaServidor[0]["fechaActualizacion"]);
				$anioServidor = $fechaDivididaServidor[0];
				$mesServidor = $fechaDivididaServidor[1];
				$diaServidor = $fechaDivididaServidor[2];

				//La fecha del paquete debe ser menor a la del servidor
			
				if($anioJson <= $anioServidor && $mesJson <= $mesServidor && (substr($diaJson, 0,-8) < substr($diaServidor,0,-8))){
					//No actualizar, obterner el nuevo registro y asignarlo al paquete de respuesta
					$this->obternerActividadFecha($idActividad,"error_fecha");
				}
				else{
					//Verificar el tiempo de intervalo que tienen
//********************$respuestaTiempo = $this->verificarTiempoIntervalo($fechaJson,$fechaServidor[0]["fechaActualizacion"]);
					$respuestaTiempo = false;
					if($respuestaTiempo != true){
						//Es correcto 
						$resultadoEstadoEliminacion = $this->cambioEstadoElimnar($idActividad);
						if($resultadoEstadoEliminacion == true){
							//El estado de eliminacion esta activo y se actualizan los datos
							$this->actualizarActividades($idActividad,$numeroArray,"Activo");
						}
						else{
							//El estado de eliminacion esta en papelera, entonces cambia el estado
							//y se actualizan los datos , 
							$this->actualizarActividades($idActividad,$numeroArray,"Activo");
						}

					}
					else{
						//Es cuando hay un conflicto y  agrega el reigistro en el paquete 
						$this->obternerActividadFecha($idActividad,"error_tiempo");
					}
					
				}
			
			}
			catch(PDOException $e){
				echo "Error: ".$e->getMessage();
			}
		}

		public function verificarTiempoIntervalo($fechaJson, $fechaServidor){
			//Tiempo JSON
			$tiempoJson = explode(" ", $fechaJson);
			$tiempoDivididoJson = explode(":", $tiempoJson[1]);
			$horaJson = $tiempoDivididoJson[0];
			$minutoJson = $tiempoDivididoJson[1];
			$segundoJson = $tiempoDivididoJson[2];
			//Tiempo del Servidor
			$tiempoServidor = explode(" ", $fechaServidor);
			$tiempoDivididoServidor = explode(":", $tiempoServidor[1]);
			$horaServidor = $tiempoDivididoServidor[0];
			$minutosServidor = $tiempoDivididoServidor[1];
			$segundoServidor = $tiempoDivididoServidor[2];

			$minutosDiferidos = 0;
			if($horaJson > $horaServidor){
				$minutosRestantes = (60 - $minutosServidor);
				$minutosDiferidos = $minutosRestantes + $minutoJson;
				
			}
			else{
				if($horaServidor > $horaJson && $horaJson > $horaServidor){
					$minutosRestantes = (60 - $minutoJson);
					$minutosDiferidos = $minutosRestantes + $minutosServidor;
						
				}
				else{
					if($horaJson == $horaServidor){
						if($minutosServidor < $minutoJson){
							$minutosDiferidos =  ((60 - $minutosServidor) - (60 - $minutoJson));

						}
						else{
							$minutosDiferidos = ((60 - $minutoJson) - (60 - $minutosServidor));

						}


					}
				}
			}

			if($minutosDiferidos < 0){
				$resultado = true;
			}
			else{
				$resultado = false;
			}

			return $resultado;
			
		}

		public function cambioEstadoElimnar($idActividad){
			try {
				$connection = getConnection();
				$dbh = $connection->prepare("SELECT estado FROM actividades WHERE idActividades = ?");
				$dbh->bindParam(1,$idActividad);
				$dbh->execute();
				$estado = $dbh->fetchAll();
				$connection = null;
				if($estado[0]["estado"] == "Activo"){
					$resultado = true;
				}
				else{
					$resultado = false;
				}

				return $resultado;
			}
			catch(PDOException $e){
				echo "Error: ".$e->getMessage();
			}
		}
		public function actualizarActividades($idActividad,$numeroArray,$estado){
			try{
				$connection = getConnection();
				$dbh = $connection->prepare("UPDATE `actividades` SET `idAsignacion`=?,`nombre`=?,`descripcion`=?,`fechaCreacion`=?,`fechaRealizacion`=?,`fechaActualizacion`=(select now()),`estado`=?,`tipo`=?,`color`=? WHERE idActividades = ?");
				$dbh->bindParam(1,$numeroArray["idAsignacion"]);
				$dbh->bindParam(2,$numeroArray["nombre"]);
				$dbh->bindParam(3,$numeroArray["descripcion"]);
				$dbh->bindParam(4,$numeroArray["fechaCreacion"]);
				$dbh->bindParam(5,$numeroArray["fechaRealizacion"]);
//**************$dbh->bindParam(6,$numeroArray["fechaActualizacion"]);
				$dbh->bindParam(6,$numeroArray["estado"]);
				$dbh->bindParam(7,$numeroArray["tipo"]);
				$dbh->bindParam(8,$numeroArray["color"]);
				$dbh->bindParam(9,$idActividad);
				$dbh->execute();
				$connection = null;
				/*
				$arrayActualizar = array(
									'tabla' => 'actividades',	
									'tipoAccion'=> 'actualizar',
									'accion' => '1',
									'idActividad' => $idActividad,
									'estado' => 'Activo',
									'fechaSincronizacion' => $this->hoy);
				array_push($this->jsonRespuesta, $arrayActualizar);
				*/

				$arrayActualizar = array('tipoAccion' => '1');
				array_push($this->jsonRespuesta, $arrayActualizar);
			}
			catch(PDOException $e){
				echo "Error: ".$e->getMessage();
			}
		}

		public function obternerActividadFecha($idActividad,$tipoError){
			try{
				$connection = getConnection();
				$dbh = $connection->prepare("SELECT * FROM actividades WHERE idActividades = ?");
				$dbh->bindParam(1,$idActividad);
				$dbh->execute();
				$actividad = $dbh->fetchAll();
				$connection = null;
				
				$arrayError = array(	'tabla' => 'actividades',
										'tipoAccion'=> $tipoError,
										'accion' => '0',
										'idActividades' => $actividad[0]["idActividades"],
										'idAsignacion' => $actividad[0]["idAsignacion"],
										'nombre' => $actividad[0]["nombre"],
										'descripcion' => $actividad[0]["descripcion"],
										'fechaCreacion' => $actividad[0]["fechaCreacion"],
										'fechaRealizacion' => $actividad[0]["fechaRealizacion"],
										'fechaActualizacion' => $actividad[0]["fechaActualizacion"],
										'estado' => $actividad[0]["estado"],
										'tipo' => $actividad[0]["tipo"],
										'color' => $actividad[0]["color"]);
				array_push($this->jsonRespuesta, $arrayError);
			}
			catch(PDOException $e){
				echo "Error: ".$e->getMessage();
			}
		}

		public function verificarFechaDispositivo($numeroArray){
			
			if($numeroArray["ultimaFecha"] == ""){
				echo "Fecha vacia (Traer toda la informacion del usuario)";
			}
			else{
				/*
					Metodo para obtener las actividades cuando el paquete no tiene
					informacion, pero tiene fecha.
					Entonces cuando tiene fecha extrae la informacion despues de la fecha
				*/
				$fechaj = explode(" ", $numeroArray["ultimaFecha"]);
				$arrayTiempoJson = explode(":", $fechaj[1]);
				$arrayUltimaFecha = $this->obtenerAcvidadesUltimaFecha($numeroArray["ultimaFecha"]);
				for ($x=0; $x < count($arrayUltimaFecha); $x++) { 
					$arrayUltima = array('tabla' => 'actividades',
											'tipoAccion'=> 'ultima_fecha',
												'idActividades' => $arrayUltimaFecha[$x]["idActividades"],
												'idAsignacion' => $arrayUltimaFecha[$x]["idAsignacion"],
												'nombre' => $arrayUltimaFecha[$x]["nombre"],
												'descripcion' => $arrayUltimaFecha[$x]["descripcion"],
												'fechaCreacion' => $arrayUltimaFecha[$x]["fechaCreacion"],
												'fechaRealizacion' => $arrayUltimaFecha[$x]["fechaRealizacion"],
												'fechaActualizacion' => $arrayUltimaFecha[$x]["fechaActualizacion"],
												'estado' => $arrayUltimaFecha[$x]["estado"],
												'tipo' => $arrayUltimaFecha[$x]["tipo"],
												'color' => $arrayUltimaFecha[$x]["color"]);
							array_push($this->jsonRespuesta, $arrayUltima);				
					//$fechaSer = explode(" ", $arrayUltimaFecha[$x]["fechaActualizacion"]);
					//$arrayTiempoServidor = explode(":", $fechaSer[1]);

					/*if($fechaSer[0] == $fechaj[0]){
						if(($arrayTiempoJson[0] <= $arrayTiempoServidor[0] && $arrayTiempoJson[1] <= $arrayTiempoServidor[1]) && $arrayTiempoJson[2] <= $arrayTiempoServidor[2]){
							$arrayUltima = array(	
												'tabla' => 'actividades',
												'tipoAccion'=> 'ultima_fecha',
												'idActividades' => $arrayUltimaFecha[$x]["idActividades"],
												'idAsignacion' => $arrayUltimaFecha[$x]["idAsignacion"],
												'nombre' => $arrayUltimaFecha[$x]["nombre"],
												'descripcion' => $arrayUltimaFecha[$x]["descripcion"],
												'fechaCreacion' => $arrayUltimaFecha[$x]["fechaCreacion"],
												'fechaRealizacion' => $arrayUltimaFecha[$x]["fechaRealizacion"],
												'fechaActualizacion' => $arrayUltimaFecha[$x]["fechaActualizacion"],
												'estado' => $arrayUltimaFecha[$x]["estado"],
												'tipo' => $arrayUltimaFecha[$x]["tipo"],
												'color' => $arrayUltimaFecha[$x]["color"],
												'fechaSincronizacion' => $this->hoy);
							array_push($this->jsonRespuesta, $arrayUltima);				
						}
					}
					else{
						$arrayMayorFecha = array(
												'tabla' => 'actividades',	
												'tipoAccion'=> 'ultima_fecha',
												'idActividades' => $arrayUltimaFecha[$x]["idActividades"],
												'idAsignacion' => $arrayUltimaFecha[$x]["idAsignacion"],
												'nombre' => $arrayUltimaFecha[$x]["nombre"],
												'descripcion' => $arrayUltimaFecha[$x]["descripcion"],
												'fechaCreacion' => $arrayUltimaFecha[$x]["fechaCreacion"],
												'fechaRealizacion' => $arrayUltimaFecha[$x]["fechaRealizacion"],
												'fechaActualizacion' => $arrayUltimaFecha[$x]["fechaActualizacion"],
												'estado' => $arrayUltimaFecha[$x]["estado"],
												'tipo' => $arrayUltimaFecha[$x]["tipo"],
												'color' => $arrayUltimaFecha[$x]["color"],
												'fechaSincronizacion' => $this->hoy);
						array_push($this->jsonRespuesta, $arrayMayorFecha);	
					}*/
				}
			}
		}

		
		public function obtenerAcvidadesUltimaFecha($ultimaFecha){
			try{
				$connection = getConnection();
				$dbh = $connection->prepare("SELECT * FROM `actividades` WHERE CAST(`fechaActualizacion` AS datetime) > CAST( ? AS datetime);");
				$dbh->bindParam(1,$ultimaFecha);
				$dbh->execute();
				$actividad = $dbh->fetchAll();
				$connection = null;
				return $actividad;
			}
			catch(PDOException $e){
				echo "Error: ".$e->getMessage();
			}
		}


		public function extraerInformacion($idProfesor){
			$asignacion = $this->extraerInformacionAsignacion($idProfesor);
			for ($a=0; $a < count($asignacion) ; $a++) { 
				$this->extraerInformacionMateria($asignacion[$a]["idMateria"]);
				$this->extraerInformacionGrupo($asignacion[$a]["idGrupo"]);
				$this->extraerInformacionActividad($asignacion[$a]["idAsignacion"]);

				//echo $asignacion[$a]["idProfesor"]."\n";

			}
			$contadorMateria = 0;
			for ($b=0; $b < count($this->jsonRespuesta); $b++) {
				if($this->jsonRespuesta[$b]["tabla"] == "materia"){
					$arrayNombre[$contadorMateria] = $this->jsonRespuesta[$b];
					$contadorMateria++;
				}
			}

			$contadorGrupo = count($arrayNombre);
			for ($b=0; $b < count($this->jsonRespuesta); $b++) {
				if($this->jsonRespuesta[$b]["tabla"] == "grupo"){
					
					$arrayNombre[$contadorGrupo] = $this->jsonRespuesta[$b];
					$contadorGrupo++;
				}
			}

			
			$contadorAsignacion = count($arrayNombre);
			for ($b=0; $b < count($this->jsonRespuesta); $b++) {
				if($this->jsonRespuesta[$b]["tabla"] == "asignacion"){
					
					$arrayNombre[$contadorAsignacion] = $this->jsonRespuesta[$b];
					$contadorAsignacion++;
				}
			}
			
			$contadorActividades = count($arrayNombre);
			for ($b=0; $b < count($this->jsonRespuesta); $b++) {
				if($this->jsonRespuesta[$b]["tabla"] == "actividades"){
					
					$arrayNombre[$contadorActividades] = $this->jsonRespuesta[$b];
					$contadorActividades++;
				}
			}
			$this->jsonRespuesta = null;
			$this->jsonRespuesta = $arrayNombre;

		}

		public function extraerInformacionAsignacion($idProfesor){
			try{

				$connection = getConnection();
				$dbh = $connection->prepare("SELECT * FROM asignacion WHERE idProfesor = ?");
				$dbh->bindParam(1,$idProfesor);
				$dbh->execute();
				$asignacion = $dbh->fetchAll();
				$connection = null;

				for ($a=0; $a < count($asignacion); $a++) { 
						$arrayAsignacion = array(	'tabla'=> 'asignacion',
											'idAsignacion' => $asignacion[$a]["idAsignacion"],
											'idProfesor' => $asignacion[$a]["idProfesor"],
											'idGrupo' => $asignacion[$a]["idGrupo"],
											'idMateria' => $asignacion[$a]["idMateria"]);
											
						array_push($this->jsonRespuesta, $arrayAsignacion);				
				}

				return $asignacion;
				
			}
			catch(PDOException $e){
				echo "Error: ".$e->getMessage();
			}
		} 


		 


		public function extraerInformacionActividad($idAsignacion){
			try{
				$connection = getConnection();
				$dbh = $connection->prepare("SELECT * FROM actividades WHERE idAsignacion = ?");
				$dbh->bindParam(1,$idAsignacion);
				$dbh->execute();
				$actividad = $dbh->fetchAll();
				$connection = null;

				
				for ($a=0; $a < count($actividad); $a++) { 
						$arrayActividades = array(	'tabla'=> 'actividades',
											'idActividades' => $actividad[$a]["idActividades"],
											'idAsignacion' => $actividad[$a]["idAsignacion"],
											'nombre' => $actividad[$a]["nombre"],
											'descripcion' => $actividad[$a]["descripcion"],
											'fechaCreacion' => $actividad[$a]["fechaCreacion"],
											'fechaRealizacion' => $actividad[$a]["fechaRealizacion"],
											'fechaActualizacion' => $actividad[$a]["fechaActualizacion"],
											'estado' => $actividad[$a]["estado"],
											'tipo' => $actividad[$a]["tipo"],
											'color' => $actividad[$a]["color"]);
											
						array_push($this->jsonRespuesta, $arrayActividades);					
				}
			}
			catch(PDOException $e){
				echo "Error: ".$e->getMessage();
			}
		}




		public function extraerInformacionGrupo($idGrupo){
			try{
				$connection = getConnection();
				$dbh = $connection->prepare("SELECT * FROM grupo WHERE idGrupo = ?");
				$dbh->bindParam(1,$idGrupo);
				$dbh->execute();
				$grupo = $dbh->fetchAll();
				$connection = null;
				for ($a=0; $a < count($grupo); $a++) { 
						$arrayGrupo = array(	'tabla'=> 'grupo',
											'idGrupo' => $grupo[$a]["idGrupo"],
											'grupo' => $grupo[$a]["grupo"],
											'grado' => $grupo[$a]["grado"]);
											
						array_push($this->jsonRespuesta, $arrayGrupo);
				}
			}
			catch(PDOException $e){
				echo "Error: ".$e->getMessage();
			}
		}

		public function extraerInformacionMateria($idMateria){
			try{
				$connection = getConnection();
				$dbh = $connection->prepare("SELECT * FROM materia WHERE idMateria = ?");
				$dbh->bindParam(1,$idMateria);
				$dbh->execute();
				$materia = $dbh->fetchAll();
				$connection = null;

				for ($a=0; $a < count($materia); $a++) { 
						$arrayMateria = array(	'tabla'=> 'materia',
											'idMateria' => $materia[$a]["idMateria"],
											'materia' => $materia[$a]["materia"]);
											
						array_push($this->jsonRespuesta, $arrayMateria);	
				}
				
				
			}
			catch(PDOException $e){
				echo "Error: ".$e->getMessage();
			}
		}

		public function cambiarEstatus($idActividades,$estado){
			try{
				$connection = getConnection();
				$dbh = $connection->prepare("UPDATE actividades SET estado = ? WHERE idActividades = ?");
				$dbh->bindParam(1, $estado);
				$dbh->bindParam(2, $idActividades);
				$dbh->execute();
				$connection = null;

				$arrayEliminacion = array(	'tabla'=> 'actividades',
											'tipoAccion' => 'cambio_estado',
											'accion' => "1",
											'idActividad' => $idActividades);
											
				array_push($this->jsonRespuesta, $arrayEliminacion);	
			}
			catch(PDOException $e){
				echo "Error: ".$e->getMessage();
			}


		}

		public function retornoJson(){
			$connection = getConnection();
			$dbh = $connection->prepare("select now() as fecha;");
			$dbh->execute();
			$fecha = $dbh->fetchAll();
			$connection=null;
			$jsonTiempo = array('fecha' => $fecha[0]["fecha"],'tipoAccion'=>'fechaActualizacion'); 
			array_push($this->jsonRespuesta, $jsonTiempo);
			echo json_encode($this->jsonRespuesta);
		}	


	}
?>