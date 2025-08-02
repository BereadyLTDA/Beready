<?php
// Configuração de conexão com o banco de dados
$host = "localhost";
$user = "root";
$password = "";
$dbname = "beready";

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}
?>
