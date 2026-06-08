<?php
session_start();

include 'config/koneksi.php';
include 'includes/functions.php';

define('BASE_URL', '/EUGENVERSE/');

if (!isset($_SESSION['read_chapters'])) {
    $_SESSION['read_chapters'] = [];
}

$id   = isset($_GET['id']) ? intval($_GET['id']) : 0;
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM komik WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
} else {
    $stmt = $conn->prepare("SELECT * FROM komik WHERE slug = ? LIMIT 1");
    $stmt->bind_param("s", $slug);
}

$stmt->execute();
$query = $stmt->get_result();

if ($query->num_rows < 1) {
    die("Komik tidak ditemukan.");
}

$komik = $query->fetch_assoc();
$komik_id = $komik['id'];
$stmt->close();

// View counter
$stmt_view = $conn->prepare("UPDATE komik SET views = views + 1 WHERE id = ?");
$stmt_view->bind_param("i", $komik_id);
$stmt_view->execute();
$stmt_view->close();

// Ambil chapter
$stmt_chapters = $conn->prepare("SELECT * FROM chapters WHERE komik_id = ? ORDER BY CAST(chapter_number AS UNSIGNED) ASC");
$stmt_chapters->bind_param("i", $komik_id);
$stmt_chapters->execute();
$result_chapters = $stmt_chapters->get_result();

$all_chapters = [];
while ($row = $result_chapters->fetch_assoc()) {
    $all_chapters[] = $row;
}
$stmt_chapters->close();

$firstData = !empty($all_chapters) ? $all_chapters[0] : null;
$latestData = !empty($all_chapters) ? end($all_chapters) : null;

// Related series
$genre = "%" . $komik['genre'] . "%";
$stmt_related = $conn->prepare("SELECT * FROM komik WHERE id != ? AND genre LIKE ? LIMIT 6");
$stmt_related->bind_param("is", $komik_id, $genre);
$stmt_related->execute();
$related = $stmt_related->get_result();

/* ===================================================
   CEK BOOKMARK (Sesuaikan $_SESSION['user_id'] jika berbeda)
=================================================== */
$is_bookmarked = false;
if (isset($_SESSION['user_id'])) { 
    $user_id = $_SESSION['user_id'];
    $stmt_check_bk = $conn->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND komik_id = ?");
    $stmt_check_bk->bind_param("ii", $user_id, $komik_id);
    $stmt_check_bk->execute();
    $res_check_bk = $stmt_check_bk->get_result();
    if ($res_check_bk->num_rows > 0) {
        $is_bookmarked = true;
    }
    $stmt_check_bk->close();
}

$tipe = strtolower(trim($komik['tipe']));
$slugKomik = trim($komik['slug']);
$coverFile = trim($komik['cover']);

if (empty($slugKomik)) {
    $slugKomik = str_replace(' ', '-', strtolower($komik['judul']));
}

