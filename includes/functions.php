<?php
/**
 * HEPTA Compta Web - Fonctions Métier & Traitements Comptables
 */

require_once __DIR__ . '/../config/database.php';

function generateUUID($prefix = '') {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    return $prefix ? $prefix . '-' . substr($uuid, 0, 8) : $uuid;
}

function logAudit($user, $action, $typeObjet, $objetId, $details, $resultat) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("INSERT INTO journal_audit (id, date, utilisateur, action, type_objet, objet_id, details, resultat) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            generateUUID('AUD'),
            $user ?: 'ANONYME',
            $action,
            $typeObjet,
            $objetId,
            $details,
            $resultat
        ]);
    } catch (Exception $e) {
        error_log("Echec Ecriture Audit: " . $e->getMessage());
    }
}

function getInitialData() {
    $pdo = getDbConnection();
    
    $parametres = $pdo->query("SELECT cle AS CLE, valeur AS VALEUR, description AS DESCRIPTION FROM parametres")->fetchAll();
    $journaux = $pdo->query("SELECT code AS CODE, libelle AS LIBELLE, type AS TYPE, statut AS STATUT FROM journaux WHERE statut = 'ACTIF'")->fetchAll();
    $planComptable = $pdo->query("SELECT compte AS COMPTE, libelle AS LIBELLE, type AS TYPE, statut AS STATUT FROM plan_comptable WHERE statut = 'ACTIF'")->fetchAll();
    $tiers = $pdo->query("SELECT id AS ID, type AS TYPE, nom AS NOM, reference AS REFERENCE, email AS EMAIL, telephone AS TELEPHONE, adresse AS ADRESSE, statut AS STATUT, date_creation AS DATE_CREATION FROM tiers WHERE statut = 'ACTIF'")->fetchAll();
    $categories = $pdo->query("SELECT id AS ID, libelle AS LIBELLE, type_operation AS TYPE_OPERATION, compte_comptable AS COMPTE_COMPTABLE, taux_tva AS TAUX_TVA, compte_tva AS COMPTE_TVA FROM categories")->fetchAll();
    $tresorerie = $pdo->query("SELECT id AS ID, libelle AS LIBELLE, type AS TYPE, compte_comptable AS COMPTE_COMPTABLE, statut AS STATUT FROM tresorerie WHERE statut = 'ACTIF'")->fetchAll();

    return [
        'parametres' => $parametres,
        'journaux' => $journaux,
        'planComptable' => $planComptable,
        'tiers' => $tiers,
        'categories' => $categories,
        'tresorerie' => $tresorerie
    ];
}

function getJournalEntries($filters = []) {
    $pdo = getDbConnection();
    $sql = "SELECT id AS ID, operation_id AS OPERATION_ID, journal AS JOURNAL, piece AS PIECE, date AS DATE, compte AS COMPTE, libelle_compte AS LIBELLE_COMPTE, libelle_ecriture AS LIBELLE_ECRITURE, tiers_id AS TIERS_ID, debit AS DEBIT, credit AS CREDIT, statut AS STATUT FROM ecritures WHERE 1=1";
    $params = [];

    if (!empty($filters['journal']) && $filters['journal'] !== 'ALL') {
        $sql .= " AND journal = ?";
        $params[] = $filters['journal'];
    }
    if (!empty($filters['dateDebut'])) {
        $sql .= " AND date >= ?";
        $params[] = $filters['dateDebut'];
    }
    if (!empty($filters['dateFin'])) {
        $sql .= " AND date <= ?";
        $params[] = $filters['dateFin'];
    }
    if (!empty($filters['search'])) {
        $q = '%' . $filters['search'] . '%';
        $sql .= " AND (piece LIKE ? OR libelle_ecriture LIKE ? OR compte LIKE ? OR tiers_id LIKE ?)";
        $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q;
    }

    $sql .= " ORDER BY date DESC, id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getBalanceComptable() {
    $pdo = getDbConnection();
    
    $planStmt = $pdo->query("SELECT compte AS COMPTE, libelle AS LIBELLE FROM plan_comptable");
    $planComptable = $planStmt->fetchAll();

    $ecrituresStmt = $pdo->query("SELECT compte AS COMPTE, libelle_compte AS LIBELLE_COMPTE, debit AS DEBIT, credit AS CREDIT FROM ecritures WHERE statut = 'VALIDE'");
    $ecritures = $ecrituresStmt->fetchAll();

    $balanceMap = [];

    foreach ($planComptable as $p) {
        $balanceMap[$p['COMPTE']] = [
            'compte' => $p['COMPTE'],
            'libelle' => $p['LIBELLE'],
            'mouvementDebit' => 0.0,
            'mouvementCredit' => 0.0
        ];
    }

    foreach ($ecritures as $e) {
        $compte = (string)$e['COMPTE'];
        $debit = (float)$e['DEBIT'];
        $credit = (float)$e['CREDIT'];

        if (!isset($balanceMap[$compte])) {
            $balanceMap[$compte] = [
                'compte' => $compte,
                'libelle' => $e['LIBELLE_COMPTE'] ?: "Compte $compte",
                'mouvementDebit' => 0.0,
                'mouvementCredit' => 0.0
            ];
        }
        $balanceMap[$compte]['mouvementDebit'] += $debit;
        $balanceMap[$compte]['mouvementCredit'] += $credit;
    }

    ksort($balanceMap);
    $result = [];

    foreach ($balanceMap as $compte => $item) {
        if ($item['mouvementDebit'] > 0 || $item['mouvementCredit'] > 0 || array_filter($planComptable, fn($p) => $p['COMPTE'] === $compte)) {
            $soldeNet = round($item['mouvementDebit'] - $item['mouvementCredit'], 2);
            $soldeDebiteur = $soldeNet > 0 ? $soldeNet : 0;
            $soldeCrediteur = $soldeNet < 0 ? abs($soldeNet) : 0;

            $result[] = [
                'compte' => $item['compte'],
                'libelle' => $item['libelle'],
                'mouvementDebit' => round($item['mouvementDebit'], 2),
                'mouvementCredit' => round($item['mouvementCredit'], 2),
                'soldeDebiteur' => $soldeDebiteur,
                'soldeCrediteur' => $soldeCrediteur
            ];
        }
    }

    return $result;
}

