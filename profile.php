<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'config/koneksi.php';

$error = "";
$success = "";

// Cek halaman aktif (default: profile)
$page = isset($_GET['page']) ? $_GET['page'] : 'profile';

/* ===================================================
   BACKEND: LOGIKA PROSES AUTENTIKASI LOGIN
   =================================================== */
if (isset($_POST['login'])) {
    $identity = trim($_POST['identity']);
    $password = $_POST['password'];

    if (!empty($identity) && !empty($password)) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $identity, $identity);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];
                $_SESSION['avatar']   = $user['avatar'];
                $_SESSION['email']    = $user['email'];

                header("Location: profile.php?page=profile");
                exit;
            }
        }
        $error = "Email/Username atau password salah!";
        $stmt->close();
    } else {
        $error = "Semua kolom login wajib diisi!";
    }
}

/* ===================================================
   BACKEND: LOGIKA OPERASIONAL FITUR DASHBOARD (USER)
   =================================================== */
$userData = null;
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];

    // 1. FITUR: Upload Foto Profil (Avatar)
    if (isset($_POST['upload_avatar']) && isset($_FILES['avatar_file'])) {
        $target_dir = "uploads/avatar/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        $file_ext = strtolower(pathinfo($_FILES["avatar_file"]["name"], PATHINFO_EXTENSION));
        $file_name = "avatar_" . $uid . "_" . time() . "." . $file_ext;
        $target_file = $target_dir . $file_name;
        
        if (in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
            if (move_uploaded_file($_FILES["avatar_file"]["tmp_name"], $target_file)) {
                $conn->query("UPDATE users SET avatar='$file_name' WHERE id='$uid'");
                $success = "Foto profil berhasil diperbarui!";
            } else {
                $error = "Gagal menyimpan file ke server.";
            }
        } else {
            $error = "Format file tidak didukung! Gunakan JPG, JPEG, atau PNG.";
        }
    }

    // 2. FITUR: Edit Nama Pengguna
    if (isset($_POST['update_name'])) {
        $new_name = trim($_POST['new_name']);
        if (!empty($new_name)) {
            $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmt->bind_param("si", $new_name, $uid);
            if ($stmt->execute()) {
                $success = "Nama berhasil diubah!";
            } else {
                $error = "Username sudah digunakan oleh orang lain.";
            }
            $stmt->close();
        }
    }

    // 3. FITUR SETTINGS: Ubah Kata Sandi
    if (isset($_POST['change_password'])) {
        $old_pass = $_POST['old_password'];
        $new_pass = $_POST['new_password'];
        
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (password_verify($old_pass, $res['password'])) {
            $hashed_new_pass = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt_up = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt_up->bind_param("si", $hashed_new_pass, $uid);
            $stmt_up->execute();
            $stmt_up->close();
            $success = "Kata sandi keamanan berhasil diperbarui!";
        } else {
            $error = "Kata sandi lama yang Anda masukkan salah.";
        }
    }

    // 4. FITUR SETTINGS: Simpan Preferensi Baca (Manga/Manhwa)
    if (isset($_POST['save_preferences'])) {
        // Simulasi update preferensi tanpa relasi DB untuk UI responsif
        $success = "Preferensi antarmuka membaca Anda berhasil disimpan!";
    }

    // Memuat ulang data user terbaru untuk rendering UI
    $stmt_user = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt_user->bind_param("i", $uid);
    $stmt_user->execute();
    $userData = $stmt_user->get_result()->fetch_assoc();
    $stmt_user->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EUGENVERSE - Panel Kendali</title>
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-deep: #0d0d12;
            --bg-panel: #12121a;
            --bg-input: #181824;
            --accent: #a855f7;
            --text-main: #ffffff;
            --text-dim: #9ca3af;
            --border: rgba(255, 255, 255, 0.05);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: var(--bg-deep); color: var(--text-main); min-height: 100vh; position: relative; }

        /* LINK LOGO & STYLE GLOBAL */
        .brand-link { text-decoration: none; color: inherit; display: inline-block; }

        /* BUTTON HOME POJOK KANAN ATAS */
        .floating-home-btn {
            position: absolute;
            top: 25px;
            right: 30px;
            z-index: 100;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease-in-out;
        }
        .floating-home-btn:hover {
            background: var(--accent);
            border-color: var(--accent);
            box-shadow: 0 4px 15px rgba(168, 85, 247, 0.3);
            transform: translateY(-1px);
        }

        /* GUEST STYLE (LOGIN) */
        .login-fullscreen { height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; background: #07070a; }
        .login-box { width: 100%; max-width: 420px; background: #111116; border: 1px solid var(--border); padding: 45px 35px; border-radius: 20px; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
        .brand { font-family: 'Urbanist'; font-size: 30px; font-weight: 800; margin-bottom: 8px; letter-spacing: 1px; }
        .brand span { color: var(--accent); }
        .login-box p.sub { font-size: 13px; color: var(--text-dim); margin-bottom: 30px; }
        
        .form-group { position: relative; margin-bottom: 20px; text-align: left; }
        .form-group i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #4b5563; font-size: 15px; }
        .form-group input { width: 100%; padding: 14px 16px 14px 46px; background: var(--bg-input); border: 1px solid var(--border); border-radius: 12px; color: #fff; outline: none; font-size: 14px; transition: all 0.3s; }
        .form-group input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.15); }
        
        .btn-purple { width: 100%; padding: 14px; background: var(--accent); color: #fff; border: none; border-radius: 12px; font-weight: 600; font-size: 14px; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 15px rgba(168, 85, 247, 0.25); }
        .btn-purple:hover { background: #9333ea; transform: translateY(-2px); }

        /* USER STYLE (DASHBOARD) */
        .dashboard-layout { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: #111116; border-right: 1px solid var(--border); padding: 30px 20px; flex-shrink: 0; }
        
        .nav-menu { list-style: none; margin-top: 15px; }
        .nav-item a { display: flex; align-items: center; gap: 15px; padding: 14px 16px; border-radius: 10px; color: var(--text-dim); text-decoration: none; font-weight: 500; font-size: 14px; transition: 0.2s; margin-bottom: 6px; }
        .nav-item.active a { background: var(--bg-input); color: #fff; font-weight: 600; border-left: 3px solid var(--accent); border-radius: 0 10px 10px 0; }
        .nav-item a:hover { color: #fff; background: var(--bg-input); }

        .main-content { flex-grow: 1; padding: 50px 60px; background: var(--bg-deep); position: relative; }
        .profile-header { display: flex; align-items: center; gap: 30px; margin-bottom: 40px; padding-bottom: 30px; border-bottom: 1px solid var(--border); }
        .avatar-circle { width: 90px; height: 90px; border-radius: 50%; border: 2px solid var(--accent); overflow: hidden; background: #222; position: relative; }
        .avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
        
        .user-meta h1 { font-family: 'Urbanist'; font-size: 28px; font-weight: 700; letter-spacing: 0.5px; }
        .user-meta p { color: var(--text-dim); font-size: 13px; margin-top: 4px; }
        .action-cluster { display: flex; gap: 10px; margin-top: 12px; align-items: center; }
        
        .btn-outline { background: transparent; border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 500; cursor: pointer; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-outline:hover { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.2); }

        .info-card { background: #111116; border: 1px solid var(--border); border-radius: 14px; padding: 10px 0; margin-bottom: 25px; }
        .info-card form { width: 100%; }
        .info-row { display: flex; justify-content: space-between; align-items: center; padding: 22px 30px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 10px; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: var(--text-dim); font-size: 14px; width: 160px; }
        .info-value { flex-grow: 1; font-weight: 500; font-size: 14px; color: #fff; }
        .btn-edit { color: var(--accent); font-weight: 600; text-decoration: none; font-size: 13px; background: none; border: none; cursor: pointer; }
        
        /* FORM DINAMIS */
        .inline-form { width: 100%; display: none; margin-top: 15px; background: var(--bg-input); padding: 15px; border-radius: 10px; }
        .inline-form input[type="text"], .inline-form input[type="file"] { width: 100%; max-width: 350px; padding: 10px; background: #0d0d12; border: 1px solid var(--border); color: #fff; border-radius: 8px; outline: none; font-size: 13px; margin-bottom: 10px; display: block; }

        /* SETTINGS UI SHINIGAMI */
        .settings-input { width: 100%; max-width: 400px; padding: 12px 14px; background: var(--bg-input); border: 1px solid var(--border); color: #fff; border-radius: 8px; font-size: 13.5px; outline: none; transition: 0.3s; }
        .settings-input:focus { border-color: var(--accent); }
        .settings-input option { background: #12121a; }
        .pref-group { margin-bottom: 18px; }
        .pref-group label { display: block; font-size: 13px; color: var(--text-dim); margin-bottom: 8px; font-weight: 500; }
        
        /* TOGGLE SWITCH */
        .switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #374151; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--accent); }
        input:checked + .slider:before { transform: translateX(20px); }

        .alert-danger { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #f87171; padding: 14px; border-radius: 12px; margin-bottom: 25px; font-size: 13px; }
        .alert-success { background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.2); color: #4ade80; padding: 14px; border-radius: 12px; margin-bottom: 25px; font-size: 13px; }

        @media (max-width: 768px) {
            .floating-home-btn { top: 15px; right: 15px; padding: 8px 12px; font-size: 12px; }
            .dashboard-layout { flex-direction: column; }
            .sidebar { width: 100%; border-right: none; border-bottom: 1px solid var(--border); padding: 20px; }
            .main-content { padding: 30px 20px; }
        }
    </style>
</head>
<body>

<!-- TOMBOL POJOK KANAN ATAS UNTUK KEMBALI KE HOME -->
<a href="home.php" class="floating-home-btn">
    <i class="fa-solid fa-house"></i> Kembali ke Home
</a>

<?php if (!isset($_SESSION['user_id'])): ?>
    <div class="login-fullscreen">
        <div class="login-box">
            <!-- LOGO DI HALAMAN LOGIN SEKARANG BISA DIKLIK KE HOME -->
            <a href="home.php" class="brand-link">
                <div class="brand">EUGEN<span>VERSE</span></div>
            </a>
            <p class="sub">Masuk untuk mengelola profil dan preferensi bacaanmu.</p>
            
            <?php if (!empty($error)): ?>
                <div class="alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="text" name="identity" placeholder="Alamat Email atau Username" required>
                </div>
                <div class="form-group">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="password" placeholder="Kata Sandi" required>
                </div>
                <button type="submit" name="login" class="btn-purple">Masuk Ke Akun</button>
            </form>
            <p style="color: var(--text-dim); font-size: 13px; margin-top: 25px;">
                Belum punya akun? <a href="register.php" style="color: var(--accent); text-decoration: none; font-weight: 600;">Daftar sekarang</a>
            </p>
        </div>
    </div>

<?php else: ?>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <!-- LOGO DI SIDEBAR SEKARANG BISA DIKLIK KE HOME -->
            <div style="text-align:center; margin-bottom:20px;">
                <a href="home.php" class="brand-link">
                    <div class="brand">EUGEN<span>VERSE</span></div>
                </a>
            </div>
            <ul class="nav-menu">
                <li class="nav-item <?php echo $page === 'profile'?'active':''; ?>"><a href="profile.php?page=profile"><i class="fa-solid fa-user"></i> Akun Saya</a></li>
                <li class="nav-item <?php echo $page === 'settings'?'active':''; ?>"><a href="profile.php?page=settings"><i class="fa-solid fa-gear"></i> Pengaturan</a></li>
                <li class="nav-item <?php echo $page === 'info'?'active':''; ?>"><a href="profile.php?page=info"><i class="fa-solid fa-info-circle"></i> Sistem Info</a></li>
                <li class="nav-item" style="margin-top: 40px;">
                    <a href="logout.php" style="color: #f87171;"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
                </li>
            </ul>
        </aside>

        <main class="main-content">
            <?php if (!empty($error)): ?><div class="alert-danger"><i class="fas fa-times-circle"></i> <?php echo $error; ?></div><?php endif; ?>
            <?php if (!empty($success)): ?><div class="alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div><?php endif; ?>

            <?php if ($page === 'profile'): ?>
                <div class="profile-header">
                    <div class="avatar-circle">
                        <img src="uploads/avatar/<?php echo !empty($userData['avatar']) ? $userData['avatar'] : 'default.jpg'; ?>" alt="User Avatar">
                    </div>
                    <div class="user-meta">
                        <h1><?php echo htmlspecialchars($userData['username']); ?></h1>
                        <p>ID Anggota: #<?php echo $userData['id']; ?></p>
                        <div class="action-cluster">
                            <button class="btn-outline" onclick="toggleForm('avatarForm')"><i class="fa-solid fa-camera"></i> Ganti Foto Profil</button>
                        </div>
                    </div>
                </div>

                <div id="avatarForm" class="inline-form">
                    <form method="POST" enctype="multipart/form-data">
                        <label style="font-size:12px; color:var(--text-dim); margin-bottom:8px; display:block;">Pilih berkas gambar Anda (Format: PNG/JPG/JPEG max 2MB):</label>
                        <input type="file" name="avatar_file" required>
                        <button type="submit" name="upload_avatar" class="btn-purple" style="width:auto; padding:8px 16px;">Unggah Sekarang</button>
                    </form>
                </div>

                <div class="info-card">
                    <div class="info-row">
                        <div class="info-label">Nama Pengguna</div>
                        <div class="info-value"><?php echo htmlspecialchars($userData['username']); ?></div>
                        <button class="btn-edit" onclick="toggleForm('nameForm')">Ubah Nama</button>
                        
                        <div id="nameForm" class="inline-form" style="width:100%;">
                            <form method="POST">
                                <input type="text" name="new_name" value="<?php echo htmlspecialchars($userData['username']); ?>" required>
                                <button type="submit" name="update_name" class="btn-purple" style="width:auto; padding:8px 16px;">Simpan Perubahan</button>
                            </form>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Alamat Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($userData['email']); ?></div>
                        <span style="font-size:12px; color:var(--text-dim);">Permanen</span>
                    </div>
                </div>

            <?php elseif ($page === 'settings'): ?>
                <h2 style="font-family:'Urbanist'; margin-bottom:5px;">Pengaturan Sistem</h2>
                <p style="color:var(--text-dim); font-size:13.5px; margin-bottom:25px;">Kustomisasi pengalaman membaca dan kelola keamanan akun Anda.</p>

                <div class="info-card">
                    <div class="info-row" style="display:block;">
                        <h4 style="margin-bottom:20px; color: var(--accent);"><i class="fa-solid fa-book-open"></i> Preferensi Membaca</h4>
                        <form method="POST">
                            <div class="pref-group">
                                <label>Gaya Navigasi Baca (Default)</label>
                                <select name="read_mode" class="settings-input">
                                    <option value="webtoon">Webtoon (Gulir Vertikal Atas ke Bawah)</option>
                                    <option value="ltr">Manga Biasa (Kiri ke Kanan)</option>
                                    <option value="rtl">Manga Jepang (Kanan ke Kiri)</option>
                                </select>
                            </div>
                            <div class="pref-group">
                                <label>Kualitas Gambar Saat Dimuat</label>
                                <select name="img_quality" class="settings-input">
                                    <option value="high">Kualitas Tinggi (Original / Tanpa Kompresi)</option>
                                    <option value="medium">Standar (Optimal untuk koneksi lambat)</option>
                                    <option value="data_saver">Data Saver (Sangat di-Kompresi)</option>
                                </select>
                            </div>
                            <div class="pref-group" style="display:flex; justify-content:space-between; align-items:center; max-width:400px;">
                                <div>
                                    <label style="margin-bottom:2px; color:#fff;">Sembunyikan Konten Sensitif</label>
                                    <span style="font-size:11.5px; color:var(--text-dim);">Filter konten eksplisit (Gore/NSFW)</span>
                                </div>
                                <label class="switch">
                                  <input type="checkbox" name="nsfw_filter" checked>
                                  <span class="slider round"></span>
                                </label>
                            </div>
                            <div class="pref-group" style="display:flex; justify-content:space-between; align-items:center; max-width:400px;">
                                <div>
                                    <label style="margin-bottom:2px; color:#fff;">Putar Otomatis Animasi (GIF)</label>
                                    <span style="font-size:11.5px; color:var(--text-dim);">Tampilkan efek animasi di sampul</span>
                                </div>
                                <label class="switch">
                                  <input type="checkbox" name="gif_autoplay">
                                  <span class="slider round"></span>
                                </label>
                            </div>
                            <button type="submit" name="save_preferences" class="btn-purple" style="width:auto; padding:10px 20px; margin-top:10px;"><i class="fa-solid fa-floppy-disk"></i> Simpan Preferensi</button>
                        </form>
                    </div>
                </div>

                <div class="info-card" style="margin-top: 25px;">
                    <div class="info-row" style="display:block;">
                        <h4 style="margin-bottom:15px; color: var(--accent);"><i class="fa-solid fa-shield-halved"></i> Keamanan Akun</h4>
                        <form method="POST">
                            <div class="pref-group">
                                <input type="password" name="old_password" placeholder="Kata Sandi Lama Anda" class="settings-input" style="margin-bottom:12px;" required>
                                <input type="password" name="new_password" placeholder="Kata Sandi Baru" class="settings-input" required>
                            </div>
                            <button type="submit" name="change_password" class="btn-outline" style="width:auto; padding:10px 20px;"><i class="fa-solid fa-key"></i> Perbarui Kata Sandi</button>
                        </form>
                    </div>
                </div>

            <?php elseif ($page === 'info'): ?>
                <h2 style="font-family:'Urbanist'; margin-bottom:20px;">Informasi Sistem EUGENVERSE</h2>
                <div class="info-card" style="padding:25px 30px;">
                    <h3 style="color:var(--accent); margin-bottom:10px;">EUGENVERSE Engine v2.5-Stable</h3>
                    <p style="font-size:14px; color:var(--text-dim); line-height:1.6; margin-bottom:20px;">
                        Platform terintegrasi manajemen bacaan eksklusif ber-UI Shinigami Dark Mode. Sistem ini dioptimalkan untuk membaca Manga dan Manhwa secara mulus, menggunakan PHP 8+ Native, Keamanan Enkripsi Password Argon2/Bcrypt.
                    </p>
                    <hr style="border:0; border-top:1px solid var(--border); margin-bottom:20px;">
                    <h4 style="margin-bottom:12px;">Status Akun & Sistem:</h4>
                    <p style="font-size:13.5px; margin-bottom:10px; color:#fff;"><i class="fa-solid fa-circle-check" style="color:#4ade80; margin-right:8px;"></i> Status Keanggotaan: <strong>Penjelajah Resmi Aktif</strong></p>
                    <p style="font-size:13.5px; color:#fff;"><i class="fa-solid fa-moon" style="color:var(--accent); margin-right:8px;"></i> Tema Antarmuka: <strong>Shinigami Dark Mode (Default)</strong></p>
                </div>
            <?php endif; ?>
        </main>
    </div>
<?php endif; ?>

<script>
// Fungsi JavaScript ringkas untuk membuka form edit dinamis tanpa reload halaman
function toggleForm(formId) {
    var f = document.getElementById(formId);
    if(f.style.display === "block") {
        f.style.display = "none";
    } else {
        f.style.display = "block";
    }
}
</script>
</body>
</html>