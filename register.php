<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'config/koneksi.php';

$error = "";
$success = "";

/* ===================================================
   BACKEND: PROSES REGISTRASI USER (PREPARED STATEMENT)
   =================================================== */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    if (!empty($username) && !empty($email) && !empty($password)) {
        
        // 1. Validasi format email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Format alamat email tidak valid!";
        } else {
            // 2. Cek apakah email sudah terdaftar di database
            $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows > 0) {
                $error = "Alamat email ini sudah terdaftar! Silakan gunakan email lain.";
                $stmt_check->close();
            } else {
                $stmt_check->close();

                // 3. Hash password demi keamanan tingkat tinggi (Bcrypt)
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Set default role dan avatar untuk user baru
                $default_role = 'user';
                $default_avatar = 'default.jpg';

                // 4. Masukkan data user baru menggunakan Prepared Statement
                $stmt_insert = $conn->prepare("INSERT INTO users (username, email, password, role, avatar) VALUES (?, ?, ?, ?, ?)");
                $stmt_insert->bind_param("sssss", $username, $email, $hashed_password, $default_role, $default_avatar);

                if ($stmt_insert->execute()) {
                    $success = "Akun berhasil dibuat! Silakan masuk menggunakan akun baru Anda.";
                } else {
                    $error = "Terjadi kesalahan sistem, pendaftaran gagal dilakukan.";
                }
                $stmt_insert->close();
            }
        }
    } else {
        $error = "Seluruh kolom pendaftaran wajib diisi!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - EUGENVERSE</title>
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* ===================================================
           RESET & GLOBAL STYLE (SHINIGAMI ID STYLE)
           =================================================== */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }

        body {
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #07070a;
            color: #fff;
            padding: 20px;
        }

        /* ===================================================
           REGISTER CARD COMPONENT
           =================================================== */
        .register-card {
            width: 100%;
            max-width: 420px;
            background: #111116;
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 45px 35px;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            text-align: center;
        }

        /* HEADER BRANDING */
        .brand-logo {
            font-family: 'Urbanist', sans-serif;
            font-size: 30px;
            font-weight: 800;
            color: #fff;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .brand-logo span {
            color: #a855f7; /* Menyamakan Ungu Shinigami ID */
        }
        .register-card p {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 30px;
        }

        /* ===================================================
           FORM & INPUT CONTROLS
           =================================================== */
        .form-group {
            position: relative;
            margin-bottom: 20px;
            text-align: left;
        }
        .form-group i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #4b5563;
            font-size: 15px;
            transition: color 0.3s ease;
        }
        .form-group input {
            width: 100%;
            padding: 14px 16px 14px 46px;
            background: #181824;
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            outline: none;
            color: #fff;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .form-group input:focus {
            border-color: #a855f7;
            background: #1c1c2c;
            box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.15);
        }
        .form-group input:focus ~ i {
            color: #a855f7;
        }

        /* BUTTON ACTION */
        .submit-btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            background: #a855f7;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(168, 85, 247, 0.25);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-top: 10px;
        }
        .submit-btn:hover {
            background: #9333ea;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(168, 85, 247, 0.35);
        }

        /* ===================================================
           STATUS ALERT BOXES
           =================================================== */
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            text-align: left;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #f87171;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #34d399;
        }

        /* BOTTOM LINK */
        .register-footer {
            margin-top: 26px;
            font-size: 13px;
            color: #6b7280;
            font-weight: 500;
        }
        .register-footer a {
            color: #a855f7;
            text-decoration: none;
            font-weight: 600;
            margin-left: 5px;
        }
        .register-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

<div class="register-card">

    <div class="brand-logo">EUGEN<span>VERSE</span></div>
    <p>Buat akun baru untuk mulai menyimpan pustaka komik Anda.</p>

    <?php if (!empty($error)) { ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php } ?>

    <?php if (!empty($success)) { ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $success; ?></span>
        </div>
    <?php } ?>

    <form method="POST" action="">

        <div class="form-group">
            <input type="text" name="username" placeholder="Nama Pengguna (Username)" required autocomplete="username">
            <i class="fas fa-user"></i>
        </div>

        <div class="form-group">
            <input type="email" name="email" placeholder="Alamat Email" required autocomplete="email">
            <i class="fas fa-envelope"></i>
        </div>

        <div class="form-group">
            <input type="password" name="password" placeholder="Kata Sandi Baru" required autocomplete="new-password">
            <i class="fas fa-lock"></i>
        </div>

        <button type="submit" class="submit-btn">Daftar Akun Baru</button>

    </form>

    <div class="register-footer">
        Sudah memiliki akun?<a href="profile.php">Masuk di sini</a>
    </div>

</div>

</body>
</html>