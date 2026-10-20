<?php
/**
 * HEPTA Compta Web - Contrôleur API JSON
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput, true) ?: $_POST;

    switch ($action) {
        case 'getInitialData':
            echo json_encode(getInitialData());
            break;

        case 'getDashboardData':
            $params = $inputData['params'] ?? null;
            echo json_encode(getDashboardData($params));
            break;

        case 'getJournalEntries':
            $filters = $inputData['filters'] ?? null;
            echo json_encode(getJournalEntries($filters));
            break;

        case 'getBalanceComptable':
            echo json_encode(getBalanceComptable());
            break;

        case 'getFacturesOuvertes':
            $tiersId = $_GET['tiersId'] ?? $inputData['tiersId'] ?? '';
            echo json_encode(getFacturesOuvertes($tiersId));
            break;

        case 'creerTiers':
            echo json_encode(creerTiers($inputData));
            break;

        case 'modifierTiers':
            echo json_encode(modifierTiers($inputData));
            break;

        case 'supprimerTiers':
            $id = $inputData['id'] ?? $_GET['id'] ?? '';
            echo json_encode(supprimerTiers($id));
            break;

        case 'enregistrerOperation':
            echo json_encode(enregistrerOperation($inputData));
            break;

        case 'enregistrerReglement':
            echo json_encode(enregistrerReglement($inputData));
            break;

        case 'enregistrerComptePlan':
            echo json_encode(enregistrerComptePlan($inputData));
            break;

        case 'supprimerComptePlan':
            $compte = $inputData['compte'] ?? $_GET['compte'] ?? '';
            echo json_encode(supprimerComptePlan($compte));
            break;

        case 'enregistrerCategorie':
            echo json_encode(enregistrerCategorie($inputData));
            break;

        case 'supprimerCategorie':
            $id = $inputData['id'] ?? $_GET['id'] ?? '';
            echo json_encode(supprimerCategorie($id));
            break;

        case 'initialiserApplication':
            echo json_encode(initialiserApplicationBDD());
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action inconnue : ' . $action]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur API : ' . $e->getMessage()]);
}