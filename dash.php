<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: log.php');
    exit;
}

require_once 'connexion.php';

/*
 * Les données du tableau de bord proviennent de la base vereora.
 * Aucune donnée de démonstration n'est ajoutée.
 */
try {
    $sqlVentes = "
        SELECT
            v.id_vente,
            c.nom_client,
            v.date_vente,
            v.statut,
            v.mode_paiement,
            COALESCE(SUM(d.quantite * d.prix_unitaire), 0) AS montant,
            COALESCE(SUM(d.quantite * d.cout_unitaire), 0) AS cout
        FROM ventes v
        LEFT JOIN clients c ON c.id_client = v.id_client
        LEFT JOIN details_vente d ON d.id_vente = v.id_vente
        GROUP BY v.id_vente, c.nom_client, v.date_vente, v.statut, v.mode_paiement
        ORDER BY v.date_vente DESC
    ";
    $ventesDB = $pdo->query($sqlVentes)->fetchAll();

    $detailsDB = $pdo->query("
        SELECT d.id_vente, p.nom_produit, d.quantite
        FROM details_vente d
        INNER JOIN produits p ON p.id_produit = d.id_produit
    ")->fetchAll();

    $produitsParVente = [];
    foreach ($detailsDB as $detail) {
        $idVente = (int) $detail['id_vente'];
        if (!isset($produitsParVente[$idVente])) {
            $produitsParVente[$idVente] = [];
        }
        $produitsParVente[$idVente][] = [
            'nom' => $detail['nom_produit'],
            'qte' => (int) $detail['quantite']
        ];
    }

    $sales = [];
    foreach ($ventesDB as $vente) {
        $statut = $vente['statut'];
        if ($statut === 'validee') {
            $statutAffiche = 'Terminée';
        } elseif ($statut === 'en_attente') {
            $statutAffiche = 'En cours';
        } else {
            $statutAffiche = 'Annulée';
        }

        $sales[] = [
            'id' => 'V-' . (int) $vente['id_vente'],
            'client' => $vente['nom_client'] ?: 'Client non renseigné',
            'date' => date('c', strtotime($vente['date_vente'])),
            'montant' => (float) $vente['montant'],
            'cout' => (float) $vente['cout'],
            'produits' => $produitsParVente[(int) $vente['id_vente']] ?? [],
            'paiement' => $vente['mode_paiement'] ?: 'Non renseigné',
            'vente' => $statutAffiche,
            'statutDB' => $statut
        ];
    }

    $stockDB = $pdo->query("
        SELECT nom_produit, quantite_stock, seuil_alerte
        FROM produits
        ORDER BY nom_produit
    ")->fetchAll();

    // Objectifs enregistrés en base pour la date du jour.
    $objectifsDB = $pdo->query("
        SELECT id_objectif, periode, objectif_ventes, objectif_ca,
               objectif_benefice, objectif_panier_moyen
        FROM objectifs
        WHERE date_debut <= CURDATE() AND date_fin >= CURDATE()
        ORDER BY FIELD(periode, 'jour', 'mois', 'annee'),
                 date_debut DESC, id_objectif DESC
    ")->fetchAll();

    $objectifs = [];
    foreach ($objectifsDB as $objectif) {
        if (!isset($objectifs[$objectif['periode']])) {
            $objectifs[$objectif['periode']] = [
                'ventes' => (int) $objectif['objectif_ventes'],
                'ca' => (float) $objectif['objectif_ca'],
                'benefice' => (float) $objectif['objectif_benefice'],
                'panier' => (float) $objectif['objectif_panier_moyen']
            ];
        }
    }

    $stock = [];
    foreach ($stockDB as $produit) {
        $stock[] = [
            'nom' => $produit['nom_produit'],
            'qte' => (int) $produit['quantite_stock'],
            'seuil' => (int) $produit['seuil_alerte']
        ];
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    exit('Impossible de charger les données du tableau de bord. Vérifie la structure des tables de la base vereora.');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Véréora— Tableau de bord des ventes</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --forest: #0b3d2e;
            --emerald: #10b981;
            --emerald-soft: #d1fae5;
            --bg-app: #f1f3f6;
            --card-bg: #ffffff;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --border: #e5e7eb;
            --sidebar-width: 240px;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-app);
            color: var(--text-main);
            margin: 0;
            font-size: 14px;
        }

        /* ================= SIDEBAR ================= */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--forest);
            color: #e5e7eb;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            padding: 1.5rem 1rem;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transition: transform .3s ease;
        }
        .sidebar .brand {
            font-size: 1.3rem;
            font-weight: 700;
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-bottom: 2rem;
        }
        .sidebar .brand i { color: var(--emerald); }
        .sidebar .nav-link {
            color: #cbd5e1;
            padding: .65rem .9rem;
            border-radius: 6px;
            margin-bottom: .2rem;
            display: flex;
            align-items: center;
            gap: .7rem;
            font-size: .88rem;
            font-weight: 500;
            transition: background .2s;
        }
        .sidebar .nav-link:hover { background: rgba(255,255,255,.06); color: #fff; }
        .sidebar .nav-link.active { background: var(--emerald); color: #fff; }
        .sidebar .nav-link i { font-size: 1rem; }
        .sidebar .logout { margin-top: auto; color: #fca5a5; }

        /* ================= MAIN ================= */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 1.5rem 2rem;
            transition: margin .3s ease;
        }

        /* ================= HEADER ================= */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .page-header h1 { font-size: 1.4rem; font-weight: 600; margin: 0; }
        .page-header .greeting { font-size: .85rem; color: var(--text-muted); margin: .15rem 0 0; }

        .header-actions { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
        .header-actions .form-select {
            width: auto;
            font-size: .82rem;
            border-radius: 6px;
            border-color: var(--border);
            padding: .4rem 2rem .4rem .75rem;
            background-color: #fff;
        }
        .custom-range { display: flex; align-items: center; gap: .3rem; }
        .custom-range .form-control { font-size: .8rem; padding: .35rem .5rem; }

        .icon-btn {
            width: 36px; height: 36px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: #fff;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-muted);
            cursor: pointer;
            position: relative;
        }
        .icon-btn .dot {
            position: absolute; top: 7px; right: 9px;
            width: 7px; height: 7px;
            background: var(--danger);
            border-radius: 50%;
            border: 1.5px solid #fff;
        }
        .avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: var(--emerald);
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: .85rem;
            cursor: pointer;
        }

        /* ================= CARTES KPI ================= */
        .kpi-card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 1.1rem 1.2rem;
            border: 1px solid var(--border);
            height: 100%;
            display: flex;
            flex-direction: column;
            gap: .35rem;
        }
        .kpi-card .kpi-label {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--text-muted);
            font-weight: 600;
        }
        .kpi-card .kpi-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-main);
        }
        .kpi-card .kpi-objective {
            font-size: .78rem;
            color: var(--text-muted);
        }
        .kpi-card .kpi-status {
            font-size: .72rem;
            font-weight: 600;
            padding: .2rem .6rem;
            border-radius: 4px;
            align-self: flex-start;
            margin-top: .2rem;
        }
        .kpi-status.ok { background: var(--emerald-soft); color: #065f46; }
        .kpi-status.ko { background: #fee2e2; color: #991b1b; }

        /* ================= PANELS ================= */
        .panel {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 1.2rem 1.3rem;
            border: 1px solid var(--border);
            height: 100%;
        }
        .panel h5 {
            font-size: .95rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-main);
        }
        .chart-container { position: relative; width: 100%; }

        /* ================= SEGMENTED CONTROL ================= */
        .segmented {
            display: inline-flex;
            border: 1px solid var(--border);
            border-radius: 6px;
            overflow: hidden;
            background: #fff;
        }
        .segmented .seg {
            border: none;
            background: transparent;
            padding: .35rem .8rem;
            font-size: .8rem;
            font-weight: 500;
            color: var(--text-muted);
            cursor: pointer;
        }
        .segmented .seg.active {
            background: var(--forest);
            color: #fff;
        }
        .segmented .seg:not(:last-child) { border-right: 1px solid var(--border); }

        /* ================= ALERTES STOCK ================= */
        .alert-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: .65rem .75rem;
            border-radius: 6px;
            margin-bottom: .5rem;
            border-left: 3px solid;
            background: #fafafa;
        }
        .alert-item.low   { border-color: var(--warning); }
        .alert-item.critical { border-color: #ea580c; }
        .alert-item.out   { border-color: var(--danger); }
        .alert-item .name { font-weight: 600; font-size: .85rem; }
        .alert-item .meta { font-size: .72rem; color: var(--text-muted); }
        .alert-item .status-pill {
            font-size: .65rem;
            padding: .15rem .5rem;
            border-radius: 4px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-low { background: #fef3c7; color: #92400e; }
        .status-critical { background: #ffedd5; color: #9a3412; }
        .status-out { background: #fee2e2; color: #991b1b; }

        .empty-state {
            text-align: center;
            padding: 1.5rem 0;
            color: var(--text-muted);
            font-size: .85rem;
        }

        /* ================= TABLEAU ================= */
        .table { font-size: .82rem; margin-bottom: 0; }
        .table th {
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--text-muted);
            font-weight: 600;
            border-bottom: 1px solid var(--border);
            padding: .6rem .5rem;
        }
        .table td {
            padding: .7rem .5rem;
            vertical-align: middle;
            border-color: #f3f4f6;
        }
        .badge { padding: .3em .65em; border-radius: 5px; font-size: .7rem; font-weight: 600; }
        .bg-paid { background: #d1fae5; color: #065f46; }
        .bg-partial { background: #fef3c7; color: #92400e; }
        .bg-pending { background: #e5e7eb; color: #374151; }
        .bg-done { background: #dbeafe; color: #1e40af; }
        .bg-progress { background: #fef3c7; color: #92400e; }
        .bg-cancelled { background: #fee2e2; color: #991b1b; }

        /* ================= RESPONSIVE ================= */
        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1rem; }
        }
    </style>
</head>
<body>

<!-- ================= SIDEBAR ================= -->
<aside class="sidebar" id="sidebar">
    <a href="#" class="brand"><i class="bi bi-graph-up-arrow"></i> Véréora</a>
    <nav class="nav flex-column flex-grow-1">
        <a class="nav-link active" href="#"><i class="bi bi-grid-1x2"></i> Tableau de bord</a>
    <a class="nav-link logout" href="logout.php">
        <i class="bi bi-box-arrow-right"></i> Déconnexion
    </a>

</aside>

<!-- ================= MAIN ================= -->
<main class="main-content" id="mainContent">

    <!-- HEADER -->
    <header class="page-header">
        <div>
            <h1>Tableau de bord des ventes</h1>
            <p class="greeting">
                Bonjour, <?= htmlspecialchars($_SESSION['user_prenom'], ENT_QUOTES, 'UTF-8') ?>
                — Bienvenue sur votre tableau de bord
            </p>

        </div>
        <div class="header-actions">
            <select class="form-select" id="periodFilter">
                <option value="today" selected>Aujourd'hui</option>
                <option value="month">Ce mois-ci</option>
                <option value="custom">Période personnalisée</option>
            </select>

            <div id="customRange" class="custom-range d-none">
                <input type="date" class="form-control" id="dateStart">
                <span class="mx-1">→</span>
                <input type="date" class="form-control" id="dateEnd">
                <button class="btn btn-sm btn-primary" id="applyCustom">Appliquer</button>
            </div>

            <div class="avatar">V</div>
            <button class="icon-btn d-lg-none" id="menuToggle"><i class="bi bi-list"></i></button>
        </div>
    </header>

    <!-- ===== 4 CARTES KPI ===== -->
    <section class="row g-3 mb-4" id="kpiRow"></section>

    <!-- ===== ÉVOLUTION DU CA ===== -->
    <section class="row g-3 mb-4">
        <div class="col-12">
            <div class="panel">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <h5 class="mb-0">Évolution du chiffre d'affaires</h5>
                    <div class="segmented" id="revenueViewSwitcher">
                        <button class="seg active" data-view="week">Semaine</button>
                        <button class="seg" data-view="month">Mois</button>
                        <button class="seg" data-view="year">Année</button>
                    </div>
                </div>
                <div class="chart-container" style="height:300px">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== RÉPARTITION + TOP PRODUITS ===== -->
    <section class="row g-3 mb-4">
        <div class="col-lg-5">
            <div class="panel h-100">
                <h5>Répartition des quantités vendues par produit</h5>
                <div class="chart-container" style="height:280px">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="panel h-100">
                <h5>Produits les plus vendus</h5>
                <div class="chart-container" style="height:280px">
                    <canvas id="topProductsChart"></canvas>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== VENTES vs OBJECTIFS ===== -->
    <section class="row g-3 mb-4">
        <div class="col-12">
            <div class="panel">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <h5 class="mb-0">Nombre de ventes par période</h5>
                    <div class="segmented" id="objectivesViewSwitcher">
                        <button class="seg active" data-view="week">Semaine</button>
                        <button class="seg" data-view="month">Mois</button>
                        <button class="seg" data-view="year">Année</button>
                    </div>
                </div>
                <div class="chart-container" style="height:300px">
                    <canvas id="objectivesChart"></canvas>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== ALERTES STOCK + DERNIÈRES VENTES ===== -->
    <section class="row g-3 mb-4">
        <div class="col-lg-4">
            <div class="panel h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Alertes de stock</h5>
                    <span class="badge bg-danger" id="alertsCount">0</span>
                </div>
                <div id="stockAlerts"></div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="panel h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">Dernières ventes</h5>
                    <a href="#" class="btn btn-sm btn-outline-secondary">Voir toutes les ventes</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>N° vente</th>
                                <th>Client</th>
                                <th>Date & heure</th>
                                <th>Montant</th>
                                <th>Mode de paiement</th>
                                <th>Vente</th>
                            </tr>
                        </thead>
                        <tbody id="salesTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
/* =====================================================================
   TABLEAU DE BORD DES VENTES — Données issues de la base de données
   ===================================================================== */

/* ---------- DONNÉES RÉELLES DE LA BASE ---------- */
const sales = <?= json_encode($sales, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
const stock = <?= json_encode($stock, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
const objectives = <?= json_encode($objectifs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;

/* ---------- UTILITAIRES ---------- */
const fmt = (n) => n.toLocaleString('fr-FR');
const fmtF = (n) => fmt(n) + ' FCFA';

/* ---------- FILTRAGE PAR PÉRIODE ---------- */
let currentPeriod = 'today';
let customStart = null;
let customEnd = null;

function filterSalesByPeriod(includeAllStatuses = false) {
    const now = new Date();

    return sales.filter(s => {
        // Les indicateurs et graphiques utilisent uniquement les ventes validées.
        if (!includeAllStatuses && s.statutDB !== 'validee') return false;

        const d = new Date(s.date);

        if (currentPeriod === 'today') {
            return d.toDateString() === now.toDateString();
        }

        if (currentPeriod === 'month') {
            return d.getMonth() === now.getMonth()
                && d.getFullYear() === now.getFullYear();
        }

        if (currentPeriod === 'custom') {
            if (!customStart || !customEnd) return false;
            return d >= new Date(customStart + 'T00:00:00')
                && d <= new Date(customEnd + 'T23:59:59');
        }

        return true;
    });
}

/* ---------- KPI ET OBJECTIFS EN BASE ---------- */
function getCurrentObjective() {
    if (currentPeriod === 'today') return objectives.jour || null;
    if (currentPeriod === 'month') return objectives.mois || null;
    return null; // Pas d'objectif défini pour une période personnalisée.
}

function computeKPIs(list) {
    const nbVentes = list.length;
    const ca = list.reduce((total, vente) => total + vente.montant, 0);
    const benefice = list.reduce((total, vente) => total + (vente.montant - vente.cout), 0);
    const panier = nbVentes ? ca / nbVentes : 0;
    const objectif = getCurrentObjective();

    const kpis = [
        { label: 'Nombre de ventes validées', value: nbVentes, target: objectif ? objectif.ventes : null, format: n => fmt(n) },
        { label: 'Chiffre d’affaires', value: ca, target: objectif ? objectif.ca : null, format: n => fmtF(n) },
        { label: 'Bénéfice brut estimé', value: benefice, target: objectif ? objectif.benefice : null, format: n => fmtF(n) },
        { label: 'Panier moyen', value: panier, target: objectif ? objectif.panier : null, format: n => fmtF(Math.round(n)) }
    ];

    return kpis.map(k => {
        let objectiveText = 'Aucun objectif défini pour cette période';
        let statusText = '';
        let statusClass = '';
        if (k.target !== null) {
            objectiveText = 'Objectif : ' + k.format(k.target);
            const reached = k.value >= k.target;
            statusText = reached ? 'Objectif atteint' : 'Objectif non atteint';
            statusClass = reached ? 'ok' : 'ko';
        }
        return {
            label: k.label,
            value: k.format(k.value),
            objectiveText,
            statusText,
            statusClass
        };
    });
}

function renderKPIs() {
    const kpis = computeKPIs(filterSalesByPeriod());
    document.getElementById('kpiRow').innerHTML = kpis.map(k => `
        <div class="col-md-6 col-xl-3">
            <div class="kpi-card">
                <div class="kpi-label">${k.label}</div>
                <div class="kpi-value">${k.value}</div>
                <div class="kpi-objective">${k.objectiveText}</div>
                ${k.statusText ? `<span class="kpi-status ${k.statusClass}">${k.statusText}</span>` : ''}
            </div>
        </div>
    `).join('');
}

/* ---------- GRAPHIQUE ÉVOLUTION DU CHIFFRE D’AFFAIRES ---------- */
let revenueChart;

function aggregateSales(view, mode = 'amount') {
    const now = new Date();
    const list = sales.filter(s => s.statutDB === 'validee');
    const labels = [];
    const values = [];

    if (view === 'week') {
        const monday = new Date(now);
        monday.setHours(0, 0, 0, 0);
        monday.setDate(now.getDate() - ((now.getDay() + 6) % 7));

        for (let i = 0; i < 7; i++) {
            const day = new Date(monday);
            day.setDate(monday.getDate() + i);
            labels.push(day.toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit' }));
            values.push(sumSalesForPeriod(list, day, day, mode));
        }
    } else if (view === 'month') {
        const year = now.getFullYear();
        const month = now.getMonth();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        for (let dayNumber = 1; dayNumber <= daysInMonth; dayNumber++) {
            const day = new Date(year, month, dayNumber);
            labels.push(String(dayNumber));
            values.push(sumSalesForPeriod(list, day, day, mode));
        }
    } else {
        const year = now.getFullYear();
        const monthNames = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];

        for (let month = 0; month < 12; month++) {
            const first = new Date(year, month, 1);
            const last = new Date(year, month + 1, 0, 23, 59, 59);
            labels.push(monthNames[month]);
            values.push(sumSalesForPeriod(list, first, last, mode));
        }
    }

    return { labels, values };
}

function sumSalesForPeriod(list, start, end, mode) {
    const startDate = new Date(start);
    startDate.setHours(0, 0, 0, 0);
    const endDate = new Date(end);
    endDate.setHours(23, 59, 59, 999);

    return list.reduce((sum, sale) => {
        const date = new Date(sale.date);
        if (date < startDate || date > endDate) return sum;
        return sum + (mode === 'count' ? 1 : sale.montant);
    }, 0);
}

function renderRevenueChart(view = 'week') {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    const result = aggregateSales(view, 'amount');

    if (revenueChart) revenueChart.destroy();
    revenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: result.labels,
            datasets: [{
                label: 'Chiffre d’affaires',
                data: result.values,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16,185,129,.08)',
                borderWidth: 2.5,
                pointBackgroundColor: '#0b3d2e',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                tension: .35,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: c => fmtF(c.parsed.y) } }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: { callback: v => (v / 1000000).toFixed(1) + ' M' }
                },
                x: { grid: { display: false } }
            }
        }
    });
}

/* ---------- RÉPARTITION DU CA PAR PRODUIT ---------- */
let categoryChart;

function renderCategoryChart() {
    const totals = {};
    filterSalesByPeriod().forEach(sale => {
        sale.produits.forEach(produit => {
            totals[produit.nom] = (totals[produit.nom] || 0) + 0;
        });
    });

    // Les détails de vente permettent de compter les quantités
    filterSalesByPeriod().forEach(sale => {
        sale.produits.forEach(produit => {
            totals[produit.nom] = (totals[produit.nom] || 0) + produit.qte;
        });
    });

    if (categoryChart) categoryChart.destroy();
    categoryChart = new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(totals),
            datasets: [{
                data: Object.values(totals),
                backgroundColor: ['#0b3d2e', '#10b981', '#6ee7b7', '#a7f3d0', '#34d399', '#0f766e'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 12, font: { size: 11 } } },
                tooltip: { callbacks: { label: c => ` ${c.label} : ${fmt(c.parsed)} unité(s)` } }
            }
        }
    });
}

/* ---------- TOP PRODUITS ---------- */
let topProductsChart;

function renderTopProductsChart() {
    const totals = {};
    filterSalesByPeriod().forEach(sale => {
        sale.produits.forEach(produit => {
            totals[produit.nom] = (totals[produit.nom] || 0) + produit.qte;
        });
    });

    const sorted = Object.entries(totals).sort((a, b) => b[1] - a[1]).slice(0, 5);

    if (topProductsChart) topProductsChart.destroy();
    topProductsChart = new Chart(document.getElementById('topProductsChart'), {
        type: 'bar',
        data: {
            labels: sorted.map(item => item[0]),
            datasets: [{
                label: 'Quantités vendues',
                data: sorted.map(item => item[1]),
                backgroundColor: ['#0b3d2e', '#0f766e', '#10b981', '#34d399', '#6ee7b7'],
                borderRadius: 4,
                barThickness: 18
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                y: { grid: { display: false } }
            }
        }
    });
}

/* ---------- NOMBRE DE VENTES PAR PÉRIODE ---------- */
let objectivesChart;

function renderObjectivesChart(view = 'week') {
    const result = aggregateSales(view, 'count');

    if (objectivesChart) objectivesChart.destroy();
    objectivesChart = new Chart(document.getElementById('objectivesChart'), {
        type: 'bar',
        data: {
            labels: result.labels,
            datasets: [{
                label: 'Ventes validées',
                data: result.values,
                backgroundColor: '#10b981',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { usePointStyle: true, padding: 12 } },
                tooltip: { callbacks: { label: c => `${c.dataset.label} : ${fmt(c.parsed.y)}` } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } },
                x: { grid: { display: false } }
            }
        }
    });
}

/* ---------- ALERTES STOCK ---------- */
function renderStockAlerts() {
    const container = document.getElementById('stockAlerts');
    const badge = document.getElementById('alertsCount');
    const alerts = [];

    stock.forEach(p => {
        let css = null, label = '', pill = '';
        if (p.qte === 0) { css='out'; label='Rupture de stock'; pill='status-out'; }
        else if (p.qte <= Math.max(1, Math.floor(p.seuil / 2))) { css='critical'; label='Rupture imminente'; pill='status-critical'; }
        else if (p.qte <= p.seuil) { css='low'; label='Stock faible'; pill='status-low'; }
        if (css) alerts.push({ ...p, css, label, pill });
    });

    const order = { out:0, critical:1, low:2 };
    alerts.sort((a,b) => order[a.css] - order[b.css]);
    badge.textContent = alerts.length;

    if (!alerts.length) {
        container.innerHTML = `<div class="empty-state"><i class="bi bi-check-circle me-1"></i> Aucune alerte de stock pour le moment.</div>`;
        return;
    }

    container.innerHTML = alerts.map(a => `
        <div class="alert-item ${a.css}">
            <div>
                <div class="name">${a.nom}</div>
                <div class="meta">Dispo : ${a.qte} — Seuil : ${a.seuil}</div>
                <div class="meta text-danger fw-semibold">Réapprovisionner</div>
            </div>
            <span class="status-pill ${a.pill}">${a.label}</span>
        </div>
    `).join('');
}

/* ---------- TABLEAU VENTES ---------- */
function renderSalesTable() {
    const list = [...filterSalesByPeriod(true)]
        .sort((a, b) => new Date(b.date) - new Date(a.date))
        .slice(0, 10);
    const tbody = document.getElementById('salesTableBody');

    if (!list.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-3">Aucune vente sur cette période.</td></tr>`;
        return;
    }

    const venteClasses = {
        'Terminée': 'bg-done',
        'En cours': 'bg-progress',
        'Annulée': 'bg-cancelled'
    };

    tbody.innerHTML = list.map(s => `
        <tr>
            <td><strong>${s.id}</strong></td>
            <td>${escapeHtml(s.client)}</td>
            <td>${new Date(s.date).toLocaleString('fr-FR', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            })}</td>
            <td>${fmtF(s.montant)}</td>
            <td>${escapeHtml(s.paiement)}</td>
            <td><span class="badge ${venteClasses[s.vente] || 'bg-pending'}">${s.vente}</span></td>
        </tr>
    `).join('');
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[character]));
}

