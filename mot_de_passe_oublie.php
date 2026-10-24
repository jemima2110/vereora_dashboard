<?php
session_start();
require_once __DIR__ . '/connexion.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/libraries/PHPMailer/src/Exception.php';
require_once __DIR__ . '/libraries/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/libraries/PHPMailer/src/SMTP.php';

$message = '';
$type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Veuillez saisir une adresse e-mail valide.';
        $type = 'danger';
    } else {
        try {
            $stmt = $pdo->prepare(
                'SELECT id_utilisateur, nom, prenom, email
                 FROM utilisateurs
                 WHERE email = :email AND statut = 1 LIMIT 1'
            );
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            $message = 'Si cette adresse est associée à un compte actif, un lien sera envoyé.';
            $type = 'success';

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expire = date('Y-m-d H:i:s', time() + 3600);

                $update = $pdo->prepare(
                    'UPDATE utilisateurs
                     SET reset_token = :token, reset_token_expire = :expire
                     WHERE id_utilisateur = :id'
                );
                $update->execute([
                    'token' => $token,
                    'expire' => $expire,
                    'id' => $user['id_utilisateur']
                ]);

                $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
                $base = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
                $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
                $link = $base . $dir . '/reinitialiser_mot_de_passe.php?token=' . urlencode($token);

                $name = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
                $mail = new PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;

                    $configMail = require __DIR__ . '/config_mail.php';

                    $mail->Username = $configMail['username'];
                    $mail->Password = $configMail['password'];

                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                    $mail->CharSet = 'UTF-8';
                    $mail->setFrom($mail->Username, 'Véréora');
                    $mail->addAddress($user['email'], $name);
                    $mail->isHTML(true);
                    $mail->Subject = 'Réinitialisation de votre mot de passe Véréora';
                    $safeName = htmlspecialchars($name ?: 'utilisateur', ENT_QUOTES, 'UTF-8');
                    $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
                    $mail->Body = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:24px;color:#263238">
                        <div style="background:#0b3d2e;color:white;padding:22px;text-align:center"><h1>Véréora</h1></div>
                        <h2>Réinitialisation du mot de passe</h2>
                        <p>Bonjour ' . $safeName . ',</p>
                        <p>Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe. Ce lien expire dans une heure.</p>
                        <p style="text-align:center;margin:28px"><a href="' . $safeLink . '" style="background:#0b3d2e;color:white;text-decoration:none;padding:13px 22px;border-radius:6px">Réinitialiser mon mot de passe</a></p>
                        <p>Si vous n’avez pas demandé cette réinitialisation, ignorez cet e-mail.</p></div>';
                    $mail->AltBody = "Bonjour $name,\nRéinitialisez votre mot de passe avec ce lien : $link\nCe lien expire dans une heure.";
                    $mail->send();
                } catch (Exception $e) {
                    error_log('Erreur PHPMailer Véréora : ' . $mail->ErrorInfo);
                    $message = 'L’envoi de l’e-mail a échoué. Vérifie les paramètres Gmail dans ce fichier.';
                    $type = 'danger';
                }
            }
        } catch (PDOException $e) {
            error_log('Erreur mot de passe oublié Véréora : ' . $e->getMessage());
            $message = 'Erreur de base de données. Vérifie les colonnes reset_token et reset_token_expire.';
            $type = 'danger';
        } catch (Throwable $e) {
            error_log('Erreur Véréora : ' . $e->getMessage());
            $message = 'Une erreur est survenue. Vérifie les chemins des fichiers PHPMailer.';
            $type = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mot de passe oublié | Véréora</title>
<style>
:root{--forest:#0b3d2e;--emerald:#10b981;--background:#f1f3f6}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:var(--background);font-family:Arial,sans-serif;color:#1f2937}
.container{width:100%;max-width:950px;min-height:480px;display:grid;grid-template-columns:1fr 1fr;overflow:hidden;background:white;border-radius:18px;box-shadow:0 18px 55px #0f172a1f}
.intro{background:var(--forest);color:white;padding:48px 40px;display:flex;flex-direction:column;justify-content:center}
.brand{font-size:28px;font-weight:800;letter-spacing:1px}
.intro h1{font-size:32px;line-height:1.2;margin:34px 0 14px}
.intro p,.subtitle{line-height:1.7;color:#d5e5df}
.form-panel{padding:48px 40px;display:flex;flex-direction:column;justify-content:center}
h2{margin:0 0 10px;font-size:27px}.subtitle{color:#6b7280;margin:0 0 24px}
label{display:block;font-weight:600;margin-bottom:9px}
input{width:100%;padding:14px;border:1px solid #d1d5db;border-radius:8px;font-size:16px}
input:focus{outline:2px solid #10b981}
button{width:100%;margin-top:18px;padding:14px;border:0;border-radius:8px;background:var(--forest);color:white;font-size:16px;font-weight:700;cursor:pointer}
.message{padding:12px 14px;border-radius:8px;margin:0 0 20px;line-height:1.5}.success{background:#ecfdf5;color:#065f46}.danger{background:#fef2f2;color:#991b1b}
.back{display:block;margin-top:24px;text-align:center;color:var(--forest);text-decoration:none;font-weight:600}
@media(max-width:720px){body{padding:14px}.container{grid-template-columns:1fr}.intro{padding:28px}.intro h1{font-size:25px;margin-top:20px}.form-panel{padding:32px 26px}}
</style>
</head>
<body>
<main class="container">
<section class="intro">
<div class="brand">VÉRÉORA</div>
<h1>Retrouvez l’accès à votre compte.</h1>
<p>Saisissez l’adresse e-mail associée à votre compte pour recevoir un lien sécurisé de réinitialisation.</p>
</section>
<section class="form-panel">
<h2>Mot de passe oublié ?</h2>
<p class="subtitle">Le lien de réinitialisation est valable une heure.</p>
<?php if ($message !== ''): ?>
<div class="message <?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<form method="POST" action="mot_de_passe_oublie.php">
<label for="email">Adresse e-mail</label>
<input type="email" id="email" name="email" placeholder="exemple@domaine.com" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" autocomplete="email" required>
<button type="submit">Envoyer le lien</button>
</form>
<a class="back" href="log.php">← Retour à la connexion</a>
</section>
</main>
</body>
</html>
