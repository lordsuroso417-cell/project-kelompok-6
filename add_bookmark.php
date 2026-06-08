<?php
session_start();
include 'config/koneksi.php';

// PENTING: Jika nama session loginmu bukan 'user_id' (misal: 'id' atau 'id_user'),
// ganti kata 'user_id' di bawah ini dengan nama session milikmu!
if (!isset($_SESSION['user_id'])) {
    echo "not_logged_in";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['komik_id'])) {
    $user_id = $_SESSION['user_id']; // Sesuaikan dengan session milikmu
    $komik_id = intval($_POST['komik_id']);

    // Cek apakah sudah pernah dibookmark
    $stmt = $conn->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND komik_id = ?");
    $stmt->bind_param("ii", $user_id, $komik_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Jika sudah ada, hapus dari library (Un-bookmark)
        $stmt_delete = $conn->prepare("DELETE FROM bookmarks WHERE user_id = ? AND komik_id = ?");
        $stmt_delete->bind_param("ii", $user_id, $komik_id);
        $stmt_delete->execute();
        echo "removed";
    } else {
        // Jika belum ada, masukkan ke library (Bookmark)
        $stmt_insert = $conn->prepare("INSERT INTO bookmarks (user_id, komik_id) VALUES (?, ?)");
        $stmt_insert->bind_param("ii", $user_id, $komik_id);
        $stmt_insert->execute();
        echo "added";
    }
    $stmt->close();
}