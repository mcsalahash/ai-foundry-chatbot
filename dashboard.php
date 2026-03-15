<?php
/**
 * dashboard.php — Tableau de bord de supervision des jobs VTOM
 *
 * Affiche l'état en temps quasi-réel des jobs ordonnancés :
 * statut (ERREUR / HORAIRE DÉPASSÉ / OK / EN COURS / EN ATTENTE),
 * trigramme application, environnement, unité de soumission,
 * lien vers le fichier log et lien vers la consigne.
 */

require_once 'security.php';
require_once 'jobs_data.php';

$nonce = base64_encode(random_bytes(16));
sendSecurityHeaders($nonce);

$jobs  = getJobsData();
$stats = getJobsStats($jobs);

// Libellés et classes CSS par statut
$statutConfig = [
    'ERREUR'          => ['label' => 'ERREUR',           'class' => 'badge-erreur',    'icon' => '✕'],
    'HORAIRE_DEPASSE' => ['label' => 'HORAIRE DÉPASSÉ',  'class' => 'badge-depasse',   'icon' => '⏱'],
    'OK'              => ['label' => 'OK',               'class' => 'badge-ok',        'icon' => '✓'],
    'EN_COURS'        => ['label' => 'EN COURS',         'class' => 'badge-en-cours',  'icon' => '↻'],
    'EN_ATTENTE'      => ['label' => 'EN ATTENTE',       'class' => 'badge-attente',   'icon' => '…'],
];

