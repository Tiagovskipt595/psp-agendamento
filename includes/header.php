<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>css/style.css">
</head>
<body>
    <header>
        <div class="header-container">
            <div class="logo">
                <span class="logo-icon">👮</span>
                <div>
                    <h1><?= SITE_NAME ?></h1>
                    <span>Polícia de Segurança Pública</span>
                </div>
            </div>
            <nav>
                <ul>
                    <li><a href="<?= SITE_URL ?>">Início</a></li>
                    <?php if (estaLogado()): ?>
                        <li><a href="dashboard.php">Painel</a></li>
                        <li><a href="logout.php">Sair</a></li>
                    <?php else: ?>
                        <li><a href="login.php" class="btn-login">Área do Agente</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <?php
        $flash = getFlash();
        if ($flash):
            $classe = 'alert-' . ($flash['tipo'] === 'sucesso' ? 'success' : $flash['tipo']);
        ?>
            <div class="container">
                <div class="alert <?= $classe ?>">
                    <?= sanitize($flash['mensagem']) ?>
                </div>
            </div>
        <?php endif; ?>

        <script src="<?= SITE_URL ?>js/toasts.js"></script>
        <script src="<?= SITE_URL ?>js/validator.js"></script>
