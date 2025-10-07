<?php
session_start();
require_once 'config.php';
require_once 'vendor/autoload.php'; // Pour Dompdf

use Dompdf\Dompdf;
use Dompdf\Options;

// Initialisation des variables
$numero_table_bac = $nom = $prenoms = $date_naissance = $sexe = $statut = $entite = $filiere = '';
$numero_whatsapp = $email = '';
$filiere_choisie = ''; // Nouvelle variable ajoutée
$errors = [];
$step = 1;
$studentFound = false;

// Vérifier si on est à l'étape 2 ou 3
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step1'])) {
        // Étape 1 : Vérification du numéro de table
        $numero_table_bac = sanitizeInput($_POST['numero_table_bac']);
        
        if (empty($numero_table_bac)) {
            $errors[] = "Le numéro de table BAC est requis.";
        } else {
            // Rechercher l'étudiant dans la base
            $conn = getDBConnection();
            
            $query = "SELECT * FROM etudiants WHERE numero_table_bac = ?";
            
            $stmt = $conn->prepare($query);
            
            if ($stmt) {
                $stmt->bind_param("s", $numero_table_bac);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $student = $result->fetch_assoc();
                    $nom = $student['nom'];
                    $prenoms = $student['prenoms'];
                    $date_naissance = $student['date_de_naissance'];
                    $sexe = $student['sexe'];
                    $statut = $student['statut'];
                    $studentFound = true;
                    $step = 2;
                    $_SESSION['numero_table_bac'] = $numero_table_bac;
                } else {
                    $errors[] = "Numéro de table BAC non trouvé.";
                }
                
                $stmt->close();
            } else {
                $errors[] = "Erreur de préparation de la requête: " . $conn->error;
            }
            
            $conn->close();
        }
    } elseif (isset($_POST['step2'])) {
        // Étape 2 : Récupération des contacts et génération PDF
        $numero_whatsapp = sanitizeInput($_POST['numero_whatsapp']);
        $email = sanitizeInput($_POST['email']);
        $filiere_choisie = sanitizeInput($_POST['filiere_choisie']); // Nouveau champ
        $numero_table_bac = $_SESSION['numero_table_bac'];
        
        // Validation
        if (empty($numero_whatsapp)) {
            $errors[] = "Le numéro WhatsApp est requis.";
        } elseif (!isValidPhone($numero_whatsapp)) {
            $errors[] = "Format de numéro WhatsApp invalide. Utilisez le format international: +22940522199";
        }
        
        if (empty($email)) {
            $errors[] = "L'email est requis.";
        } elseif (!isValidEmail($email)) {
            $errors[] = "Format d'email invalide.";
        }
        
        if (empty($filiere_choisie)) {
            $errors[] = "La filière est requise.";
        }
        
        if (empty($errors)) {
            // Enregistrer les contacts
            $conn = getDBConnection();
            
            // Vérifier si le contact existe déjà
            $checkStmt = $conn->prepare("SELECT id FROM contacts WHERE numero_table_bac = ?");
            if ($checkStmt) {
                $checkStmt->bind_param("s", $numero_table_bac);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows > 0) {
                    // Mettre à jour
                    $stmt = $conn->prepare("UPDATE contacts SET numero_whatsapp = ?, email = ?, filiere_choisie = ? WHERE numero_table_bac = ?");
                } else {
                    // Insérer
                    $stmt = $conn->prepare("INSERT INTO contacts (numero_table_bac, numero_whatsapp, email, filiere_choisie) VALUES (?, ?, ?, ?)");
                }
                
                if ($stmt) {
                    if ($checkResult->num_rows > 0) {
                        $stmt->bind_param("ssss", $numero_whatsapp, $email, $filiere_choisie, $numero_table_bac);
                    } else {
                        $stmt->bind_param("ssss", $numero_table_bac, $numero_whatsapp, $email, $filiere_choisie);
                    }
                    
                    if ($stmt->execute()) {
                        // Récupérer les informations complètes
                        $infoQuery = "
                            SELECT e.*, c.numero_whatsapp, c.email, c.filiere_choisie 
                            FROM etudiants e 
                            LEFT JOIN contacts c ON e.numero_table_bac = c.numero_table_bac 
                            WHERE e.numero_table_bac = ?
                        ";
                        
                        $infoStmt = $conn->prepare($infoQuery);
                        if ($infoStmt) {
                            $infoStmt->bind_param("s", $numero_table_bac);
                            $infoStmt->execute();
                            $studentInfo = $infoStmt->get_result()->fetch_assoc();
                            
                            // Générer le PDF
                            generatePDF($studentInfo);
                            exit;
                        } else {
                            $errors[] = "Erreur de préparation de la requête: " . $conn->error;
                        }
                    } else {
                        $errors[] = "Erreur lors de l'enregistrement des informations: " . $stmt->error;
                    }
                    
                    $stmt->close();
                } else {
                    $errors[] = "Erreur de préparation de la requête: " . $conn->error;
                }
                
                $checkStmt->close();
            } else {
                $errors[] = "Erreur de préparation de la requête: " . $conn->error;
            }
            
            $conn->close();
        } else {
            $step = 2;
            // Récupérer les informations de l'étudiant
            $conn = getDBConnection();
            $query = "SELECT * FROM etudiants WHERE numero_table_bac = ?";
            
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("s", $numero_table_bac);
                $stmt->execute();
                $result = $stmt->get_result();
                $student = $result->fetch_assoc();
                $nom = $student['nom'];
                $prenoms = $student['prenoms'];
                $date_naissance = $student['date_de_naissance'];
                $sexe = $student['sexe'];
                $statut = $student['statut'];
                $entite = $student['entite'];
                $filiere = $student['filiere'];
                $stmt->close();
            } else {
                $errors[] = "Erreur de préparation de la requête: " . $conn->error;
            }
            $conn->close();
        }
    }
}

