<?php
require_once '../config/config.php';

if (!estaLogado() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . 'validar.php');
}

$db = getDbConnection();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if ($id) {
    $stmt = $db->prepare("UPDATE agendamentos SET estado = 'presente' WHERE id = ?");
    $stmt->execute([$id]);
}

redirect(SITE_URL . 'validar.php');
