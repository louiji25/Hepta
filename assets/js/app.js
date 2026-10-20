let APP_STATE = { tiers: [], categories: [], tresorerie: [], balanceData: [], activeReglementType: 'REGLEMENT' };
let mainFinancialChartInstance = null;

window.onload = function() {
  document.getElementById('saisie-date').valueAsDate = new Date();
  document.getElementById('reglement-date').valueAsDate = new Date();
  loadInitialData();
};

function showLoading(show) { 
  document.getElementById('loading-overlay').style.display = show ? 'flex' : 'none'; 
}

function showToast(msg, isError) {
  const toast = document.getElementById('toast');
  toast.textContent = msg;
  toast.className = 'toast ' + (isError ? 'toast-error' : 'toast-success');
  toast.style.display = 'block';
  setTimeout(() => { toast.style.display = 'none'; }, 3000);
}

async function apiCall(action, data = {}, method = 'POST') {
  try {
    let url = `api/api.php?action=${action}`;
    let options = { method: method, headers: { 'Content-Type': 'application/json' } };
    if (method === 'POST') {
      options.body = JSON.stringify(data);
    }
    const response = await fetch(url, options);
    return await response.json();
  } catch (err) {
    return { success: false, message: "Erreur réseau ou serveur : " + err.message };
  }
}

function switchTab(tabId, btn) {
  document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.nav-tab').forEach(el => el.classList.remove('active'));
  document.getElementById('tab-' + tabId).classList.add('active');
  if (btn) btn.classList.add('active');

  if (tabId === 'dashboard') loadDashboardData();
  else if (tabId === 'balance') loadBalanceData();
  else if (tabId === 'journal') refreshJournalTable();
  else if (tabId === 'tiers') renderTiersTable();
  else if (tabId === 'plan') renderPlanTable();
  else if (tabId === 'categories') renderCategoriesTable();
}

function renderTiersTable() {
  const tbody = document.getElementById('tiers-tbody');
  tbody.innerHTML = '';
  const actifs = APP_STATE.tiers.filter(t => t.STATUT === 'ACTIF');
  if (actifs.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">Aucun tiers enregistré.</td></tr>';
    return;
  }
  actifs.forEach(t => {
    tbody.innerHTML += `
      <tr>
        <td><strong>${t.ID}</strong></td>
        <td><span class="badge badge-success">${t.TYPE}</span></td>
        <td>${t.NOM}</td>
        <td>${t.REFERENCE || ''}</td>
        <td>${t.EMAIL || ''}</td>
        <td>${t.TELEPHONE || ''}</td>
        <td>${t.ADRESSE || ''}</td>
        <td style="text-align: center;">
          <button class="btn btn-primary" style="padding:3px 6px; font-size:10px;" onclick="editerTiers('${t.ID}')">Modifier</button>
          <button class="btn btn-danger" style="padding:3px 6px; font-size:10px; background:#e74c3c; color:white;" onclick="supprimerTiersItem('${t.ID}')">Suppr.</button>
        </td>
      </tr>
    `;
  });
}

function editerTiers(id) {
  const t = APP_STATE.tiers.find(item => String(item.ID) === String(id));
  if (!t) return;
  document.getElementById('tiers-type').value = t.TYPE;
  document.getElementById('tiers-nom').value = t.NOM;
  document.getElementById('tiers-ref').value = t.REFERENCE || '';
  document.getElementById('tiers-email').value = t.EMAIL || '';
  document.getElementById('tiers-tel').value = t.TELEPHONE || '';
  document.getElementById('tiers-adresse').value = t.ADRESSE || '';
  openTiersModal();
}

async function supprimerTiersItem(id) {
  if (!confirm("Voulez-vous vraiment supprimer ce tiers ?")) return;
  showLoading(true);
  const res = await apiCall('supprimerTiers', { id });
  showLoading(false);
  showToast(res.message, !res.success);
  if (res.success) {
    await loadInitialData(); // Recharge toutes les données globales (y compris les tiers)
    renderTiersTable();     // Met à jour l'affichage du tableau des tiers
  }
}