/* ---------- RAFRAÎCHISSEMENT GLOBAL ---------- */
function refreshAll() {
    renderKPIs();
    renderCategoryChart();
    renderTopProductsChart();
    renderSalesTable();
    renderStockAlerts();
}

/* ---------- ÉVÉNEMENTS ---------- */
document.getElementById('periodFilter').addEventListener('change', (e) => {
    currentPeriod = e.target.value;
    document.getElementById('customRange').classList.toggle('d-none', currentPeriod !== 'custom');
    if (currentPeriod !== 'custom') refreshAll();
});

document.getElementById('applyCustom').addEventListener('click', () => {
    customStart = document.getElementById('dateStart').value;
    customEnd = document.getElementById('dateEnd').value;
    if (customStart && customEnd) refreshAll();
});

document.querySelectorAll('#revenueViewSwitcher .seg').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#revenueViewSwitcher .seg').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderRevenueChart(btn.dataset.view);
    });
});

document.querySelectorAll('#objectivesViewSwitcher .seg').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#objectivesViewSwitcher .seg').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderObjectivesChart(btn.dataset.view);
    });
});

document.getElementById('menuToggle').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('show');
});

/* ---------- INITIALISATION ---------- */
document.addEventListener('DOMContentLoaded', () => {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('dateStart').value = today;
    document.getElementById('dateEnd').value = today;

    refreshAll();
    renderRevenueChart('week');
    renderObjectivesChart('week');
});
</script>
</body>
</html>
