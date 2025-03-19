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
$email = $_SESSION['email'] ?? '';
$matricula = $_GET['matricula'] ?? '';

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

//---------------------------------------SALDO CLIENTE---------------------------------------
$querySaldo = "SELECT saldo FROM cliente WHERE email = $1";
pg_prepare($conn, "buscar_saldo", $querySaldo);
$resultSaldo = pg_execute($conn, "buscar_saldo", array($email));

if ($resultSaldo) {
    $rowSaldo = pg_fetch_assoc($resultSaldo);
    $saldo = $rowSaldo['saldo'] ?? 'Indisponível';
} else {
    $saldo = 'Indisponível';
    $error = pg_last_error($conn);
    echo "<script>alert('Erro ao buscar saldo: " . htmlspecialchars($error) . "');</script>";
}

if (empty($matricula)) {
    die("Nenhuma matrícula foi fornecida.");
}

//---------------------------------------DETALHES DO CARRO---------------------------------------
$query = "SELECT * FROM carro WHERE matricula = $1";
$result = pg_query_params($conn, $query, [$matricula]);

if (!$result || pg_num_rows($result) === 0) {
    die("Carro não encontrado.");
}
$carro = pg_fetch_assoc($result);

// Query para buscar o custo diário atual
$query_custo = "
    SELECT custo_diario, datainicio 
    FROM historico_preco 
    WHERE matricula = $1 
    ORDER BY datainicio DESC 
    LIMIT 1";
$result_custo = pg_query_params($conn, $query_custo, [$matricula]);

if (!$result_custo || pg_num_rows($result_custo) === 0) {
    die("Custo diário não encontrado.");
}
$custo = pg_fetch_assoc($result_custo);

