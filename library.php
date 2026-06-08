<?php
session_start();
include 'config/koneksi.php';
include 'includes/functions.php';
include 'includes/header.php';
include 'includes/navbar.php';

/* =========================
   BASE URL
========================= */
if (!defined('BASE_URL')) {
    define('BASE_URL', '/EUGENVERSE/');
}

/* =========================
   DETEKSI TAB AKTIF
========================= */
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'bookmark';
$allowed_tabs = ['bookmark', 'history']; // Hanya menyisakan Bookmark & History
if (!in_array($tab, $allowed_tabs)) {
    $tab = 'bookmark';
}

/* =========================
   CHECK LOGIN STATUS & DATA FETCHING
========================= */
$is_logged_in = isset($_SESSION['user_id']);
$result = null;
$stmt = null; 

if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    
    if ($tab == 'bookmark') {
        // Bookmark menggunakan Database bawaan kamu
        $query = "
            SELECT komik.* FROM bookmarks 
            JOIN komik ON bookmarks.komik_id = komik.id 
            WHERE bookmarks.user_id = ? 
            ORDER BY bookmarks.created_at DESC
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
  } else {
        // History disinkronkan dengan session 'read_chapters' yang tersimpan saat membaca
        // Kita mengambil ID komiknya saja (array_keys)
        $saved_ids = isset($_SESSION['read_chapters']) ? array_keys($_SESSION['read_chapters']) : [];
        
        if (!empty($saved_ids)) {
            // Bersihkan ID dan balik urutannya agar yang paling baru dibaca berada di atas
            $ids_clean = array_map('intval', $saved_ids);
            $ids_clean = array_reverse($ids_clean);
            $ids_str = implode(',', $ids_clean);
            
            // Ambil data dari tabel komik berdasarkan ID yang tersimpan di session
            $query = "SELECT * FROM komik WHERE id IN ($ids_str) ORDER BY FIELD(id, $ids_str)";
            $result = mysqli_query($conn, $query);
        }
    }
}

// Konfigurasi teks dinamis berdasarkan tab
$page_titles = [
    'bookmark' => ['title' => 'Koleksi Bookmark Saya', 'desc' => 'Daftar seri komik yang kamu simpan dan ikuti perkembangan terbarunya.'],
    'history'  => ['title' => 'Riwayat Membaca', 'desc' => 'Daftar komik yang terakhir kali kamu buka atau baca.']
];
?>

<style>
    /* =========================
       SHINIGAMI-INSPIRED TABS
    ========================= */
    .lib-tabs-container {
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        margin-bottom: 35px;
        display: flex;
        gap: 25px;
    }
    .lib-tab-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 4px;
        color: #9ca3af;
        text-decoration: none;
        font-size: 15px;
        font-weight: 600;
        position: relative;
        transition: color 0.3s ease;
    }
    .lib-tab-item:hover, .lib-tab-item.active {
        color: #fff;
    }
    .lib-tab-item.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        width: 100%;
        height: 2px;
        background: #7c3aed;
    }
    .lib-tab-item i {
        font-size: 14px;
    }
    .lib-tab-item.active i {
        color: #7c3aed;
    }

    /* =========================
       SHINIGAMI LOGIN REQUIRED BOX
    ========================= */
    .login-required-box {
        text-align: center;
        padding: 80px 20px;
        max-width: 500px;
        margin: 0 auto;
    }
    .login-required-box .avatar-placeholder {
        font-size: 80px;
        color: #374151;
        margin-bottom: 25px;
        display: inline-block;
    }
    .login-required-box h2 {
        font-size: 26px;
        font-weight: 700;
        color: #fff;
        margin: 0 0 10px 0;
    }
    .login-required-box p {
        font-size: 14px;
        color: #9ca3af;
        margin: 0 0 25px 0;
    }
    .login-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #7c3aed;
        color: #fff;
        padding: 12px 40px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 15px;
        text-decoration: none;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(124, 58, 237, 0.25);
    }
    .login-btn:hover {
        background: #6d28d9;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(124, 58, 237, 0.35);
    }

    /* =========================
       HOMEPAGE ACCORDING UI & GRID
    ========================= */
    .home-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 40px 20px 60px 20px;
    }
    .section-block {
        margin-bottom: 55px;
    }
    .section-header {
        margin-bottom: 30px;
        border-left: 4px solid #7c3aed;
        padding-left: 14px;
    }
    .section-header h2 {
        font-size: 24px;
        font-weight: 700;
        margin: 0 0 6px 0;
        color: #fff;
        letter-spacing: 0.3px;
    }
    .section-header p {
        font-size: 13px;
        color: #6b7280;
        margin: 0;
    }
    .comic-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 20px;
    }
    .comic-card {
        background: #121217;
        border-radius: 14px;
        overflow: hidden;
        text-decoration: none;
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.02);
        display: flex;
        flex-direction: column;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .comic-card:hover {
        transform: translateY(-6px);
        border-color: rgba(124, 58, 237, 0.3);
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.6);
    }
    .comic-thumb-wrap {
        position: relative;
        width: 100%;
        height: 250px;
        overflow: hidden;
        background: #09090d;
    }
    .comic-card img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .comic-card:hover img {
        transform: scale(1.06);
    }
    .comic-card h3 {
        font-size: 14px;
        font-weight: 600;
        margin: 14px 14px 6px 14px;
        line-height: 1.4;
        color: #f3f4f6;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .comic-card .chap {
        font-size: 12px;
        color: #a78bfa;
        font-weight: 600;
        margin: 0 14px 12px 14px;
    }
    .card-badge {
        margin: 0 14px 14px 14px;
        align-self: flex-start;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.02);
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        color: #9ca3af;
        max-width: calc(100% - 28px);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .empty-box {
        grid-column: 1 / -1;
        text-align: center;
        padding: 70px 20px;
        background: #121217;
        border-radius: 14px;
        color: #4b5563;
        font-size: 14px;
        border: 1px dashed rgba(255,255,255,0.06);
        line-height: 1.6;
    }

    /* =========================
       RESPONSIVE BREAKPOINTS
    ========================= */
    @media(max-width: 1024px) {
        .comic-grid { grid-template-columns: repeat(4, 1fr); }
    }
    @media(max-width: 768px) {
        .comic-grid { grid-template-columns: repeat(2, 1fr); gap: 14px; }
        .comic-thumb-wrap { height: 210px; }
        .lib-tabs-container { gap: 15px; }
        .lib-tab-item { font-size: 14px; }
    }
