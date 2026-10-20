CREATE DATABASE IF NOT EXISTS `compta_hepta` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `compta_hepta`;

-- Table 1: PARAMETRES
CREATE TABLE IF NOT EXISTS `parametres` (
  `cle` VARCHAR(50) NOT NULL,
  `valeur` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `parametres` (`cle`, `valeur`, `description`) VALUES
('PAYS', 'MG', 'Pays du référentiel comptable'),
('REFERENTIEL', 'PCG2014', 'Référentiel comptable applicable'),
('DEVISE', 'Ar', 'Devise principale de la tenue de compte'),
('TOLERANCE_ARRONDI', '0.01', 'Tolérance d\'écart de centimes autorisée'),
('COMPTE_ATTENTE', '471000', 'Compte d\'attente par défaut'),
('TVA_COLLECTEE_DEFAUT', '445710', 'Compte de TVA collectée par défaut'),
('TVA_DEDUCTIBLE_DEFAUT', '445660', 'Compte de TVA déductible par défaut')
ON DUPLICATE KEY UPDATE `valeur` = VALUES(`valeur`);

-- Table 2: JOURNAUX
CREATE TABLE IF NOT EXISTS `journaux` (
  `code` VARCHAR(10) NOT NULL,
  `libelle` VARCHAR(100) NOT NULL,
  `type` VARCHAR(20) NOT NULL,
  `statut` VARCHAR(20) DEFAULT 'ACTIF',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `journaux` (`code`, `libelle`, `type`, `statut`) VALUES
('HA', 'Journal des Achats', 'ACHAT', 'ACTIF'),
('VT', 'Journal des Ventes', 'VENTE', 'ACTIF'),
('BQ', 'Journal de Banque', 'TRESORERIE', 'ACTIF'),
('CA', 'Journal de Caisse', 'TRESORERIE', 'ACTIF'),
('OD', 'Opérations Diverses', 'OD', 'ACTIF')
ON DUPLICATE KEY UPDATE `libelle` = VALUES(`libelle`);

-- Table 3: PLAN COMPTABLE
CREATE TABLE IF NOT EXISTS `plan_comptable` (
  `compte` VARCHAR(20) NOT NULL,
  `libelle` VARCHAR(150) NOT NULL,
  `type` VARCHAR(30) NOT NULL,
  `statut` VARCHAR(20) DEFAULT 'ACTIF',
  PRIMARY KEY (`compte`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `plan_comptable` (`compte`, `libelle`, `type`, `statut`) VALUES
('101000', 'Capital social', 'CAPITAL', 'ACTIF'),
('401000', 'Fournisseurs collectifs', 'TIERS', 'ACTIF'),
('411000', 'Clients collectifs', 'TIERS', 'ACTIF'),
('445660', 'TVA déductible sur autres biens et services', 'TAXE', 'ACTIF'),
('445710', 'TVA collectée', 'TAXE', 'ACTIF'),
('512000', 'Banque principale', 'TRESORERIE', 'ACTIF'),
('530000', 'Caisse centrale', 'TRESORERIE', 'ACTIF'),
('607000', 'Achats de marchandises', 'CHARGE', 'ACTIF'),
('613200', 'Locations immobilières', 'CHARGE', 'ACTIF'),
('626000', 'Frais postaux et de télécommunications', 'CHARGE', 'ACTIF'),
('706000', 'Prestations de services', 'PRODUIT', 'ACTIF'),
('707000', 'Ventes de marchandises', 'PRODUIT', 'ACTIF')
ON DUPLICATE KEY UPDATE `libelle` = VALUES(`libelle`);

-- Table 4: TIERS
CREATE TABLE IF NOT EXISTS `tiers` (
  `id` VARCHAR(20) NOT NULL,
  `type` VARCHAR(20) NOT NULL,
  `nom` VARCHAR(150) NOT NULL,
  `reference` VARCHAR(50) NULL,
  `email` VARCHAR(100) NULL,
  `telephone` VARCHAR(30) NULL,
  `adresse` TEXT NULL,
  `statut` VARCHAR(20) DEFAULT 'ACTIF',
  `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `tiers` (`id`, `type`, `nom`, `reference`, `email`, `telephone`, `adresse`, `statut`) VALUES
('TIR-001', 'FOURNISSEUR', 'Fournisseur Exemple SARL', 'FR123456789', 'contact@fournisseur.mg', '0202211111', 'Antananarivo', 'ACTIF'),
('TIR-002', 'CLIENT', 'Client Démo EURL', 'FR987654321', 'compta@clientdemo.mg', '0202233333', 'Tamatave', 'ACTIF')
ON DUPLICATE KEY UPDATE `nom` = VALUES(`nom`);

-- Table 5: CATEGORIES
CREATE TABLE IF NOT EXISTS `categories` (
  `id` VARCHAR(20) NOT NULL,
  `libelle` VARCHAR(150) NOT NULL,
  `type_operation` VARCHAR(20) NOT NULL,
  `compte_comptable` VARCHAR(20) NOT NULL,
  `taux_tva` DECIMAL(5,2) DEFAULT 0.00,
  `compte_tva` VARCHAR(20) NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `categories` (`id`, `libelle`, `type_operation`, `compte_comptable`, `taux_tva`, `compte_tva`) VALUES
('CAT-ACH-01', 'Achats de marchandises', 'HA', '607000', 20.00, '445660'),
('CAT-ACH-02', 'Loyers et charges', 'HA', '613200', 0.00, ''),
('CAT-ACH-03', 'Télécoms & Internet', 'HA', '626000', 20.00, '445660'),
('CAT-VTE-01', 'Ventes de marchandises', 'VT', '707000', 20.00, '445710'),
('CAT-VTE-02', 'Prestations de services', 'VT', '706000', 20.00, '445710'),
('CAT-DEP-01', 'Dépense courante (Divers)', 'DEPENSE', '626000', 20.00, '445660')
ON DUPLICATE KEY UPDATE `libelle` = VALUES(`libelle`);

-- Table 6: TRESORERIE
CREATE TABLE IF NOT EXISTS `tresorerie` (
  `id` VARCHAR(20) NOT NULL,
  `libelle` VARCHAR(100) NOT NULL,
  `type` VARCHAR(20) NOT NULL,
  `compte_comptable` VARCHAR(20) NOT NULL,
  `statut` VARCHAR(20) DEFAULT 'ACTIF',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `tresorerie` (`id`, `libelle`, `type`, `compte_comptable`, `statut`) VALUES
('TRS-BNQ-01', 'Banque Principale', 'BANQUE', '512000', 'ACTIF'),
('TRS-CS-01', 'Caisse Espèces', 'CAISSE', '530000', 'ACTIF')
ON DUPLICATE KEY UPDATE `libelle` = VALUES(`libelle`);

-- Table 7: OPERATIONS
CREATE TABLE IF NOT EXISTS `operations` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `type` VARCHAR(20) NOT NULL,
  `date` DATE NOT NULL,
  `tiers_id` VARCHAR(150) NULL,
  `piece` VARCHAR(50) NULL,
  `montant_ht` DECIMAL(15,2) DEFAULT 0.00,
  `montant_tva` DECIMAL(15,2) DEFAULT 0.00,
  `montant_ttc` DECIMAL(15,2) DEFAULT 0.00,
  `categorie_id` VARCHAR(20) NOT NULL,
  `libelle` VARCHAR(255) NOT NULL,
  `statut` VARCHAR(20) DEFAULT 'VALIDEE',
  `auteur` VARCHAR(100) NULL,
  `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`date`),
  INDEX (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table 8: ECRITURES
CREATE TABLE IF NOT EXISTS `ecritures` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `operation_id` INT NULL,
  `journal` VARCHAR(10) NOT NULL,
  `piece` VARCHAR(50) NULL,
  `date` DATE NOT NULL,
  `compte` VARCHAR(20) NOT NULL,
  `libelle_compte` VARCHAR(150) NULL,
  `libelle_ecriture` VARCHAR(255) NULL,
  `tiers_id` VARCHAR(150) NULL,
  `debit` DECIMAL(15,2) DEFAULT 0.00,
  `credit` DECIMAL(15,2) DEFAULT 0.00,
  `statut` VARCHAR(20) DEFAULT 'VALIDE',
  PRIMARY KEY (`id`),
  INDEX (`date`),
  INDEX (`journal`),
  INDEX (`compte`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table 9: REGLEMENTS
CREATE TABLE IF NOT EXISTS `reglements` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `type` VARCHAR(20) NOT NULL,
  `date` DATE NOT NULL,
  `tiers_id` VARCHAR(150) NULL,
  `moyen_paiement` VARCHAR(30) NULL,
  `tresorerie_id` VARCHAR(20) NOT NULL,
  `montant_total` DECIMAL(15,2) DEFAULT 0.00,
  `reference` VARCHAR(50) NULL,
  `statut` VARCHAR(20) DEFAULT 'VALIDE',
  `auteur` VARCHAR(100) NULL,
  `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table 10: AFFECTATIONS_REGLEMENTS
CREATE TABLE IF NOT EXISTS `affectations_reglements` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `reglement_id` INT NOT NULL,
  `facture_op_id` INT NOT NULL,
  `montant_affecte` DECIMAL(15,2) DEFAULT 0.00,
  `date_affectation` DATE NOT NULL,
  `statut` VARCHAR(20) DEFAULT 'VALIDE',
  PRIMARY KEY (`id`),
  INDEX (`reglement_id`),
  INDEX (`facture_op_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table 11: JOURNAL_AUDIT
CREATE TABLE IF NOT EXISTS `journal_audit` (
  `id` VARCHAR(50) NOT NULL,
  `date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `utilisateur` VARCHAR(100) NULL,
  `action` VARCHAR(50) NOT NULL,
  `type_objet` VARCHAR(50) NULL,
  `objet_id` VARCHAR(50) NULL,
  `details` TEXT NULL,
  `resultat` VARCHAR(20) NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;