// Fonction de génération du PDF
function generatePDF($data) {
    try {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('isPhpEnabled', true);
        
        $dompdf = new Dompdf($options);

        // Chemin des logos - avec vérification d'existence
        $logoLeftPath = __DIR__ . "/images/logo_2.jpg";
        $logoRightPath = __DIR__ . "/images/logo_f_j_t.png";
        $qrcodePath = __DIR__ . "/images/qrcode.png";
        
        $logoLeftBase64 = '';
        $logoRightBase64 = '';
        $qrcodeBase64 = '';
        
        if (file_exists($logoLeftPath)) {
            $logoLeftType = pathinfo($logoLeftPath, PATHINFO_EXTENSION);
            $logoLeftData = file_get_contents($logoLeftPath);
            $logoLeftBase64 = 'data:image/' . $logoLeftType . ';base64,' . base64_encode($logoLeftData);
        }
        
        if (file_exists($logoRightPath)) {
            $logoRightType = pathinfo($logoRightPath, PATHINFO_EXTENSION);
            $logoRightData = file_get_contents($logoRightPath);
            $logoRightBase64 = 'data:image/' . $logoRightType . ';base64,' . base64_encode($logoRightData);
        }
        
        if (file_exists($qrcodePath)) {
            $qrcodeType = pathinfo($qrcodePath, PATHINFO_EXTENSION);
            $qrcodeData = file_get_contents($qrcodePath);
            $qrcodeBase64 = 'data:image/' . $qrcodeType . ';base64,' . base64_encode($qrcodeData);
        }
        // Formater la date de naissance
        $date_naissance = !empty($data['date_de_naissance']) ? date('d/m/Y', strtotime($data['date_de_naissance'])) : '';
        
        // Afficher la filière choisie
        $filiere_affichee = !empty($data['filiere_choisie']) ? 
            ($data['filiere_choisie'] == 'MI' ? 'Mathématique Informatique (MI)' : 'Physique Chimie (PC)') : 
            htmlspecialchars($data['filiere']);
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Fiche de confirmation</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 0; 
                    font-size: 12pt;
                }
                .header-table {
                    width: 100%;
                    margin-bottom: 20px;
                    text-align: center;
                }
                .header-table td {
                    vertical-align: middle;
                    padding: 5px;
                }
                .header-table .logo {
                    width: 100px;
                }
                .header-table img {
                    max-height: 100px;
                }
                .institution-name { font-weight: bold; font-size: 16px; margin-bottom: 5px; }
                .ministry-name { font-size: 14px; margin: 8px 0; }
                .university-name { font-size: 13px; margin: 8px 0; font-style: italic; }
                .account-info { margin-top: 10px; font-size: 16px; line-height: 1.4; font-weight: bold; }
                .separator { margin: 5px 0; color: #333; font-size: 12px; }
                .header-text h1 { font-size: 16pt; margin: 0px; color: #000; }
                .validity { text-align: center; font-weight: bold; margin: 0px; }
                .mark { background-color: yellow; padding: 0 2px; }
                .validity {
                    text-align: right;
                    font-weight: bold;
                    text-align: center;
                    margin-top: 0px;
                }
                .main-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                }
                .main-table th, .main-table td {
                    border: 1px solid #000;
                    padding: 8px;
                    text-align: left;
                }
                .main-table th {
                    background-color: #f2f2f2;
                    font-weight: bold;
                }
                .info-section {
                    margin-top: 0px;
                }
                .info-section h3 {
                    font-size: 12pt;
                    margin-bottom: 10px;
                }
                .info-section ul {
                    margin-top: 5px;
                    padding-left: 20px;
                }
                .info-section li {
                    margin-bottom: 8px;
                    text-align: justify;
                }
                .footer {
                    display: flex;
                    justify-content: space-between;
                    margin-top: 0px;
                }
                .qr-code {
                    width: 200px;
                    height: 200px;
                    border: 1px solid #000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-style: italic;
                }
                .contact-info {
                    font-weight: bold;
                    margin-top: 10px;
                }
                .mark {
                    background-color: yellow;
                    padding: 0 2px;
                }
            </style>
        </head>
        <body>
            <table class="header-table">
                <tr>
                    <td class="logo" align="left">';
        
        if (!empty($logoLeftBase64)) {
            $html .= '<img src="'.$logoLeftBase64.'" alt="Logo gauche">';
        }
        
        $html .= '</td>
                    <td>
                        <div class="institution-name">REPUBLIQUE DU BENIN</div>
                        <div class="separator">*************</div>
                        <div class="ministry-name">MINISTERE DE L\'ENSEIGNEMENT SUPERIEUR ET DE LA RECHERCHE SCIENTIFIQUE</div>
                        <div class="separator">*************</div>
                        <div class="university-name">UNIVERSITE NATIONALE DES SCIENCES, TECHNOLOGIES, INGENIERIE ET MATHEMATIQUES (UNSTIM)</div>
                        <div class="separator">*************</div>
                        <div class="university-name">FACULTE DES SCIENCES ET TECHNIQUES (FAST) DE NATITINGOU</div>
                        <!-- <div class="separator">*************</div>
                        <div class="account-info">
                            INTITULE DU COMPTE : UNSTIM / COMPTE INSCRIPTION / TRESOR PUBLIC<br>
                            NUMERO DU COMPTE : BJ660-01001-00001048543-79
                        </div> -->
                    </td>
                    <td class="logo" align="right">';
        
        if (!empty($logoRightBase64)) {
            $html .= '<img src="'.$logoRightBase64.'" alt="Logo droite">';
        }
        
        $html .= '</td>
                </tr>
                <tr>
                    <td colspan="3" class="header-text">
                        <h1>FICHE DE SELECTION</h1>
                    </td>
                </tr>
            </table>

            <!-- <div class="validity">
                VALIDITE DE LA FICHE : <span class="mark">31 OCTOBRE 2025</span>
            </div> -->

            <table class="main-table">
                <tr>
                    <th>Objet du dépôt :</th>
                    <td colspan="7">Choix de filière</td>
                </tr>
                <tr>
                    <th>Numéro de Table au BAC :</th>
                    <td colspan="7">' . htmlspecialchars($data['numero_table_bac']) . '</td>
                </tr>
                <tr>
                    <th>Nom de l\'Étudiant :</th>
                    <td colspan="7">' . htmlspecialchars($data['nom']) . '</td>
                </tr>
                <tr>
                    <th>Prénom(s) de l\'Étudiant :</th>
                    <td colspan="7">' . htmlspecialchars($data['prenoms']) . '</td>
                </tr>
                <tr>
                    <th>Sexe :</th>
                    <td colspan="7">' . htmlspecialchars($data['sexe']) . '</td>
                </tr>
                <tr>
                    <th>Entité :</th>
                    <td colspan="3">FAST</td>
                    <th>Filière :</th>
                    <td colspan="3">' . $filiere_affichee . '</td>
                </tr>
                <tr>
                    <th>Statut :</th>
                    <td colspan="3">' . htmlspecialchars($data['statut']) . '</td>
                    <th>Montant :</th>
                    <td colspan="3">' . htmlspecialchars($data['montant']) . ' FCFA</td>
                </tr>
            </table>

            <div class="footer">
                <div class="info-section" style="width: 100%;">
                    <h3>NB : Veuillez lire attentivement les instructions d\'admissions aux écoles et instituts de l\'UNSTIM ci-dessous :</h3>
                    <ul>
                        <li>Si votre candidature est retenue, vous pourrez confirmer votre place dès le <span class="mark">1er novembre 2025</span>.</li>
                        <li>Pour toutes informations complémentaires veuillez contacter le numéro suivant : <strong>01 43 80 14 29</strong></li>
                        <li>Vous pouvez procéder à la confirmation de votre choix de filière à l\'UNSTIM dès le <span class="mark">1er novembre 2025</span> sur la plateforme <a href="https://etudiant.unstim.bj/confirmation">https://etudiant.unstim.bj/confirmation</a> ou en scannant le QR Code ci-dessous :</li>
                    </ul>
                </div>
            </div>

            <!-- QR Code centré -->
            <div style="text-align: center; margin-top: 30px;">
                ';
        
        if (!empty($qrcodeBase64)) {
            $html .= '<img src="'.$qrcodeBase64.'" alt="QR Code" style="width: 100px; height: 100px;">';
        }
        
        $html .= '
            </div>
        </body>
        </html>';
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Vider le tampon de sortie pour éviter tout conflit d'en-têtes
        if (ob_get_length()) {
            ob_end_clean();
        }
        
        // Output the generated PDF
        $dompdf->stream('fiche_de_selection_' . $data['numero_table_bac'] . '.pdf', [
            'Attachment' => true
        ]);
        exit;
        
    } catch (Exception $e) {
        // En cas d'erreur, afficher le message
        die("Erreur lors de la génération du PDF: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sélection FAST NATI UNSTIM 2025 - 2026</title>
    <!-- Balises Meta SEO Essentielles -->
    <meta name="description" content="Générez votre fiche de confirmation pour l'Université Nationale des Sciences, Technologies, Ingénierie et Mathématiques (UNSTIM). Processus simple et sécurisé.">
    <meta name="keywords" content="UNSTIM, fiche de confirmation, université Bénin, inscription universitaire, frais académiques, paiement études">
    <meta name="author" content="Université Nationale des Sciences, Technologies, Ingénierie et Mathématiques">
    <meta name="robots" content="index, follow">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="images/logo_2.jpg" type="image/jpg">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Sélection FAST NATI 2025 - 2026</h1>
            <p>Faites votre choix de filière à la FAST NATI et obtenez votre fiche de sélection</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-container">
                <h3>Erreurs :</h3>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <!-- Étape 1 : Saisie du numéro de table BAC -->
            <div class="form-container">
                <h2>Etape 1/2 : Vérification</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="numero_table_bac">Numéro de Table au BAC *</label>
                        <input type="text" id="numero_table_bac" name="numero_table_bac" 
                               value="<?php echo htmlspecialchars($numero_table_bac); ?>" 
                               required placeholder="Ex: BAC-2024-001">
                    </div>
                    <button type="submit" name="step1" class="btn-primary">Vérifier</button>
                </form>
            </div>

        <?php elseif ($step === 2 && $studentFound): ?>
            <!-- Étape 2 : Affichage des informations et saisie des contacts -->
            <div class="form-container">
                <h2>Etape 2/2 : Informations de contact</h2>
                
                <div class="student-info">
                    <h3>Informations de l'étudiant :</h3>
                    <p><strong>Numéro Table BAC :</strong> <?php echo htmlspecialchars($numero_table_bac); ?></p>
                    <p><strong>Nom :</strong> <?php echo htmlspecialchars($nom); ?></p>
                    <p><strong>Prénoms :</strong> <?php echo htmlspecialchars($prenoms); ?></p>
                    <p><strong>Sexe :</strong> <?php echo htmlspecialchars($sexe); ?></p>
                    <p><strong>Statut :</strong> <?php echo htmlspecialchars($statut); ?></p>
                    <p><strong>Entité :</strong> FAST</p>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="numero_table_bac" value="<?php echo htmlspecialchars($numero_table_bac); ?>">
                    
                    <div class="form-group">
                        <label for="numero_whatsapp">Numéro WhatsApp *</label>
                        <input type="tel" id="numero_whatsapp" name="numero_whatsapp" 
                               value="<?php echo htmlspecialchars($numero_whatsapp); ?>" 
                               required placeholder="Format international sans le 01 Ex : +22940522199">
                        <small>Format international requis</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($email); ?>" 
                               required placeholder="Ex: etudiant@example.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="filiere_choisie">Filière choisie *</label>
                        <select id="filiere_choisie" name="filiere_choisie" required>
                            <option value="">Sélectionnez votre filière</option>
                            <option value="MI" <?php echo ($filiere_choisie == 'MI') ? 'selected' : ''; ?>>Mathématique Informatique (MI)</option>
                            <option value="PC" <?php echo ($filiere_choisie == 'PC') ? 'selected' : ''; ?>>Physique Chimie (PC)</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="step2" class="btn-success">
                        📄 Télécharger ma fiche de sélection
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <div class="instructions">
            <h3>Instructions :</h3>
            <ol>
                <li>Saisissez votre numéro de table BAC</li>
                <li>Renseignez vos coordonnées et choisissez votre filière</li>
                <li>Téléchargez votre fiche de sélection</li>
            </ol>
        </div>
    </div>
</body>
</html>