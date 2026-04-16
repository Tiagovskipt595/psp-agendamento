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

            // Buscar ID do agendamento criado
            $stmt = $db->prepare("SELECT id FROM agendamentos WHERE codigo_agendamento = ?");
            $stmt->execute([$codigo]);
            $agendamentoId = $stmt->fetch()['id'];

            $emailEnviado = false;
            $smsEnviado = false;

            // Enviar email de confirmação
            try {
                $resultadoEmail = enviarEmailConfirmacao($codigo, $db);
                if ($resultadoEmail) {
                    $emailEnviado = true;
                    error_log("Email de confirmação enviado para: " . $email);
                }
            } catch (Exception $e) {
                error_log("Erro ao enviar email: " . $e->getMessage());
            }

            // Enviar SMS de confirmação (se estiver ativo)
            try {
                if (file_exists(__DIR__ . '/../config/sms.php')) {
                    require_once __DIR__ . '/../config/sms.php';
                    if (defined('SMS_ENABLED') && SMS_ENABLED) {
                        $resultadoSms = enviarSMSConfirmacao($codigo, $db);
                        if ($resultadoSms) {
                            $smsEnviado = true;
                            error_log("SMS de confirmação enviado para: " . $telemovel);
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Erro ao enviar SMS: " . $e->getMessage());
            }

            // Atualizar tracking de notificações na base de dados
            $stmt = $db->prepare("UPDATE agendamentos SET
                notificacao_email_enviada = ?,
                notificacao_sms_enviada = ?,
                data_notificacao_email = NOW(),
                data_notificacao_sms = NOW()
                WHERE id = ?");
            $stmt->execute([$emailEnviado ? 1 : 0, $smsEnviado ? 1 : 0, $agendamentoId]);

            logSeguranca('agendamento_criado', ['codigo' => $codigo, 'email' => $email, 'email_enviado' => $emailEnviado, 'sms_enviado' => $smsEnviado]);
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
                    <label>📅 Selecione a Data *</label>
                    <div class="calendario" id="calendarioWidget">
                        <div class="calendario-header">
                            <button type="button" class="calendario-nav-btn" onclick="mudarMes(-1)">❮</button>
                            <h3 id="calendarioMes"><?= date('F Y') ?></h3>
                            <button type="button" class="calendario-nav-btn" onclick="mudarMes(1)">❯</button>
                        </div>
                        <div class="dias-semana">
                            <span>Seg</span><span>Ter</span><span>Qua</span><span>Qui</span><span>Sex</span><span>Sáb</span><span>Dom</span>
                        </div>
                        <div class="dias-mes" id="calendarioDias">
                            <!-- Dias gerados por JavaScript -->
                        </div>
                    </div>
                    <input type="hidden" id="data" name="data" required>
                </div>

                <div class="form-group" id="horariosSection" style="display: none;">
                    <label for="hora">🕐 Horários Disponíveis para <span id="dataSelecionadaLabel"></span> *</label>
                    <div id="slotsHorario" class="slots-horario">
                        <p style="color: var(--text-light);">Selecione uma data primeiro</p>
                    </div>
                    <input type="hidden" id="horaSelecionada" name="hora" required>
                </div>

                <div class="text-right mt-2">
                    <button type="button" class="btn btn-primary" id="btnContinuarStep1" onclick="nextStep(1)" disabled>Continuar ➝</button>
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

// Variáveis do calendário
let mesAtual = new Date();
let disponibilidadeMensal = {};
let dataSelecionadaCalendario = null;

// Nomes dos meses em português
const mesesPT = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

// Inicializar calendário ao carregar
document.addEventListener('DOMContentLoaded', () => {
    renderizarCalendario();
    carregarDisponibilidadeMensal();
});

// Carregar disponibilidade do mês inteiro
async function carregarDisponibilidadeMensal() {
    const mes = mesAtual.toISOString().slice(0, 7); // YYYY-MM
    try {
        const response = await fetch(`api_calendario.php?esquadra_id=${esquadraId}&mes=${mes}&servico_id=${servicoId}`);
        disponibilidadeMensal = await response.json();
        renderizarCalendario();
    } catch (e) {
        console.error('Erro ao carregar disponibilidade:', e);
    }
}

// Renderizar calendário
function renderizarCalendario() {
    const container = document.getElementById('calendarioDias');
    const mesLabel = document.getElementById('calendarioMes');

    const ano = mesAtual.getFullYear();
    const mes = mesAtual.getMonth();

    mesLabel.textContent = `${mesesPT[mes]} ${ano}`;

    // Primeiro dia do mês e total de dias
    const primeiroDia = new Date(ano, mes, 1);
    const ultimoDia = new Date(ano, mes + 1, 0);
    const totalDias = ultimoDia.getDate();

    // Dia da semana do primeiro dia (0=Domingo, convertir para segunda=0)
    let diaSemanaPrimeiro = primeiroDia.getDay() - 1;
    if (diaSemanaPrimeiro < 0) diaSemanaPrimeiro = 6;

    // Data de hoje
    const hoje = new Date();
    hoje.setHours(0, 0, 0, 0);

    let html = '';

    // Células vazias antes do primeiro dia
    for (let i = 0; i < diaSemanaPrimeiro; i++) {
        html += '<div class="dia vazio"></div>';
    }

    // Dias do mês
    for (let dia = 1; dia <= totalDias; dia++) {
        const dataCompleta = `${ano}-${String(mes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
        const dataObj = new Date(ano, mes, dia);

        let classes = ['dia'];
        let conteudo = dia;

        // Verificar se é hoje
        if (dataObj.getTime() === hoje.getTime()) {
            classes.push('hoje');
        }

        // Verificar disponibilidade
        if (disponibilidadeMensal[dataCompleta]) {
            const info = disponibilidadeMensal[dataCompleta];
            if (!info.disponivel) {
                classes.push('desabilitado');
            } else if (info.estado === 'limitado') {
                classes.push('com-agendamentos');
            }
        } else {
            classes.push('desabilitado');
        }

        // Verificar se está selecionado
        if (dataSelecionadaCalendario === dataCompleta) {
            classes.push('selecionado');
        }

        // Verificar se é passado
        if (dataObj < hoje) {
            classes.push('desabilitado');
        }

        html += `<div class="${classes.join(' ')}" onclick="selecionarData('${dataCompleta}', this)">${conteudo}</div>`;
    }

    container.innerHTML = html;
}

// Navegação entre meses
function mudarMes(direcao) {
    mesAtual.setMonth(mesAtual.getMonth() + direcao);
    dataSelecionadaCalendario = null;
    document.getElementById('data').value = '';
    document.getElementById('horaSelecionada').value = '';
    document.getElementById('horariosSection').style.display = 'none';
    document.getElementById('btnContinuarStep1').disabled = true;
    carregarDisponibilidadeMensal();
}

// Selecionar data
function selecionarData(data, element) {
    // Remover seleção anterior
    document.querySelectorAll('.dia.selecionado').forEach(el => el.classList.remove('selecionado'));

    // Verificar se está desabilitado
    if (element.classList.contains('desabilitado')) {
        return;
    }

    // Selecionar novo
    element.classList.add('selecionado');
    dataSelecionadaCalendario = data;
    document.getElementById('data').value = data;

    // Mostrar seção de horários
    const [ano, mes, dia] = data.split('-');
    const dataFormatada = `${dia}/${mes}/${ano}`;
    document.getElementById('dataSelecionadaLabel').textContent = dataFormatada;
    document.getElementById('horariosSection').style.display = 'block';

    // Carregar horários
    carregarHorarios(data);
}

// Carregar horários disponíveis
async function carregarHorarios(data) {
    const container = document.getElementById('slotsHorario');
    container.innerHTML = '<div class="skeleton" style="height: 100px;"></div>';
    document.getElementById('horaSelecionada').value = '';

    try {
        const response = await fetch(`api_horarios.php?esquadra_id=${esquadraId}&data=${data}&duracao=${duracao}`);
        const horarios = await response.json();

        container.innerHTML = '';

        if (horarios.length === 0) {
            container.innerHTML = '<p style="color: var(--text-light);">⚠️ Não há horários disponíveis para esta data</p>';
            return;
        }

        let html = '';
        let temManha = false;
        let temTarde = false;

        horarios.forEach(hora => {
            const horaNum = parseInt(hora.hora.split(':')[0]);
            const periodo = horaNum < 12 ? 'manha' : 'tarde';

            if (periodo === 'manha' && !temManha) {
                html += '<div style="grid-column: 1/-1; margin-bottom: 10px;"><strong>Manhã</strong></div>';
                temManha = true;
            }
            if (periodo === 'tarde' && !temTarde) {
                html += '<div style="grid-column: 1/-1; margin: 10px 0;"><strong>Tarde</strong></div>';
                temTarde = true;
            }

            const classeSlot = hora.disponivel ? 'slot' : 'slot ocupado';
            const onclick = hora.disponivel ? `selecionarHora(this, '${hora.hora}')` : '';
            html += `<div class="${classeSlot}" ${onclick}>${hora.hora}</div>`;
        });

        container.innerHTML = html;

        // Verificar se já tem hora selecionada
        const horaSelecionada = document.getElementById('horaSelecionada').value;
        if (horaSelecionada) {
            document.querySelectorAll('.slot').forEach(slot => {
                if (slot.textContent.trim() === horaSelecionada) {
                    slot.classList.add('selecionado');
                }
            });
        }
    } catch (e) {
        console.error('Erro ao carregar horários:', e);
        container.innerHTML = '<p style="color: var(--danger);">Erro ao carregar horários. Tente novamente.</p>';
    }
}

function selecionarHora(element, hora) {
    document.querySelectorAll('.slot').forEach(s => s.classList.remove('selecionado'));
    element.classList.add('selecionado');
    document.getElementById('horaSelecionada').value = hora;
    document.getElementById('btnContinuarStep1').disabled = false;
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
