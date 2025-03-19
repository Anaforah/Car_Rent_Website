<?php
//---------------------------------------COMEÇAR SESSÃO---------------------------------------
session_start();

$str = "dbname=anasofiaalmeida user=anasofiaalmeida password= host=localhost port=5433";
$conn = pg_connect($str);

if (!$conn) {
    die("Erro de conexão com o banco de dados.");
}

$nome = $_SESSION['nome'] ?? '';                                                    // Ir buscar os valores da sessão e Verifica se o nome existe
$saldo = $_SESSION['saldo'] ?? '';
$email_administrador = $_SESSION['email'] ?? '';
$preco_inicial = $_SESSION['preco_inicial'] ?? '';

//------------------------------IMPEDIR ENTRADA DE NÃO ADMINISTRADORES------------------------------
$queryEntrada = "SELECT email FROM administrador WHERE email = $1";
pg_prepare($conn, "verificar_entrada", $queryEntrada);                         //Preparar dados
$resultEntrada = pg_execute($conn, "verificar_entrada", array($email_administrador));

if(!$resultEntrada ||  pg_num_rows($resultEntrada) === 0 || !isset($_SESSION['email'])){
    header('HTTP/1.0 403 Forbidden');
    header("Location: index.php");
    exit;
}
//------------------------------CARRO VISIVEL------------------------------

//selecionar carro,nome etc... do carro
//atributo AS serve para dar um apelido a coluna
// ao carro adicionamos a subconsulta para buscar o custo diário mais recente
//DISTINCT ON apenas seleciona 1 elemento
//JOIN (adicionar historico visibilidade para ir buscar os visíveis (que sejam TRUE))

$queryCarroVisivel = "SELECT
    c.matricula, 
    c.nome, 
    c.imagem, 
    hp.custo_diario AS preco_inicial
FROM 
    carro c
JOIN 
    (SELECT DISTINCT ON (hp.matricula) 
        hp.matricula,
        hp.custo_diario,
        hp.datainicio
    FROM 
        historico_preco hp
    ORDER BY 
        hp.matricula, hp.datainicio DESC) hp
ON 
    c.matricula = hp.matricula
JOIN 
    historico_visibilidade hv 
ON 
    c.matricula = hv.matricula
WHERE 
    c.email = $1 
    AND hv.visivel = TRUE;";

pg_prepare($conn, "carro_query", $queryCarroVisivel);

$resultCarroVisivel = pg_execute($conn, "carro_query", array($email_administrador));            // Executar a consulta com o parâmetro


//------------------------------CARRO INVISIVEL------------------------------

//Mesma lógica que o carro visível mas o valor do historico visivel é FALSE

$queryCarroIN = "SELECT 
    c.matricula, 
    c.nome, 
    c.imagem, 
    hp.custo_diario AS preco_inicial
FROM 
    carro c
JOIN 
    (SELECT DISTINCT ON (hp.matricula) 
        hp.matricula,
        hp.custo_diario,
        hp.datainicio
    FROM 
        historico_preco hp
    ORDER BY 
        hp.matricula, hp.datainicio DESC) hp
ON 
    c.matricula = hp.matricula
JOIN 
    (SELECT DISTINCT ON (hv.matricula) 
        hv.matricula,
        hv.visivel,
        hv.datainicio
    FROM 
        historico_visibilidade hv
    ORDER BY 
        hv.matricula, hv.datainicio DESC) hv
ON 
    c.matricula = hv.matricula
WHERE 
    c.email = $1 
    AND hv.visivel = FALSE";

pg_prepare($conn, "carroIN_query", $queryCarroIN);

// Executar a consulta com o parâmetro
$resultCarroIN = pg_execute($conn, "carroIN_query", array($email_administrador));


