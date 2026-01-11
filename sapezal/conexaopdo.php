<?php
$mysql = "mysql:host=45.187.75.236;dbname=Myouro";
$user   = "root";
$senha  = "lad013109a";
try {
    $con = new PDO($mysql, $user, $senha);
    $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo 'Não foi Possível conectar com o servidor de Sapezal </br>';// . $e->getMessage();
}
?>