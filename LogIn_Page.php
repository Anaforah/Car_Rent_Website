<?php
//---------------------------------------COMEÇAR SESSÃO---------------------------------------
session_start();

// Dados de conexão com o banco de dados
$str = "dbname=anasofiaalmeida user=anasofiaalmeida password= host=localhost port=5433";
$conn = pg_connect($str);

if (!$conn) {
    echo "<script>
        alert('Erro de conexão com o banco de dados.');
        window.location.href = 'LogIn_Page.php';
    </script>";
    exit();
}

//---------------------------------------RECEBER DADOS FORM---------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Usar metodo POST por sensibilidade dos dados
    if (isset($_POST['email'], $_POST['password'], $_POST['userType'], $_POST['name'])) {
        // Validar campos vazios
        if (empty($_POST['email'])) {
            echo "<script>
                alert('Preencha seu e-mail.');
                window.location.href = 'LogIn_Page.php';
            </script>";
            exit();
        }

        if (empty($_POST['password'])) {
            echo "<script>
                alert('Preencha sua senha.');
                window.location.href = 'LogIn_Page.php';
            </script>";
            exit();
        }

        // Escapar e receber dados do formulário
        $email = pg_escape_string($conn, $_POST['email']);
        $password = pg_escape_string($conn, $_POST['password']);
        $name = pg_escape_string($conn, $_POST['name']);
        $userType = $_POST['userType'];

        try {
            // Verificar se o usuário existe no banco
            $queryPessoa = "SELECT nome, email FROM pessoa WHERE nome = $1 AND email = $2 AND password = $3";
            $resultPessoa = pg_query_params($conn, $queryPessoa, array($name, $email, $password));

            if (!$resultPessoa) {
                throw new Exception(pg_last_error($conn)); // Levantar exceção em caso de erro na query
            }

            $quantidade = pg_num_rows($resultPessoa);

            if ($quantidade == 1) { // Login bem-sucedido
                $usuario = pg_fetch_assoc($resultPessoa);

                // Configurar sessão
                $_SESSION['nome'] = $usuario['nome'];
                $_SESSION['email'] = $usuario['email'];

                if ($userType === 'Cliente') {
                    header("Location: HomePageUSER.php");
                    exit();
                } else {
                    header("Location: HomePageADMIN.php");
                    exit();
                }
            } else {
                // Usuário não encontrado
                echo "<script>
                    alert('Falha ao logar! Email ou senha incorretos.');
                    window.location.href = 'LogIn_Page.php';
                </script>";
                exit();
            }
        } catch (Exception $e) {
            // Exibir mensagem de erro no alerta e redirecionar
            $errorMessage = htmlspecialchars($e->getMessage()); // Escapar mensagem de erro para segurança
            echo "<script>
                alert('Erro: $errorMessage');
                window.location.href = 'LogIn_Page.php';
            </script>";
            exit();
        }
    }
}

pg_close($conn);
?>


<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <title>Car website</title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link rel="stylesheet"  href="css/indexcss.css">
    <link rel="stylesheet" href="css/geralcss.css">
    <link rel="stylesheet"  href="css/indexbutton.css">
</head>
<body>
<div class="Principal_Container">
<section>
    <form class="Formulario" action="" method="POST">
        <h1 class="Title">Log In</h1>

        <div class="form-box">
            <div class="button-box">
                <div id="btn"></div>
                <button type="button" class="toggle-btn1" onclick="leftClick()">Cliente</button>
                <button type="button" class="toggle-btn2" onclick="rightClick()">Administrador</button>
            </div>
        </div>

        <div class="FormLabel">
            <label for="name"></label><br>
            <input type="text" id="name" name="name" placeholder="Nome:" required><br><br>

            <label for="email"></label><br>
            <input type="email" id="email" name="email" placeholder="Email:" required><br><br>

            <label for="password"></label><br>
            <input type="password" id="password" name="password" placeholder="Password:" required><br><br>
        </div>

        <!-- Campo oculto para armazenar o tipo de usuário -->
        <input type="hidden" id="userType" name="userType" value="Cliente">

        <div class="link_container">
            <a href="index.php" class="link">Ainda não tens conta? Sign up</a>

        </div>
        <br>
        <button type="submit" class="Botao">Log In</button>
    </form>
</section>
</div>
<script src="js/index_button.js"></script>
</body>
</html>