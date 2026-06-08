<?php
include 'config/koneksi.php';
session_start();

if ($_POST['isi_komentar']) {
    $chapter_id = mysqli_real_escape_string($conn, $_POST['chapter_id']);
    $isi = mysqli_real_escape_string($conn, $_POST['isi_komentar']);
    // Contoh user_id dari session, sesuaikan dengan sistem login Anda
    $user_id = $_SESSION['user_id'] ?? 1; 

    $query = "INSERT INTO komentar (chapter_id, user_id, isi, tanggal) VALUES ('$chapter_id', '$user_id', '$isi', NOW())";
    if (mysqli_query($conn, $query)) {
        echo "success";
    } else {
        echo "error";
    }
}
?>