<?php
//---------------------------------------COMEÇAR SESSÃO---------------------------------------
session_start();


$str = "dbname=anasofiaalmeida user=anasofiaalmeida password= host=localhost port=5433";
$conn = pg_connect($str);

if (!$conn) {
    die("Erro de conexão com o banco de dados.");
}

$nome = $_SESSION['nome'] ?? '';                                    // // Ir buscar os valores da sessão e Verifica se o nome existe
$email = $_SESSION['email'] ?? '';
$saldo = $_SESSION['saldo'] ?? '';
$matricula = $_GET['id'] ?? '';

//------------------------------IMPEDIR ENTRADA DE NÃO ADMINISTRADORES------------------------------
$queryEntrada = "SELECT email FROM administrador WHERE email = $1";
pg_prepare($conn, "verificar_entrada", $queryEntrada);                         //Preparar dados
$resultEntrada = pg_execute($conn, "verificar_entrada", array($email));

if(!$resultEntrada ||  pg_num_rows($resultEntrada) === 0 || !isset($_SESSION['email'])){
    header('HTTP/1.0 403 Forbidden');
    header("Location: index.php");
    exit;
}

if (empty($matricula)) {
    die("Nenhuma matrícula foi fornecida.");
}


//---------------------------------------Alterar o custo diário---------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar entrada do formulário
    $novo_custo_diario = $_POST['preco_inicial'] ?? null;
    $visibilidade = $_POST['visivel'] === 'true' ? 'TRUE' : 'FALSE';

    if ($novo_custo_diario !== null && is_numeric($novo_custo_diario)) {
        // Iniciar transação
        pg_query($conn, "BEGIN");                           //"BEGIN" é usada para iniciar uma transação no banco de dados
                                                                    //alterações no banco de dados serão confirmadas ou revertidas como um tod o

        try {                                                       //facilita lidar com exceções ou erros
            // Dá update ao valor
            $query_update_datafim = "UPDATE historico_preco SET datafim = NOW() WHERE matricula = $1 AND datafim IS NULL";
            $result_update_datafim = pg_query_params($conn, $query_update_datafim, [$matricula]);

            if (!$result_update_datafim) {
                throw new Exception("Erro ao atualizar a datafim: " . pg_last_error($conn));
            }

            // Inserir um novo registro de custo diário
            $query_insert_custo = "
                INSERT INTO historico_preco (matricula, custo_diario, datainicio, email)
                VALUES ($1, $2, NOW(), $3)";
            $result_insert_custo = pg_query_params($conn, $query_insert_custo, [$matricula, $novo_custo_diario, $email]);

            if (!$result_insert_custo) {
                throw new Exception("Erro ao inserir novo custo diário: " . pg_last_error($conn));
            }

            // Atualizar visibilidade
            $query_update_visibilidade = "UPDATE historico_visibilidade SET visivel = $1 WHERE matricula = $2";
            $result_update_visibilidade = pg_query_params($conn, $query_update_visibilidade, [$visibilidade, $matricula]);

            if (!$result_update_visibilidade) {
                throw new Exception("Erro ao atualizar visibilidade: " . pg_last_error($conn));
            }

            // Confirmar transação
            pg_query($conn, "COMMIT");                  //COMMIT Confirma as mudanças feitas durante a transação.
            header("Location: HomePageADMIN.php");
            exit();                                           //Finalizar a execução do php para evitar conflitos
        } catch (Exception $e) {                              //Caso haja erro
            // Reverter transação em caso de erro
            pg_query($conn, "ROLLBACK");
            // Mostrar mensagem de erro detalhada
            echo "<p>Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}
//---------------------------------------DADOS DO CARRO---------------------------------------
$query = "SELECT * FROM carro WHERE matricula = $1";
$result = pg_query_params($conn, $query, [$matricula]);

if (!$result || pg_num_rows($result) === 0) {
    die("Carro não encontrado.");
}
$carro = pg_fetch_assoc($result);

// Query para buscar o custo diário atual
// SELECT MAX - obter o maior valor da coluna id_historico no caso o que foi inserido mais recentemente
$query_custo = "
    SELECT custo_diario, datainicio 
FROM historico_preco 
WHERE matricula = $1 
  AND id_historico = (SELECT MAX(id_historico) 
                      FROM historico_preco 
                      WHERE matricula = $1);";              //LIMIT para buscar o custo mais recente
$result_custo = pg_query_params($conn, $query_custo, [$matricula]);

if ($result_custo && pg_num_rows($result_custo) > 0) {
    $custo = pg_fetch_assoc($result_custo);
} else {
    $custo = ['custo_diario' => '0'];                       // Caso não haja valor
}


//---------------------------------------buscar historico de preços---------------------------------------
$query_hp = "
    SELECT custo_diario, datainicio 
    FROM historico_preco 
    WHERE matricula = $1 
    ORDER BY id_historico DESC
";
$result_hp = pg_query_params($conn, $query_hp, [$matricula]);


$historico_precos = [];                                     // Inicializa o array antes do loop
if ($result_hp && pg_num_rows($result_hp) > 0) {
                                                            // loop sobre todos os registros e adiciona ao array
    while ($hp = pg_fetch_assoc($result_hp)) {
        $historico_precos[] = $hp;
    }
}

//---------------------------------------Buscar pedidos de reserva---------------------------------------
$query_reservas = "
    SELECT *
    FROM pedido_reserva 
    WHERE matricula = $1 
    ORDER BY datainicio DESC";
$result_reservas = pg_query_params($conn, $query_reservas, [$matricula]);
$pedido_reserva = [];
if ($result_reservas && pg_num_rows($result_reservas) > 0) {
                                                            // loop sobre todos os registros e adiciona ao array
    while ($query_reservas = pg_fetch_assoc($result_reservas)) {
        $pedido_reserva[] = $query_reservas;
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Detalhes do Carro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" type="text/css" href="css/geralcss.css">
    <link rel="stylesheet" type="text/css" href="css/HomePage.css">
    <link rel="stylesheet" type="text/css" href="css/detailcarro.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="Nav">
    <div class="Nav-Container">
        <h1 class="Nav_Title">Bem vindo, <?php echo htmlspecialchars($nome); ?></h1>
        <div class="Nav-Buttons">
            <a href="HomePageADMIN.php">
                <img src="Images/home2.svg" width="41" alt="Home">
            </a>
            <a href="Estatisticas.php">
            <img src="Images/clipboard-list.svg" width="41" alt="Lista icone">
            </a>
            <a href="logout.php">
                <img src="Images/logout.svg" width="41" alt="Sair">
            </a>
        </div>
    </div>
</nav>
<main>
    <div class="contentor_geral">
    <div class="Container_Left">
        <?php if ($carro['imagem']): ?>
            <img src="data:image/jpeg;base64,<?php echo base64_encode(pg_unescape_bytea($carro['imagem'])); ?>"
                 alt="Imagem do carro" width="80%">
        <?php else: ?>
            <img src="Images/carromockup1.png" width="80%" alt="Sem imagem">
        <?php endif; ?>

        <div class="Historic_Table">
            <h2>Histórico de Preços</h2>
            <div class="rolagem">
            <table class="table caption-top">
                <thead>
                <tr class="text-center">
                    <th scope="col">Preço</th>
                    <th scope="col">Data</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($historico_precos) > 0): ?>
                    <?php foreach ($historico_precos as $historico): ?>
                        <tr class="text-center">
                            <!-- Preenche as células com os dados -->
                            <td><?php echo htmlspecialchars($historico['custo_diario']); ?></td>
                            <td><?php echo htmlspecialchars($historico['datainicio']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
        <div class="Contentor_Right">
            <h1><?php echo htmlspecialchars($carro['nome']); ?></h1>
        <form method="post" action="">
            <label for="preco_inicial" class="preco">
                <strong><?php echo htmlspecialchars($custo['custo_diario'] ?? ''); ?>€</strong>
                <p class="paragraph">  /por dia</p>
            </label>
            <input type="number" step="0.01" name="preco_inicial" id="preco_inicial"
                   placeholder="<?php echo htmlspecialchars($custo['custo_diario'] ?? ''); ?>">

            <div class="form-check form-switch">
                <input
                        class="form-check-input"
                        type="checkbox"
                        role="switch"
                        name="visivel"
                        id="flexSwitchCheckChecked"
                        value="true"
                        onchange="document.getElementById('hiddenVisivel').value = this.checked ? 'true' : 'false';"
                        checked>
                <label class="form-check-label" for="flexSwitchCheckChecked">Visibilidade</label>
            </div>
            <input
                    type="hidden"
                    name="visivel"
                    id="hiddenVisivel"
                    value="true">
            <button type="submit">Salvar</button>

            <button type="submit" class="alterarprecobutton">Alterar</button>
        </form>

        <h2>Especificações</h2>
            <div class="especificacoes">
        <strong>Matrícula:</strong><p><?php echo htmlspecialchars($carro['matricula']); ?></p>
            </div>
            <div class="especificacoes">
        <strong>Combustível:</strong><p><?php echo htmlspecialchars($carro['combustivel']); ?><p>
            </div>
            <div class="especificacoes">
        <strong>Carga:</strong><p><?php echo htmlspecialchars($carro['carga']); ?></p>
            </div>
            <div class="especificacoes">
        <strong>Marca:</strong><p><?php echo htmlspecialchars($carro['marca']); ?></p>
            </div>

    </div>
    </div>
    <div class="Contentor_Reservas">
        <h2>Reservas</h2>
        <div class="rolagem">
        <table class="table caption-top">
            <thead>
            <tr>
                <th scope="col">Id_Reserva</th>
                <th scope="col">Data</th>
                <th scope="col">Email</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($pedido_reserva) > 0): ?>
            <?php foreach ($pedido_reserva as $pedidos): ?>
                    <tr>
                        <th scope="row"><?php echo htmlspecialchars($pedidos['id_reserva']); ?></th>
                        <td><?php echo htmlspecialchars($pedidos['datainicio']); ?> / <?php echo htmlspecialchars($pedidos['datafim']); ?></td>
                        <td><?php echo htmlspecialchars($pedidos['email']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</main>
<script src="//code.jquery.com/jquery-1.12.0.min.js"></script>
<script src="//code.jquery.com/jquery-migrate-1.2.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
