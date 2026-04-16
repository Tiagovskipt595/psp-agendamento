<?php
require_once '../config/config.php';
$db = getDbConnection();

$esquadraId = filter_input(INPUT_GET, 'esquadra_id', FILTER_VALIDATE_INT);
$servicoId = filter_input(INPUT_GET, 'servico_id', FILTER_VALIDATE_INT);

if (!$esquadraId || !$servicoId) {
    redirect(SITE_URL . 'index.php');
}

// Buscar dados da esquadra e serviço
$stmt = $db->prepare("SELECT e.*, s.nome as servico_nome, s.duracao_minutos FROM esquadras e
                      JOIN servicos s ON s.esquadra_id = e.id
                      WHERE e.id = ? AND s.id = ?");
$stmt->execute([$esquadraId, $servicoId]);
$dados = $stmt->fetch();

if (!$dados) {
    redirect(SITE_URL . 'index.php');
}

// Processar formulário
$erros = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        logSeguranca('csrf_falha', ['pagina' => 'agendar']);
        $erros[] = 'Token de segurança inválido. Tente novamente.';
    } else {
        $nome = sanitizeAvancado($_POST['nome'] ?? '');
        $cc = sanitizeAvancado($_POST['cc'] ?? '', 'cc');
        $email = sanitizeAvancado($_POST['email'] ?? '', 'email');
        $telemovel = sanitizeAvancado($_POST['telemovel'] ?? '', 'tel');
        $data = sanitizeAvancado($_POST['data'] ?? '');
        $hora = sanitizeAvancado($_POST['hora'] ?? '');

        // Validações
        if (empty($nome)) $erros[] = 'Nome é obrigatório';
        if (strlen($nome) < 3) $erros[] = 'Nome deve ter pelo menos 3 caracteres';
        if (empty($cc)) $erros[] = 'Número do Cartão de Cidadão é obrigatório';
        if (!preg_match('/^[0-9]{6,}[A-Z]{0,2}$/', $cc)) $erros[] = 'Formato de CC inválido';
        if (empty($email) || !validarEmail($email)) $erros[] = 'Email inválido';
        if (empty($telemovel)) $erros[] = 'Telemóvel é obrigatório';
        if (!preg_match('/^[+]?[0-9\s-]{9,}$/', $telemovel)) $erros[] = 'Formato de telemóvel inválido';
        if (empty($data)) $erros[] = 'Data é obrigatória';
        if (empty($hora)) $erros[] = 'Hora é obrigatória';

        // Verificar data não é passada
        if (!empty($data) && strtotime($data) < strtotime('today')) {
            $erros[] = 'Não é possível agendar para datas passadas';
        }

        // Verificar se já existe agendamento neste horário
        if (empty($erros)) {
            $stmt = $db->prepare("SELECT id FROM agendamentos WHERE data_agendamento = ? AND hora_agendamento = ? AND esquadra_id = ?");
            $stmt->execute([$data, $hora, $esquadraId]);
            if ($stmt->fetch()) {
                $erros[] = 'Este horário já não está disponível';
            }
        }

        if (empty($erros)) {
            // Criar agendamento
            $codigo = gerarCodigoAgendamento();
            $stmt = $db->prepare("INSERT INTO agendamentos
                                  (esquadra_id, servico_id, codigo_agendamento, nome_cidadao, cc_numero, email, telemovel, data_agendamento, hora_agendamento)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$esquadraId, $servicoId, $codigo, $nome, $cc, $email, $telemovel, $data, $hora]);

            // Enviar email de confirmação
            try {
                enviarEmailConfirmacao($codigo, $db);
            } catch (Exception $e) {
                error_log("Erro ao enviar email: " . $e->getMessage());
            }

            logSeguranca('agendamento_criado', ['codigo' => $codigo, 'email' => $email]);
            redirect(SITE_URL . 'confirmacao.php?codigo=' . urlencode($codigo));
        }
    }
}

include 'includes/header.php';
?>

