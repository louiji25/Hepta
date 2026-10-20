<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Compta Web - HEPTA</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

  <div id="loading-overlay">Chargement fluide en cours...</div>
  <div id="toast" class="toast"></div>

  <header>
    <h1>Compta Web - HEPTA</h1>
    <div class="nav-tabs">
      <button class="nav-tab active" onclick="switchTab('dashboard', this)">Dashboard</button>
      <button class="nav-tab" onclick="switchTab('journal', this)">Journal Saisie</button>
      <button class="nav-tab" onclick="switchTab('balance', this)">Balance des Comptes</button>
      <button class="nav-tab" onclick="openReglementModal('REGLEMENT')">Règlement Fs.</button>
      <button class="nav-tab" onclick="openReglementModal('ENCAISSEMENT')">Encaissement Cl.</button>
      <button class="nav-tab" onclick="switchTab('tiers', this)">Table Tiers</button>
      <button class="nav-tab" onclick="switchTab('plan', this)">Plan Comptable</button>
      <button class="nav-tab" onclick="switchTab('categories', this)">Catégories</button>
      <button class="nav-tab" onclick="initApp()">Initialiser BDD</button>
    </div>
  </header>

  <div class="container">

    <!-- TAB 1 : DASHBOARD -->
    <div id="tab-dashboard" class="tab-content active">
      <div class="toolbar">
        <div class="toolbar-group">
          <label style="margin:0;">Période :</label>
          <select id="global-period-type" onchange="loadDashboardData()">
            <option value="annuel">Annuel</option>
            <option value="mensuel">Mensuel</option>
          </select>
          <input type="text" id="global-period-val" value="<?= date('Y') ?>" style="width: 100px;" onchange="loadDashboardData()">
        </div>
        <button class="btn btn-primary" onclick="loadDashboardData()">Actualiser</button>
      </div>

      <div class="kpi-grid">
        <div class="kpi-card success">
          <div class="kpi-title">Chiffre d'Affaires</div>
          <div class="kpi-value" id="kpi-ca">0,00 Ar</div>
        </div>
        <div class="kpi-card danger">
          <div class="kpi-title">Total Charges</div>
          <div class="kpi-value" id="kpi-charges">0,00 Ar</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Résultat Comptable</div>
          <div class="kpi-value" id="kpi-resultat">0,00 Ar</div>
        </div>
        <div class="kpi-card warning">
          <div class="kpi-title">Volume Ventes</div>
          <div class="kpi-value" id="kpi-vol-ventes">0,00 Ar</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Écritures</div>
          <div class="kpi-value" id="kpi-nb-ecritures">0</div>
        </div>
      </div>

      <div class="chart-container">
        <h3>Évolution Produits, Charges et Résultat</h3>
        <canvas id="mainFinancialChart"></canvas>
      </div>
    </div>

    <!-- TAB 2 : JOURNAL DE SAISIE -->
    <div id="tab-journal" class="tab-content">
      <div class="toolbar">
        <div class="toolbar-group">
          <button class="btn btn-primary" onclick="openSaisieModal()">+ Nouvelle Saisie</button>
          <input type="text" id="search-input" placeholder="Rechercher..." style="width: 200px;" onkeyup="applyFilters()">
        </div>
        <div class="toolbar-group">
          <select id="filter-journal" onchange="applyFilters()">
            <option value="ALL">Tous les journaux</option>
            <option value="HA">HA - Achats</option>
            <option value="VT">VT - Ventes</option>
            <option value="BQ">BQ - Banque</option>
            <option value="CA">CA - Caisse</option>
          </select>
          <input type="date" id="filter-date-debut" onchange="applyFilters()">
          <input type="date" id="filter-date-fin" onchange="applyFilters()">
        </div>
      </div>

      <div class="table-container">
        <table class="grid-table">
          <thead>
            <tr>
              <th style="width: 90px;">Date</th>
              <th style="width: 60px;">Jnl</th>
              <th style="width: 100px;">N° Pièce</th>
              <th style="width: 90px;">Compte</th>
              <th style="width: 150px;">Tiers</th>
              <th>Libellé écriture</th>
              <th style="width: 150px;" class="num">Débit</th>
              <th style="width: 150px;" class="num">Crédit</th>
              <th style="width: 80px;">Statut</th>
            </tr>
          </thead>
          <tbody id="journal-tbody">
            <tr><td colspan="9" style="text-align:center;">Chargement du journal...</td></tr>
          </tbody>
          <tfoot>
            <tr style="background-color: #eaedd0; font-weight: bold; border-top: 2px solid #bdc3c7;">
              <td colspan="6" style="text-align: right; padding: 10px; font-weight: bold;">Totaux :</td>
              <td class="num" id="total-debit" style="padding: 10px; font-weight: bold;">0,00</td>
              <td class="num" id="total-credit" style="padding: 10px; font-weight: bold;">0,00</td>
              <td style="padding: 10px;"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- TAB 3 : BALANCE DES COMPTES -->
    <div id="tab-balance" class="tab-content">
      <div class="toolbar">
        <h2>Balance Générale des Comptes</h2>
        <div class="toolbar-group">
          <button class="btn btn-success" onclick="telechargerBalanceExcel()">📥 Exporter Balance (CSV)</button>
          <button class="btn btn-secondary" onclick="loadBalanceData()">Actualiser</button>
        </div>
      </div>

      <div class="table-container">
        <table class="grid-table">
          <thead>
            <tr>
              <th style="width: 100px;">Compte</th>
              <th>Libellé du Compte</th>
              <th style="width: 130px;" class="num">Mvt Débit</th>
              <th style="width: 130px;" class="num">Mvt Crédit</th>
              <th style="width: 130px;" class="num">Solde Débiteur</th>
              <th style="width: 130px;" class="num">Solde Créditeur</th>
            </tr>
          </thead>
          <tbody id="balance-tbody">
            <tr><td colspan="6" style="text-align:center;">Chargement de la balance...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    
    <!-- TAB 4 : TIERS -->
    <div id="tab-tiers" class="tab-content">
      <div class="toolbar">
        <h2>Gestion des Tiers (Clients / Fournisseurs)</h2>
        <div class="toolbar-group">
          <button class="btn btn-primary" onclick="openTiersModal()">+ Nouveau Tiers</button>
          <button class="btn btn-secondary" onclick="loadInitialData()">Actualiser</button>
        </div>
      </div>
      <div class="table-container">
        <table class="grid-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Type</th>
              <th>Nom / Raison Sociale</th>
              <th>NIF / Référence</th>
              <th>Email</th>
              <th>Téléphone</th>
              <th>Adresse</th>
              <th style="width: 100px; text-align: center;">Actions</th>
            </tr>
          </thead>
          <tbody id="tiers-tbody">
            <tr><td colspan="8" style="text-align:center;">Chargement...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB 5 : PLAN COMPTABLE -->
    <div id="tab-plan" class="tab-content">
      <div class="toolbar">
        <h2>Plan Comptable Général</h2>
        <div class="toolbar-group">
          <button class="btn btn-primary" onclick="openPlanModal()">+ Nouveau Compte</button>
          <button class="btn btn-secondary" onclick="loadInitialData()">Actualiser</button>
        </div>
      </div>
      <div class="table-container">
        <table class="grid-table">
          <thead>
            <tr>
              <th style="width: 120px;">Compte</th>
              <th>Libellé du Compte</th>
              <th>Type</th>
              <th>Statut</th>
              <th style="width: 100px; text-align: center;">Actions</th>
            </tr>
          </thead>
          <tbody id="plan-tbody">
            <tr><td colspan="5" style="text-align:center;">Chargement...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB 6 : CATEGORIES -->
    <div id="tab-categories" class="tab-content">
      <div class="toolbar">
        <h2>Gestion des Catégories d'Opérations</h2>
        <div class="toolbar-group">
          <button class="btn btn-primary" onclick="openCategorieModal()">+ Nouvelle Catégorie</button>
          <button class="btn btn-secondary" onclick="loadInitialData()">Actualiser</button>
        </div>
      </div>
      <div class="table-container">
        <table class="grid-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Libellé</th>
              <th>Type Opération</th>
              <th>Compte Comptable</th>
              <th class="num">Taux TVA (%)</th>
              <th>Compte TVA</th>
              <th style="width: 100px; text-align: center;">Actions</th>
            </tr>
          </thead>
          <tbody id="categories-tbody">
            <tr><td colspan="7" style="text-align:center;">Chargement...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <!-- MODAL SAISIE -->
  <div class="modal-backdrop" id="modal-saisie">
    <div class="modal">
      <div class="modal-header">
        <h3>Nouvelle Saisie Comptable</h3>
        <button class="close-btn" onclick="closeModal('modal-saisie')">&times;</button>
      </div>
      <div class="modal-body">
        <form id="form-saisie">
          <div class="form-grid">
            <div class="form-group">
              <label>Type d'opération</label>
              <select id="saisie-type" onchange="onTypeOperationChange()">
                <option value="HA">Facture d'achat fournisseur</option>
                <option value="VT">Facture de vente client</option>
                <option value="DEPENSE">Dépense courante</option>
              </select>
            </div>
            <div class="form-group">
              <label>Date</label>
              <input type="date" id="saisie-date" required>
            </div>
            <div class="form-group">
              <label>N° Pièce</label>
              <input type="text" id="saisie-piece" required>
            </div>
          </div>
          <div class="form-grid">
            <div class="form-group" id="grp-tiers">
              <label>Tiers*</label>
              <div class="input-with-btn">
                <select id="saisie-tiers" style="flex:1;"></select>
                <button type="button" class="btn btn-secondary btn-icon" onclick="openTiersModal()" title="Créer un nouveau tiers">+</button>
              </div>
            </div>
            <div class="form-group">
              <label>Catégorie Opération *</label>
              <select id="saisie-categorie" onchange="onCategorieChange()"></select>
            </div>
            <div class="form-group" id="grp-tresorerie" style="display:none;">
              <label>Compte Trésorerie *</label>
              <select id="saisie-tresorerie"></select>
            </div>
            <div class="form-group placeholder-col"></div>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label>Montant HT</label>
              <input type="number" step="0.01" id="saisie-ht" oninput="calculerTVA()" required>
            </div>
            <div class="form-group">
              <label>Montant TVA</label>
              <input type="number" step="0.01" id="saisie-tva" oninput="calculerTTC()">
            </div>
            <div class="form-group">
              <label>Montant TTC</label>
              <input type="number" step="0.01" id="saisie-ttc" readonly style="background:#e9ecef;">
            </div>
          </div>
          <div class="form-group">
            <label>Libellé</label>
            <input type="text" id="saisie-libelle" required>
          </div>
          <div class="form-grid" id="grp-mode-reglement" style="display:none;">
            <div class="form-group">
              <label>Mode de règlement *</label>
              <select id="saisie-mode-reglement" onchange="onModeReglementChange()">
                <option value="DIFFERE">À crédit / Différé</option>
                <option value="IMMEDIAT">Règlement immédiat (Comptant)</option>
                <option value="PARTIEL">Règlement partiel</option>
              </select>
            </div>
            <div class="form-group" id="grp-saisie-tresorerie" style="display:none;">
              <label>Compte Trésorerie *</label>
              <select id="saisie-tresorerie-reglement"></select>
            </div>
            <div class="form-group" id="grp-saisie-montant-paye" style="display:none;">
              <label>Montant payé (Immédiat) *</label>
              <input type="number" step="0.01" id="saisie-montant-paye" value="0">
            </div>
          </div>
          <div class="form-grid" id="grp-details-reglement" style="display:none;">
            <div class="form-group">
              <label>Moyen de paiement</label>
              <select id="saisie-moyen-paiement">
                <option value="VIREMENT">Virement</option>
                <option value="ESPECES">Espèces</option>
                <option value="CHEQUE">Chèque</option>
                <option value="CARTE">Carte Bancaire</option>
              </select>
            </div>
            <div class="form-group">
              <label>Réf. Règlement</label>
              <input type="text" id="saisie-ref-reglement" placeholder="N° chèque / virement">
            </div>
            <div class="form-group placeholder-col"></div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" onclick="closeModal('modal-saisie')">Annuler</button>
        <button class="btn btn-success" onclick="submitSaisie()">Enregistrer</button>
      </div>
    </div>
  </div>

  <!-- MODAL SECONDAIRE CREATION TIERS -->
  <div class="modal-backdrop" id="modal-tiers">
    <div class="modal modal-secondary">
      <div class="modal-header">
        <h3>Créer un Nouveau Tiers</h3>
        <button class="close-btn" onclick="closeModal('modal-tiers')">&times;</button>
      </div>
      <div class="modal-body">
        <form id="form-tiers">
          <div class="form-group" style="margin-bottom:10px;">
            <label>Type de Tiers *</label>
            <select id="tiers-type">
              <option value="FOURNISSEUR">Fournisseur</option>
              <option value="CLIENT">Client</option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:10px;">
            <label>Nom / Raison Sociale *</label>
            <input type="text" id="tiers-nom" required>
          </div>
          <div class="form-group" style="margin-bottom:10px;">
            <label>NIF</label>
            <input type="text" id="tiers-ref">
          </div>
          <div class="form-group" style="margin-bottom:10px;">
            <label>Email</label>
            <input type="email" id="tiers-email">
          </div>
          <div class="form-group" style="margin-bottom:10px;">
            <label>Téléphone</label>
            <input type="text" id="tiers-tel">
          </div>
          <div class="form-group" style="margin-bottom:10px;">
            <label>Adresse</label>
            <input type="text" id="tiers-adresse">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" onclick="closeModal('modal-tiers')">Annuler</button>
        <button class="btn btn-primary" onclick="submitTiers()">Créer Tiers</button>
      </div>
    </div>
  </div>

  <!-- MODAL REGLEMENT -->
  <div class="modal-backdrop" id="modal-reglement">
    <div class="modal">
      <div class="modal-header">
        <h3 id="modal-reglement-title">Règlement</h3>
        <button class="close-btn" onclick="closeModal('modal-reglement')">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Tiers</label>
            <select id="reglement-tiers" onchange="loadFacturesOuvertes()"></select>
          </div>
          <div class="form-group">
            <label>Date</label>
            <input type="date" id="reglement-date">
          </div>
          <div class="form-group">
            <label>Trésorerie</label>
            <select id="reglement-tresorerie"></select>
          </div>
        </div>
        <div class="table-container" style="margin-top:15px;">
          <table class="grid-table">
            <thead>
              <tr>
                <th style="width:30px;"><input type="checkbox"></th>
                <th>Date</th>
                <th>Pièce</th>
                <th>Libellé</th>
                <th class="num">Solde Dû</th>
                <th class="num">Montant Payé</th>
              </tr>
            </thead>
            <tbody id="reglement-factures-tbody">
              <tr><td colspan="6" style="text-align:center;">Sélectionnez un tiers.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" onclick="closeModal('modal-reglement')">Annuler</button>
        <button class="btn btn-success" onclick="submitReglement()">Valider</button>
      </div>
    </div>
  </div>

  <!-- MODAL PLAN COMPTABLE -->
  <div class="modal-backdrop" id="modal-plan">
    <div class="modal modal-secondary">
      <div class="modal-header">
        <h3 id="modal-plan-title">Nouveau Compte Comptable</h3>
        <button class="close-btn" onclick="closeModal('modal-plan')">&times;</button>
      </div>
      <div class="modal-body">
        <form id="form-plan">
          <input type="hidden" id="plan-is-edit" value="false">
          <div class="form-group" style="margin-bottom:10px;">
            <label>N° de Compte *</label>
            <input type="text" id="plan-compte" required>
          </div>
          <div class="form-group" style="margin-bottom:10px;">
            <label>Libellé *</label>
            <input type="text" id="plan-libelle" required>
          </div>
          <div class="form-group" style="margin-bottom:10px;">
            <label>Type de Compte *</label>
            <select id="plan-type">
              <option value="CHARGE">CHARGE</option>
              <option value="PRODUIT">PRODUIT</option>
              <option value="TIERS">TIERS</option>
              <option value="TRESORERIE">TRESORERIE</option>
              <option value="CAPITAL">CAPITAL</option>
              <option value="TAXE">TAXE</option>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" onclick="closeModal('modal-plan')">Annuler</button>
        <button class="btn btn-primary" onclick="submitPlanCompte()">Enregistrer</button>
      </div>
    </div>
  </div>

  <!-- MODAL CATEGORIE -->
  <div class="modal-backdrop" id="modal-categorie">
    <div class="modal modal-secondary">
      <div class="modal-header">
        <h3 id="modal-cat-title">Nouvelle Catégorie</h3>
        <button class="close-btn" onclick="closeModal('modal-categorie')">&times;</button>
      </div>
      <div class="modal-body">
        <form id="form-categorie">
          <input type="hidden" id="cat-is-edit" value="false">
          <input type="hidden" id="cat-id" value="">
          <div class="form-group" style="margin-bottom:10px;">
            <label>Libellé *</label>
            <input type="text" id="cat-libelle" required>
          </div>
          <div class="form-group" style="margin-bottom:10px;">
            <label>Type d'Opération *</label>
            <select id="cat-type-op">
              <option value="HA">HA (Achat)</option>
              <option value="VT">VT (Vente)</option>
              <option value="DEPENSE">DEPENSE</option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:10px;">
            <label>Compte Comptable *</label>
            <input type="text" id="cat-compte" required>
          </div>
          <div class="form-group" style="margin-bottom:10px;">
            <label>Taux TVA (%)</label>
            <input type="number" step="0.01" id="cat-taux-tva" value="20">
          </div>
          <div class="form-group" style="margin-bottom:10px;">
            <label>Compte TVA</label>
            <input type="text" id="cat-compte-tva" value="445660">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" onclick="closeModal('modal-categorie')">Annuler</button>
        <button class="btn btn-primary" onclick="submitCategorieData()">Enregistrer</button>
      </div>
    </div>
  </div>

  <script src="assets/js/app.js"></script>
</body>
</html>