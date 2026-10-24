<?php
session_start();

require_once 'connexion.php';


if (isset($_SESSION['user_id'])) {
    header('Location: dash.php');
    exit;
}

$messageErreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifiant = trim($_POST['identifiant'] ?? '');
    $motDePasse = $_POST['password'] ?? '';

    if ($identifiant === '' || $motDePasse === '') {
        $messageErreur = 'Veuillez renseigner tous les champs.';
    } else {
        try {
            $sql = "SELECT id_utilisateur, nom, prenom, email,
                           mot_de_passe, role, statut
                    FROM utilisateurs
                    WHERE email = :email
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute(['email' => $identifiant]);
            $utilisateur = $stmt->fetch();

            if (
                $utilisateur &&
                (int) $utilisateur['statut'] === 1 &&
                password_verify($motDePasse, $utilisateur['mot_de_passe'])
            ) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $utilisateur['id_utilisateur'];
                $_SESSION['user_nom'] = $utilisateur['nom'];
                $_SESSION['user_prenom'] = $utilisateur['prenom'];
                $_SESSION['user_email'] = $utilisateur['email'];
                $_SESSION['user_role'] = $utilisateur['role'];

                header('Location: dash.php');
                exit;
            }

            $messageErreur = 'Identifiant ou mot de passe incorrect.';
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $messageErreur = 'Une erreur est survenue. Veuillez réessayer.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Véréora — Connexion</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --forest: #0b3d2e;
            --emerald: #10b981;
            --background: #f1f3f6;
            --text: #1f2937;
            --muted: #6b7280;
            --border: #e5e7eb;
            --danger: #ef4444;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--background);
            color: var(--text);
        }

        .login-page {
            display: flex;
            min-height: 100vh;
        }

        .presentation {
            flex: 1.1;
            padding: 3rem;
            color: white;
            background: linear-gradient(135deg, #0b3d2e, #06281e, #0f5132);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .logo {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--emerald);
        }

        .pitch { max-width: 480px; }

        .pitch h2 {
            font-size: 2.2rem;
            line-height: 1.3;
        }

        .pitch p {
            color: #d1fae5;
            line-height: 1.7;
        }

        .illustration {
            width: 100%;
            max-width: 380px;
            margin-top: 2rem;
        }

        .footnote {
            color: #a7f3d0;
            font-size: 0.8rem;
        }

        .form-side {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .form-card {
            width: 100%;
            max-width: 420px;
            padding: 2.5rem;
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 12px 40px #0b3d2e12;
        }

        .mobile-logo {
            display: none;
            margin-bottom: 1.5rem;
            color: var(--forest);
            font-size: 1.4rem;
            font-weight: 700;
        }

        h1 {
            margin-bottom: 0.4rem;
            font-size: 1.6rem;
        }

        .subtitle {
            margin-bottom: 1.8rem;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .field { margin-bottom: 1.2rem; }

        .field label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .input-wrap {
            display: flex;
            align-items: center;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0 0.8rem;
        }

        .input-wrap:focus-within {
            border-color: var(--emerald);
            box-shadow: 0 0 0 3px #10b98120;
        }

        .input-wrap input {
            width: 100%;
            min-width: 0;
            padding: 0.85rem 0.6rem;
            border: none;
            outline: none;
            font: inherit;
        }

        .input-wrap input:focus { box-shadow: none; }

        .toggle-password {
            padding: 0.4rem;
            border: none;
            background: none;
            color: var(--muted);
            cursor: pointer;
        }

        .field-error {
            display: none;
            margin-top: 0.4rem;
            color: var(--danger);
            font-size: 0.78rem;
        }

        .field-error.show { display: block; }

        .invalid { border-color: var(--danger) !important; }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin: 1.3rem 0;
            font-size: 0.8rem;
        }

        .remember {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--muted);
        }

        .form-options a {
            color: #059669;
            text-decoration: none;
        }

        .btn-submit {
            width: 100%;
            padding: 0.9rem;
            border: none;
            border-radius: 8px;
            background: var(--emerald);
            color: white;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-submit:hover { background: #059669; }

        .btn-submit:disabled {
            opacity: 0.7;
            cursor: wait;
        }

        .alert {
            margin-bottom: 1rem;
            padding: 0.8rem;
            border-radius: 8px;
            background: #fee2e2;
            color: #991b1b;
            font-size: 0.85rem;
        }

        .security-note {
            margin-top: 1.3rem;
            color: var(--muted);
            text-align: center;
            font-size: 0.78rem;
        }

        @media (max-width: 992px) {
            .presentation { display: none; }

            .form-card {
                border: none;
                box-shadow: none;
            }

            .mobile-logo { display: block; }
        }

        @media (max-width: 480px) {
            .form-side { padding: 1rem; }

            .form-card { padding: 1.5rem 1.2rem; }

            h1 { font-size: 1.4rem; }
        }
    </style>
</head>

<body>
<div class="login-page">

    <section class="presentation">
        <div class="logo">↗ Véréora</div>

        <div class="pitch">
            <h2>Gérez vos ventes<br>en toute simplicité</h2>

            <p>
                Suivez vos performances, analysez votre chiffre
                d'affaires et gardez le contrôle de votre activité commerciale.
            </p>

            <svg class="illustration" viewBox="0 0 380 220"
                 xmlns="http://www.w3.org/2000/svg">
                <rect width="380" height="220" rx="14" fill="#ffffff0d"/>

                <g stroke="#ffffff20">
                    <line x1="30" y1="60" x2="350" y2="60"/>
                    <line x1="30" y1="100" x2="350" y2="100"/>
                    <line x1="30" y1="140" x2="350" y2="140"/>
                    <line x1="30" y1="180" x2="350" y2="180"/>
                </g>

                <polyline points="40,160 100,130 160,145 220,90 280,105 340,55"
                          fill="none" stroke="#10b981" stroke-width="3"
                          stroke-linecap="round" stroke-linejoin="round"/>

                <g fill="#10b981">
                    <circle cx="40" cy="160" r="5"/>
                    <circle cx="100" cy="130" r="5"/>
                    <circle cx="160" cy="145" r="5"/>
                    <circle cx="220" cy="90" r="5"/>
                    <circle cx="280" cy="105" r="5"/>
                    <circle cx="340" cy="55" r="5"/>
                </g>
            </svg>
        </div>

        <div class="footnote">
            © 2026 Véréora — Tous droits réservés.
        </div>
    </section>

    <section class="form-side">
        <div class="form-card">

            <div class="mobile-logo">↗ Véréora</div>

            <h1>Bienvenue !</h1>
            <p class="subtitle">
                Connectez-vous à votre Tableau de bord.
            </p>

            <?php if ($messageErreur !== ''): ?>
                <div class="alert" id="alert" role="alert">
                    <?= htmlspecialchars($messageErreur, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form id="loginForm" method="POST" action="login.php">
                <div class="field">
                    <label for="identifiant">
                        Identifiant ou adresse e-mail
                    </label>

                    <div class="input-wrap" id="identifiantWrap">
                        <span aria-hidden="true">👤</span>

                        <input type="email" id="identifiant"
                               name="identifiant"
                               placeholder="vous@entreprise.com"
                               autocomplete="username"
                               required>
                    </div>

                    <div class="field-error" id="idError">
                        Veuillez renseigner votre adresse e-mail.
                    </div>
                </div>

                <div class="field">
                    <label for="password">Mot de passe</label>

                    <div class="input-wrap" id="passwordWrap">
                        <span aria-hidden="true">🔒</span>

                        <input type="password" id="password"
                               name="password"
                               placeholder="Votre mot de passe"
                               autocomplete="current-password"
                               required>

                    </div>

                    <div class="field-error" id="passwordError">
                        Veuillez renseigner votre mot de passe.
                    </div>
                </div>

                <div class="form-options">

                    <a href="mot_de_passe_oublie.php" id="forgotPassword">
                        Mot de passe oublié ?
                    </a>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    Se connecter
                </button>
            </form>

            <p class="security-note">
                Accès réservé aux utilisateurs autorisés.
            </p>
        </div>
    </section>
</div>

<script>
    const form = document.getElementById('loginForm');
    const identifiant = document.getElementById('identifiant');
    const password = document.getElementById('password');
    const button = document.getElementById('submitBtn');

    document.getElementById('togglePassword').addEventListener('click', function () {
        password.type = password.type === 'password' ? 'text' : 'password';

        this.textContent = password.type === 'password' ? '👁' : '🙈';

        this.setAttribute(
            'aria-label',
            password.type === 'password'
                ? 'Afficher le mot de passe'
                : 'Masquer le mot de passe'
        );
    });

    identifiant.addEventListener('input', function () {
        document.getElementById('idError').classList.remove('show');
        document.getElementById('identifiantWrap').classList.remove('invalid');
    });

    password.addEventListener('input', function () {
        document.getElementById('passwordError').classList.remove('show');
        document.getElementById('passwordWrap').classList.remove('invalid');
    });

    form.addEventListener('submit', function () {
        button.disabled = true;
        button.textContent = 'Connexion...';
    });

    document.getElementById('forgotPassword').addEventListener('click', function (event) {
        event.preventDefault();
        alert('La réinitialisation du mot de passe sera ajoutée ultérieurement.');
    });

const loginForm = document.getElementById('loginForm');
const submitBtn = document.getElementById('submitBtn');
const passwordInput = document.getElementById('password');
const togglePassword = document.getElementById('togglePassword');

const idError = document.getElementById('idError');
const passwordError = document.getElementById('passwordError');


togglePassword.addEventListener('click', function () {
    const isPassword = passwordInput.type === 'password';

    passwordInput.type = isPassword ? 'text' : 'password';

    togglePassword.setAttribute(
        'aria-label',
        isPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'
    );
});

// Envoyer le formulaire à l'API
loginForm.addEventListener('submit', async function (event) {
    event.preventDefault();

    idError.style.display = 'none';
    passwordError.style.display = 'none';

    submitBtn.disabled = true;
    submitBtn.textContent = 'Connexion en cours...';

    try {
        const response = await fetch('api/login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                identifiant: document.getElementById('identifiant').value.trim(),
                password: passwordInput.value
            })
        });

        const result = await response.json();

        if (result.success) {
            window.location.href = result.redirect;
        } else {
            alert(result.message);
        }

    } catch (error) {
        alert('Impossible de contacter le serveur. Vérifiez votre connexion.');

    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Se connecter';
    }
});
</script>

</body>
</html>
