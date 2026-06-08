<?php
// 1. Memulai atau mengaktifkan pelacakan sesi yang sedang berjalan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Mengosongkan seluruh variabel $_SESSION yang terdaftar
$_SESSION = array();

// 3. Menghancurkan session cookie di browser jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Menghancurkan data sesi secara permanen di server
session_destroy();

// 5. Mengarahkan kembali pengguna ke halaman profile.php (yang sekarang otomatis memuat Form Login)
header("Location: profile.php");
exit;
?>