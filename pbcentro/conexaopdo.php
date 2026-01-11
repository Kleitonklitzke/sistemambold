<?php
$mysql = "mysql:host=mbpimenta.ddns.net;dbname=Myouro";
$user   = "root";
$senha  = "lad013113z";
try {
    $con = new PDO($mysql, $user, $senha);
    $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo 'Não foi Possível conectar com o servidor de Pimenta </br>';// . $e->getMessage();
}
?>