</style>

<div class="home-container">

    <!-- Sub-Nav Tab Menu (Hanya Bookmark dan History) -->
    <div class="lib-tabs-container">
        <a href="library.php?tab=bookmark" class="lib-tab-item <?php echo $tab == 'bookmark' ? 'active' : ''; ?>"><i class="fas fa-bookmark"></i> Bookmark</a>
        <a href="library.php?tab=history" class="lib-tab-item <?php echo $tab == 'history' ? 'active' : ''; ?>"><i class="fas fa-history"></i> History</a>
    </div>

    <?php if (!$is_logged_in) { ?>
        <!-- State Belum Login -->
        <div class="login-required-box">
            <div class="avatar-placeholder">
                <i class="fas fa-user-circle"></i>
            </div>
            <h2>Wajib Login!</h2>
            <p>Login dulu buat melihat <?php echo $tab; ?> kamu</p>
            <a href="login.php" class="login-btn">Login</a>
        </div>
    <?php } else { ?>
        <!-- State Sudah Login -->
        <section class="section-block">
            <div class="section-header">
                <h2><?php echo $page_titles[$tab]['title']; ?></h2>
                <p><?php echo $page_titles[$tab]['desc']; ?></p>
            </div>

            <div class="comic-grid">
                <?php 
                if ($result && mysqli_num_rows($result) > 0) { 
                    while ($row = mysqli_fetch_assoc($result)) {
                        // Mengambil data chapter terbaru secara dinamis dari database (Sama dengan logic Home)
                        $latestChapter = mysqli_query($conn, "SELECT chapter_number FROM chapters WHERE komik_id='" . $row['id'] . "' ORDER BY CAST(chapter_number AS UNSIGNED) DESC LIMIT 1");
                        $chapterData = mysqli_fetch_assoc($latestChapter);
                        
                        $tipe = strtolower(trim($row['tipe']));
                        $slugKomik = trim($row['slug']);
                        if (empty($slugKomik)) {
                            $slugKomik = str_replace(' ', '-', strtolower($row['judul']));
                        }
                        $coverPath = BASE_URL . "uploads/" . $tipe . "/" . $slugKomik . "/" . trim($row['cover']);
                ?>
                    <a href="detail.php?id=<?php echo $row['id']; ?>" class="comic-card">
                        <div class="comic-thumb-wrap">
                            <img src="<?php echo $coverPath; ?>" alt="<?php echo e($row['judul']); ?>" loading="lazy" onerror="this.src='<?php echo BASE_URL; ?>uploads/default-cover.jpg'">
                        </div>
                        <h3><?php echo e($row['judul']); ?></h3>
                        <div class="chap">
                            <?php echo $chapterData ? 'Ch. 1 - ' . e($chapterData['chapter_number']) : 'Belum ada chapter'; ?>
                        </div>
                        <div class="card-badge"><?php echo e($row['tipe']); ?></div>
                    </a>
                <?php 
                    } 
                } else { 
                ?>
                    <!-- State Data Kosong -->
                    <div class="empty-box">
                        <i class="far fa-folder-open" style="font-size: 26px; color: #7c3aed; margin-bottom: 12px; display: block;"></i>
                        Belum ada data di <?php echo $tab; ?> kamu.<br>
                        <span style="color: #6b7280; font-size: 13px;">Jelajahi beranda untuk membaca komik.</span>
                    </div>
                <?php } ?>
            </div>
        </section>
    <?php 
        if ($stmt !== null) {
            $stmt->close();
        }
    } 
    ?>

</div>

<?php include 'includes/footer.php'; ?>