function getFacturesOuvertes($tiersIdOrName) {
    $pdo = getDbConnection();
    
    // 1. Récupérer l'objet tiers correspondant (ID et Nom)
    $stmtTiers = $pdo->prepare("SELECT id, nom FROM tiers WHERE id = ? OR nom = ?");
    $stmtTiers->execute([$tiersIdOrName, $tiersIdOrName]);
    $tiersObj = $stmtTiers->fetch();

    $targetId = $tiersObj ? $tiersObj['id'] : $tiersIdOrName;
    $targetName = $tiersObj ? $tiersObj['nom'] : $tiersIdOrName;

    // 2. Récupérer toutes les opérations d'achats (HA) ou de ventes (VT) validées
    $sqlOps = "SELECT id AS ID, type AS TYPE, date AS DATE, piece AS PIECE, tiers_id AS TIERS_ID, libelle AS LIBELLE, montant_ttc AS MONTANT_TTC FROM operations WHERE statut = 'VALIDEE' AND (type = 'HA' OR type = 'VT')";
    $paramsOps = [];

    if (!empty($tiersIdOrName)) {
        // Filtrer en vérifiant si le tiers_id enregistré correspond à l'ID ou au Nom du tiers
        $sqlOps .= " AND (tiers_id = ? OR tiers_id = ? OR tiers_id LIKE ?)";
        $paramsOps[] = $targetId;
        $paramsOps[] = $targetName;
        $paramsOps[] = '%' . $targetName . '%';
    }

    $opsStmt = $pdo->prepare($sqlOps);
    $opsStmt->execute($paramsOps);
    $operations = $opsStmt->fetchAll();

    // 3. Récupérer les affectations existantes pour calculer les soldes
    $affStmt = $pdo->query("SELECT facture_op_id, montant_affecte FROM affectations_reglements WHERE statut = 'VALIDE'");
    $affectations = $affStmt->fetchAll();

    $result = [];

    foreach ($operations as $op) {
        $mtTotal = (float)$op['MONTANT_TTC'];
        $mtRegle = 0.0;

        foreach ($affectations as $a) {
            if ((string)$a['facture_op_id'] === (string)$op['ID']) {
                $mtRegle += (float)$a['montant_affecte'];
            }
        }

        $solde = round($mtTotal - $mtRegle, 2);

        // Si le solde est supérieur à 0, la facture est ouverte
        if ($solde > 0.001) {
            $result[] = [
                'id' => $op['ID'],
                'type' => $op['TYPE'],
                'date' => $op['DATE'],
                'piece' => $op['PIECE'],
                'tiersId' => $op['TIERS_ID'],
                'libelle' => $op['LIBELLE'],
                'montantTotal' => $mtTotal,
                'montantRegle' => $mtRegle,
                'solde' => $solde
            ];
        }
    }

    return $result;
}

