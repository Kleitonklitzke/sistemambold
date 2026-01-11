<?php
session_start();

// se já estiver logado, manda pro painel
if (isset($_SESSION['usuario'])) {
    header('Location: consolidado.php');
    exit;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Login - Painel</title>
  <style>
    body {
      background: #f0f2f5;
      font-family: Arial, sans-serif;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      margin: 0;
    }
    .box {
      background: #fff;
      padding: 20px 25px;
      border-radius: 10px;
      box-shadow: 0 2px 8px rgba(0,0,0,.1);
      width: 320px;
    }
    h2 { margin-top: 0; text-align: center; }
    label { display: block; margin-top: 10px; }
    input[type=text],
    input[type=password] {
      width: 100%;
      padding: 8px 10px;
      border: 1px solid #ccc;
      border-radius: 6px;
      margin-top: 4px;
      box-sizing: border-box;
    }
    button {
      margin-top: 15px;
      width: 100%;
      padding: 10px;
      border: none;
      background: #1565c0;
      color: #fff;
      border-radius: 6px;
      font-weight: bold;
      cursor: pointer;
    }
    .erro {
      background: #ffe0e0;
      color: #a00;
      padding: 6px 10px;
      border-radius: 6px;
      margin-bottom: 10px;
      font-size: .9rem;
    }
  </style>
</head>
<body>
  <div class="box">
    <h2>Painel MB</h2>
    <?php if (!empty($_GET['erro'])): ?>
      <div class="erro">Usuário ou senha inválidos.</div>
    <?php endif; ?>
    <form method="post" action="valida.php">
      <label>Usuário</label>
      <input type="text" name="usuario" required>

      <label>Senha</label>
      <input type="password" name="senha" required>

      <button type="submit">Entrar</button>
    </form>
  </div>
</body>
</html>