if (!$resultCarroVisivel && !$resultCarroIN) {
    die("Erro ao buscar dados dos carros.");
} else{
    $carros = pg_fetch_all($resultCarroVisivel);                                            //recuperar resultados de uma consulta executada e armazená-los em uma variável
    $carrosIN = pg_fetch_all($resultCarroIN);
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
    <link rel="stylesheet" href="css/geralcss.css">
    <link rel="stylesheet" href="css/HomePage.css">


</head>
<body>
<nav class="Nav">
    <div class = "Nav-Container">
        <h1 class="Nav_Title">Bem vindo, <?php echo htmlspecialchars($nome); ?></h1> <!-- Exibe o nome do usuário - htmlspecialchars() é usada para converter caracteres especiais caso existirem-->
        <div class="Nav-Buttons">
            <img src="Images/home2.svg" width="41" alt="Home" />
            <a href="Estatisticas.php">
                <img src="Images/clipboard-list.svg" width="41" alt="Lista icone">
            </a>
            <a href="logout.php">
                <img src="Images/logout.svg" width="41" alt="Sair">
            </a>
        </div>
    </div>
</nav>
<div class="Botao_Contentor">
    <p>Online</p>
    <a href="AddCarro.php">
        <button class="Botao">Adicionar carro +</button>
    </a>
</div>
<ul class="Lista_Carros">
    <?php if ($carros): // Verifica se há resultados ?>                                         <!--Funcionar somente se encontrar valores-->
        <?php foreach ($carros as $carro): ?>                                                   <!--loop de todos os carros-->

            <li class="carro_container">
                <a href="DetailCarro.php?id=<?php echo urlencode($carro['matricula']); ?>" style="text-decoration: none; color: inherit;">     <!-- urlencode() é uma funçã para codificar uma string-->
                    <?php if ($carro['imagem']): ?>                                             <!-- se tiver imagem colocar senão fica uma imagem default-->
                        <img src="data:image/jpeg;base64,<?php echo base64_encode(pg_unescape_bytea($carro['imagem'])); ?>"
                             alt="Imagem do carro" width="150">
                    <?php else: ?>
                        <img src="Images/carromockup1.png" width="150" alt="Sem imagem">
                    <?php endif; ?>
                    <strong>Preço Inicial:</strong>
                    <?php echo htmlspecialchars($carro['preco_inicial'] ?? 'Não disponível'); ?><br>
                    <strong>Nome:</strong> <?php echo htmlspecialchars($carro['nome']); ?><br>
                    <strong>Matrícula:</strong> <?php echo htmlspecialchars($carro['matricula']); ?>
                </a>
            </li>

        <?php endforeach; ?>
    <?php else: ?>
        <li>Nenhum carro encontrado.</li>
    <?php endif; ?>
</ul>

<div class="Botao_Contentor">
<p>Offline</p>
</div>
<ul class="Lista_Carros">
<?php if ($carrosIN): // Verifica se há resultados ?>
    <?php foreach ($carrosIN as $carro): ?>

        <li class="carro_container" style="background-color: #E6E6E6">
            <a href="DetailCarro.php?id=<?php echo urlencode($carro['matricula']); ?>" style="text-decoration: none; color: inherit;">
                <?php if ($carro['imagem']): ?>
                    <img src="data:image/jpeg;base64,<?php echo base64_encode(pg_unescape_bytea($carro['imagem'])); ?>"
                         alt="Imagem do carro" width="150">
                <?php else: ?>
                    <img src="Images/carromockup1.png" width="150" alt="Sem imagem">
                <?php endif; ?>
                <strong>Preço Inicial:</strong>
                <?php echo htmlspecialchars($carro['preco_inicial'] ?? 'Não disponível'); ?><br>
                <strong>Nome:</strong> <?php echo htmlspecialchars($carro['nome']); ?><br>
                <strong>Matrícula:</strong> <?php echo htmlspecialchars($carro['matricula']); ?>
            </a>
        </li>

    <?php endforeach; ?>
<?php else: ?>
    <li>Nenhum carro encontrado.</li>
<?php endif; ?>
</ul>
</body>
</html>