//---------------------------------------FORMULARIO PEDIDO RESERVA---------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datainicio = $_POST['DataInicio'] ?? '';
    $datafim = $_POST['DataFim'] ?? '';

    // Validação das datas
    if (!$datainicio || !$datafim || $datainicio > $datafim) {
        echo "<p>Por favor, insira datas válidas.</p>";
    } else {
        // Calcular a diferença entre as datas
        $dateInicio = new DateTime($datainicio);
        $dateFim = new DateTime($datafim);
        $intervalo = $dateInicio->diff($dateFim);
        $dias = $intervalo->days;

        // Calcular o custo total
        $custo_total = $dias * $custo['custo_diario'];

        // Verificar saldo
        if ($saldo < $custo_total) {
            echo "<p>Saldo insuficiente para esta reserva. Custo total: $custo_total</p>";
        } else {
            // Verificar disponibilidade do carro nas datas escolhidas
            //COUNT(*) conta todas as reservas
            $query_verificar_reserva = "
                SELECT COUNT(*) AS num_reservas 
                FROM pedido_reserva 
                WHERE matricula = $1 
                AND (
                    ($2 BETWEEN datainicio AND datafim) OR
                    ($3 BETWEEN datainicio AND datafim) OR
                    (datainicio BETWEEN $2 AND $3) OR
                    (datafim BETWEEN $2 AND $3)
                )";
            $result_verificar_reserva = pg_query_params($conn, $query_verificar_reserva, [$matricula, $datainicio, $datafim]);

            if (!$result_verificar_reserva) {
                $error = pg_last_error($conn);
                die("Erro ao verificar disponibilidade: " . htmlspecialchars($error));
            }

            $row_verificar_reserva = pg_fetch_assoc($result_verificar_reserva);
            if ($row_verificar_reserva['num_reservas'] > 0) {
                echo "<p>Erro: O carro já está reservado para as datas selecionadas.</p>";
            } else {
                // Inserir o pedido de reserva
                $query_pedido_reserva = "INSERT INTO pedido_reserva (datainicio, datafim, matricula, email) VALUES ($1, $2, $3, $4)";
                $result_pedido_reserva = pg_query_params($conn, $query_pedido_reserva, [$datainicio, $datafim, $matricula, $email]);

                if ($result_pedido_reserva) {
                    // Atualizar saldo do usuário
                    $saldo -= $custo_total;
                    $_SESSION['saldo'] = $saldo;
                    $query_update_saldo = "UPDATE cliente SET saldo = $1 WHERE email = $2";
                    pg_query_params($conn, $query_update_saldo, [$saldo, $email]);

                    echo "<p>Reserva efetuada com sucesso! Custo total: $custo_total</p>";
                } else {
                    $error = pg_last_error($conn);
                    echo "<p>Erro ao inserir reserva: " . htmlspecialchars($error) . "</p>";
                }
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Rent Carro</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/css/bootstrap.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" href="css/geralcss.css">
    <link rel="stylesheet" type="text/css" href="css/HomePage.css">

    <link rel="stylesheet" href="css/detailcarro.css">
    <link rel="stylesheet" href="css/rentcarro.css">
    <link rel="stylesheet" type="text/css" href="css/HomePageUSER.css">
</head>
<body>
<nav class="Nav">
    <div class="Nav-Container">
        <h1 class="Nav_Title">Bem vindo, <?php echo htmlspecialchars($nome); ?></h1>
        <div class="Nav-Buttons">
            <a href="HomePageUSER.php">
                <img src="Images/home2.svg" width="41" alt="Home">
            </a>
            <a href="PedidoReservaHistorico.php">
                <img src="Images/clipboard-list.svg" width="41" alt="Lista icone">
            </a>
            <a href="logout.php">
                <img src="Images/logout.svg" width="41" alt="Sair">
            </a>
        </div>
    </div>
</nav>

<main>
    <div class="saldofiltro_container">
        <div class="especificacoes1" style="margin-left: auto;">
            <p class="saldo">Saldo:</p><p class="strong"><?php echo htmlspecialchars($saldo); ?>€</p>
        </div>
    </div>
    <div class="contentor_geral">
        <div class="Container_Left">
            <?php if ($carro['imagem']): ?>
                <img src="data:image/jpeg;base64,<?php echo base64_encode(pg_unescape_bytea($carro['imagem'])); ?>"
                     alt="Imagem do carro"
                     style="width: 80%;">
            <?php else: ?>
                <img src="Images/carromockup1.png" width="80%" alt="Sem imagem">
            <?php endif; ?>
            <div>
                <h2>Reserve Já!</h2>
                <form method="post" action="" class="form-inline">
                    <div class="formcontainer">
                        <div class="form-group">
                            <label for="DataInicio">Início</label>
                            <input type="date" id="DataInicio" name="DataInicio" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="DataFim">Fim</label>
                            <input type="date" id="DataFim" name="DataFim" class="form-control" required>
                        </div>
                        <button type="submit" class="Botao">Reservar Carro</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="Contentor_Right">
            <h2>Especificações</h2>
            <div class="preco" style="margin-bottom: 5%">
                <strong><?php echo htmlspecialchars($custo['custo_diario']); ?>€</strong><p>/por dia</p>
            </div>
            <div class="especificacoes">
                <strong>Nome:</strong><p> <?php echo htmlspecialchars($carro['nome']); ?></p>
            </div>
            <div class="especificacoes">
                <strong>Matrícula:</strong><p><?php echo htmlspecialchars($carro['matricula']); ?></p>
            </div>
            <div class="especificacoes">
                <strong>Combustível:</strong><p> <?php echo htmlspecialchars($carro['combustivel']); ?></p>
            </div>
            <div class="especificacoes">
                <strong>Carga:</strong> <p> <?php echo htmlspecialchars($carro['carga']); ?></p>
            </div>
            <div class="especificacoes">
                <strong>Marca:</strong> <p><?php echo htmlspecialchars($carro['marca']); ?></p>
            </div>
        </div>
    </div>
</main>
</body>
</html>