function creerTiers($tiersData) {
    $pdo = getDbConnection();
    try {
        $pdo->beginTransaction();

        if (empty($tiersData['nom']) || empty($tiersData['type'])) {
            return ['success' => false, 'message' => 'Le nom et le type de tiers sont obligatoires.'];
        }

        $stmtCheck = $pdo->prepare("SELECT id FROM tiers WHERE LOWER(TRIM(nom)) = LOWER(TRIM(?)) AND statut = 'ACTIF'");
        $stmtCheck->execute([$tiersData['nom']]);
        if ($stmtCheck->fetch()) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Un tiers avec ce nom existe déjà.'];
        }

        $stmtMax = $pdo->query("SELECT id FROM tiers WHERE id LIKE 'TIR-%'");
        $maxNum = 0;
        while ($row = $stmtMax->fetch()) {
            $numPart = (int)str_replace('TIR-', '', $row['id']);
            if ($numPart > $maxNum) $maxNum = $numPart;
        }
        $newId = 'TIR-' . str_pad($maxNum + 1, 3, '0', STR_PAD_LEFT);

        $stmtIns = $pdo->prepare("INSERT INTO tiers (id, type, nom, reference, email, telephone, adresse, statut, date_creation) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIF', NOW())");
        $stmtIns->execute([
            $newId,
            strtoupper($tiersData['type']),
            trim($tiersData['nom']),
            $tiersData['reference'] ?? '',
            $tiersData['email'] ?? '',
            $tiersData['telephone'] ?? '',
            $tiersData['adresse'] ?? ''
        ]);

        logAudit(null, 'CREATION', 'TIERS', $newId, "Création tiers {$tiersData['nom']}", 'SUCCES');
        $pdo->commit();
        return ['success' => true, 'id' => $newId, 'nom' => $tiersData['nom'], 'type' => $tiersData['type']];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'message' => 'Erreur serveur : ' . $e->getMessage()];
    }
}

function getLibelleCompteHelper($pdo, $numeroCompte) {
    $stmt = $pdo->prepare("SELECT libelle FROM plan_comptable WHERE compte = ?");
    $stmt->execute([$numeroCompte]);
    $res = $stmt->fetch();
    return $res ? $res['libelle'] : 'Compte ' . $numeroCompte;
}

