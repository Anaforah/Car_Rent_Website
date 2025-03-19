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
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Usar método POST por sensibilidade dos dados
    if (isset($_POST['email'], $_POST['password'], $_POST['name'])) {
        if (empty($_POST['email'])) {
            echo "<script>
                alert('Preencha seu e-mail.');
                window.location.href = 'index.php';
            </script>";
            exit();
        }

        if (empty($_POST['password'])) {
            echo "<script>
                alert('Preencha sua senha.');
                window.location.href = 'index.php';
            </script>";
            exit();
        }

        $email = pg_escape_string($conn, $_POST['email']);
        $password = pg_escape_string($conn, $_POST['password']);
        $nome = pg_escape_string($conn, $_POST['name']);

        try {
            // Inserir dados na tabela 'pessoa'
            $queryPessoa = "INSERT INTO pessoa (email, nome, password) VALUES ($1, $2, $3)";
            pg_prepare($conn, "insert_pessoa", $queryPessoa); // Preparar statement para 'pessoa'
            $resultPessoa = pg_execute($conn, "insert_pessoa", array($email, $nome, $password));

            if (!$resultPessoa) {
                throw new Exception(pg_last_error($conn));
            }

            // Saldo aleatório entre 1000 e 2000
            $saldoAleatorio = rand(1000, 2000);

            // Inserir dados na tabela 'cliente'
            $queryCliente = "INSERT INTO cliente (email, saldo) VALUES ($1, $2)";
            $uniqueClientStmtName = uniqid("insert_cliente_"); // Nome único para o statement
            pg_prepare($conn, $uniqueClientStmtName, $queryCliente); // Preparar statement para 'cliente'
            $resultCliente = pg_execute($conn, $uniqueClientStmtName, array($email, $saldoAleatorio)); // Executar com nome único

            if (!$resultCliente) {
                throw new Exception(pg_last_error($conn));
            }

            // Configurar sessão
            $_SESSION['nome'] = $nome;
            $_SESSION['email'] = $email;

            // Redirecionar para a página do usuário
            header("Location: HomePageUSER.php");
            exit();
        } catch (Exception $e) {
            $errorMessage = pg_last_error($conn);

            // Verificar se o erro é de duplicação de chave única
            if (strpos($errorMessage, "duplicate key value") !== false) {
                echo "<script>
                    alert('Erro: O e-mail informado já está cadastrado.');
                    window.location.href = 'index.php';
                </script>";
                exit();
            } else {
                // Outros erros
                echo "<script>
                     alert('Erro ao cadastrar usuário: " . htmlspecialchars($errorMessage) . "'); 
                    window.location.href = 'index.php';
                </script>";
                exit();
            }
        }
    }
}
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

            <link rel="stylesheet" type="text/css" href="css/indexcss.css">
            <link rel="stylesheet" type="text/css" href="css/geralcss.css">
            <link rel="stylesheet" type="text/css" href="css/indexbutton.css">
    </head>
    <body>
    <div class="Principal_Container">
        <section>
            <form class="Formulario" action="" method="POST">
                <h1 class="Title">Sign up</h1>

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
                <a href="LogIn_Page.php" class="link">Já tens uma conta? Log in</a>

            </div>
                <br>
                <button type="submit" class="Botao">Sign up</button>
            </form>
        </section>
    </div>
    </body>
</html>