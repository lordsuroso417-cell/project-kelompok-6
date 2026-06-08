<?php
include 'config/koneksi.php';

$chapter_id = $_GET['chapter_id'];
$query = mysqli_query($conn, "SELECT * FROM komentar WHERE chapter_id = '$chapter_id' ORDER BY id DESC");

if (mysqli_num_rows($query) > 0) {
    while ($row = mysqli_fetch_assoc($query)) {
        ?>
        <div class="comment-item">
            <div class="comment-avatar"><i class="fa-solid fa-user"></i></div>
            <div class="comment-body">
                <div class="comment-meta">
                    <span class="comment-author">User #<?php echo $row['user_id']; ?></span>
                    <span><?php echo $row['tanggal']; ?></span>
                </div>
                <div class="comment-text"><?php echo htmlspecialchars($row['isi']); ?></div>
            </div>
        </div>
        <?php
    }
} else {
    echo "<p style='color:#666; text-align:center;'>Belum ada komentar.</p>";
}
?>