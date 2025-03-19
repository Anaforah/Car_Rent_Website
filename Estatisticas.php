<?php
//---------------------------------------COMEÇAR SESSÃO---------------------------------------
session_start();

$str = "dbname=anasofiaalmeida user=anasofiaalmeida password= host=localhost port=5433";
$conn = pg_connect($str);

if (!$conn) {
    die("Erro de conexão com o banco de dados.");
}
$nome = $_SESSION['nome'] ?? '';                                    // Ir buscar os valores da sessão e Verifica se o nome existe
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

//---------------------------------------CONTAR CARROS---------------------------------------
$queryContarCarros = "SELECT COUNT(*) AS total FROM carro WHERE email = $1";
pg_prepare($conn, "count_carros_query", $queryContarCarros);

// Executar a consulta com o parâmetro
$resultContarCarros = pg_execute($conn, "count_carros_query", [$email_administrador]);

if ($resultContarCarros) {
    $linha = pg_fetch_assoc($resultContarCarros);                   //armazenar e acessar dados em um formato chave-valor (chave é o nome da coluna e o valor é o valor da coluna)
    $numeroCarros = $linha['total'];
} else {
    $numeroCarros = 0;                                              // Se houver erro, mostra 0
}

//---------------------------------------CONTAR CLIENTES---------------------------------------
//DISTINCT retorna só 1 valor sem repetições
// JOIN traz as informações dos carros reservados juntamente com os dados das reservas

$queryContarClientes = "SELECT COUNT(DISTINCT pr.email) AS total 
FROM pedido_reserva pr
JOIN carro c ON pr.matricula = c.matricula                          
WHERE c.email = $1;";
pg_prepare($conn, "count_clients_query", $queryContarClientes);

$resultContarClientes = pg_execute($conn, "count_clients_query", [$email_administrador]);

if ($resultContarClientes) {
    $linhaClientes = pg_fetch_assoc($resultContarClientes);         //armazenar e acessar dados em um formato chave-valor (chave é o nome da coluna e o valor é o valor da coluna)
    $numeroClientes = $linhaClientes['total'];
} else {
    $numeroClientes = 0;
}

//---------------------------------------MÉDIA DE PREÇOS---------------------------------------
//AVG serve para a média do preço
//Conta apenas os preços ativos (datafim NULL)
$queryMediaPreco = "SELECT AVG(custo_diario) FROM historico_preco WHERE email = $1 AND datafim IS NULL";
pg_prepare($conn, "Media_Preco_query", $queryMediaPreco);

// Executar a consulta com o parâmetro
$resultMediaPreco = pg_execute($conn, "Media_Preco_query", [$email_administrador]);

$mediaPreco = 0;
if ($resultMediaPreco) {
    $mediaPreco = pg_fetch_result($resultMediaPreco, 0, 0);                                                     // Extrai o valor da média
    $mediaPrecoFormatada = number_format((float)$mediaPreco, 1, ',', '');       //impedir valores muito decimais, inves disso arredonda apenas para 0,0
}

//---------------------------------------MÉDIA DE RECEITAS---------------------------------------
//SUM soma todos os valores da tabela
// A tabela carro é unida à tabela pedido_reserva
//A tabela historico_preco é unida à tabela carro
$queryReceita = "SELECT SUM(hp.custo_diario) AS total_custo_diario
FROM pedido_reserva pr
JOIN carro c ON pr.matricula = c.matricula
JOIN historico_preco hp ON hp.matricula = c.matricula
WHERE c.email = $1;";
pg_prepare($conn, "Receita_query", $queryReceita);

// Executar a consulta com o parâmetro
$resultReceita = pg_execute($conn, "Receita_query", [$email_administrador]);

if ($resultReceita) {
    $mediaReceita = pg_fetch_result($resultReceita, 0, 0);
}

//---------------------------------------MÉDIA DE RESERVAS POR CLIENTE---------------------------------------

//seleciona o email da tabela pedido reserva e conta todas as reservas
//GROUP BY pr.email: Agrupa os resultados por email, ou seja, para cada cliente
$queryReceitaCliente = "SELECT AVG(reservas_por_cliente.total_reservas) AS media_reservas
FROM (
    SELECT pr.email, COUNT(*) AS total_reservas
    FROM pedido_reserva pr
    JOIN carro c ON pr.matricula = c.matricula
    WHERE c.email = $1
    GROUP BY pr.email
) AS reservas_por_cliente;";
pg_prepare($conn, "ReceitaCliente_query", $queryReceitaCliente);

// Executar a consulta com o parâmetro
$resultReceitaCliente = pg_execute($conn, "ReceitaCliente_query", [$email_administrador]);

if ($resultReceitaCliente) {

    $mediaReceitaCliente = pg_fetch_result($resultReceitaCliente, 0, 0);
    $mediaReceitaClienteFormatada = number_format((float)$mediaReceitaCliente, 1, ',', '');
}

//---------------------------------------MÉDIA DE DIAS POR RESERVA---------------------------------------
$queryMediaDias = "SELECT 
    AVG(datafim - datainicio) AS media_dias
FROM 
    pedido_reserva pr
JOIN 
    carro c ON pr.matricula = c.matricula
WHERE 
    c.email = $1;";
pg_prepare($conn, "MediaDias_query", $queryMediaDias);

// Executar a consulta com o parâmetro
$resultMediaDias = pg_execute($conn, "MediaDias_query", [$email_administrador]);

if ($resultMediaDias) {

    $mediaDias = pg_fetch_result($resultMediaDias, 0, 0);
    $mediaDiasFormatada = number_format((float)$mediaReceitaCliente, 1, ',', '');
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Estatísticas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/geralcss.css">
    <link rel="stylesheet" href="css/HomePage.css">
    <link rel="stylesheet" href="css/estatisticas.css">


</head>
<body>
<nav class="Nav">
    <div class = "Nav-Container">
        <h1 class="Nav_Title">Bem vindo, <?php echo htmlspecialchars($nome); ?></h1> <!-- Exibe o nome do usuário -->
        <div class="Nav-Buttons">
            <a href="HomePageADMIN.php">
            <img src="Images/home2.svg" width="41" alt="Home" />
            </a>
                <img src="Images/clipboard-list.svg" width="41" alt="Lista icone">
            <a href="logout.php">
                <img src="Images/logout.svg" width="41" alt="Sair">
            </a>
        </div>
    </div>
</nav>
<main>
    <div class="estatisticas_container">
        <div class="alignleft">
        <h3 class="h3container" id="id1">Carros: <strong><?php echo htmlspecialchars($numeroCarros); ?></strong></h3>
        <h3 class="h3container" id="id2">Média de Preços: <strong><?php echo htmlspecialchars($mediaPrecoFormatada); ?></strong></h3>
        <h3 class="h3container" id="id6">Média dos dias de renda: <strong><?php echo htmlspecialchars($mediaDiasFormatada); ?></strong></h3>
        </div>
        <div class="alignright">
        <h3 class="h3container" id="id3">Clientes: <strong><?php echo htmlspecialchars($numeroClientes); ?></strong></h3>
        <h3 class="h3container" id="id4">Receita Total: <strong><?php echo htmlspecialchars($mediaReceita); ?></strong></h3>
        <h3 class="h3container" id="id5">Média de Reservas por Cliente: <strong><?php echo htmlspecialchars($mediaReceitaClienteFormatada); ?></strong></h3>
        </div>
    </div>
</main>
</body>
</html>