function enregistrerOperation($opPayload) {
    $pdo = getDbConnection();
    try {
        $pdo->beginTransaction();

        if (empty($opPayload['date']) || empty($opPayload['type']) || empty($opPayload['categorieId']) || !isset($opPayload['montantHT'])) {
            return ['success' => false, 'message' => 'Champs obligatoires manquants.'];
        }

        $stmtCat = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmtCat->execute([$opPayload['categorieId']]);
        $cat = $stmtCat->fetch();
        if (!$cat) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Catégorie comptable introuvable.'];
        }

        $tiersNomOuId = $opPayload['tiersId'] ?? '';
        if ($tiersNomOuId) {
            $stmtTiers = $pdo->prepare("SELECT nom FROM tiers WHERE id = ?");
            $stmtTiers->execute([$tiersNomOuId]);
            $found = $stmtTiers->fetch();
            if ($found) $tiersNomOuId = $found['nom'];
        }

        $user = 'UTILISATEUR';
        $ht = round((float)$opPayload['montantHT'], 2);
        $tva = round((float)($opPayload['montantTVA'] ?? 0), 2);
        $ttc = round($ht + $tva, 2);

        $stmtOpIns = $pdo->prepare("INSERT INTO operations (type, date, tiers_id, piece, montant_ht, montant_tva, montant_ttc, categorie_id, libelle, statut, auteur, date_creation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'VALIDEE', ?, NOW())");
        $stmtOpIns->execute([
            $opPayload['type'],
            $opPayload['date'],
            $tiersNomOuId,
            $opPayload['piece'] ?? '',
            $ht,
            $tva,
            $ttc,
            $opPayload['categorieId'],
            $opPayload['libelle'],
            $user
        ]);
        $opId = $pdo->lastInsertId();

        $journal = $opPayload['journal'] ?? ($opPayload['type'] === 'HA' ? 'HA' : 'VT');
        $ecritures = [];

        if ($opPayload['type'] === 'HA') {
            $ecritures[] = ['journal' => $journal, 'piece' => $opPayload['piece'], 'date' => $opPayload['date'], 'compte' => $cat['compte_comptable'], 'libelleCompte' => getLibelleCompteHelper($pdo, $cat['compte_comptable']), 'libelleEcriture' => $opPayload['libelle'], 'tiersId' => $tiersNomOuId, 'debit' => $ht, 'credit' => 0];
            if ($tva > 0) {
                $compteTva = $cat['compte_tva'] ?: '445660';
                $ecritures[] = ['journal' => $journal, 'piece' => $opPayload['piece'], 'date' => $opPayload['date'], 'compte' => $compteTva, 'libelleCompte' => getLibelleCompteHelper($pdo, $compteTva), 'libelleEcriture' => 'TVA Sur ' . $opPayload['libelle'], 'tiersId' => $tiersNomOuId, 'debit' => $tva, 'credit' => 0];
            }
            $ecritures[] = ['journal' => $journal, 'piece' => $opPayload['piece'], 'date' => $opPayload['date'], 'compte' => '401000', 'libelleCompte' => getLibelleCompteHelper($pdo, '401000'), 'libelleEcriture' => $opPayload['libelle'], 'tiersId' => $tiersNomOuId, 'debit' => 0, 'credit' => $ttc];
        } elseif ($opPayload['type'] === 'VT') {
            $ecritures[] = ['journal' => $journal, 'piece' => $opPayload['piece'], 'date' => $opPayload['date'], 'compte' => '411000', 'libelleCompte' => getLibelleCompteHelper($pdo, '411000'), 'libelleEcriture' => $opPayload['libelle'], 'tiersId' => $tiersNomOuId, 'debit' => $ttc, 'credit' => 0];
            $ecritures[] = ['journal' => $journal, 'piece' => $opPayload['piece'], 'date' => $opPayload['date'], 'compte' => $cat['compte_comptable'], 'libelleCompte' => getLibelleCompteHelper($pdo, $cat['compte_comptable']), 'libelleEcriture' => $opPayload['libelle'], 'tiersId' => $tiersNomOuId, 'debit' => 0, 'credit' => $ht];
            if ($tva > 0) {
                $compteTva = $cat['compte_tva'] ?: '445710';
                $ecritures[] = ['journal' => $journal, 'piece' => $opPayload['piece'], 'date' => $opPayload['date'], 'compte' => $compteTva, 'libelleCompte' => getLibelleCompteHelper($pdo, $compteTva), 'libelleEcriture' => 'TVA Collectée ' . $opPayload['libelle'], 'tiersId' => $tiersNomOuId, 'debit' => 0, 'credit' => $tva];
            }
        } elseif ($opPayload['type'] === 'DEPENSE') {
            $stmtTr = $pdo->prepare("SELECT * FROM tresorerie WHERE id = ?");
            $stmtTr->execute([$opPayload['tresorerieId']]);
            $treso = $stmtTr->fetch();
            $compteTreso = $treso ? $treso['compte_comptable'] : '512000';
            $journal = ($treso && $treso['type'] === 'CAISSE') ? 'CA' : 'BQ';

            $ecritures[] = ['journal' => $journal, 'piece' => $opPayload['piece'], 'date' => $opPayload['date'], 'compte' => $cat['compte_comptable'], 'libelleCompte' => getLibelleCompteHelper($pdo, $cat['compte_comptable']), 'libelleEcriture' => $opPayload['libelle'], 'tiersId' => $tiersNomOuId, 'debit' => $ht, 'credit' => 0];
            if ($tva > 0) {
                $compteTva = $cat['compte_tva'] ?: '445660';
                $ecritures[] = ['journal' => $journal, 'piece' => $opPayload['piece'], 'date' => $opPayload['date'], 'compte' => $compteTva, 'libelleCompte' => 'TVA Déductible', 'libelleEcriture' => 'TVA ' . $opPayload['libelle'], 'tiersId' => $tiersNomOuId, 'debit' => $tva, 'credit' => 0];
            }
            $ecritures[] = ['journal' => $journal, 'piece' => $opPayload['piece'], 'date' => $opPayload['date'], 'compte' => $compteTreso, 'libelleCompte' => getLibelleCompteHelper($pdo, $compteTreso), 'libelleEcriture' => $opPayload['libelle'], 'tiersId' => $tiersNomOuId, 'debit' => 0, 'credit' => $ttc];
        }

        $stmtEcrIns = $pdo->prepare("INSERT INTO ecritures (operation_id, journal, piece, date, compte, libelle_compte, libelle_ecriture, tiers_id, debit, credit, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'VALIDE')");
        foreach ($ecritures as $e) {
            $stmtEcrIns->execute([$opId, $e['journal'], $e['piece'], $e['date'], $e['compte'], $e['libelleCompte'], $e['libelleEcriture'], $e['tiersId'], $e['debit'], $e['credit']]);
        }

        $modeReglement = $opPayload['modeReglement'] ?? 'DIFFERE';
        if (($opPayload['type'] === 'HA' || $opPayload['type'] === 'VT') && ($modeReglement === 'IMMEDIAT' || $modeReglement === 'PARTIEL')) {
            $stmtTr = $pdo->prepare("SELECT * FROM tresorerie WHERE id = ?");
            $stmtTr->execute([$opPayload['tresorerieId']]);
            $treso = $stmtTr->fetch();

            if ($treso) {
                $montantPaye = $ttc;
                if ($modeReglement === 'PARTIEL') {
                    $montantPaye = min(max((float)($opPayload['montantPaye'] ?? 0), 0), $ttc);
                }

                if ($montantPaye > 0) {
                    $regType = $opPayload['type'] === 'HA' ? 'REGLEMENT' : 'ENCAISSEMENT';
                    $regJournal = $treso['type'] === 'CAISSE' ? 'CA' : 'BQ';
                    $moyenPaiement = $opPayload['moyenPaiement'] ?? 'VIREMENT';
                    $refReg = $opPayload['refReglement'] ?: ($opPayload['piece'] ?: 'REG');
                    $libReg = trim(($opPayload['type'] === 'HA' ? 'Règlement' : 'Encaissement') . ' pièce ' . $opPayload['piece']);

                    $stmtRegIns = $pdo->prepare("INSERT INTO reglements (type, date, tiers_id, moyen_paiement, tresorerie_id, montant_total, reference, statut, auteur, date_creation) VALUES (?, ?, ?, ?, ?, ?, ?, 'VALIDE', ?, NOW())");
                    $stmtRegIns->execute([$regType, $opPayload['date'], $tiersNomOuId, $moyenPaiement, $opPayload['tresorerieId'], $montantPaye, $refReg, $user]);
                    $regId = $pdo->lastInsertId();

                    $stmtAffIns = $pdo->prepare("INSERT INTO affectations_reglements (reglement_id, facture_op_id, montant_affecte, date_affectation, statut) VALUES (?, ?, ?, ?, 'VALIDE')");
                    $stmtAffIns->execute([$regId, $opId, $montantPaye, $opPayload['date']]);

                    $compteTiers = $opPayload['type'] === 'HA' ? '401000' : '411000';
                    if ($opPayload['type'] === 'HA') {
                        $stmtEcrIns->execute([$regId, $regJournal, $refReg, $opPayload['date'], $compteTiers, getLibelleCompteHelper($pdo, $compteTiers), $libReg, $tiersNomOuId, $montantPaye, 0]);
                        $stmtEcrIns->execute([$regId, $regJournal, $refReg, $opPayload['date'], $treso['compte_comptable'], getLibelleCompteHelper($pdo, $treso['compte_comptable']), $libReg, $tiersNomOuId, 0, $montantPaye]);
                    } else {
                        $stmtEcrIns->execute([$regId, $regJournal, $refReg, $opPayload['date'], $treso['compte_comptable'], getLibelleCompteHelper($pdo, $treso['compte_comptable']), $libReg, $tiersNomOuId, $montantPaye, 0]);
                        $stmtEcrIns->execute([$regId, $regJournal, $refReg, $opPayload['date'], $compteTiers, getLibelleCompteHelper($pdo, $compteTiers), $libReg, $tiersNomOuId, 0, $montantPaye]);
                    }
                }
            }
        }

        logAudit($user, 'CREATION_OP', 'OPERATION', $opId, "Opération {$opPayload['type']} de {$ttc} ({$modeReglement})", 'SUCCES');
        $pdo->commit();
        return ['success' => true, 'message' => 'Opération enregistrée avec succès.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

function enregistrerReglement($reglementPayload) {
    $pdo = getDbConnection();
    try {
        $pdo->beginTransaction();

        // 1. Vérification de la trésorerie
        $stmtTr = $pdo->prepare("SELECT * FROM tresorerie WHERE id = ?");
        $stmtTr->execute([$reglementPayload['tresorerieId'] ?? '']);
        $treso = $stmtTr->fetch();
        if (!$treso) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['success' => false, 'message' => 'Trésorerie invalide ou introuvable.'];
        }

        // 2. Calcul du montant total
        $totalMontant = 0.0;
        if (!empty($reglementPayload['affectations']) && is_array($reglementPayload['affectations'])) {
            foreach ($reglementPayload['affectations'] as $a) {
                $totalMontant += (float)($a['montant'] ?? 0);
            }
        }

        if ($totalMontant <= 0) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['success' => false, 'message' => 'Le montant du règlement doit être supérieur à zéro.'];
        }

        // 3. Gestion propre du tiers (Conserver l'ID si la colonne est une clé étrangère, ou le nom selon votre structure)
        $tiersIdVal = $reglementPayload['tiersId'] ?? null;

        $user = 'UTILISATEUR';
        $journal = $treso['type'] === 'CAISSE' ? 'CA' : 'BQ';
        $isEncaissement = ($reglementPayload['type'] ?? '') === 'ENCAISSEMENT';

        // 4. Insertion du règlement
        $stmtReg = $pdo->prepare("INSERT INTO reglements (type, date, tiers_id, moyen_paiement, tresorerie_id, montant_total, reference, statut, auteur, date_creation) VALUES (?, ?, ?, ?, ?, ?, ?, 'VALIDE', ?, NOW())");
        $stmtReg->execute([
            $reglementPayload['type'] ?? 'REGLEMENT',
            $reglementPayload['date'] ?? date('Y-m-d'),
            $tiersIdVal,
            $reglementPayload['moyenPaiement'] ?? 'VIREMENT',
            $reglementPayload['tresorerieId'],
            $totalMontant,
            $reglementPayload['reference'] ?? '',
            $user
        ]);
        $regId = $pdo->lastInsertId();

        // 5. Insertion des affectations
        $stmtAff = $pdo->prepare("INSERT INTO affectations_reglements (reglement_id, facture_op_id, montant_affecte, date_affectation, statut) VALUES (?, ?, ?, ?, 'VALIDE')");
        foreach ($reglementPayload['affectations'] as $a) {
            $stmtAff->execute([
                $regId, 
                (int)($a['factureId'] ?? 0), 
                round((float)($a['montant'] ?? 0), 2), 
                $reglementPayload['date'] ?? date('Y-m-d')
            ]);
        }

        // 6. Écritures comptables
        $compteTiers = $isEncaissement ? '411000' : '401000';
        $libelle = trim(($isEncaissement ? 'Encaissement' : 'Règlement') . ' ' . ($reglementPayload['reference'] ?? ''));
        $ref = !empty($reglementPayload['reference']) ? $reglementPayload['reference'] : 'REG';

        $stmtEcr = $pdo->prepare("INSERT INTO ecritures (operation_id, journal, piece, date, compte, libelle_compte, libelle_ecriture, tiers_id, debit, credit, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'VALIDE')");

        if ($isEncaissement) {
            $stmtEcr->execute([$regId, $journal, $ref, $reglementPayload['date'], $treso['compte_comptable'], getLibelleCompteHelper($pdo, $treso['compte_comptable']), $libelle, $tiersIdVal, $totalMontant, 0]);
            $stmtEcr->execute([$regId, $journal, $ref, $reglementPayload['date'], $compteTiers, getLibelleCompteHelper($pdo, $compteTiers), $libelle, $tiersIdVal, 0, $totalMontant]);
        } else {
            $stmtEcr->execute([$regId, $journal, $ref, $reglementPayload['date'], $compteTiers, getLibelleCompteHelper($pdo, $compteTiers), $libelle, $tiersIdVal, $totalMontant, 0]);
            $stmtEcr->execute([$regId, $journal, $ref, $reglementPayload['date'], $treso['compte_comptable'], getLibelleCompteHelper($pdo, $treso['compte_comptable']), $libelle, $tiersIdVal, 0, $totalMontant]);
        }

        $pdo->commit();
        return ['success' => true, 'message' => 'Règlement enregistré avec succès.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Enregistre l'erreur dans les logs du serveur pour diagnostic précis
        error_log("Erreur enregistrerReglement: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur SQL : ' . $e->getMessage()];
    }
}

function getDashboardData($filtreParams = null) {
    $pdo = getDbConnection();

    $params = $filtreParams ?: ['type' => 'annuel', 'valeur' => date('Y')];
    $currentYear = date('Y');

    $sqlEcr = "SELECT date, compte, debit, credit FROM ecritures WHERE statut = 'VALIDE'";
    $sqlOps = "SELECT type, date, montant_ttc FROM operations WHERE statut = 'VALIDEE'";

    if ($params['type'] === 'mensuel') {
        $moisCible = $params['valeur'] ?: date('Y-m');
        $sqlEcr .= " AND date LIKE " . $pdo->quote($moisCible . '%');
        $sqlOps .= " AND date LIKE " . $pdo->quote($moisCible . '%');
    } else {
        $anneeCible = $params['valeur'] ?: $currentYear;
        $sqlEcr .= " AND date LIKE " . $pdo->quote($anneeCible . '%');
        $sqlOps .= " AND date LIKE " . $pdo->quote($anneeCible . '%');
    }

    $ecritures = $pdo->query($sqlEcr)->fetchAll();
    $operations = $pdo->query($sqlOps)->fetchAll();

    $totalCA = 0.0; $totalCharges = 0.0; $volumeVentesMontant = 0.0; $nombreVentesPieces = 0;

    foreach ($ecritures as $e) {
        $debit = (float)$e['debit'];
        $credit = (float)$e['credit'];
        $compteStr = (string)$e['compte'];
        if (str_starts_with($compteStr, '70')) $totalCA += ($credit - $debit);
        elseif (str_starts_with($compteStr, '6')) $totalCharges += ($debit - $credit);
    }

    foreach ($operations as $o) {
        if ($o['type'] === 'VT') {
            $volumeVentesMontant += (float)$o['montant_ttc'];
            $nombreVentesPieces += 1;
        }
    }

    $resultatComptable = $totalCA - $totalCharges;

    $chartEvolution = ['labels' => [], 'produits' => [], 'charges' => [], 'resultat' => []];
    $monthlyMap = [];

    foreach ($ecritures as $e) {
        if (empty($e['date'])) continue;
        $moisKey = substr($e['date'], 0, 7);
        if (!isset($monthlyMap[$moisKey])) $monthlyMap[$moisKey] = ['p' => 0.0, 'c' => 0.0];
        $debit = (float)$e['debit'];
        $credit = (float)$e['credit'];
        if (str_starts_with((string)$e['compte'], '7')) $monthlyMap[$moisKey]['p'] += ($credit - $debit);
        if (str_starts_with((string)$e['compte'], '6')) $monthlyMap[$moisKey]['c'] += ($debit - $credit);
    }

    ksort($monthlyMap);
    foreach ($monthlyMap as $m => $val) {
        $chartEvolution['labels'][] = $m;
        $chartEvolution['produits'][] = round($val['p'], 2);
        $chartEvolution['charges'][] = round($val['c'], 2);
        $chartEvolution['resultat'][] = round($val['p'] - $val['c'], 2);
    }

    $chartVolume = ['labels' => [], 'ventesMontant' => [], 'chargesMontant' => []];
    $volMap = [];

    foreach ($operations as $o) {
        if (empty($o['date'])) continue;
        $moisKey = substr($o['date'], 0, 7);
        if (!isset($volMap[$moisKey])) $volMap[$moisKey] = ['vMontant' => 0.0, 'cMontant' => 0.0];
        if ($o['type'] === 'VT') $volMap[$moisKey]['vMontant'] += (float)$o['montant_ttc'];
        elseif ($o['type'] === 'HA') $volMap[$moisKey]['cMontant'] += (float)$o['montant_ttc'];
    }

    ksort($volMap);
    foreach ($volMap as $m => $val) {
        $chartVolume['labels'][] = $m;
        $chartVolume['ventesMontant'][] = round($val['vMontant'], 2);
        $chartVolume['chargesMontant'][] = round($val['cMontant'], 2);
    }

    $margeProduits = $totalCA > 0 ? ($resultatComptable / $totalCA) * 100 : 0;
    $tauxCharges = $totalCA > 0 ? ($totalCharges / $totalCA) * 100 : 0;

    $totDebGlobal = $pdo->query("SELECT SUM(debit) FROM ecritures WHERE statut = 'VALIDE'")->fetchColumn() ?: 0;
    $totCredGlobal = $pdo->query("SELECT SUM(credit) FROM ecritures WHERE statut = 'VALIDE'")->fetchColumn() ?: 0;
    $ecartEquilibre = abs($totDebGlobal - $totCredGlobal);

    return [
        'kpis' => [
            'chiffreAffaires' => round($totalCA, 2),
            'varCA' => 0,
            'totalCharges' => round($totalCharges, 2),
            'varCharges' => 0,
            'resultatComptable' => round($resultatComptable, 2),
            'varResultat' => 0,
            'volumeVentesMontant' => round($volumeVentesMontant, 2),
            'nombreVentesPieces' => $nombreVentesPieces,
            'nombreEcritures' => count($ecritures)
        ],
        'complementaires' => [
            'margeProduits' => round($margeProduits, 1),
            'tauxCharges' => round($tauxCharges, 1),
            'equilibreComptable' => $ecartEquilibre < 0.05 ? "Équilibré (OK)" : "Anomalie d'écart de " . number_format($ecartEquilibre, 2)
        ],
        'charts' => ['evolution' => $chartEvolution, 'volume' => $chartVolume]
    ];
}

function modifierTiers($tiersData) {
    $pdo = getDbConnection();
    try {
        $stmt = $pdo->prepare("UPDATE tiers SET type = ?, nom = ?, reference = ?, email = ?, telephone = ?, adresse = ? WHERE id = ?");
        $stmt->execute([
            strtoupper($tiersData['type']),
            trim($tiersData['nom']),
            $tiersData['reference'] ?? '',
            $tiersData['email'] ?? '',
            $tiersData['telephone'] ?? '',
            $tiersData['adresse'] ?? '',
            $tiersData['id']
        ]);
        logAudit(null, 'MODIFICATION', 'TIERS', $tiersData['id'], "Modification tiers {$tiersData['nom']}", 'SUCCES');
        return ['success' => true, 'message' => 'Tiers mis à jour avec succès.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

function supprimerTiers($id) {
    $pdo = getDbConnection();
    try {
        $stmt = $pdo->prepare("UPDATE tiers SET statut = 'INACTIF' WHERE id = ?");
        $stmt->execute([$id]);
        logAudit(null, 'SUPPRESSION', 'TIERS', $id, "Désactivation tiers ID $id", 'SUCCES');
        return ['success' => true, 'message' => 'Tiers supprimé avec succès.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

function enregistrerComptePlan($compteData) {
    $pdo = getDbConnection();
    try {
        if (empty($compteData['isEdit'])) {
            $stmtChk = $pdo->prepare("SELECT compte FROM plan_comptable WHERE compte = ?");
            $stmtChk->execute([$compteData['compte']]);
            if ($stmtChk->fetch()) {
                return ['success' => false, 'message' => 'Ce numéro de compte existe déjà.'];
            }
            $stmt = $pdo->prepare("INSERT INTO plan_comptable (compte, libelle, type, statut) VALUES (?, ?, ?, 'ACTIF')");
            $stmt->execute([$compteData['compte'], $compteData['libelle'], $compteData['type']]);
            logAudit(null, 'CREATION', 'PLAN_COMPTABLE', $compteData['compte'], "Ajout compte {$compteData['compte']}", 'SUCCES');
        } else {
            $stmt = $pdo->prepare("UPDATE plan_comptable SET libelle = ?, type = ? WHERE compte = ?");
            $stmt->execute([$compteData['libelle'], $compteData['type'], $compteData['compte']]);
            logAudit(null, 'MODIFICATION', 'PLAN_COMPTABLE', $compteData['compte'], "Modification compte {$compteData['compte']}", 'SUCCES');
        }
        return ['success' => true, 'message' => 'Compte enregistré avec succès.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

function supprimerComptePlan($numeroCompte) {
    $pdo = getDbConnection();
    try {
        $stmt = $pdo->prepare("DELETE FROM plan_comptable WHERE compte = ?");
        $stmt->execute([$numeroCompte]);
        logAudit(null, 'SUPPRESSION', 'PLAN_COMPTABLE', $numeroCompte, "Suppression compte $numeroCompte", 'SUCCES');
        return ['success' => true, 'message' => 'Compte supprimé avec succès.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

function enregistrerCategorie($catData) {
    $pdo = getDbConnection();
    try {
        if (empty($catData['isEdit'])) {
            $newId = 'CAT-' . strtoupper(substr(md5(uniqid()), 0, 6));
            $stmt = $pdo->prepare("INSERT INTO categories (id, libelle, type_operation, compte_comptable, taux_tva, compte_tva) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$newId, $catData['libelle'], $catData['typeOperation'], $catData['compteComptable'], $catData['tauxTva'] ?: 0, $catData['compteTva'] ?: '']);
            logAudit(null, 'CREATION', 'CATEGORIES', $newId, "Création catégorie {$catData['libelle']}", 'SUCCES');
        } else {
            $stmt = $pdo->prepare("UPDATE categories SET libelle = ?, type_operation = ?, compte_comptable = ?, taux_tva = ?, compte_tva = ? WHERE id = ?");
            $stmt->execute([$catData['libelle'], $catData['typeOperation'], $catData['compteComptable'], $catData['tauxTva'] ?: 0, $catData['compteTva'] ?: '', $catData['id']]);
            logAudit(null, 'MODIFICATION', 'CATEGORIES', $catData['id'], "Modification catégorie {$catData['id']}", 'SUCCES');
        }
        return ['success' => true, 'message' => 'Catégorie enregistrée avec succès.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

function supprimerCategorie($id) {
    $pdo = getDbConnection();
    try {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        logAudit(null, 'SUPPRESSION', 'CATEGORIES', $id, "Suppression catégorie $id", 'SUCCES');
        return ['success' => true, 'message' => 'Catégorie supprimée avec succès.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

function initialiserApplicationBDD() {
    $pdo = getDbConnection();
    try {
        logAudit('SYSTEM', 'INITIALISATION', 'SYSTEME', 'ALL', 'Initialisation BDD effectuée', 'SUCCES');
        return ['success' => true, 'message' => 'Base de données opérationnelle.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur lors de l\'initialisation : ' . $e->getMessage()];
    }
}