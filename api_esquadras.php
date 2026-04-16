<?php
/**
 * API: Listar Esquadras
 * Retorna lista de esquadras ativas em JSON
 */
require_once 'config/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Buscar esquadras ativas
$db = getDbConnection();
$stmt = $db->prepare("SELECT id, nome, morada, telefone FROM esquadras WHERE ativo = 1 ORDER BY nome");
$stmt->execute();
$esquadras = $stmt->fetchAll();

echo json_encode($esquadras);
