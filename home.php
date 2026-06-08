<?php
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
   DATA HERO SPOTLIGHT
========================= */
$hero = mysqli_query($conn, "SELECT * FROM komik ORDER BY id DESC LIMIT 1");
$heroData = mysqli_fetch_assoc($hero);

$heroPath = BASE_URL . "uploads/default-cover.jpg";
$heroChapter = 0;
$is_bookmarked_hero = false; // Tambahan untuk cek status bookmark

if ($heroData) {
    $heroPath = BASE_URL . "uploads/" . strtolower($heroData['tipe']) . "/" . $heroData['slug'] . "/" . $heroData['cover'];
    
    $countHeroCh = mysqli_query($conn, "SELECT id FROM chapters WHERE komik_id = '" . $heroData['id'] . "'");
    $heroChapter = mysqli_num_rows($countHeroCh);

    // Cek apakah komik ini sudah ada di bookmark user
    if (isset($_SESSION['user_id'])) { 
        $user_id = $_SESSION['user_id'];
        $stmt_check_bk = $conn->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND komik_id = ?");
        $stmt_check_bk->bind_param("ii", $user_id, $heroData['id']);
        $stmt_check_bk->execute();
        $res_check_bk = $stmt_check_bk->get_result();
        if ($res_check_bk->num_rows > 0) {
            $is_bookmarked_hero = true;
        }
        $stmt_check_bk->close();
    }
}
/* =========================
   DATA LATEST UPDATE
========================= */
$update = mysqli_query($conn, "SELECT * FROM komik ORDER BY updated_at DESC, id DESC LIMIT 12");

/* =========================
   DATA POPULAR / TRENDING
========================= */
$trend = mysqli_query($conn, "SELECT * FROM komik ORDER BY views DESC LIMIT 6");
?>

