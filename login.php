<?php
require_once '../config/config.php';
$db = getDbConnection();

$erros = [];
$errosGerais = [];

// Rate limiting para login
$rateLimit = rateLimit('login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 5, 300);
if (!$rateLimit['permitido']) {
    $errosGerais[] = 'Muitas tentativas. Tente novamente em ' . $rateLimit['retry_after'] . ' segundos.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errosGerais)) {
    // Validar CSRF
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        logSeguranca('csrf_falha', ['pagina' => 'login']);
        $errosGerais[] = 'Token de segurança inválido. Tente novamente.';
    }

    if (empty($errosGerais)) {
        $email = sanitizeAvancado($_POST['email'] ?? '', 'email');
        $password = $_POST['password'] ?? '';

        if (empty($email)) $erros[] = 'Email é obrigatório';
        if (empty($password)) $erros[] = 'Password é obrigatória';

        if (empty($erros)) {
            $stmt = $db->prepare("SELECT u.*, e.nome as esquadra_nome FROM usuarios u
                                  JOIN esquadras e ON u.esquadra_id = e.id
                                  WHERE u.email = ? AND u.ativo = 1");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            if ($usuario && verificarPassword($password, $usuario['password_hash'])) {
                // Regenerar ID da sessão para prevenir session fixation
                session_regenerate_id(true);

                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['esquadra_id'] = $usuario['esquadra_id'];
                $_SESSION['esquadra_nome'] = $usuario['esquadra_nome'];
                $_SESSION['perfil'] = $usuario['perfil'];

                logSeguranca('login_sucesso', ['usuario_id' => $usuario['id'], 'email' => $email]);
                renovarTokenCSRF();
                redirect(SITE_URL . 'dashboard.php');
            } else {
                logSeguranca('login_falha', ['email' => $email]);
                $erros[] = 'Email ou password inválidos';
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container container-narrow">
    <div class="card">
        <div class="card-header">
            <h2>Área do Agente</h2>
            <p>Autentique-se para aceder ao painel de gestão</p>
        </div>

        <?php if (!empty($errosGerais)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errosGerais as $erro): ?>
                        <li><?= e($erro) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($erros)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($erros as $erro): ?>
                        <li><?= e($erro) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" data-validate>
            <?= csrfInput() ?>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required value="<?= $_POST['email'] ?? '' ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Entrar</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
