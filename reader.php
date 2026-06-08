<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/config/koneksi.php';
include __DIR__ . '/includes/functions.php';

/* =========================
   BASE URL
========================= */
if (!defined('BASE_URL')) {
    define('BASE_URL', '/EUGENVERSE/');
}

/* =========================
   AMBIL ID CHAPTER
========================= */
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id < 1) {
    die("Chapter tidak valid");
}

/* =========================
   AMBIL DATA CHAPTER + KOMIK
========================= */
$query = mysqli_query($conn, "
SELECT
    chapters.*,
    komik.judul AS nama_komik,
    komik.slug AS komik_slug,
    komik.tipe AS komik_tipe,
    komik.id AS komik_id
FROM chapters
JOIN komik
ON komik.id = chapters.komik_id
WHERE chapters.id='$id'
LIMIT 1
");

if (!$query || mysqli_num_rows($query) < 1) {
    die("Chapter tidak ditemukan");
}

$data = mysqli_fetch_assoc($query);

$komik_id   = $data['komik_id'];
$chapter_id = $data['id'];

/* =========================
   FIX CHAPTER NUMBER
========================= */
$chapterNumber = $data['chapter_number'] ?? $data['chapters_number'] ?? '0';

/* =========================
   SIMPAN HISTORY BACA (DATABASE / SESSION)
========================= */
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    // Update atau Insert ke tabel history database agar terintegrasi dengan library.php
    $check_history = mysqli_query($conn, "SELECT id FROM history WHERE user_id = '$user_id' AND komik_id = '$komik_id'");
    if (mysqli_num_rows($check_history) > 0) {
        mysqli_query($conn, "UPDATE history SET chapter_id = '$chapter_id', last_page = 1, updated_at = NOW() WHERE user_id = '$user_id' AND komik_id = '$komik_id'");
    } else {
        mysqli_query($conn, "INSERT INTO history (user_id, komik_id, chapter_id, last_page, created_at, updated_at) VALUES ('$user_id', '$komik_id', '$chapter_id', 1, NOW(), NOW())");
    }
}

if (!isset($_SESSION['read_chapters'])) {
    $_SESSION['read_chapters'] = [];
}
$_SESSION['read_chapters'][$komik_id][] = $chapter_id;
$_SESSION['read_chapters'][$komik_id] = array_unique($_SESSION['read_chapters'][$komik_id]);

/* =========================
   PATH FOLDER CHAPTER
========================= */
$tipe   = strtolower(trim($data['komik_tipe']));
$slug   = trim($data['komik_slug']);
$folder = trim($data['folder']);

if (empty($slug)) {
    $slug = strtolower($data['nama_komik']);
    $slug = str_replace(' ', '-', $slug);
}

/* =========================
   PATH FOLDER IMAGE (Autodetect Variasi Folder)
========================= */
$possibleFolders = [
    __DIR__ . "/uploads/" . $tipe . "/" . $slug . "/chapters/" . $folder . "/",
    __DIR__ . "/uploads/" . $tipe . "/" . $slug . "/" . $folder . "/"
];

$imageFolder = $possibleFolders[0]; 
$imageUrl = BASE_URL . "uploads/" . $tipe . "/" . $slug . "/chapters/" . $folder . "/";

foreach ($possibleFolders as $index => $path) {
    if (is_dir($path)) {
        $imageFolder = $path;
        if ($index === 1) {
            $imageUrl = BASE_URL . "uploads/" . $tipe . "/" . $slug . "/" . $folder . "/";
        }
        break;
    }
}

/* =========================
   AMBIL GAMBAR MENGGUNAKAN SCANDIR
========================= */
$images = [];
if (is_dir($imageFolder)) {
    $files = scandir($imageFolder);
    if ($files !== false) {
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $images[] = $imageFolder . $file;
                }
            }
        }
    }
}

/* SORT GAMBAR NATURAL */
natsort($images);
$images = array_values($images);

/* =========================
   PREV & NEXT CHAPTER (LOGIKA CASTING NUMERIK)
========================= */
$prev = mysqli_query($conn, "SELECT id FROM chapters WHERE komik_id='$komik_id' AND CAST(chapter_number AS UNSIGNED) < CAST('$chapterNumber' AS UNSIGNED) ORDER BY CAST(chapter_number AS UNSIGNED) DESC LIMIT 1");
$prevData = mysqli_fetch_assoc($prev);