<div class="container container-narrow">
    <a href="servicos.php?esquadra_id=<?= $esquadraId ?>" class="btn btn-secondary mb-2">← Voltar</a>

    <!-- Step Indicator -->
    <div class="step-indicator">
        <div class="step ativa" id="step1">
            <div class="step-number">1</div>
            <div class="step-label">Data & Hora</div>
        </div>
        <div class="step" id="step2">
            <div class="step-number">2</div>
            <div class="step-label">Seus Dados</div>
        </div>
        <div class="step" id="step3">
            <div class="step-number">3</div>
            <div class="step-label">Confirmar</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Agendar: <?= sanitize($dados['servico_nome']) ?></h2>
            <p><?= sanitize($dados['nome']) ?> - <?= sanitize($dados['morada']) ?></p>
        </div>

        <?php if (!empty($erros)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($erros as $erro): ?>
                        <li><?= e($erro) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" id="formAgendamento" data-validate>
            <?= csrfInput() ?>

            <!-- Step 1: Data e Hora -->
            <div class="step-content" id="stepContent1">
                <div class="form-group">
                    <label for="data">📅 Data *</label>
                    <input type="date" id="data" name="data" required min="<?= date('Y-m-d') ?>" data-validate="data-futura">
                </div>

                <div class="form-group">
                    <label for="hora">🕐 Hora *</label>
                    <div id="slotsHorario" class="slots-horario">
                        <p style="color: var(--text-light);">Selecione uma data primeiro</p>
                    </div>
                    <input type="hidden" id="horaSelecionada" name="hora" required>
                </div>

                <div class="text-right mt-2">
                    <button type="button" class="btn btn-primary" onclick="nextStep(1)">Continuar ➝</button>
                </div>
            </div>

            <!-- Step 2: Dados Pessoais -->
            <div class="step-content" id="stepContent2" style="display: none;">
                <div class="form-group">
                    <label for="nome">👤 Nome Completo *</label>
                    <input type="text" id="nome" name="nome" required minlength="3" placeholder="Ex: João Silva" value="<?= $_POST['nome'] ?? '' ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="cc">🆔 Cartão de Cidadão *</label>
                        <input type="text" id="cc" name="cc" required data-validate="cc" placeholder="Ex: 12345678AB" value="<?= $_POST['cc'] ?? '' ?>">
                    </div>

                    <div class="form-group">
                        <label for="telemovel">📱 Telemóvel *</label>
                        <input type="tel" id="telemovel" name="telemovel" required placeholder="Ex: 912 345 678" value="<?= $_POST['telemovel'] ?? '' ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">📧 Email *</label>
                    <input type="email" id="email" name="email" required placeholder="Ex: joao.silva@email.com" value="<?= $_POST['email'] ?? '' ?>">
                </div>

                <div class="text-right mt-2" style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="prevStep(2)">← Voltar</button>
                    <button type="button" class="btn btn-primary" onclick="nextStep(2)">Revisar ➝</button>
                </div>
            </div>

            <!-- Step 3: Revisão -->
            <div class="step-content" id="stepContent3" style="display: none;">
                <h3 class="mb-2">Revise os Dados</h3>
                <div class="card" style="background: var(--light-bg);">
                    <p><strong>📅 Data:</strong> <span id="reviewData"></span></p>
                    <p><strong>🕐 Hora:</strong> <span id="reviewHora"></span></p>
                    <p><strong>👤 Nome:</strong> <span id="reviewNome"></span></p>
                    <p><strong>🆔 CC:</strong> <span id="reviewCc"></span></p>
                    <p><strong>📱 Telemóvel:</strong> <span id="reviewTelemovel"></span></p>
                    <p><strong>📧 Email:</strong> <span id="reviewEmail"></span></p>
                </div>

                <div class="alert alert-info mt-2">
                    ℹ️ Ao confirmar, receberá um email com os detalhes do agendamento.
                </div>

                <div class="text-right mt-2" style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="prevStep(3)">← Voltar</button>
                    <button type="submit" class="btn btn-success btn-lg">✓ Confirmar Agendamento</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const esquadraId = <?= $esquadraId ?>;
const servicoId = <?= $servicoId ?>;
const duracao = <?= $dados['duracao_minutos'] ?>;

let stepAtual = 1;
const totalSteps = 3;

document.getElementById('data').addEventListener('change', carregarHorarios);

async function carregarHorarios() {
    const data = document.getElementById('data').value;
    if (!data) return;

    const container = document.getElementById('slotsHorario');
    container.innerHTML = '<div class="skeleton" style="height: 100px;"></div>';

    try {
        const response = await fetch(`api_horarios.php?esquadra_id=${esquadraId}&data=${data}&duracao=${duracao}`);
        const horarios = await response.json();

        container.innerHTML = '';

        if (horarios.length === 0) {
            container.innerHTML = '<p style="color: var(--text-light);">⚠️ Não há horários disponíveis para esta data</p>';
            return;
        }

        horarios.forEach(hora => {
            const slot = document.createElement('div');
            slot.className = 'slot' + (hora.disponivel ? '' : ' ocupado');
            slot.textContent = hora.hora;
            if (hora.disponivel) {
                slot.onclick = () => selecionarHora(slot, hora.hora);
            }
            container.appendChild(slot);
        });

        // Auto-validar se já tem hora selecionada
        const horaSelecionada = document.getElementById('horaSelecionada').value;
        if (horaSelecionada) {
            Toast.success('Horários carregados!');
        }
    } catch (e) {
        console.error('Erro ao carregar horários:', e);
        container.innerHTML = '<p style="color: var(--danger);">Erro ao carregar horários. Tente novamente.</p>';
        Toast.error('Erro ao carregar horários');
    }
}

function selecionarHora(element, hora) {
    document.querySelectorAll('.slot').forEach(s => s.classList.remove('selecionado'));
    element.classList.add('selecionado');
    document.getElementById('horaSelecionada').value = hora;
    Toast.success(`Horário selecionado: ${hora}`);
}

function nextStep(step) {
    // Validar step atual antes de avançar
    if (step === 1) {
        const data = document.getElementById('data').value;
        const hora = document.getElementById('horaSelecionada').value;

        if (!data) {
            Toast.warning('Selecione uma data');
            return;
        }
        if (!hora) {
            Toast.warning('Selecione uma hora');
            return;
        }
    }

    if (step === 2) {
        const nome = document.getElementById('nome').value.trim();
        const cc = document.getElementById('cc').value.trim();
        const email = document.getElementById('email').value.trim();
        const telemovel = document.getElementById('telemovel').value.trim();

        if (!nome || nome.length < 3) {
            Toast.warning('Nome deve ter pelo menos 3 caracteres');
            document.getElementById('nome').focus();
            return;
        }
        if (!cc) {
            Toast.warning('Preencha o Cartão de Cidadão');
            document.getElementById('cc').focus();
            return;
        }
        if (!email || !isValidEmail(email)) {
            Toast.warning('Email inválido');
            document.getElementById('email').focus();
            return;
        }
        if (!telemovel) {
            Toast.warning('Preencha o telemóvel');
            document.getElementById('telemovel').focus();
            return;
        }

        // Preencher revisão
        preencherRevisao();
    }

    // Transição de step
    document.getElementById(`stepContent${step}`).style.display = 'none';
    document.getElementById(`stepContent${step + 1}`).style.display = 'block';
    document.getElementById(`stepContent${step + 1}`).classList.add('fade-in');

    // Atualizar indicador
    document.getElementById(`step${step}`).classList.remove('ativa');
    document.getElementById(`step${step}`).classList.add('completa');
    document.getElementById(`step${step + 1}`).classList.add('ativa');

    stepAtual = step + 1;
}

function prevStep(step) {
    document.getElementById(`stepContent${step}`).style.display = 'none';
    document.getElementById(`stepContent${step - 1}`).style.display = 'block';
    document.getElementById(`stepContent${step - 1}`).classList.add('fade-in');

    document.getElementById(`step${step}`).classList.remove('ativa');
    document.getElementById(`step${step - 1}`).classList.remove('completa');
    document.getElementById(`step${step - 1}`).classList.add('ativa');

    stepAtual = step - 1;
}

function preencherRevisao() {
    const data = document.getElementById('data').value;
    const hora = document.getElementById('horaSelecionada').value;

    document.getElementById('reviewData').textContent = formatarData(data);
    document.getElementById('reviewHora').textContent = hora;
    document.getElementById('reviewNome').textContent = document.getElementById('nome').value;
    document.getElementById('reviewCc').textContent = document.getElementById('cc').value;
    document.getElementById('reviewTelemovel').textContent = document.getElementById('telemovel').value;
    document.getElementById('reviewEmail').textContent = document.getElementById('email').value;
}

function formatarData(dataISO) {
    const [ano, mes, dia] = dataISO.split('-');
    return `${dia}/${mes}/${ano}`;
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

// Formatação automática de inputs
document.getElementById('telemovel').addEventListener('input', function(e) {
    let value = e.target.value.replace(/[^0-9]/g, '');
    if (value.length >= 9) {
        value = value.replace(/(\d{3})(\d{3})(\d{3})/, '$1 $2 $3');
    } else if (value.length >= 6) {
        value = value.replace(/(\d{3})(\d{3})/, '$1 $2');
    } else if (value.length >= 3) {
        value = value.replace(/(\d{3})/, '$1 ');
    }
    e.target.value = value;
});

document.getElementById('cc').addEventListener('input', function(e) {
    let value = e.target.value.toUpperCase().replace(/[^0-9A-Z]/g, '');
    e.target.value = value;
});
</script>

<?php include 'includes/footer.php'; ?>
