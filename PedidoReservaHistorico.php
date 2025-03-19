<?php
//---------------------------------------COMEÇAR SESSÃO---------------------------------------
session_start();

// Conexão com o banco de dados
$str = "dbname=anasofiaalmeida user=anasofiaalmeida password= host=localhost port=5433";
$conn = pg_connect($str);

if (!$conn) {
    die("Erro de conexão com o banco de dados.");
}

// Obter informações do usuário na sessão
$nome = $_SESSION['nome'] ?? 'Usuário';
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

//---------------------------------------SALDO CLIENTE---------------------------------------
$querySaldo = "SELECT saldo FROM cliente WHERE email = $1";
pg_prepare($conn, "buscar_saldo", $querySaldo);
$resultSaldo = pg_execute($conn, "buscar_saldo", array($email));

if ($resultSaldo) {
    $rowSaldo = pg_fetch_assoc($resultSaldo);                                   //armazenar e acessar dados em um formato chave-valor (chave é o nome da coluna e o valor é o valor da coluna)
    $saldo = $rowSaldo['saldo'] ?? 'Indisponível';
} else {
    $saldo = 'Indisponível';
    $error = pg_last_error($conn);
    echo "<script>alert('Erro ao buscar saldo: " . htmlspecialchars($error) . "');</script>";
}


//---------------------------------------DADOS DO PEDIDO RESERVA---------------------------------------
$data_atual = date('Y-m-d');

$query_reserva = "
    SELECT datainicio, datafim, matricula, id_reserva
    FROM pedido_reserva 
    WHERE email = $1 
    ORDER BY datainicio DESC";
$result_reserva = pg_query_params($conn, $query_reserva, [$email]);

if (!$result_reserva) {
    $error = pg_last_error($conn);
    echo "<script>alert('Erro ao buscar reservas: " . htmlspecialchars($error) . "');</script>";
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>ADMIN</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/css/bootstrap.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/geralcss.css">
    <link rel="stylesheet" href="css/HomePage.css">
    <link rel="stylesheet" href="css/detailcarro.css">

    <link rel="stylesheet" href="css/HomePageUSER.css">
</head>
<body>
<nav class="Nav">
    <div class="Nav-Container">
        <h1 class="Nav_Title">Bem vindo, <?php echo htmlspecialchars($nome); ?></h1> <!-- Exibe o nome do usuário -->
        <div class="Nav-Buttons">
            <a href="HomePageUSER.php">
                <img src="Images/home2.svg" width="41" alt="Home" />
            </a>
            <a href="PedidoReservaHistorico.php">
                <img src="Images/clipboard-list.svg" width="41" alt="Lista ícone" />
            </a>
            <a href="logout.php">
                <img src="Images/logout.svg" width="41" alt="Sair">
            </a>
        </div>
    </div>
</nav>
<div class="saldofiltro_container">
    <div class="especificacoes1" style="margin-left: auto;">
        <p class="saldo">Saldo:</p><p class="strong"><?php echo htmlspecialchars($saldo); ?>€</p>
    </div>
</div>
<div>
    <div class="Contentor_Reservas">
        <h2>Pedidos Reservas</h2>
        <div class="rolagem">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Número de Reserva</th>
                    <th scope="col">Matrícula</th>
                    <th scope="col">Data de Início</th>
                    <th scope="col">State</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($result_reserva && pg_num_rows($result_reserva) > 0): ?>
                    <?php while ($reservas = pg_fetch_assoc($result_reserva)): ?>                       <!--armazenar e acessar dados em um formato chave-valor (chave é o nome da coluna e o valor é o valor da coluna) -->
                        <?php
                                                                                                        // Calcular o estado para cada reserva
                        $data_fim = new DateTime($reservas['datafim']);
                        $data_atual_obj = new DateTime($data_atual);

                        $state = ($data_fim < $data_atual_obj) ? 'Terminado' : 'Por levantar';
                        ?>
                        <tr>
                            <th scope="row"><?php echo htmlspecialchars($reservas['id_reserva']); ?></th>
                            <td><?php echo htmlspecialchars($reservas['matricula']); ?></td>
                            <td><?php echo htmlspecialchars($reservas['datainicio']); ?></td>
                            <td><?php echo htmlspecialchars($state); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">Nenhuma reserva encontrada.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="//code.jquery.com/jquery-1.12.0.min.js"></script>
<script src="//code.jquery.com/jquery-migrate-1.2.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/js/bootstrap.min.js"></script>
</body>
</html>