$basePath = BASE_URL . "uploads/" . $tipe . "/" . $slugKomik . "/";
$coverPath = $basePath . $coverFile;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($komik['judul']); ?> | EUGENVERSE</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { background: #07070a; color: #f3f4f6; margin: 0; font-family: sans-serif; }
        .home-shortcut { position: fixed; top: 25px; right: 25px; z-index: 100; background: rgba(124, 58, 237, 0.9); backdrop-filter: blur(8px); width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 50%; color: #fff; text-decoration: none; box-shadow: 0 4px 15px rgba(124, 58, 237, 0.4); transition: all 0.3s ease; }
        .home-shortcut:hover { transform: scale(1.1) rotate(15deg); background: #6d28d9; }
        .detail-hero { position: relative; display: flex; padding: 80px 60px 40px 60px; overflow: hidden; min-height: 550px; align-items: flex-end; }
        .detail-bg { position: absolute; inset: 0; z-index: 0; }
        .detail-bg img { width: 100%; height: 100%; object-fit: cover; filter: blur(30px) brightness(0.4); transform: scale(1.1); }
        .detail-overlay { position: absolute; inset: 0; background: linear-gradient(to top, #07070a 0%, rgba(7, 7, 10, 0.7) 50%, rgba(7, 7, 10, 0.3) 100%); }
        .detail-content { position: relative; z-index: 2; display: flex; gap: 45px; width: 100%; max-width: 1200px; margin: 0 auto; align-items: flex-end; }
        .detail-cover { width: 250px; min-width: 250px; }
        .detail-cover img { width: 100%; height: 360px; object-fit: cover; border-radius: 16px; box-shadow: 0 15px 35px rgba(0,0,0,0.6); border: 1px solid rgba(255, 255, 255, 0.1); }
        .detail-info { flex: 1; padding-bottom: 10px; }
        .detail-info h1 { font-size: 48px; font-weight: 800; margin: 0 0 8px 0; color: #fff; line-height: 1.2; }
        .alt-title { color: #9ca3af; font-size: 16px; margin-bottom: 25px; }
        .action-wrap { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 30px; }
        .main-btn, .sec-btn { display: inline-flex; align-items: center; gap: 10px; padding: 14px 28px; border-radius: 12px; text-decoration: none; color: #fff; font-weight: 600; font-size: 15px; transition: all 0.3s ease; }
        .main-btn { background: #7c3aed; box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3); }
        .main-btn:hover { background: #6d28d9; transform: translateY(-2px); }
        .sec-btn { background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.08); }
        .sec-btn:hover { background: rgba(255, 255, 255, 0.12); transform: translateY(-2px); }
        .meta-row { display: flex; gap: 20px; margin-bottom: 20px; color: #e5e7eb; flex-wrap: wrap; font-size: 15px; }
        .meta-row .rating { color: #fbbf24; }
        .meta-list { display: flex; gap: 12px; flex-wrap: wrap; }
        .meta-item { background: rgba(23, 23, 31, 0.6); border: 1px solid rgba(255, 255, 255, 0.05); padding: 8px 16px; border-radius: 20px; font-size: 13px; }
        .meta-item strong { color: #a78bfa; }
        .container-vessel { max-width: 1200px; margin: 0 auto; padding: 0 60px; }
        .section { padding: 40px 0; border-bottom: 1px solid rgba(255, 255, 255, 0.04); }
        .section h2 { font-size: 20px; border-left: 4px solid #7c3aed; padding-left: 14px; margin: 0 0 25px 0; color: #fff; }
        .desc { line-height: 1.8; color: #9ca3af; font-size: 15px; }
        .chapters-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .chapter-card { position: relative; background: #13131a; border-radius: 12px; overflow: hidden; text-decoration: none; color: #fff; display: flex; align-items: center; border: 1px solid rgba(255, 255, 255, 0.03); transition: all 0.3s ease; }
        .chapter-card:hover { transform: translateY(-3px); background: #191924; border-color: rgba(124, 58, 237, 0.3); }
        .chapter-card.chapter-read { border-color: rgba(124, 58, 237, 0.4); background: rgba(17, 17, 24, 0.8); }
        .chapter-card.chapter-read .chapter-name { color: #a78bfa; }
        .read-status-badge { position: absolute; top: 8px; right: 8px; background: #7c3aed; color: #fff; font-size: 9px; font-weight: 700; padding: 3px 8px; border-radius: 20px; }
        .chapter-thumb { width: 110px; height: 75px; min-width: 110px; position: relative; overflow: hidden; background: #09090d; }
        .chapter-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .badge-up { position: absolute; bottom: 6px; left: 6px; background: #ef4444; color: #fff; font-size: 9px; padding: 2px 6px; border-radius: 4px; }
        .chapter-details { padding: 12px 16px; flex: 1; min-width: 0; }
        .chapter-name { font-weight: 600; font-size: 14px; display: block; margin-bottom: 6px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; }
        .chapter-time { font-size: 12px; color: #6b7280; }
        .related-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 16px; }
        .related-card { background: #13131a; border-radius: 12px; overflow: hidden; text-decoration: none; color: #fff; border: 1px solid rgba(255, 255, 255, 0.03); transition: all 0.3s ease; }
        .related-card img { width: 100%; height: 200px; object-fit: cover; }
        .related-card h3 { padding: 12px; margin: 0; font-size: 13px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; }
        @media(max-width: 1024px) { .chapters-grid { grid-template-columns: repeat(2, 1fr); } .related-grid { grid-template-columns: repeat(4, 1fr); } }
        @media(max-width: 768px) { .detail-hero { padding: 40px 20px; } .detail-content { flex-direction: column; align-items: center; text-align: center; } .detail-cover { width: 180px; min-width: 180px; } .detail-cover img { height: 260px; } .detail-info h1 { font-size: 32px; } .action-wrap, .meta-row, .meta-list { justify-content: center; } .container-vessel { padding: 0 20px; } .chapters-grid { grid-template-columns: 1fr; } .related-grid { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>

<a href="<?php echo BASE_URL; ?>home.php" class="home-shortcut" title="Beranda"><i class="fas fa-home"></i></a>

<section class="detail-hero">
    <div class="detail-bg">
        <img src="<?php echo e($coverPath); ?>" onerror="this.src='<?php echo BASE_URL; ?>uploads/default-cover.jpg'">
        <div class="detail-overlay"></div>
    </div>
    <div class="detail-content">
        <div class="detail-cover"><img src="<?php echo e($coverPath); ?>" onerror="this.src='<?php echo BASE_URL; ?>uploads/default-cover.jpg'"></div>
        <div class="detail-info">
            <h1><?php echo e($komik['judul']); ?></h1>
            <div class="alt-title"><?php echo e($komik['judul_alternatif'] ?? ''); ?></div>
            <div class="action-wrap">
                <?php if($firstData){ ?>
                    <a href="reader.php?id=<?php echo $firstData['id']; ?>" class="main-btn"><i class="fas fa-play"></i> Baca Chapter 1</a>
                <?php } ?>
                
                <a href="#" class="sec-btn btn-bookmark" data-komik-id="<?php echo $komik_id; ?>" style="<?php echo $is_bookmarked ? 'background: rgba(124, 58, 237, 0.2); border-color: #7c3aed;' : ''; ?>">
                    <i class="bookmark-icon <?php echo $is_bookmarked ? 'fas' : 'far'; ?> fa-bookmark"></i> 
                    <span class="bookmark-text"><?php echo $is_bookmarked ? 'Tersimpan' : 'Bookmark'; ?></span>
                </a>

                <a href="download.php?id=<?php echo $komik_id; ?>" class="sec-btn"><i class="fas fa-download"></i> Download</a>
            </div>
            <div class="meta-row">
                <span class="rating"><i class="fas fa-star"></i> <?php echo e($komik['rating'] ?? '9.0'); ?></span>
                <span><i class="fas fa-eye"></i> <?php echo number_short($komik['views']); ?> Views</span>
                <?php if($latestData){ ?> <span><i class="fas fa-book"></i> Ch. <?php echo e($latestData['chapter_number']); ?></span> <?php } ?>
            </div>
            <div class="meta-list">
                <div class="meta-item"><strong>Tipe:</strong> <?php echo e($komik['tipe']); ?></div>
                <div class="meta-item"><strong>Genre:</strong> <?php echo e($komik['genre']); ?></div>
            </div>
        </div>
    </div>
</section>

<div class="container-vessel">
    <section class="section">
        <h2>Sinopsis</h2>
        <p class="desc"><?php echo nl2br(e($komik['sinopsis'] ?? 'Belum ada sinopsis.')); ?></p>
    </section>

    <section class="section">
        <h2>Daftar Chapter</h2>
        <div class="chapters-grid">
            <?php
            if (!empty($all_chapters)) {
                foreach ($all_chapters as $row) {
                    $is_new = (strtotime($row['created_at']) > strtotime('-24 hours'));
                    $chapterFolder = trim($row['folder'] ?? $row['image_folder'] ?? '');
                    $thumb = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='120' height='80'><rect width='100%' height='100%' fill='%2313131a'/></svg>";

                    $possiblePaths = [
                        __DIR__ . "/uploads/" . $tipe . "/" . $slugKomik . "/chapters/" . $chapterFolder,
                        __DIR__ . "/uploads/" . $tipe . "/" . $slugKomik . "/" . $chapterFolder
                    ];

                    foreach ($possiblePaths as $index => $localPath) {
                        $localPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $localPath);
                        if (!empty($chapterFolder) && is_dir($localPath)) {
                            $files = scandir($localPath);
                            if ($files !== false) {
                                $images = array_values(array_filter($files, function($f) {
                                    return in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp']);
                                }));
                                if (!empty($images)) {
                                    natsort($images);
                                    $images = array_values($images);
                                    $thumb = BASE_URL . "uploads/" . $tipe . "/" . $slugKomik . "/" . ($index == 0 ? "chapters/" : "") . $chapterFolder . "/" . $images[0];
                                    break;
                                }
                            }
                        }
                    }

                    $isRead = false;
                    if (isset($_SESSION['read_chapters'][$komik_id]) && in_array($row['id'], $_SESSION['read_chapters'][$komik_id])) {
                        $isRead = true;
                    }
            ?>
            <a href="reader.php?id=<?php echo $row['id']; ?>" class="chapter-card <?php echo $isRead ? 'chapter-read' : ''; ?>">
                <div class="chapter-thumb">
                    <img src="<?php echo $thumb; ?>" loading="lazy" onerror="this.src='<?php echo BASE_URL; ?>uploads/default-cover.jpg'">
                    <?php if($is_new){ ?><span class="badge-up">UP</span><?php } ?>
                </div>
                <div class="chapter-details">
                    <span class="chapter-name">Chapter <?php echo e($row['chapter_number']); ?></span>
                    <span class="chapter-time"><i class="far fa-clock"></i> <?php echo tanggal($row['created_at']); ?></span>
                </div>
                <?php if($isRead){ ?><span class="read-status-badge">✓ DIBACA</span><?php } ?>
            </a>
            <?php } } else { echo "<p style='color:#6b7280;'>Belum ada chapter.</p>"; } ?>
        </div>
    </section>

    <section class="section">
        <h2>Related Series</h2>
        <div class="related-grid">
            <?php while($r = $related->fetch_assoc()){ 
                $relatedPath = BASE_URL . "uploads/" . strtolower(trim($r['tipe'])) . "/" . trim($r['slug']) . "/" . trim($r['cover']);
            ?>
                <a href="detail.php?id=<?php echo $r['id']; ?>" class="related-card">
                    <img src="<?php echo e($relatedPath); ?>" onerror="this.src='<?php echo BASE_URL; ?>uploads/default-cover.jpg'">
                    <h3><?php echo e($r['judul']); ?></h3>
                </a>
            <?php } $stmt_related->close(); ?>
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
</body>
</html>