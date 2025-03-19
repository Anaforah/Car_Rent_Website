<?php
//---------------------------------------COMEÇAR SESSÃO---------------------------------------
session_start();


$str = "dbname=anasofiaalmeida user=anasofiaalmeida password= host=localhost port=5433";
$conn = pg_connect($str);

if (!$conn) {
    die("Erro de conexão com o banco de dados.");
}

// Recupera dados da sessão
$nome = $_SESSION['nome'] ?? '';
$saldo = $_SESSION['saldo'] ?? '';
$email_administrador = $_SESSION['email'] ?? '';

//------------------------------IMPEDIR ENTRADA DE NÃO ADMINISTRADORES------------------------------
$queryEntrada = "SELECT email FROM administrador WHERE email = $1";
pg_prepare($conn, "verificar_entrada", $queryEntrada);                         //Preparar dados
$resultEntrada = pg_execute($conn, "verificar_entrada", array($email_administrador));

if(!$resultEntrada ||  pg_num_rows($resultEntrada) === 0 || !isset($_SESSION['email'])){
    header('HTTP/1.0 403 Forbidden');
    header("Location: index.php");
    exit;
}

//---------------------------------------FORM ADD CARRO---------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Captura os dados do formulário
    $nome_veiculo = $_POST['Nome_veiculo'];
    $marca = $_POST['Marca'];
    $carga = $_POST['Carga'];
    $combustivel = $_POST['Combustivel_veiculo'];
    $matricula = $_POST['matricula'];
    $preco_inicial = $_POST['Preco_inicial'];

    // Verifica e processa o upload da imagem
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
        $imagemBlob = pg_escape_bytea($conn, file_get_contents($_FILES['imagem']['tmp_name'])); // Prepara o binário para o PostgreSQL
    } else {
        $imagemBlob = NULL;
    }

    // Query para inserir o veículo no banco
    $queryCarro = "INSERT INTO carro (matricula, nome, marca, carga, combustivel, imagem, email)
    VALUES ($1, $2, $3, $4, $5, $6, $7)";

    $resultCarro = pg_query_params($conn, $queryCarro, array(
        $matricula,
        $nome_veiculo,
        $marca,
        $carga,
        $combustivel,
        $imagemBlob,
        $email_administrador
    ));

    // Verificar se a inserção no carro ocorreu sem erros
    if (!$resultCarro) {
        $error = pg_last_error($conn);
        echo "<p>Erro ao inserir no carro: " . htmlspecialchars($error) . "</p>";
    }

    // Query para inserir no histórico de preços
    $queryPreco = "INSERT INTO historico_preco (custo_diario, datainicio, datafim, matricula, email)
               VALUES ($1, $2, $3, $4, $5)";

    $datainicio = date('Y-m-d');
    $resultPreco = pg_query_params($conn, $queryPreco, array(
        $preco_inicial,
        $datainicio,
        null,
        $matricula,
        $email_administrador
    ));

    // Verificar se a inserção no histórico de preços ocorreu sem erros
    if (!$resultPreco) {
        $error = pg_last_error($conn);
        echo "<p>Erro ao inserir no histórico de preços: " . htmlspecialchars($error) . "</p>";
    }

    // Inserir automaticamente a visibilidade como TRUE
    $queryVisivel = "INSERT INTO historico_visibilidade (datainicio, datafim, visivel, matricula, email)
               VALUES ($1, $2, $3, $4, $5)";

    $datainicioVIS = date('Y-m-d');
    $resultVisivel = pg_query_params($conn, $queryVisivel, array(
        $datainicioVIS,
        null,  // Data de fim é nula inicialmente
        true,  // Usando true diretamente para o tipo booleano (fica automaticamente visivel)
        $matricula,
        $email_administrador
    ));

    // Verificar se a inserção na visibilidade ocorreu sem erros
    if (!$resultVisivel) {
        $error = pg_last_error($conn);
        echo "<p>Erro ao inserir no histórico de visibilidade: " . htmlspecialchars($error) . "</p>";
    } else {
        echo "<p>Registro de visibilidade inserido com sucesso!</p>";
    }

    // redirecionar
    if ($resultCarro && $resultPreco && $resultVisivel) {
        header("Location: HomePageADMIN.php");
        exit();
    } else {
        echo "<script>alert('Erro ao inserir o veículo no banco de dados');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>ADMIN</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="css/geralcss.css">
    <link rel="stylesheet" type="text/css" href="css/HomePage.css">
    <link rel="stylesheet" type="text/css" href="css/AddCarrocss.css">
</head>
<body>
<nav class="Nav">
    <div class = "Nav-Container">
        <h1 class="Nav_Title">Bem vindo, <?php echo htmlspecialchars($nome); ?></h1> <!-- Exibe o nome do usuário -->
        <div class="Nav-Buttons">
            <a href="HomePageADMIN.php">
                <img src="Images/home2.svg" width="41" alt="Home" />
            </a>
            <a href="Estatisticas.php">
            <img src="Images/clipboard-list.svg" width="41" alt="Lista icone" />
            </a>
            <a href="logout.php">
                <img src="Images/logout.svg" width="41" alt="Sair">
            </a>
        </div>
    </div>
</nav>
<form action="" method="post" enctype="multipart/form-data">
    <div style="margin-left: 5%">
    <a href="HomePageADMIN.php">
        <img src="Images/arrow-left.svg" width="41" alt="Home" />
    </a>
    </div>
    <div class="Inline_Contentor">
    <div class="Image_Contentor">
    <label for="imagem"></label>
    <input class="Upload_Image" type="file" id="imagem" name="imagem" accept="image/*" placeholder="Upload image">
    </div>
    <div class="FormLabel">
    <label for="Nome_veiculo"></label>
    <input type="text" id="Nome_veiculo" name="Nome_veiculo" placeholder="Nome do veículo" required>

    <label for="Preco_inicial"></label>
    <input type="number" id="Preco_inicial" name="Preco_inicial" placeholder="Preço inicial por dia" required>

    <label for="matricula"></label>
    <input type="text" id="matricula" name="matricula" placeholder="Matrícula" required>

    <label for="Combustivel_veiculo"></label>
    <input type="text" id="Combustivel_veiculo" name="Combustivel_veiculo" placeholder="Combustível">

    <label for="Carga"></label>
    <input type="number" id="Carga" name="Carga" placeholder="Carga">

    <label for="Marca"></label>
    <input type="text" id="Marca" name="Marca" placeholder="Marca" required>

    </div>
    </div>
    <div class="button_container">
        <button class="Botao" type="submit">Criar Carro</button>
    </div>
</form>
</body>
</html>