async function renderPlanTable() {
  const tbody = document.getElementById('plan-tbody');
  tbody.innerHTML = '';
  const data = await apiCall('getInitialData', {}, 'GET');
  const plan = data.planComptable || [];
  if (plan.length === 0) {
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">Aucun compte.</td></tr>';
    return;
  }
  plan.forEach(p => {
    tbody.innerHTML += `
      <tr>
        <td><strong>${p.COMPTE}</strong></td>
        <td>${p.LIBELLE}</td>
        <td>${p.TYPE}</td>
        <td><span class="badge badge-success">${p.STATUT}</span></td>
        <td style="text-align: center;">
          <button class="btn btn-danger" style="padding:3px 6px; font-size:10px; background:#e74c3c; color:white;" onclick="supprimerCompte('${p.COMPTE}')">Suppr.</button>
        </td>
      </tr>
    `;
  });
}

function openPlanModal() {
  document.getElementById('form-plan').reset();
  document.getElementById('plan-is-edit').value = 'false';
  document.getElementById('plan-compte').readOnly = false;
  document.getElementById('modal-plan').classList.add('active');
}

async function submitPlanCompte() {
  const payload = {
    isEdit: document.getElementById('plan-is-edit').value === 'true',
    compte: document.getElementById('plan-compte').value,
    libelle: document.getElementById('plan-libelle').value,
    type: document.getElementById('plan-type').value
  };
  if (!payload.compte || !payload.libelle) { showToast("Veuillez remplir tous les champs obligatoires.", true); return; }
  showLoading(true);
  const res = await apiCall('enregistrerComptePlan', payload);
  showLoading(false);
  showToast(res.message, !res.success);
  if (res.success) {
    closeModal('modal-plan');
    renderPlanTable();
  }
}

async function supprimerCompte(compte) {
  if (!confirm(`Supprimer le compte ${compte} ?`)) return;
  showLoading(true);
  const res = await apiCall('supprimerComptePlan', { compte });
  showLoading(false);
  showToast(res.message, !res.success);
  if (res.success) renderPlanTable();
}

function renderCategoriesTable() {
  const tbody = document.getElementById('categories-tbody');
  tbody.innerHTML = '';
  if (!APP_STATE.categories || APP_STATE.categories.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">Aucune catégorie.</td></tr>';
    return;
  }
  APP_STATE.categories.forEach(c => {
    tbody.innerHTML += `
      <tr>
        <td><strong>${c.ID}</strong></td>
        <td>${c.LIBELLE}</td>
        <td><span class="badge badge-success">${c.TYPE_OPERATION}</span></td>
        <td>${c.COMPTE_COMPTABLE}</td>
        <td class="num">${c.TAUX_TVA}</td>
        <td>${c.COMPTE_TVA || ''}</td>
        <td style="text-align: center;">
          <button class="btn btn-primary" style="padding:3px 6px; font-size:10px;" onclick='editerCategorie(${JSON.stringify(c)})'>Modifier</button>
          <button class="btn btn-danger" style="padding:3px 6px; font-size:10px; background:#e74c3c; color:white;" onclick="supprimerCategorieItem('${c.ID}')">Suppr.</button>
        </td>
      </tr>
    `;
  });
}

function openCategorieModal() {
  document.getElementById('form-categorie').reset();
  document.getElementById('cat-is-edit').value = 'false';
  document.getElementById('cat-id').value = '';
  document.getElementById('modal-categorie').classList.add('active');
}

function editerCategorie(cat) {
  document.getElementById('cat-is-edit').value = 'true';
  document.getElementById('cat-id').value = cat.ID;
  document.getElementById('cat-libelle').value = cat.LIBELLE;
  document.getElementById('cat-type-op').value = cat.TYPE_OPERATION;
  document.getElementById('cat-compte').value = cat.COMPTE_COMPTABLE;
  document.getElementById('cat-taux-tva').value = cat.TAUX_TVA;
  document.getElementById('cat-compte-tva').value = cat.COMPTE_TVA;
  document.getElementById('modal-categorie').classList.add('active');
}

