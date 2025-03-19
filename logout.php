<?php
session_start(); // Inicia a sessão

// Remove todas as variáveis da sessão
session_unset();

// Destrói a sessão
session_destroy();

// Redireciona para a página de login
header("Location: LogIn_Page.php");
exit();
?>
