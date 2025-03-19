<?php
//---------------------------------------COMEÇAR SESSÃO---------------------------------------
session_start();

$str = "dbname=anasofiaalmeida user=anasofiaalmeida password= host=localhost port=5433";
$conn = pg_connect($str);

if (!$conn) {
    die("Erro de conexão com o banco de dados.");
}

$nome = $_SESSION['nome'] ?? '';                                        // Ir buscar os valores da sessão e Verifica se o nome existe
$email = $_SESSION['email'] ?? '';
$saldo = $_SESSION['saldo'] ?? '';

//------------------------------IMPEDIR ENTRADA DE NÃO CLIENTES------------------------------
$queryEntrada = "SELECT email FROM cliente WHERE email = $1";
pg_prepare($conn, "verificar_entrada", $queryEntrada);                         //Preparar dados
$resultEntrada = pg_execute($conn, "verificar_entrada", array($email));

if(!$resultEntrada ||  pg_num_rows($resultEntrada) === 0 || !isset($_SESSION['email'])){
    header('HTTP/1.0 403 Forbidden');
    header("Location: index.php");
    exit;
}
//------------------------------SALDO CLIENTE------------------------------

$querySaldo = "SELECT saldo FROM cliente WHERE email = $1";
pg_prepare($conn, "buscar_saldo", $querySaldo);                         //Preparar dados
$resultSaldo = pg_execute($conn, "buscar_saldo", array($email));        //Executar

if ($resultSaldo) {
    $rowSaldo = pg_fetch_assoc($resultSaldo);                                          //armazenar e acessar dados em um formato chave-valor (chave é o nome da coluna e o valor é o valor da coluna)
    $saldo = $rowSaldo['saldo'] ?? 'Indisponível';
} else {
    $saldo = 'Indisponível';
    $error = pg_last_error($conn);
    echo "<script>alert('Erro ao buscar saldo: " . htmlspecialchars($error) . "');</script>";
}

//------------------------------FILTROS------------------------------

$marcaSelecionada = $_GET['marca'] ?? '';
$cargaSelecionada = $_GET['carga'] ?? '';
$ordenarPreco = $_GET['ordenar_preco'] ?? '';

//Selecionar marcas
// DISTINCT permite não haver repetições de marcas
$queryMarcas = "SELECT DISTINCT marca FROM carro ORDER BY marca";
pg_prepare($conn, "listar_marcas", $queryMarcas);
$resultMarcas = pg_execute($conn, "listar_marcas", array());

$marcas = $resultMarcas ? pg_fetch_all_columns($resultMarcas) : [];                 //pg_fetch_all_columns retorna todas as colunas dos resultados da consulta como um array indexado

//selecionar carro,nome etc... do carro
//atributo AS serve para dar um apelido a coluna
// ao carro adicionamos a subconsulta para buscar o custo diário mais recente
//DISTINCT ON apenas seleciona 1 elemento
//JOIN (adicionar historico visibilidade para ir buscar os visíveis (que sejam TRUE))

$queryFiltro = "SELECT 
    c.matricula, 
    c.nome, 
    c.imagem,
    c.carga,
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
    hv.visivel = TRUE";

$condicoes = [];
$valores = [];

// Adicionar filtro por marca
if (!empty($marcaSelecionada)) {
    $condicoes[] = "c.marca = $1";
    $valores[] = $marcaSelecionada;
}

// Adicionar filtro por carga
if (!empty($cargaSelecionada)) {
    $condicoes[] = "c.carga = $" . (count($valores) + 1);                   //(count($valores) + 1) serve para garantir que os parâmetros passados para a consulta SQL sejam numerados sequencialmente de forma única
    $valores[] = $cargaSelecionada;                                         //+1 serve para ajustar de acordo com o número de valores já passados na consulta
}

// Adicionar filtro por preço máximo
if (!empty($precoMaximo)) {
    $condicoes[] = "hp.custo_diario <= $" . (count($valores) + 1);
    $valores[] = $precoMaximo;
}

// Adicionar condições à consulta
if (count($condicoes) > 0) {
    $queryFiltro .= " AND " . implode(" AND ", $condicoes);        //operador .= adiciona o valor à variável
                                                                            //implode transforma o array $condicoes em uma string única
}