$next = mysqli_query($conn, "SELECT id FROM chapters WHERE komik_id='$komik_id' AND CAST(chapter_number AS UNSIGNED) > CAST('$chapterNumber' AS UNSIGNED) ORDER BY CAST(chapter_number AS UNSIGNED) ASC LIMIT 1");
$nextData = mysqli_fetch_assoc($next);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($data['nama_komik']); ?> - Chapter <?php echo e($chapterNumber); ?> | EUGENVERSE</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: #07070a; color: #f3f4f6; padding-bottom: 100px; }
        a { text-decoration: none; color: inherit; }

        /* =========================
           TOP BAR NAVIGASI
        ========================= */
        .top-bar {
            background: rgba(17, 17, 22, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            position: sticky; top: 0; z-index: 999;
            width: 100%; height: 60px;
            display: flex; justify-content: space-between; align-items: center;
            padding: 0 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .top-bar-btn {
            width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;
            background: rgba(255, 255, 255, 0.03); border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.08); color: #9ca3af; transition: all 0.2s;
        }
        .top-bar-btn:hover { color: #fff; background: #7c3aed; border-color: #a78bfa; }
        .title-section { font-size: 14px; font-weight: 600; color: #fff; text-align: center; }
        .title-section span { color: #a78bfa; margin-left: 4px; }

        /* =========================
           CONTAINER GAMBAR WEBTOON
        ========================= */
        .reader-container { max-width: 750px; margin: 0 auto; background: #000; }
        .chapter-image { width: 100%; display: block; line-height: 0; }
        .chapter-image img { width: 100%; height: auto; display: block; }

        /* =========================
           FLOATING ACTION CONTROL MENU (SHINIGAMI STYLE)
        ========================= */
        .floating-menu-wrap {
            position: fixed; bottom: 30px; left: 50%;
            transform: translateX(-50%); z-index: 1000;
            background: rgba(20, 20, 27, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 10px 24px; border-radius: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.6);
            border: 1px solid rgba(255, 255, 255, 0.08);
            display: flex; align-items: center; gap: 18px;
        }
        .float-btn {
            width: 44px; height: 44px; border-radius: 50%;
            background: #1c1c24; border: 1px solid rgba(255,255,255,0.05);
            display: flex; align-items: center; justify-content: center;
            color: #9ca3af; font-size: 16px; cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .float-btn:hover {
            background: #7c3aed; color: #fff;
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(124, 58, 237, 0.4);
        }
        .float-btn.disabled {
            opacity: 0.25; pointer-events: none; background: #121216;
        }

        /* REPORT BUTTON SECTION */
        .report-section { text-align: center; margin: 40px 0 20px 0; }
        .btn-report {
            background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2);
            color: #f87171; padding: 10px 24px; border-radius: 30px;
            font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.3s;
        }
        .btn-report:hover { background: #ef4444; color: #fff; }

        /* =========================
           KOMENTAR DESIGN
        ========================= */
        .comments-container {
            max-width: 750px; margin: 30px auto 60px auto;
            background: #111116; border-radius: 16px; padding: 24px;
            border: 1px solid rgba(255, 255, 255, 0.04);
        }
        .comments-header { font-size: 16px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .comments-header i { color: #7c3aed; }
        .comment-form { display: flex; flex-direction: column; gap: 12px; margin-bottom: 30px; }
        .comment-input {
            width: 100%; background: #07070a; border: 1px solid rgba(255, 255, 255, 0.08);
            color: #fff; padding: 14px; border-radius: 10px; resize: vertical; min-height: 80px; font-size: 14px;
        }
        .comment-input:focus { outline: none; border-color: #7c3aed; }
        .btn-submit {
            align-self: flex-end; background: #7c3aed; color: #fff; border: none;
            padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px;
        }
        .btn-submit:hover { background: #6d28d9; }

        /* UTILITY SCROLL BUTTON */
        .scroll-nav-btn { position: fixed; right: 25px; background: rgba(20,20,27,0.8); border: 1px solid rgba(255,255,255,0.05); color:#9ca3af; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; z-index:1000; font-size:14px; transition: 0.2s; }
        .scroll-nav-btn:hover { background:#7c3aed; color:#fff; }
        #scrollTopBtn { bottom: 85px; }
        #scrollBottomBtn { bottom: 35px; }

        @media(max-width: 768px){
            .title-section { font-size: 12px; max-width: 55%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .floating-menu-wrap { gap: 10px; padding: 8px 16px; bottom: 15px; }
            .float-btn { width: 38px; height: 38px; font-size: 14px; }
            .scroll-nav-btn { right: 10px; width:34px; height:34px; }
            #scrollTopBtn { bottom: 125px; }
            #scrollBottomBtn { bottom: 80px; }
        }
    </style>
</head>
<body>

<div class="top-bar">
    <a href="detail.php?id=<?php echo $komik_id; ?>" class="top-bar-btn" title="Kembali ke Detail">
        <i class="fa-solid fa-arrow-left"></i>
    </a>
    <div class="title-section">
        <?php echo e($data['nama_komik']); ?> 
        <i class="fa-solid fa-chevron-right" style="font-size:9px; margin: 0 4px; color:#6b7280;"></i>
        <span>Chapter <?php echo e($chapterNumber); ?></span>
    </div>
    <a href="index.php" class="top-bar-btn" title="Beranda">
        <i class="fa-solid fa-house"></i>
    </a>
</div>

<div class="reader-container">
    <?php
    if (count($images) > 0) {
        foreach ($images as $img) {
            $fileName = basename($img);
            $imgUrl = $imageUrl . $fileName;
    ?>
            <div class="chapter-image">
                <img src="<?php echo $imgUrl; ?>" loading="lazy" alt="Page Image content">
            </div>
    <?php
        }
    } else {
        echo "
        <div style='padding:100px 20px; text-align:center; color:#6b7280;'>
            <i class='fa-regular fa-image' style='font-size:40px; margin-bottom:12px; color:#27273a;'></i>
            <p style='margin:0; font-size:14px;'>Tidak ada berkas gambar yang terbaca dalam folder penyimpanan ini.</p>
        </div>";
    }
    ?>
</div>

<div class="report-section">
    <a href="#" class="btn-report"><i class="fa-regular fa-flag"></i> Report Broken Chapter</a>
</div>

<div class="comments-container">
    <div class="comments-header"><i class="fa-regular fa-comments"></i> Kolom Diskusi</div>
    <form class="comment-form" id="commentForm">
        <input type="hidden" id="chapter_id" value="<?php echo $chapter_id; ?>">
        <textarea class="comment-input" id="isi_komentar" placeholder="Berikan tanggapanmu mengenai chapter ini..." required></textarea>
        <button type="submit" class="btn-submit">Kirim Komentar</button>
    </form>
    <div id="commentList"></div>
</div>

<div class="floating-menu-wrap">
    <a href="<?php echo $prevData ? 'reader.php?id='.$prevData['id'] : '#'; ?>" 
       class="float-btn <?php echo !$prevData ? 'disabled' : ''; ?>" title="Chapter Sebelumnya">
        <i class="fa-solid fa-chevron-left"></i>
    </a>

    <div class="float-btn" title="Pengaturan Tampilan" onclick="alert('Fitur kustomisasi resolusi segera hadir!')">
        <i class="fa-solid fa-gear"></i>
    </div>

    <div class="float-btn" id="playScrollBtn" title="Auto Scroll Konten">
        <i class="fa-solid fa-play"></i>
    </div>

    <a href="detail.php?id=<?php echo $komik_id; ?>" class="float-btn" title="Daftar Bab Komik">
        <i class="fa-solid fa-bars"></i>
    </a>

    <a href="<?php echo $nextData ? 'reader.php?id='.$nextData['id'] : '#'; ?>" 
       class="float-btn <?php echo !$nextData ? 'disabled' : ''; ?>" title="Chapter Selanjutnya">
        <i class="fa-solid fa-chevron-right"></i>
    </a>
</div>

<div class="scroll-nav-btn" id="scrollTopBtn" title="Ke Atas"><i class="fa-solid fa-chevron-up"></i></div>
<div class="scroll-nav-btn" id="scrollBottomBtn" title="Ke Bawah"><i class="fa-solid fa-chevron-down"></i></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
    // LOAD KOMENTAR AJAX SYSTEM
    function loadComments(){
        let chapter_id = $('#chapter_id').val();
        $.ajax({
            url: 'ambil_komentar.php',
            type: 'GET',
            data: { chapter_id: chapter_id },
            success: function(data){
                $('#commentList').html(data);
            }
        });
    }
    loadComments();
    setInterval(loadComments, 8000); // Polling update per 8 detik

    $('#commentForm').on('submit', function(e){
        e.preventDefault();
        let chapter_id = $('#chapter_id').val();
        let isi = $('#isi_komentar').val();
        $.ajax({
            url: 'simpan_komentar.php',
            type: 'POST',
            data: { chapter_id: chapter_id, isi_komentar: isi },
            success: function(){
                $('#isi_komentar').val('');
                loadComments();
            }
        });
    });

    // SIDE UTILITY BUTTON SCROLL EVENT
    $('#scrollTopBtn').click(function(){ $('html, body').animate({scrollTop : 0}, 400); });
    $('#scrollBottomBtn').click(function(){ $('html, body').animate({scrollTop : $(document).height()}, 400); });

    // AUTOSCROLL PLAY/PAUSE FUNCTION
    let scrollInterval = null;
    $('#playScrollBtn').click(function(){
        if(scrollInterval === null){
            scrollInterval = setInterval(function(){
                window.scrollBy(0, 1);
            }, 20); // Kecepatan scroll berjalan lambat konstan nyaman dibaca
            $(this).html('<i class="fa-solid fa-pause"></i>').css('background', '#ef4444');
        } else {
            clearInterval(scrollInterval);
            scrollInterval = null;
            $(this).html('<i class="fa-solid fa-play"></i>').css('background', '#1c1c24');
        }
    });
});
</script>

</body>
</html>