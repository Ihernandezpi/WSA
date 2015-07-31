<?php
	function getConnection(){
		try{
			$db_username = "root";
			$db_password = "nachitohevatix";
			$db_connection = new PDO("mysql:host=127.0.0.1;dbname=pineahat_actividadeslight;charset=UTF8",$db_username,$db_password);
			$db_connection->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
		}
		catch(PDOException $e){
			echo "Error; ".$e->getMessage();
		}
		return $db_connection;
	}
?>