async function submitCategorieData() {
  const payload = {
    isEdit: document.getElementById('cat-is-edit').value === 'true',
    id: document.getElementById('cat-id').value,
    libelle: document.getElementById('cat-libelle').value,
    typeOperation: document.getElementById('cat-type-op').value,
    compteComptable: document.getElementById('cat-compte').value,
    tauxTva: document.getElementById('cat-taux-tva').value,
    compteTva: document.getElementById('cat-compte-tva').value
  };
  if (!payload.libelle || !payload.compteComptable) { showToast("Champs obligatoires manquants.", true); return; }
  showLoading(true);
  const res = await apiCall('enregistrerCategorie', payload);
  showLoading(false);
  showToast(res.message, !res.success);
  if (res.success) {
    closeModal('modal-categorie');
    await loadInitialData(); // Met à jour l'objet global APP_STATE
    renderCategoriesTable(); // Rafraîchit instantanément le tableau visuel
  }
}

async function supprimerCategorieItem(id) {
  if (!confirm("Voulez-vous supprimer cette catégorie ?")) return;
  showLoading(true);
  const res = await apiCall('supprimerCategorie', { id });
  showLoading(false);
  showToast(res.message, !res.success);
  if (res.success) {
    await loadInitialData(); // Recharge les catégories et l'état global
    renderCategoriesTable(); // Met à jour l'affichage du tableau des catégories
  }
}

async function loadInitialData() {
  showLoading(true);
  const data = await apiCall('getInitialData', {}, 'GET');
  APP_STATE.tiers = data.tiers || [];
  APP_STATE.categories = data.categories || [];
  APP_STATE.tresorerie = data.tresorerie || [];
  loadDashboardData();
  showLoading(false);
}

async function loadDashboardData() {
  showLoading(true);
  const params = { type: document.getElementById('global-period-type').value, valeur: document.getElementById('global-period-val').value };
  const res = await apiCall('getDashboardData', { params });
  showLoading(false);
  if (res.kpis) {
    document.getElementById('kpi-ca').textContent = formatMontant(res.kpis.chiffreAffaires) + ' Ar';
    document.getElementById('kpi-charges').textContent = formatMontant(res.kpis.totalCharges) + ' Ar';
    document.getElementById('kpi-resultat').textContent = formatMontant(res.kpis.resultatComptable) + ' Ar';
    document.getElementById('kpi-vol-ventes').textContent = formatMontant(res.kpis.volumeVentesMontant) + ' Ar';
    document.getElementById('kpi-nb-ecritures').textContent = res.kpis.nombreEcritures;
    renderChart(res.charts.evolution);
  }
}

function renderChart(data) {
  const ctx = document.getElementById('mainFinancialChart').getContext('2d');
  if (mainFinancialChartInstance) mainFinancialChartInstance.destroy();
  mainFinancialChartInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels: data.labels,
      datasets: [
        { label: 'Produits', data: data.produits, borderColor: '#2ecc71', tension: 0.2 },
        { label: 'Charges', data: data.charges, borderColor: '#e74c3c', tension: 0.2 },
        { label: 'Résultat', data: data.resultat, borderColor: '#3498db', borderDash: [5, 5], tension: 0.2 }
      ]
    },
    options: { responsive: true, maintainAspectRatio: false }
  });
}

async function loadBalanceData() {
  showLoading(true);
  const balance = await apiCall('getBalanceComptable', {}, 'GET');
  APP_STATE.balanceData = balance;
  const tbody = document.getElementById('balance-tbody');
  tbody.innerHTML = '';
  if (!balance || balance.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Aucune écriture trouvée.</td></tr>';
    showLoading(false);
    return;
  }
  balance.forEach(b => {
    tbody.innerHTML += `
      <tr>
        <td><strong>${b.compte}</strong></td>
        <td>${b.libelle}</td>
        <td class="num">${b.mouvementDebit > 0 ? formatMontant(b.mouvementDebit) : ''}</td>
        <td class="num">${b.mouvementCredit > 0 ? formatMontant(b.mouvementCredit) : ''}</td>
        <td class="num" style="font-weight:bold; color:#2980b9;">${b.soldeDebiteur > 0 ? formatMontant(b.soldeDebiteur) : ''}</td>
        <td class="num" style="font-weight:bold; color:#c0392b;">${b.soldeCrediteur > 0 ? formatMontant(b.soldeCrediteur) : ''}</td>
      </tr>
    `;
  });
  showLoading(false);
}

