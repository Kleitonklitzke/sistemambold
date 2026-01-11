<?php
$mysql = "mysql:host=177.222.211.245;dbname=Myouro";
$user   = "root";
$senha  = "jab012257g";
try {
    $con = new PDO($mysql, $user, $senha);
    $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo 'Não foi Possível conectar com o servidor de Alvorada </br>';// . $e->getMessage();
}
?>