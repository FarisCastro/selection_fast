<?php
session_start();
require_once 'config.php';

// Configuration du mot de passe administrateur
$admin_password = "fuck9You9Like5@Never"; 

// Gestion de la déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Vérification d'authentification
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    if (isset($_POST['admin_password'])) {
        if ($_POST['admin_password'] === $admin_password) {
            $_SESSION['admin_authenticated'] = true;
            header('Location: admin.php');
            exit;
        } else {
            $error = "Mot de passe incorrect";
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Connexion Admin</title>
        <link rel="stylesheet" href="style.css">
        <link rel="icon" href="images/logo_2.png" type="image/png">
        <style>
            .login-container {
                max-width: 500px;
                margin: 50px auto;
            }
        </style>
    </head>
    <body>
        <div class="container login-container">
            <div class="header">
                <h1>Connexion Administration</h1>
                <p>Accès réservé au personnel autorisé</p>
            </div>
            <div class="form-container">
                <?php if (isset($error)): ?>
                    <div class="error-container">
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="admin_password">Mot de passe administrateur</label>
                        <input type="password" id="admin_password" name="admin_password" required 
                               placeholder="Entrez le mot de passe administrateur">
                    </div>
                    <button type="submit" class="btn-primary">Se connecter</button>
                </form>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="index.php" style="color: #3498db; text-decoration: none;">← Retour à l'accueil public</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// TRAITEMENT DE L'EXPORT CSV
if (isset($_POST['export_csv'])) {
    exportToCSV();
}

// Récupérer la liste des étudiants avec leurs choix de filière
function getStudentsWithFiliere() {
    $conn = getDBConnection();
    
    $query = "
        SELECT 
            e.numero_table_bac,
            e.nom,
            e.prenoms,
            e.date_de_naissance,
            e.sexe,
            e.statut,
            e.montant,
            e.serie,
            c.numero_whatsapp,
            c.email,
            c.filiere_choisie
        FROM contacts c
        INNER JOIN etudiants e ON c.numero_table_bac = e.numero_table_bac
        ORDER BY e.nom, e.prenoms
    ";
    
    $result = $conn->query($query);
    $students = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    }
    
    $conn->close();
    return $students;
}

// Fonction d'export CSV corrigée (encodage UTF-8)
function exportToCSV() {
    $students = getStudentsWithFiliere();
    
    // Définir les en-têtes pour le téléchargement avec BOM UTF-8
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="etudiants_filiere_' . date('Y-m-d') . '.csv"');
    
    // Créer le fichier de sortie
    $output = fopen('php://output', 'w');
    
    // Ajouter BOM UTF-8 pour Excel
    fputs($output, "\xEF\xBB\xBF");
    
    // En-têtes du CSV
    fputcsv($output, [
        'Numéro Table BAC',
        'Nom',
        'Prénoms',
        'Date de Naissance',
        'Sexe',
        'Statut',
        'Montant',
        'Série',
        'Filière choisie',
        'WhatsApp',
        'Email'
    ], ';');
    
    // Données
    foreach ($students as $student) {
        // Formater la filière choisie
        $filiere_choisie = '';
        if ($student['filiere_choisie'] == 'MI') {
            $filiere_choisie = 'Mathématique Informatique (MI)';
        } elseif ($student['filiere_choisie'] == 'PC') {
            $filiere_choisie = 'Physique Chimie (PC)';
        }
        
        // Formater la date de naissance
        $date_naissance = '';
        if (!empty($student['date_de_naissance'])) {
            $date_naissance = date('d/m/Y', strtotime($student['date_de_naissance']));
        }
        
        fputcsv($output, [
            $student['numero_table_bac'],
            $student['nom'],
            $student['prenoms'],
            $date_naissance,
            $student['sexe'],
            $student['statut'],
            $student['montant'],
            $student['serie'],
            $filiere_choisie,
            $student['numero_whatsapp'],
            $student['email']
        ], ';');
    }
    
    fclose($output);
    exit;
}

$students = getStudentsWithFiliere();
$totalStudents = count($students);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration</title>
    <meta name="description" content="Page d'administration - Gestion des étudiants et choix de filière">
    <meta name="author" content="Université Nationale des Sciences, Technologies, Ingénierie et Mathématiques">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="images/logo_2.png" type="image/png">
    <style>
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .stats-container {
            background: rgba(52, 152, 219, 0.1);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow-x: auto;
        }
        
        .students-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }
        
        .students-table th {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            border: none;
            font-size: 14px;
        }
        
        .students-table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 14px;
        }
        
        .students-table tr:last-child td {
            border-bottom: none;
        }
        
        .students-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .filiere-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            display: inline-block;
        }
        
        .filiere-MI {
            background-color: #e8f6f3;
            color: #1a5276;
            border: 1px solid #aed6f1;
        }
        
        .filiere-PC {
            background-color: #fef9e7;
            color: #7d6608;
            border: 1px solid #f9e79f;
        }
        
        .btn-export {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-export:hover {
            background: linear-gradient(135deg, #229954 0%, #1e8449 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .btn-back {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-back:hover {
            background: linear-gradient(135deg, #7f8c8d 0%, #707b7c 100%);
            transform: translateY(-2px);
        }
        
        .btn-logout {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-left: 10px;
        }
        
        .btn-logout:hover {
            background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
            transform: translateY(-2px);
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
            background: white;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .admin-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .button-group {
                justify-content: center;
            }
            
            .students-table {
                font-size: 13px;
                min-width: 900px;
            }
            
            .students-table th,
            .students-table td {
                padding: 10px 8px;
            }
        }
        
        @media (max-width: 480px) {
            .students-table {
                min-width: 800px;
            }
            
            .students-table th,
            .students-table td {
                padding: 8px 6px;
                font-size: 12px;
            }
            
            .filiere-badge {
                font-size: 0.75em;
                padding: 4px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Administration</h1>
        </div>

        <div class="form-container">
            <div class="admin-header">
                <div class="button-group">
                    <a href="index.php" class="btn-back">← Retour à l'accueil</a>
                    <a href="admin.php?logout=1" class="btn-logout">🚪 Déconnexion</a>
                </div>
                <?php if ($totalStudents > 0): ?>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="export_csv" class="btn-export">
                        📊 Exporter en CSV (<?php echo $totalStudents; ?>)
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <?php if ($totalStudents > 0): ?>
            <div class="stats-container">
                <h3>Statistiques générales</h3>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $totalStudents; ?></div>
                        <div class="stat-label">Etudiants inscrits</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">
                            <?php 
                            $miCount = array_filter($students, function($student) {
                                return $student['filiere_choisie'] == 'MI';
                            });
                            echo count($miCount);
                            ?>
                        </div>
                        <div class="stat-label">Mathématique Informatique (MI)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">
                            <?php 
                            $pcCount = array_filter($students, function($student) {
                                return $student['filiere_choisie'] == 'PC';
                            });
                            echo count($pcCount);
                            ?>
                        </div>
                        <div class="stat-label">Physique Chimie (PC)</div>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <table class="students-table">
                    <thead>
                        <tr>
                            <th>Numéro Table BAC</th>
                            <th>Nom</th>
                            <th>Prénoms</th>
                            <th>Date de Naissance</th>
                            <th>Sexe</th>
                            <th>Statut</th>
                            <th>Montant</th>
                            <th>Série</th>
                            <th>Filière choisie</th>
                            <th>WhatsApp</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($student['numero_table_bac']); ?></strong></td>
                                <td><?php echo htmlspecialchars($student['nom']); ?></td>
                                <td><?php echo htmlspecialchars($student['prenoms']); ?></td>
                                <td>
                                    <?php 
                                    if (!empty($student['date_de_naissance'])) {
                                        echo date('d/m/Y', strtotime($student['date_de_naissance']));
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($student['sexe']); ?></td>
                                <td><?php echo htmlspecialchars($student['statut']); ?></td>
                                <td><?php echo htmlspecialchars($student['montant']); ?> FCFA</td>
                                <td><?php echo htmlspecialchars($student['serie']); ?></td>
                                <td>
                                    <?php if ($student['filiere_choisie'] == 'MI'): ?>
                                        <span class="filiere-badge filiere-MI">Mathématique Informatique (MI)</span>
                                    <?php elseif ($student['filiere_choisie'] == 'PC'): ?>
                                        <span class="filiere-badge filiere-PC">Physique Chimie (PC)</span>
                                    <?php else: ?>
                                        <span style="color: #999; font-style: italic;">Non défini</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($student['numero_whatsapp']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="no-data">
                <h3>Aucun étudiant n'a encore fait de choix de filière</h3>
                <p>Les données apparaîtront ici dès que les étudiants commenceront à remplir le formulaire.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>