// Ordenar os resultados baseado na escolha do usuário
if ($ordenarPreco === 'asc') {
    $queryFiltro .= " ORDER BY hp.custo_diario ASC";                        //menor para maior
} elseif ($ordenarPreco === 'desc') {
    $queryFiltro .= " ORDER BY hp.custo_diario DESC";
} else {
    $queryFiltro .= " ORDER BY c.matricula";                                // Ordem padrão
}

// Preparar e executar a consulta
pg_prepare($conn, "filtrar_carros", $queryFiltro);
$resultCarros = pg_execute($conn, "filtrar_carros", $valores);
$carros = $resultCarros ? pg_fetch_all($resultCarros) : [];                 //retorna todos os registros da consulta em um array


?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>USER</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/geralcss.css">
    <link rel="stylesheet" href="css/HomePage.css">
    <link rel="stylesheet" href="css/detailcarro.css">
    <link rel="stylesheet" href="css/HomePageUSER.css">

</head>
<body>
<nav class="Nav">
    <div class="Nav-Container">
        <h1 class="Nav_Title">Bem-vindo, <?php echo htmlspecialchars($nome); ?></h1>
        <div class="Nav-Buttons">
            <img src="Images/home2.svg" width="41" alt="Home" />
            <a href="PedidoReservaHistorico.php">
                <img src="Images/clipboard-list.svg" width="41" alt="Lista icone">
            </a>
            <a href="logout.php">
                <img src="Images/logout.svg" width="41" alt="Sair">
            </a>
        </div>
    </div>
</nav>
<div class="saldofiltro_container">
<!-- Formulário para filtros -->
    <form method="GET" action="" class="form-inline">
        <div class="form-group">
            <label for="marca" class="sr-only">Marca:</label>
            <select name="marca" id="marca" class="form-control">
                <option value="" selected>Filtrar por Marca:</option>
                <?php foreach ($marcas as $marca): ?>
                    <option value="<?php echo htmlspecialchars($marca); ?>"
                        <?php echo $marca === $marcaSelecionada ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($marca); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="carga" class="sr-only">Carga:</label>
            <input type="text" id="carga" class="form-control" name="carga"
                   value="<?php echo htmlspecialchars($cargaSelecionada); ?>"
                   placeholder="Filtrar por Carga">
        </div>
        <div class="form-group">
            <label for="ordenar_preco" class="sr-only">Ordenar por Preço:</label>
            <select name="ordenar_preco" id="ordenar_preco" class="form-control">
                <option value="" selected>Ordenar por Preço</option>
                <option value="asc" <?php echo $ordenarPreco === 'asc' ? 'selected' : ''; ?>>Mais barato</option>
                <option value="desc" <?php echo $ordenarPreco === 'desc' ? 'selected' : ''; ?>>Mais caro</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" id="idb">Filtrar</button>
    </form>

    <div class="especificacoes1" style="margin-left: auto;">
        <p class="saldo">Saldo:</p><p class="strong"><?php echo htmlspecialchars($saldo); ?>€</p>
    </div>
</div>
<!-- Listagem de carros -->
<ul class="Lista_Carros">
    <?php if ($carros): ?>
        <?php foreach ($carros as $carro): ?>
            <li class="carro_container">
                <a href="RentCarro.php?matricula=<?php echo urlencode($carro['matricula']); ?>" style="text-decoration: none; color: inherit;"> <!-- urlencode() é uma funçã para codificar uma string-->
                    <?php if ($carro['imagem']): ?>
                        <img src="data:image/jpeg;base64,<?php echo base64_encode(pg_unescape_bytea($carro['imagem'])); ?>" alt="Imagem do carro" width="150"> <!--codificar em base64 para transformar dados binários em imagem-->
                    <?php else: ?>
                        <img src="Images/carromockup1.png" width="150" alt="Sem imagem">
                    <?php endif; ?>
                    <strong>Preço:</strong> <?php echo htmlspecialchars($carro['preco_inicial'] ?? 'Não disponível'); ?><br>
                    <strong>Nome:</strong> <?php echo htmlspecialchars($carro['nome']); ?><br>
                    <strong>Matrícula:</strong> <?php echo htmlspecialchars($carro['matricula']); ?><br>
                    <strong>Carga:</strong> <?php echo htmlspecialchars($carro['carga']); ?>
                </a>
            </li>
        <?php endforeach; ?>

    <?php else: ?>
        <li>Nenhum carro encontrado.</li>
    <?php endif; ?>
</ul>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
</body>
</html>


