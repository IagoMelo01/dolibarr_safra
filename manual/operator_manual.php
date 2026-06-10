<?php
/*
 * Short operator manual for the Safra module.
 */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
    $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'] . '/main.inc.php';
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--;
    $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . '/main.inc.php')) {
    $res = @include substr($tmp, 0, ($i + 1)) . '/main.inc.php';
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . '/main.inc.php')) {
    $res = @include dirname(substr($tmp, 0, ($i + 1))) . '/main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
    $res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
    $res = @include '../../../main.inc.php';
}
if (!$res) {
    die('Include of main fails');
}

global $langs, $user;

$langs->loadLangs(array('safra@safra'));

if (!($user->rights->safra->SafraActivity->read ?? 0)) {
    accessforbidden();
}

$urlActivities = dol_buildpath('/safra/activity/activity_list.php', 1);
$urlNewActivity = dol_buildpath('/safra/activity/activity_card.php', 1) . '?action=create';
$urlAgenda = dol_buildpath('/safra/activity/activity_kanban.php', 1);
$urlConsumption = dol_buildpath('/safra/report/input_consumption.php', 1);
$urlDashboard = dol_buildpath('/safra/safraindex.php', 1);

llxHeader('', $langs->trans('SafraOperatorManual'), '', '', 0, 0, array(), array('/safra/css/safra.css.php'));
?>
<main class="safra-manual">
    <section class="safra-manual-hero">
        <div>
            <span class="safra-manual-kicker">Guia rápido para produtor e equipe de campo</span>
            <h1>Como operar o módulo Safra</h1>
            <p>Planeje o trabalho, registre o que aconteceu no campo, controle os insumos e guarde fotos ou comprovantes na própria atividade.</p>
            <div class="safra-manual-actions">
                <a class="button safra-btn-primary" href="<?php echo $urlNewActivity; ?>"><span class="fas fa-plus"></span> Criar atividade</a>
                <a class="button safra-btn-secondary" href="<?php echo $urlAgenda; ?>"><span class="fas fa-columns"></span> Abrir agenda</a>
            </div>
        </div>
        <div class="safra-manual-rule">
            <strong>Regra simples</strong>
            <span>Uma atividade representa uma operação agrícola em um talhão. Registre nela o planejamento, a execução, os insumos e as evidências.</span>
        </div>
    </section>

    <nav class="safra-manual-shortcuts" aria-label="Atalhos do manual">
        <a href="#fluxo">Fluxo da plataforma</a>
        <a href="#atividade">Criar e executar</a>
        <a href="#insumos">Insumos e estoque</a>
        <a href="#evidencias">Anexos e fotos</a>
        <a href="#acompanhar">Agenda e relatórios</a>
        <a href="#duvidas">Dúvidas rápidas</a>
    </nav>

    <section id="fluxo" class="safra-manual-section">
        <div class="safra-manual-heading">
            <span class="fas fa-route"></span>
            <div><h2>Fluxo da plataforma</h2><p>Siga esta ordem para manter os dados simples e confiáveis.</p></div>
        </div>
        <div class="safra-flow">
            <a href="<?php echo $urlDashboard; ?>"><span>1</span><strong>Preparar</strong><small>Cadastre talhões, culturas, produtos e armazéns.</small></a>
            <i class="fas fa-chevron-right"></i>
            <a href="<?php echo $urlNewActivity; ?>"><span>2</span><strong>Planejar</strong><small>Crie a atividade, escolha talhão, data, área e operação.</small></a>
            <i class="fas fa-chevron-right"></i>
            <a href="<?php echo $urlActivities; ?>"><span>3</span><strong>Executar</strong><small>Inicie, informe equipe, máquinas e quantidades usadas.</small></a>
            <i class="fas fa-chevron-right"></i>
            <a href="#evidencias"><span>4</span><strong>Comprovar</strong><small>Anexe receituários, comprovantes, fotos e ocorrências.</small></a>
            <i class="fas fa-chevron-right"></i>
            <a href="<?php echo $urlConsumption; ?>"><span>5</span><strong>Acompanhar</strong><small>Conclua e confira agenda, estoque e consumo.</small></a>
        </div>
        <div class="safra-status-flow">
            <div><span class="safra-status-dot safra-status-dot--planned"></span><strong>Planejada</strong><small>Ainda não começou.</small></div>
            <i class="fas fa-arrow-right"></i>
            <div><span class="safra-status-dot safra-status-dot--running"></span><strong>Em execução</strong><small>O trabalho está acontecendo.</small></div>
            <i class="fas fa-arrow-right"></i>
            <div><span class="safra-status-dot safra-status-dot--done"></span><strong>Concluída</strong><small>Execução e registros finalizados.</small></div>
        </div>
    </section>

    <section id="atividade" class="safra-manual-section">
        <div class="safra-manual-heading">
            <span class="fas fa-tractor"></span>
            <div><h2>Criar e executar uma atividade</h2><p>Os campos obrigatórios são poucos. Complete as demais abas conforme a operação exigir.</p></div>
        </div>
        <ol class="safra-step-list">
            <li><span>1</span><div><strong>Abra Operações &gt; Atividades &gt; Nova atividade.</strong><p>Informe nome, tipo de operação, talhão, início planejado e área.</p></div></li>
            <li><span>2</span><div><strong>Salve antes de preencher as outras abas.</strong><p>Depois de salvar, ficam disponíveis Insumos, Cálculo de calda, Equipe, Veículos, Implementos e Anexos.</p></div></li>
            <li><span>3</span><div><strong>Revise o planejamento e clique em Iniciar atividade.</strong><p>Use o status “Em execução” quando o serviço realmente começar.</p></div></li>
            <li><span>4</span><div><strong>Registre o realizado.</strong><p>Atualize área executada, quantidades usadas, horas e observações relevantes.</p></div></li>
            <li><span>5</span><div><strong>Anexe as evidências e conclua.</strong><p>Antes de concluir, confira insumos, notas, fotos e documentos.</p></div></li>
        </ol>
        <div class="safra-manual-callout"><span class="fas fa-copy"></span><div><strong>Mesma operação em vários talhões?</strong><p>Abra a atividade modelo e use <b>Duplicar atividade</b>. O módulo cria uma atividade planejada por talhão e recalcula os insumos pela área.</p></div></div>
    </section>

    <section id="insumos" class="safra-manual-section">
        <div class="safra-manual-heading">
            <span class="fas fa-boxes"></span>
            <div><h2>Insumos e estoque</h2><p>Cada linha salva na aba Insumos movimenta o estoque imediatamente.</p></div>
        </div>
        <div class="safra-manual-grid">
            <article><span class="fas fa-plus-circle"></span><h3>Adicionar</h3><p>Escolha produto, armazém, área, dose e quantidade executada. Confira antes de salvar.</p></article>
            <article><span class="fas fa-pencil-alt"></span><h3>Corrigir</h3><p>Ao editar produto, armazém, dose ou quantidade, o movimento anterior é estornado e um novo é criado.</p></article>
            <article><span class="fas fa-times-circle"></span><h3>Cancelar</h3><p>Cancelar a atividade estorna os movimentos ativos. O histórico permanece para conferência.</p></article>
        </div>
        <div class="safra-manual-warning"><span class="fas fa-exclamation-triangle"></span><div><strong>Antes de salvar um insumo</strong><p>Confirme o produto, o armazém e a quantidade. Se houver dúvida, pare e consulte o responsável pelo estoque.</p></div></div>
    </section>

    <section id="evidencias" class="safra-manual-section">
        <div class="safra-manual-heading">
            <span class="fas fa-camera"></span>
            <div><h2>Anexos, fotos e ocorrências</h2><p>Guarde a evidência junto da atividade para facilitar rastreabilidade e auditoria.</p></div>
        </div>
        <div class="safra-manual-grid">
            <article><span class="fas fa-file-prescription"></span><h3>Receituários</h3><p>Anexe o PDF ou uma foto legível do documento usado na operação.</p></article>
            <article><span class="fas fa-receipt"></span><h3>Comprovantes</h3><p>Inclua notas, ordens, tickets e demais documentos relacionados.</p></article>
            <article><span class="fas fa-seedling"></span><h3>Fotos de campo</h3><p>Na aba Anexos, use “Tirar foto” no celular ou envie imagens já existentes.</p></article>
            <article><span class="fas fa-exclamation-circle"></span><h3>Ocorrências</h3><p>Descreva a ocorrência nas observações da aba Geral e anexe fotos ou documentos que comprovem o fato.</p></article>
        </div>
        <div class="safra-manual-callout"><span class="fas fa-tag"></span><div><strong>Use nomes claros nos arquivos</strong><p>Exemplos: <code>receituario-aplicacao.pdf</code>, <code>foto-falha-plantio-01.jpg</code> e <code>comprovante-descarte.pdf</code>.</p></div></div>
    </section>

    <section id="acompanhar" class="safra-manual-section">
        <div class="safra-manual-heading">
            <span class="fas fa-chart-line"></span>
            <div><h2>Acompanhar o trabalho</h2><p>Use as telas de gestão para decidir o que precisa de atenção.</p></div>
        </div>
        <div class="safra-manual-grid">
            <article><span class="fas fa-columns"></span><h3>Agenda de atividades</h3><p>Mostra atividades planejadas, em execução e atrasadas. Filtre por safra, cultura, talhão, tipo e período.</p><a href="<?php echo $urlAgenda; ?>">Abrir agenda</a></article>
            <article><span class="fas fa-list"></span><h3>Lista de atividades</h3><p>Localize uma atividade, abra seu card e revise todos os registros.</p><a href="<?php echo $urlActivities; ?>">Abrir atividades</a></article>
            <article><span class="fas fa-chart-bar"></span><h3>Consumo de insumos</h3><p>Veja quanto foi usado por safra, cultura, talhão e produto, com exportação CSV.</p><a href="<?php echo $urlConsumption; ?>">Abrir consumo</a></article>
        </div>
    </section>

    <section id="duvidas" class="safra-manual-section">
        <div class="safra-manual-heading">
            <span class="fas fa-question-circle"></span>
            <div><h2>Dúvidas rápidas</h2><p>Respostas para situações comuns da operação.</p></div>
        </div>
        <div class="safra-faq">
            <details><summary>Posso corrigir uma quantidade de insumo?</summary><p>Sim. Edite a linha na aba Insumos. O sistema estorna o movimento anterior e cria o movimento corrigido.</p></details>
            <details><summary>O que acontece ao cancelar uma atividade?</summary><p>Os movimentos de estoque ainda ativos são estornados. Os registros históricos continuam disponíveis.</p></details>
            <details><summary>Quando devo reabrir uma atividade?</summary><p>Somente quando precisar corrigir ou complementar uma atividade já concluída ou cancelada. Revise novamente antes de concluir.</p></details>
            <details><summary>Onde registro um problema encontrado no campo?</summary><p>Descreva a ocorrência nas observações da aba Geral e anexe fotos ou documentos na aba Anexos.</p></details>
            <details><summary>Quem pode alterar uma atividade?</summary><p>Apenas usuários com permissão de escrita no módulo Safra. Peça ajuste de acesso ao administrador quando necessário.</p></details>
        </div>
    </section>

    <section class="safra-manual-checklist">
        <h2>Checklist antes de concluir</h2>
        <label><input type="checkbox"> Área executada conferida</label>
        <label><input type="checkbox"> Insumos, armazéns e quantidades conferidos</label>
        <label><input type="checkbox"> Equipe, veículos e implementos registrados quando necessário</label>
        <label><input type="checkbox"> Ocorrências descritas</label>
        <label><input type="checkbox"> Fotos, receituários e comprovantes anexados</label>
    </section>
</main>
<?php
llxFooter();