$envConfig = [
    'PROD'    => 'env-prod',
    'PREPROD' => 'env-preprod',
    'REC'     => 'env-rec',
    'DEV'     => 'env-dev',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervision VTOM — Tableau de bord</title>
    <style nonce="<?= htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') ?>">
        /* ── Reset & base ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Header ── */
        header {
            background: #1e293b;
            border-bottom: 1px solid #334155;
            padding: 0.9rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .header-left { display: flex; align-items: center; gap: 0.75rem; }
        header h1 { font-size: 1.05rem; font-weight: 600; white-space: nowrap; }
        header .subtitle { font-size: 0.78rem; color: #64748b; }

        .header-actions { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }

        .btn {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.45rem 0.9rem;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: background 0.15s, color 0.15s;
            white-space: nowrap;
        }
        .btn-primary { background: #3b82f6; color: #fff; }
        .btn-primary:hover { background: #2563eb; }
        .btn-secondary { background: #1e293b; color: #94a3b8; border: 1px solid #334155; }
        .btn-secondary:hover { background: #334155; color: #e2e8f0; }
        .btn-ghost { background: transparent; color: #64748b; border: 1px solid #334155; }
        .btn-ghost:hover { background: #1e293b; color: #e2e8f0; }

        .refresh-indicator {
            font-size: 0.78rem;
            color: #64748b;
        }

        /* ── Compteurs de statut ── */
        .stats-bar {
            display: flex;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            flex-wrap: wrap;
        }

        .stat-card {
            flex: 1 1 120px;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
            min-width: 100px;
        }
        .stat-card .stat-value {
            font-size: 1.7rem;
            font-weight: 700;
            line-height: 1;
        }
        .stat-card .stat-label {
            font-size: 0.72rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-total    .stat-value { color: #e2e8f0; }
        .stat-erreur   .stat-value { color: #f87171; }
        .stat-depasse  .stat-value { color: #fb923c; }
        .stat-ok       .stat-value { color: #4ade80; }
        .stat-en-cours .stat-value { color: #60a5fa; }
        .stat-attente  .stat-value { color: #a78bfa; }

        /* ── Barre de filtres ── */
        .filter-bar {
            display: flex;
            gap: 0.75rem;
            padding: 0 1.5rem 1rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-bar input[type="search"],
        .filter-bar select {
            background: #1e293b;
            border: 1px solid #334155;
            color: #e2e8f0;
            padding: 0.45rem 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
            outline: none;
            transition: border-color 0.15s;
        }
        .filter-bar input[type="search"] { width: 260px; }
        .filter-bar input[type="search"]:focus,
        .filter-bar select:focus { border-color: #3b82f6; }
        .filter-bar select option { background: #1e293b; }

        .filter-bar label { font-size: 0.82rem; color: #64748b; }

        .filter-count {
            font-size: 0.8rem;
            color: #64748b;
            margin-left: auto;
        }

        /* ── Tableau ── */
        .table-wrapper {
            flex: 1;
            overflow-x: auto;
            padding: 0 1.5rem 2rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        thead th {
            background: #1e293b;
            color: #94a3b8;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 0.7rem 0.9rem;
            text-align: left;
            border-bottom: 1px solid #334155;
            white-space: nowrap;
            cursor: pointer;
            user-select: none;
        }
        thead th:hover { color: #e2e8f0; }
        thead th .sort-icon { margin-left: 4px; opacity: 0.4; }
        thead th.sorted   .sort-icon { opacity: 1; }

        tbody tr {
            border-bottom: 1px solid #1e293b;
            transition: background 0.1s;
        }
        tbody tr:hover { background: #1e293b; }
        tbody tr.hidden-row { display: none; }

        tbody td {
            padding: 0.7rem 0.9rem;
            vertical-align: middle;
        }

        .job-name {
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 0.82rem;
            color: #e2e8f0;
            font-weight: 500;
        }

        .trigrame {
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 0.82rem;
            font-weight: 700;
            color: #38bdf8;
            background: rgba(56, 189, 248, 0.1);
            border-radius: 4px;
            padding: 0.15rem 0.4rem;
            display: inline-block;
        }

        /* Badges statut */
        .badge {
            display: inline-flex; align-items: center; gap: 0.3rem;
            font-size: 0.72rem; font-weight: 700;
            letter-spacing: 0.04em;
            padding: 0.3rem 0.65rem;
            border-radius: 5px;
            white-space: nowrap;
        }
        .badge-erreur    { background: rgba(239,68,68,0.15);  color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
        .badge-depasse   { background: rgba(249,115,22,0.15); color: #fb923c; border: 1px solid rgba(249,115,22,0.3); }
        .badge-ok        { background: rgba(34,197,94,0.12);  color: #4ade80; border: 1px solid rgba(34,197,94,0.25); }
        .badge-en-cours  { background: rgba(59,130,246,0.15); color: #60a5fa; border: 1px solid rgba(59,130,246,0.3); }
        .badge-attente   { background: rgba(139,92,246,0.15); color: #a78bfa; border: 1px solid rgba(139,92,246,0.3); }

        /* Badges environnement */
        .env-badge {
            font-size: 0.7rem; font-weight: 700;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .env-prod    { background: rgba(239,68,68,0.1);  color: #f87171; }
        .env-preprod { background: rgba(249,115,22,0.1); color: #fb923c; }
        .env-rec     { background: rgba(234,179,8,0.1);  color: #facc15; }
        .env-dev     { background: rgba(34,197,94,0.1);  color: #4ade80; }

        .unite {
            font-size: 0.78rem;
            color: #94a3b8;
            font-family: 'Consolas', 'Courier New', monospace;
        }

        .timestamp {
            font-size: 0.78rem;
            color: #64748b;
            white-space: nowrap;
        }

        .commentaire {
            font-size: 0.78rem;
            color: #94a3b8;
            font-style: italic;
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Liens log & consigne */
        .link-log, .link-consigne {
            display: inline-flex; align-items: center; gap: 0.3rem;
            font-size: 0.78rem;
            padding: 0.3rem 0.6rem;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.15s;
            white-space: nowrap;
        }
        .link-log {
            color: #94a3b8;
            background: rgba(148,163,184,0.08);
            border: 1px solid #334155;
        }
        .link-log:hover { background: #1e293b; color: #e2e8f0; }

        .link-consigne {
            color: #60a5fa;
            background: rgba(59,130,246,0.08);
            border: 1px solid rgba(59,130,246,0.25);
        }
        .link-consigne:hover { background: rgba(59,130,246,0.15); }

        .no-link {
            font-size: 0.78rem;
            color: #475569;
        }

        /* ── Ligne « aucun résultat » ── */
        #no-results {
            display: none;
            text-align: center;
            padding: 2rem;
            color: #475569;
            font-size: 0.9rem;
        }

        /* ── Pied de page ── */
        footer {
            background: #1e293b;
            border-top: 1px solid #334155;
            padding: 0.6rem 1.5rem;
            font-size: 0.75rem;
            color: #475569;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        /* ── Animation icône rafraîchissement ── */
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinning { display: inline-block; animation: spin 1s linear infinite; }
    </style>
</head>
<body>

<!-- ══════════════════════════════════════════════
     EN-TÊTE
══════════════════════════════════════════════ -->
<header>
    <div class="header-left">
        <div>
            <h1>&#9881; Supervision VTOM — Jobs ordonnancés</h1>
            <div class="subtitle">
                Environnement de production &bull;
                Dernière mise à jour : <span id="last-update"><?= date('d/m/Y H:i:s') ?></span>
            </div>
        </div>
    </div>
    <div class="header-actions">
        <span class="refresh-indicator">Actualisation auto dans <span id="countdown">60</span>s</span>
        <button class="btn btn-ghost" id="btn-refresh" onclick="refreshPage()" title="Actualiser maintenant">
            <span id="refresh-icon">↻</span> Actualiser
        </button>
        <button class="btn btn-secondary" onclick="exportCSV()" title="Exporter en CSV">
            &#8659; Export CSV
        </button>
        <a href="index.php" class="btn btn-secondary" title="Retour au chatbot">
            &#8592; Chatbot
        </a>
    </div>
</header>

<!-- ══════════════════════════════════════════════
     COMPTEURS
══════════════════════════════════════════════ -->
<div class="stats-bar">
    <div class="stat-card stat-total">
        <span class="stat-value"><?= $stats['total'] ?></span>
        <span class="stat-label">Total jobs</span>
    </div>
    <div class="stat-card stat-erreur">
        <span class="stat-value"><?= $stats['erreur'] ?></span>
        <span class="stat-label">En erreur</span>
    </div>
    <div class="stat-card stat-depasse">
        <span class="stat-value"><?= $stats['horaire_depasse'] ?></span>
        <span class="stat-label">Horaire dépassé</span>
    </div>
    <div class="stat-card stat-ok">
        <span class="stat-value"><?= $stats['ok'] ?></span>
        <span class="stat-label">Terminés OK</span>
    </div>
    <div class="stat-card stat-en-cours">
        <span class="stat-value"><?= $stats['en_cours'] ?></span>
        <span class="stat-label">En cours</span>
    </div>
    <div class="stat-card stat-attente">
        <span class="stat-value"><?= $stats['en_attente'] ?></span>
        <span class="stat-label">En attente</span>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     FILTRES
══════════════════════════════════════════════ -->
<div class="filter-bar">
    <label for="search">Recherche :</label>
    <input
        type="search"
        id="search"
        placeholder="Nom du job, trigramme, unité…"
        oninput="applyFilters()"
        autocomplete="off"
    >

    <label for="filter-statut">Statut :</label>
    <select id="filter-statut" onchange="applyFilters()">
        <option value="">Tous</option>
        <option value="ERREUR">Erreur</option>
        <option value="HORAIRE_DEPASSE">Horaire dépassé</option>
        <option value="OK">OK</option>
        <option value="EN_COURS">En cours</option>
        <option value="EN_ATTENTE">En attente</option>
    </select>

    <label for="filter-env">Environnement :</label>
    <select id="filter-env" onchange="applyFilters()">
        <option value="">Tous</option>
        <?php
        $envs = array_unique(array_column($jobs, 'environnement'));
        sort($envs);
        foreach ($envs as $env):
        ?>
        <option value="<?= htmlspecialchars($env, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($env, ENT_QUOTES, 'UTF-8') ?>
        </option>
        <?php endforeach; ?>
    </select>

    <label for="filter-trigrame">Trigramme :</label>
    <select id="filter-trigrame" onchange="applyFilters()">
        <option value="">Tous</option>
        <?php
        $trigrames = array_unique(array_column($jobs, 'trigrame'));
        sort($trigrames);
        foreach ($trigrames as $tri):
        ?>
        <option value="<?= htmlspecialchars($tri, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($tri, ENT_QUOTES, 'UTF-8') ?>
        </option>
        <?php endforeach; ?>
    </select>

    <span class="filter-count" id="filter-count">
        <?= count($jobs) ?> job(s) affiché(s)
    </span>
</div>

<!-- ══════════════════════════════════════════════
     TABLEAU
══════════════════════════════════════════════ -->
<div class="table-wrapper">
    <table id="jobs-table">
        <thead>
            <tr>
                <th onclick="sortTable(0)" data-col="0">Nom du job <span class="sort-icon">↕</span></th>
                <th onclick="sortTable(1)" data-col="1">Trigramme <span class="sort-icon">↕</span></th>
                <th onclick="sortTable(2)" data-col="2">Environnement <span class="sort-icon">↕</span></th>
                <th onclick="sortTable(3)" data-col="3">Unité de soumission <span class="sort-icon">↕</span></th>
                <th onclick="sortTable(4)" data-col="4">Statut <span class="sort-icon">↕</span></th>
                <th onclick="sortTable(5)" data-col="5">Dernière exécution <span class="sort-icon">↕</span></th>
                <th>Fichier log</th>
                <th>Consigne</th>
                <th>Commentaire</th>
            </tr>
        </thead>
        <tbody id="jobs-tbody">
        <?php foreach ($jobs as $job):
            $sc  = $statutConfig[$job['statut']] ?? ['label' => $job['statut'], 'class' => 'badge-attente', 'icon' => '?'];
            $ec  = $envConfig[$job['environnement']] ?? 'env-dev';
            $log = $job['fichier_log'];
            $ts  = $job['derniere_execution'] ? date('d/m/Y H:i', strtotime($job['derniere_execution'])) : '—';
        ?>
        <tr
            data-statut="<?= htmlspecialchars($job['statut'], ENT_QUOTES, 'UTF-8') ?>"
            data-env="<?= htmlspecialchars($job['environnement'], ENT_QUOTES, 'UTF-8') ?>"
            data-trigrame="<?= htmlspecialchars($job['trigrame'], ENT_QUOTES, 'UTF-8') ?>"
            data-search="<?= htmlspecialchars(
                strtolower($job['nom'] . ' ' . $job['trigrame'] . ' ' . $job['unite_soumission'] . ' ' . $job['commentaire']),
                ENT_QUOTES, 'UTF-8'
            ) ?>"
        >
            <td><span class="job-name"><?= htmlspecialchars($job['nom'], ENT_QUOTES, 'UTF-8') ?></span></td>
            <td><span class="trigrame"><?= htmlspecialchars($job['trigrame'], ENT_QUOTES, 'UTF-8') ?></span></td>
            <td><span class="env-badge <?= $ec ?>"><?= htmlspecialchars($job['environnement'], ENT_QUOTES, 'UTF-8') ?></span></td>
            <td><span class="unite"><?= htmlspecialchars($job['unite_soumission'], ENT_QUOTES, 'UTF-8') ?></span></td>
            <td>
                <span class="badge <?= $sc['class'] ?>">
                    <?= $sc['icon'] ?> <?= htmlspecialchars($sc['label'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            </td>
            <td><span class="timestamp"><?= htmlspecialchars($ts, ENT_QUOTES, 'UTF-8') ?></span></td>
            <td>
                <?php if ($log): ?>
                <a href="#" class="link-log" title="<?= htmlspecialchars($log, ENT_QUOTES, 'UTF-8') ?>"
                   onclick="openLog(event, <?= json_encode($log) ?>)">
                    &#128196; Log
                </a>
                <?php else: ?>
                <span class="no-link">—</span>
                <?php endif; ?>
            </td>
            <td>
                <a href="<?= htmlspecialchars($job['consigne'], ENT_QUOTES, 'UTF-8') ?>"
                   target="_blank" rel="noopener noreferrer" class="link-consigne">
                    &#128196; Consigne &#8599;
                </a>
            </td>
            <td>
                <span class="commentaire" title="<?= htmlspecialchars($job['commentaire'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= $job['commentaire'] ? htmlspecialchars($job['commentaire'], ENT_QUOTES, 'UTF-8') : '<span class="no-link">—</span>' ?>
                </span>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div id="no-results">Aucun job ne correspond aux filtres sélectionnés.</div>
</div>

<!-- ══════════════════════════════════════════════
     PIED DE PAGE
══════════════════════════════════════════════ -->
<footer>
    <span>Ordonnanceur VTOM — Supervision des jobs de production</span>
    <span><?= date('d/m/Y') ?> &bull; <?= count($jobs) ?> jobs chargés</span>
</footer>

<!-- ══════════════════════════════════════════════
     JAVASCRIPT
══════════════════════════════════════════════ -->
<script nonce="<?= htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') ?>">
    // ── Données JSON pour l'export CSV ──
    const jobsData = <?= json_encode($jobs, JSON_UNESCAPED_UNICODE) ?>;

    // ── Countdown + auto-refresh ──
    let countdown = 60;
    const countdownEl = document.getElementById('countdown');

    const timer = setInterval(() => {
        countdown--;
        if (countdownEl) countdownEl.textContent = countdown;
        if (countdown <= 0) refreshPage();
    }, 1000);

    function refreshPage() {
        clearInterval(timer);
        const icon = document.getElementById('refresh-icon');
        if (icon) icon.classList.add('spinning');
        window.location.reload();
    }

    // ── Filtres ──
    function applyFilters() {
        const search   = document.getElementById('search').value.toLowerCase().trim();
        const statut   = document.getElementById('filter-statut').value;
        const env      = document.getElementById('filter-env').value;
        const trigrame = document.getElementById('filter-trigrame').value;

        const rows  = document.querySelectorAll('#jobs-tbody tr');
        let visible = 0;

        rows.forEach(row => {
            const matchSearch   = !search   || row.dataset.search.includes(search);
            const matchStatut   = !statut   || row.dataset.statut   === statut;
            const matchEnv      = !env      || row.dataset.env      === env;
            const matchTrigrame = !trigrame || row.dataset.trigrame  === trigrame;

            const show = matchSearch && matchStatut && matchEnv && matchTrigrame;
            row.classList.toggle('hidden-row', !show);
            if (show) visible++;
        });

        const countEl = document.getElementById('filter-count');
        if (countEl) countEl.textContent = visible + ' job(s) affiché(s)';

        const noRes = document.getElementById('no-results');
        if (noRes) noRes.style.display = visible === 0 ? 'block' : 'none';
    }

    // ── Tri des colonnes ──
    let sortState = { col: -1, asc: true };

    function sortTable(col) {
        const tbody = document.getElementById('jobs-tbody');
        const rows  = Array.from(tbody.querySelectorAll('tr:not(.hidden-row)'));
        const allRows = Array.from(tbody.querySelectorAll('tr'));

        const asc = sortState.col === col ? !sortState.asc : true;
        sortState = { col, asc };

        // Mise à jour des icônes dans l'en-tête
        document.querySelectorAll('thead th').forEach((th, i) => {
            th.classList.toggle('sorted', i === col);
            const icon = th.querySelector('.sort-icon');
            if (icon) {
                if (i === col) icon.textContent = asc ? '↑' : '↓';
                else icon.textContent = '↕';
            }
        });

        // Tri uniquement sur les lignes visibles
        rows.sort((a, b) => {
            const va = a.cells[col]?.textContent.trim() ?? '';
            const vb = b.cells[col]?.textContent.trim() ?? '';
            return asc
                ? va.localeCompare(vb, 'fr', { numeric: true })
                : vb.localeCompare(va, 'fr', { numeric: true });
        });

        // Replacement : lignes visibles triées, puis lignes cachées en dernier
        const hidden = allRows.filter(r => r.classList.contains('hidden-row'));
        [...rows, ...hidden].forEach(r => tbody.appendChild(r));
    }

    // ── Ouverture du log (chemin serveur → copie dans le presse-papier ou alerte) ──
    function openLog(event, path) {
        event.preventDefault();
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(path).then(() => {
                showToast('Chemin copié : ' + path);
            }).catch(() => {
                showToast('Log : ' + path);
            });
        } else {
            showToast('Log : ' + path);
        }
    }

    // ── Toast de notification ──
    function showToast(msg) {
        const toast = document.createElement('div');
        Object.assign(toast.style, {
            position: 'fixed', bottom: '1.5rem', right: '1.5rem',
            background: '#1e293b', border: '1px solid #334155',
            color: '#e2e8f0', padding: '0.6rem 1rem',
            borderRadius: '8px', fontSize: '0.82rem',
            boxShadow: '0 4px 12px rgba(0,0,0,0.4)',
            zIndex: 9999, maxWidth: '400px',
            wordBreak: 'break-all',
            opacity: '0', transition: 'opacity 0.2s',
        });
        toast.textContent = msg;
        document.body.appendChild(toast);
        requestAnimationFrame(() => { toast.style.opacity = '1'; });
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.addEventListener('transitionend', () => toast.remove());
        }, 3500);
    }

    // ── Export CSV ──
    function exportCSV() {
        const headers = [
            'Nom du job', 'Trigramme', 'Environnement',
            'Unité de soumission', 'Statut',
            'Dernière exécution', 'Fichier log', 'Consigne', 'Commentaire'
        ];

        const statutLabels = {
            'ERREUR': 'ERREUR', 'HORAIRE_DEPASSE': 'HORAIRE DÉPASSÉ',
            'OK': 'OK', 'EN_COURS': 'EN COURS', 'EN_ATTENTE': 'EN ATTENTE'
        };

        const rows = jobsData.map(j => [
            j.nom, j.trigrame, j.environnement,
            j.unite_soumission, statutLabels[j.statut] || j.statut,
            j.derniere_execution || '',
            j.fichier_log || '',
            j.consigne,
            j.commentaire || ''
        ]);

        const escape = v => '"' + String(v).replace(/"/g, '""') + '"';
        const csv = [headers, ...rows].map(r => r.map(escape).join(';')).join('\r\n');

        const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = 'supervision_vtom_' + new Date().toISOString().slice(0, 10) + '.csv';
        a.click();
        URL.revokeObjectURL(url);
    }

    // Filtre initial : affiche uniquement erreurs + horaires dépassés au chargement
    // (décommentez les lignes ci-dessous si vous voulez ce comportement par défaut)
    // document.getElementById('filter-statut').value = 'ERREUR';
    // applyFilters();
</script>

</body>
</html>