<style>
    /* =========================
       HERO CINEMATIC COMPONENT
    ========================= */
    .hero-container {
        position: relative;
        min-height: 520px;
        display: flex;
        align-items: center;
        padding: 60px;
        overflow: hidden;
        background: #07070a;
    }
    .hero-banner-bg {
        position: absolute;
        inset: 0;
        background-size: cover;
        background-position: center 20%;
        z-index: 0;
        transform: scale(1.05);
        filter: blur(4px) brightness(0.6);
    }
    .hero-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(90deg, #07070a 35%, rgba(7, 7, 10, 0.85) 60%, rgba(7, 7, 10, 0.3) 100%);
        z-index: 1;
    }
    .hero-content {
        position: relative;
        z-index: 2;
        max-width: 650px;
        width: 100%;
    }
    .hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(124, 58, 237, 0.2);
        border: 1px solid rgba(124, 58, 237, 0.4);
        color: #a78bfa;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        margin-bottom: 20px;
    }
    .hero-content h1 {
        font-size: 48px;
        font-weight: 800;
        margin: 0 0 15px 0;
        color: #fff;
        line-height: 1.2;
        text-shadow: 0 2px 10px rgba(0,0,0,0.5);
    }
    .hero-content p {
        font-size: 15px;
        line-height: 1.7;
        color: #9ca3af;
        margin: 0 0 25px 0;
    }
    .hero-meta {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
        margin-bottom: 30px;
        font-size: 13px;
        color: #e5e7eb;
        font-weight: 500;
    }
    .hero-meta span {
        background: rgba(255, 255, 255, 0.06);
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255, 255, 255, 0.03);
        padding: 6px 14px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .hero-action {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }
    .main-btn, .sec-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 14px 28px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 14px;
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .main-btn {
        background: #7c3aed;
        color: #fff;
        box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3);
    }
    .main-btn:hover {
        background: #6d28d9;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(124, 58, 237, 0.4);
    }
    .sec-btn {
        background: rgba(255, 255, 255, 0.05);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(5px);
    }
    .sec-btn:hover {
        background: rgba(255, 255, 255, 0.12);
        transform: translateY(-2px);
        border-color: rgba(255, 255, 255, 0.2);
    }

    /* =========================
       SECTION & LAYOUT CONTROLS
    ========================= */
    .home-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 50px 20px;
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

    /* =========================
       MODERN GRID CARDS
    ========================= */
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
        .hero-container { padding: 45px; min-height: 440px; }
        .hero-content h1 { font-size: 38px; }
    }
    @media(max-width: 768px) {
        .comic-grid { grid-template-columns: repeat(2, 1fr); gap: 14px; }
        .hero-container { padding: 40px 20px; text-align: center; }
        .hero-overlay {
            background: linear-gradient(0deg, #07070a 45%, rgba(7, 7, 10, 0.85) 80%, rgba(7, 7, 10, 0.4) 100%);
        }
        .hero-content { margin: 0 auto; }
        .hero-meta { justify-content: center; }
        .hero-action { justify-content: center; }
        .hero-content h1 { font-size: 30px; }
        .comic-thumb-wrap { height: 210px; }
    }
</style>

<?php if ($heroData) { ?>
<div class="hero-container">
    <div class="hero-banner-bg" style="background-image: url('<?php echo $heroPath; ?>');"></div>
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <span class="hero-badge"><i class="fas fa-bolt"></i> Latest Spotlight</span>
        <h1><?php echo e($heroData['judul']); ?></h1>
        <p><?php echo excerpt($heroData['sinopsis'], 180); ?></p>
        <div class="hero-meta">
            <span><i class="fas fa-tags"></i> <?php echo e($heroData['genre']); ?></span>
            <span><i class="fas fa-book"></i> <?php echo $heroChapter; ?> Chapters</span>
            <span><i class="fas fa-eye"></i> <?php echo number_format($heroData['views']); ?></span>
        </div>
<div class="hero-action">
            <a href="detail.php?id=<?php echo $heroData['id']; ?>" class="main-btn"><i class="fas fa-book-open"></i> Baca Sekarang</a>
            
            <!-- Mengubah tombol Favorit menjadi Bookmark dengan fungsionalitas AJAX -->
            <a href="#" class="sec-btn btn-bookmark" data-komik-id="<?php echo $heroData['id']; ?>" style="<?php echo $is_bookmarked_hero ? 'background: rgba(124, 58, 237, 0.2); border-color: #7c3aed;' : ''; ?>">
                <i class="bookmark-icon <?php echo $is_bookmarked_hero ? 'fas' : 'far'; ?> fa-bookmark"></i> 
                <span class="bookmark-text"><?php echo $is_bookmarked_hero ? 'Tersimpan' : 'Bookmark'; ?></span>
            </a>
        </div>
    </div>
</div>
<?php } ?>

<div class="home-container">

    <section class="section-block">
        <div class="section-header">
            <h2>Latest Update</h2>
            <p>Chapter terbaru dari komik-komik yang baru saja diperbarui</p>
        </div>

        <div class="comic-grid">
            <?php 
            if (mysqli_num_rows($update) > 0) { 
                while ($row = mysqli_fetch_assoc($update)) {
                    // Menggunakan CAST agar sorting angka chapter dari database aman dan berurutan
                    $latestChapter = mysqli_query($conn, "SELECT chapter_number FROM chapters WHERE komik_id='" . $row['id'] . "' ORDER BY CAST(chapter_number AS UNSIGNED) DESC LIMIT 1");
                    $chapterData = mysqli_fetch_assoc($latestChapter);
                    $coverPath = BASE_URL . "uploads/" . strtolower($row['tipe']) . "/" . $row['slug'] . "/" . $row['cover'];
            ?>
                <a href="detail.php?id=<?php echo $row['id']; ?>" class="comic-card">
                    <div class="comic-thumb-wrap">
                        <img src="<?php echo e($coverPath); ?>" alt="<?php echo e($row['judul']); ?>" loading="lazy" onerror="this.src='<?php echo BASE_URL; ?>uploads/default-cover.jpg'">
                    </div>
                    <h3><?php echo e($row['judul']); ?></h3>
                    <div class="chap">
                        <?php echo $chapterData ? 'Ch. 1 - ' . e($chapterData['chapter_number']) : 'Belum ada chapter'; ?>
                    </div>
                    <div class="card-badge"><?php echo e($row['genre']); ?></div>
                </a>
            <?php 
                } 
            } else { 
            ?>
                <div class="empty-box">
                    <i class="far fa-folder-open" style="font-size: 26px; color: #7c3aed; margin-bottom: 12px; display: block;"></i>
                    Belum ada komik di database.<br><span style="color: #6b7280; font-size: 13px;">Sepi seperti grup tugas setelah deadline.</span>
                </div>
            <?php } ?>
        </div>
    </section>

    <section class="section-block">
        <div class="section-header">
            <h2>Popular This Week</h2>
            <p>Daftar seri komik yang paling banyak dilihat sepanjang minggu ini</p>
        </div>

        <div class="comic-grid">
            <?php
            while ($row = mysqli_fetch_assoc($trend)) {
                $latestChapter = mysqli_query($conn, "SELECT chapter_number FROM chapters WHERE komik_id='" . $row['id'] . "' ORDER BY CAST(chapter_number AS UNSIGNED) DESC LIMIT 1");
                $chapterData = mysqli_fetch_assoc($latestChapter);
                $coverPath = BASE_URL . "uploads/" . strtolower($row['tipe']) . "/" . $row['slug'] . "/" . $row['cover'];
            ?>
                <a href="detail.php?id=<?php echo $row['id']; ?>" class="comic-card">
                    <div class="comic-thumb-wrap">
                        <img src="<?php echo e($coverPath); ?>" alt="<?php echo e($row['judul']); ?>" loading="lazy" onerror="this.src='<?php echo BASE_URL; ?>uploads/default-cover.jpg'">
                    </div>
                    <h3><?php echo e($row['judul']); ?></h3>
                    <div class="chap">
                        <?php echo $chapterData ? 'Ch. 1 - ' . e($chapterData['chapter_number']) : 'Belum ada chapter'; ?>
                    </div>
                    <div class="card-badge"><i class="far fa-eye" style="font-size: 11px; margin-right: 2px;"></i> <?php echo number_format($row['views']); ?></div>
                </a>
            <?php } ?>
        </div>
    </section>

</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $('.btn-bookmark').click(function(e) {
        e.preventDefault(); 
        var komik_id = $(this).data('komik-id');
        var btn = $(this);
        var icon = btn.find('.bookmark-icon');
        var textSpan = btn.find('.bookmark-text');
        var originalText = textSpan.text();

        textSpan.text('Memproses...');

        $.ajax({
            url: 'add_bookmark.php',
            type: 'POST',
            data: { komik_id: komik_id },
            success: function(response) {
                var res = response.trim(); 
                if (res === 'added') {
                    icon.removeClass('far').addClass('fas');
                    textSpan.text('Tersimpan');
                    btn.css({'background': 'rgba(124, 58, 237, 0.2)', 'border-color': '#7c3aed'});
                } else if (res === 'removed') {
                    icon.removeClass('fas').addClass('far');
                    textSpan.text('Bookmark');
                    btn.css({'background': 'rgba(255, 255, 255, 0.05)', 'border-color': 'rgba(255, 255, 255, 0.08)'});
                } else {
                    alert('Silakan login terlebih dahulu.');
                    textSpan.text(originalText);
                }
            },
            error: function() {
                alert('Terjadi kesalahan server.');
                textSpan.text(originalText);
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>

<?php include 'includes/footer.php'; ?>