function telechargerBalanceExcel() {
  if (!APP_STATE.balanceData || APP_STATE.balanceData.length === 0) {
    showToast("Aucune donnée de balance à exporter.", true);
    return;
  }

  let htmlTable = `
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/1999/xlink">
    <head>
      <meta charset="UTF-8">
      <style>
        table { border-collapse: collapse; width: 100%; font-family: Calibri, sans-serif; }
        th { background-color: #2c3e50; color: #ffffff; font-weight: bold; border: 1px solid #000000; padding: 8px; text-align: center; }
        td { border: 1px solid #dddddd; padding: 6px; font-size: 11pt; }
        .num { text-align: right; mso-number-format:"#,##0.00"; }
      </style>
    </head>
    <body>
      <table>
        <thead>
          <tr>
            <th>Compte</th>
            <th>Libellé du Compte</th>
            <th>Mvt Débit</th>
            <th>Mouvement Crédit</th>
            <th>Solde Débiteur</th>
            <th>Solde Créditeur</th>
          </tr>
        </thead>
        <tbody>
  `;

  APP_STATE.balanceData.forEach(b => {
    htmlTable += `
      <tr>
        <td><b>${b.compte}</b></td>
        <td>${b.libelle}</td>
        <td class="num">${b.mouvementDebit || 0}</td>
        <td class="num">${b.mouvementCredit || 0}</td>
        <td class="num">${b.soldeDebiteur || 0}</td>
        <td class="num">${b.soldeCrediteur || 0}</td>
      </tr>
    `;
  });

  htmlTable += `
        </tbody>
      </table>
    </body>
    </html>
  `;

  const blob = new Blob(['\ufeff' + htmlTable], { type: 'application/vnd.ms-excel' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = "balance_des_comptes_" + new Date().toISOString().split('T')[0] + ".xls";
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  
  showToast("Téléchargement du fichier Excel réussi.", false);
}

async function refreshJournalTable() {
  showLoading(true);
  const filters = {
    journal: document.getElementById('filter-journal').value,
    dateDebut: document.getElementById('filter-date-debut').value,
    dateFin: document.getElementById('filter-date-fin').value,
    search: document.getElementById('search-input').value
  };

  const entries = await apiCall('getJournalEntries', { filters });
  showLoading(false);
  const tbody = document.getElementById('journal-tbody');
  tbody.innerHTML = '';
  
  let totDebit = 0;
  let totCredit = 0;

  if (!entries || entries.length === 0) {
    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;">Aucune écriture.</td></tr>';
    document.getElementById('total-debit').textContent = '0,00';
    document.getElementById('total-credit').textContent = '0,00';
    return;
  }

  entries.forEach(e => {
    const deb = parseFloat(e.DEBIT) || 0;
    const cred = parseFloat(e.CREDIT) || 0;
    totDebit += deb;
    totCredit += cred;

    tbody.innerHTML += `
      <tr>
        <td>${e.DATE}</td>
        <td><strong>${e.JOURNAL}</strong></td>
        <td>${e.PIECE || ''}</td>
        <td><strong>${e.COMPTE}</strong></td>
        <td>${e.TIERS_ID || ''}</td>
        <td>${e.LIBELLE_ECRITURE || ''}</td>
        <td class="num">${deb > 0 ? formatMontant(deb) : ''}</td>
        <td class="num">${cred > 0 ? formatMontant(cred) : ''}</td>
        <td><span class="badge badge-success">${e.STATUT}</span></td>
      </tr>
    `;
  });

  document.getElementById('total-debit').textContent = formatMontant(totDebit);
  document.getElementById('total-credit').textContent = formatMontant(totCredit);
}

function applyFilters() { refreshJournalTable(); }

function openSaisieModal() {
  const selectTiers = document.getElementById('saisie-tiers');
  selectTiers.innerHTML = '<option value="">-- Sélectionner --</option>';
  APP_STATE.tiers.forEach(t => selectTiers.innerHTML += `<option value="${t.ID}">${t.NOM}</option>`);
  
  const selectTreso = document.getElementById('saisie-tresorerie');
  selectTreso.innerHTML = '';
  APP_STATE.tresorerie.forEach(t => selectTreso.innerHTML += `<option value="${t.ID}">${t.LIBELLE}</option>`);

  onTypeOperationChange();
  document.getElementById('modal-saisie').classList.add('active');
}

function onTypeOperationChange() {
  const type = document.getElementById('saisie-type').value;
  const selectCat = document.getElementById('saisie-categorie');
  selectCat.innerHTML = '';
  APP_STATE.categories.filter(c => c.TYPE_OPERATION === type).forEach(c => {
    selectCat.innerHTML += `<option value="${c.ID}">${c.LIBELLE}</option>`;
  });

  const grpTreso = document.getElementById('grp-tresorerie');
  const grpTiers = document.getElementById('grp-tiers');
  const grpModeReglement = document.getElementById('grp-mode-reglement');
  const placeholder = document.querySelector('.placeholder-col');

  const selectTresoDepense = document.getElementById('saisie-tresorerie');
  const selectTresoReglement = document.getElementById('saisie-tresorerie-reglement');
  selectTresoDepense.innerHTML = '';
  selectTresoReglement.innerHTML = '<option value="">-- Sélectionner --</option>';
  APP_STATE.tresorerie.forEach(t => {
    selectTresoDepense.innerHTML += `<option value="${t.ID}">${t.LIBELLE}</option>`;
    selectTresoReglement.innerHTML += `<option value="${t.ID}">${t.LIBELLE}</option>`;
  });

  if (type === 'DEPENSE') {
    grpTreso.style.display = 'block';
    grpTiers.style.display = 'none';
    grpModeReglement.style.display = 'none';
    document.getElementById('grp-details-reglement').style.display = 'none';
    if (placeholder) placeholder.style.display = 'block';
  } else {
    grpTreso.style.display = 'none';
    grpTiers.style.display = 'block';
    grpModeReglement.style.display = 'flex';
    document.getElementById('saisie-mode-reglement').value = 'DIFFERE';
    onModeReglementChange();
  }

  calculerTVA();
}

function onModeReglementChange() {
  const mode = document.getElementById('saisie-mode-reglement').value;
  const grpTresoReg = document.getElementById('grp-saisie-tresorerie');
  const grpMontantPaye = document.getElementById('grp-saisie-montant-paye');
  const grpDetailsReg = document.getElementById('grp-details-reglement');

  if (mode === 'DIFFERE') {
    grpTresoReg.style.display = 'none';
    grpMontantPaye.style.display = 'none';
    grpDetailsReg.style.display = 'none';
  } else if (mode === 'IMMEDIAT') {
    grpTresoReg.style.display = 'block';
    grpMontantPaye.style.display = 'none';
    grpDetailsReg.style.display = 'flex';
  } else if (mode === 'PARTIEL') {
    grpTresoReg.style.display = 'block';
    grpMontantPaye.style.display = 'block';
    grpDetailsReg.style.display = 'flex';
    const ttc = document.getElementById('saisie-ttc').value;
    if (!document.getElementById('saisie-montant-paye').value || document.getElementById('saisie-montant-paye').value == 0) {
      document.getElementById('saisie-montant-paye').value = ttc;
    }
  }
}

async function submitSaisie() {
  const type = document.getElementById('saisie-type').value;
  const modeReglement = type === 'DEPENSE' ? 'IMMEDIAT' : document.getElementById('saisie-mode-reglement').value;
  const tresorerieId = type === 'DEPENSE' ? document.getElementById('saisie-tresorerie').value : document.getElementById('saisie-tresorerie-reglement').value;

  if (type !== 'DEPENSE' && modeReglement !== 'DIFFERE' && !tresorerieId) {
    showToast("Veuillez sélectionner un compte de trésorerie pour le règlement.", true);
    return;
  }

  const payload = {
    type: type,
    date: document.getElementById('saisie-date').value,
    piece: document.getElementById('saisie-piece').value,
    tiersId: document.getElementById('saisie-tiers').value,
    categorieId: document.getElementById('saisie-categorie').value,
    tresorerieId: tresorerieId,
    montantHT: document.getElementById('saisie-ht').value,
    montantTVA: document.getElementById('saisie-tva').value,
    libelle: document.getElementById('saisie-libelle').value,
    modeReglement: modeReglement,
    montantPaye: document.getElementById('saisie-montant-paye') ? document.getElementById('saisie-montant-paye').value : 0,
    moyenPaiement: document.getElementById('saisie-moyen-paiement') ? document.getElementById('saisie-moyen-paiement').value : 'VIREMENT',
    refReglement: document.getElementById('saisie-ref-reglement') ? document.getElementById('saisie-ref-reglement').value : ''
  };

  showLoading(true);
  const res = await apiCall('enregistrerOperation', payload);
  showLoading(false);
  showToast(res.message, !res.success);
  if (res.success) {
    resetFormSaisie();
    refreshJournalTable();
  }
}

function resetFormSaisie() {
  const currentDate = document.getElementById('saisie-date').value;
  document.getElementById('form-saisie').reset();
  document.getElementById('saisie-date').value = currentDate;
  if (document.getElementById('saisie-mode-reglement')) {
    document.getElementById('saisie-mode-reglement').value = 'DIFFERE';
    onModeReglementChange();
  }
  onTypeOperationChange();
}

function calculerTVA() {
  const ht = parseFloat(document.getElementById('saisie-ht').value) || 0;
  const cat = APP_STATE.categories.find(c => c.ID === document.getElementById('saisie-categorie').value);
  let taux = cat ? parseFloat(cat.TAUX_TVA) || 0 : 0;
  let tva = Math.round((ht * (taux / 100)) * 100) / 100;
  document.getElementById('saisie-tva').value = tva.toFixed(2);
  calculerTTC();
}

function calculerTTC() {
  const ht = parseFloat(document.getElementById('saisie-ht').value) || 0;
  const tva = parseFloat(document.getElementById('saisie-tva').value) || 0;
  document.getElementById('saisie-ttc').value = (ht + tva).toFixed(2);
}

function openTiersModal() { document.getElementById('modal-tiers').classList.add('active'); }

async function submitTiers() {
  const payload = {
    type: document.getElementById('tiers-type').value,
    nom: document.getElementById('tiers-nom').value,
    reference: document.getElementById('tiers-ref').value,
    email: document.getElementById('tiers-email').value,
    telephone: document.getElementById('tiers-tel').value,
    adresse: document.getElementById('tiers-adresse').value
  };
  
  if (!payload.nom) { 
    showToast("Le nom du tiers est obligatoire.", true); 
    return; 
  }
  
  showLoading(true);
  const res = await apiCall('creerTiers', payload);
  showLoading(false);
  
  if (res.success) {
    showToast("Tiers créé avec succès.", false);
    closeModal('modal-tiers');
    document.getElementById('form-tiers').reset();
    
    // Ajout dans l'état global
    APP_STATE.tiers.push({ 
      ID: res.id, 
      TYPE: payload.type, 
      NOM: res.nom, 
      REFERENCE: payload.reference, 
      EMAIL: payload.email, 
      TELEPHONE: payload.telephone, 
      ADRESSE: payload.adresse, 
      STATUT: 'ACTIF' 
    });

    // 1. Mise à jour du select de la page de saisie
    const selectTiers = document.getElementById('saisie-tiers');
    if (selectTiers) {
      selectTiers.innerHTML = '<option value="">-- Sélectionner --</option>';
      APP_STATE.tiers.filter(t => t.STATUT === 'ACTIF').forEach(t => {
        selectTiers.innerHTML += `<option value="${t.ID}">${t.NOM}</option>`;
      });
      selectTiers.value = res.id;
    }

    // 2. Mise à jour automatique du tableau dans l'onglet Table Tiers
    renderTiersTable();

  } else { 
    showToast(res.message, true); 
  }
}

function openReglementModal(type) {
  APP_STATE.activeReglementType = type;
  document.getElementById('modal-reglement-title').textContent = type === 'REGLEMENT' ? 'Règlement Fournisseur' : 'Encaissement Client';
  const selectTiers = document.getElementById('reglement-tiers');
  selectTiers.innerHTML = '<option value="">-- Sélectionner --</option>';
  APP_STATE.tiers.filter(t => t.TYPE === (type === 'REGLEMENT' ? 'FOURNISSEUR' : 'CLIENT')).forEach(t => {
    selectTiers.innerHTML += `<option value="${t.ID}">${t.NOM}</option>`;
  });
  const selectTreso = document.getElementById('reglement-tresorerie');
  selectTreso.innerHTML = '';
  APP_STATE.tresorerie.forEach(t => selectTreso.innerHTML += `<option value="${t.ID}">${t.LIBELLE}</option>`);
  document.getElementById('modal-reglement').classList.add('active');
}

async function loadFacturesOuvertes() {
  const tiersId = document.getElementById('reglement-tiers').value;
  if (!tiersId) return;
  const factures = await apiCall('getFacturesOuvertes', { tiersId }, 'GET');
  const tbody = document.getElementById('reglement-factures-tbody');
  tbody.innerHTML = '';
  if (!factures || factures.length === 0) { tbody.innerHTML = '<tr><td colspan="6">Aucune facture ouverte.</td></tr>'; return; }
  factures.forEach(f => {
    tbody.innerHTML += `
      <tr>
        <td><input type="checkbox" class="chk-facture" data-id="${f.id}"></td>
        <td>${f.date}</td><td>${f.piece}</td><td>${f.libelle}</td>
        <td class="num">${formatMontant(f.solde)}</td>
        <td><input type="number" step="0.01" class="num input-pay" data-id="${f.id}" value="${f.solde.toFixed(2)}" style="width:100px;"></td>
      </tr>`;
  });
}

async function submitReglement() {
  const affectations = [];
  document.querySelectorAll('.chk-facture').forEach(chk => {
    if (chk.checked) {
      const id = chk.getAttribute('data-id');
      const montant = parseFloat(document.querySelector(`.input-pay[data-id="${id}"]`).value) || 0;
      affectations.push({ factureId: id, montant });
    }
  });
  if (!affectations.length) { showToast("Sélectionnez au moins une facture.", true); return; }
  showLoading(true);
  const res = await apiCall('enregistrerReglement', {
    type: APP_STATE.activeReglementType,
    tiersId: document.getElementById('reglement-tiers').value,
    date: document.getElementById('reglement-date').value,
    tresorerieId: document.getElementById('reglement-tresorerie').value,
    affectations
  });
  showLoading(false);
  showToast(res.message, !res.success);
  if (res.success) {
    closeModal('modal-reglement');
    const activeTab = document.querySelector('.tab-content.active').id;
    if (activeTab === 'tab-journal') refreshJournalTable();
    else if (activeTab === 'tab-balance') loadBalanceData();
    else if (activeTab === 'tab-dashboard') loadDashboardData();
  }
}

async function initApp() {
  if (confirm("Réinitialiser les paramètres ?")) {
    showLoading(true);
    const res = await apiCall('initialiserApplication', {});
    showLoading(false);
    showToast(res.message, !res.success);
    loadInitialData();
  }
}

function closeModal(id) { document.getElementById(id).classList.remove('active'); }
function formatMontant(val) {
  return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(parseFloat(val) || 0);
}