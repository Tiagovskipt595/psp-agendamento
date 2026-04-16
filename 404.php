<?php
http_response_code(404);
include 'includes/header.php';
?>
<div class="container">
    <div class="card text-center" style="padding: 3rem;">
        <h1 style="font-size: 4rem; margin: 0;">404</h1>
        <h2>Página Não Encontrada</h2>
        <p>A página que procura não existe ou foi movida.</p>
        <a href="<?= SITE_URL ?>" class="btn" style="display: inline-block; margin-top: 1rem; padding: 0.75rem 2rem; background: #0066cc; color: white; text-decoration: none; border-radius: 5px;">
            ← Voltar ao Início
        </a>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
