<?php
session_start();

// lista simples de usuários
$usuarios = [
    'kleiton'   => '452376',
    'flaviano' => '452376',
	'claudemir' => '452376',
	'jaque' => '452376',
	'elizete' => '123456',
    // pode ir colocando mais
];

$user = $_POST['usuario'] ?? '';
$pass = $_POST['senha'] ?? '';

if (isset($usuarios[$user]) && $usuarios[$user] === $pass) {
    // deu certo
    $_SESSION['usuario'] = $user;
    header('Location: consolidado.php');
    exit;
} else {
    // falhou
    header('Location: login.php?erro=1');
    exit;
}
