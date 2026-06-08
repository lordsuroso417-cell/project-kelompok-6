<?php
include 'config/koneksi.php';
include 'includes/functions.php'; // Pastikan file ini ada untuk fungsi e() atau saringan teks aman
include 'includes/header.php';
include 'includes/navbar.php';

/* =========================
   BASE URL & FILTER STATE
========================= */
if (!defined('BASE_URL')) {
    define('BASE_URL', '/EUGENVERSE/');
}

// Menangkap data filter dengan aman agar pilihan user tidak ter-reset setelah submit
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$genre  = isset($_GET['genre']) ? mysqli_real_escape_string($conn, $_GET['genre']) : '';
$type   = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : '';
$sort   = isset($_GET['sort']) && $_GET['sort'] === 'ASC' ? 'ASC' : 'DESC';

/* =========================
   BUILD DYNAMIC QUERY
========================= */
$query = "SELECT * FROM komik WHERE 1=1";

if ($search != '') {
    $query .= " AND (judul LIKE '%$search%' OR author LIKE '%$search%' OR genre LIKE '%$search%')";
}
if ($genre != '') {
    $query .= " AND genre LIKE '%$genre%'";
}
if ($type != '') {
    $query .= " AND tipe='$type'";
}

$query .= " ORDER BY updated_at $sort, id DESC";
$data = mysqli_query($conn, $query);
?>

<style>
    /* =========================
       GLOBAL & WRAPPER CONTROLS
    ========================= */
    .explore-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 40px 20px;
        background: #07070a;
        color: #fff;
    }
    
    /* =========================
       SEARCH & FILTER BAR COMPONENT
    ========================= */
    .filter-wrapper-card {
        background: #111116;
        border: 1px solid rgba(255, 255, 255, 0.03);
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 40px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    .search-main-form {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .search-row {
        position: relative;
        display: flex;
        width: 100%;
    }
    .search-input-box {
        width: 100%;
        background: #181820;
        border: 1px solid rgba(255, 255, 255, 0.08);
        padding: 16px 20px;
        padding-right: 120px;
        border-radius: 12px;
        color: #fff;
        font-size: 15px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    .search-input-box:focus {
        outline: none;
        border-color: #7c3aed;
        box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.15);
        background: #1c1c26;
    }
    .search-submit-btn {
        position: absolute;
        right: 6px;
        top: 6px;
        bottom: 6px;
        background: #7c3aed;
        color: #fff;
        border: none;
        padding: 0 24px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: background 0.2s ease;
    }
    .search-submit-btn:hover {
        background: #6d28d9;
    }
    .filter-options-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr) auto;
        gap: 12px;
        align-items: center;
    }
    .filter-select {
        background: #181820;
        border: 1px solid rgba(255, 255, 255, 0.08);
        color: #d1d5db;
        padding: 12px 16px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        outline: none;
        transition: all 0.3s ease;
        width: 100%;
    }
    .filter-select:focus {
        border-color: #7c3aed;
    }
    .reset-filter-btn {
        background: rgba(255, 255, 255, 0.05);
        color: #9ca3af;
        border: 1px solid rgba(255, 255, 255, 0.08);
        padding: 12px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 14px;
        text-decoration: none;
        text-align: center;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .reset-filter-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
    }

    /* =========================
       SECTION HEADER TITLE
    ========================= */
    .explore-header {
        margin-bottom: 30px;
        border-left: 4px solid #7c3aed;
        padding-left: 14px;
    }
    .explore-header h2 {
        font-size: 24px;
        font-weight: 700;
        margin: 0 0 4px 0;
        color: #fff;
    }
    .explore-header p {
        font-size: 13px;
        color: #6b7280;
        margin: 0;
    }

    /* =========================
       GRID & COMIC CARDS COMPONENT
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
        position: relative;
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
        margin: 14px 14px 4px 14px;
        line-height: 1.4;
        color: #f3f4f6;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .card-meta-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 0 14px 12px 14px;
    }
    .comic-card .chap {
        font-size: 12px;
        color: #a78bfa;
        font-weight: 600;
    }
    .fav-action-btn {
        background: rgba(255, 255, 255, 0.05);
        color: #ef4444;
        border: 1px solid rgba(255, 255, 255, 0.03);
        width: 26px;
        height: 26px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .fav-action-btn:hover {
        background: #ef4444;
        color: #fff;
        transform: scale(1.1);
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
        .filter-options-row { grid-template-columns: repeat(2, 1fr); }
    }
    @media(max-width: 768px) {
        .comic-grid { grid-template-columns: repeat(2, 1fr); gap: 14px; }
        .filter-options-row { grid-template-columns: 1fr; }
        .comic-thumb-wrap { height: 210px; }
    }
</style>

<div class="explore-container">

    <div class="filter-wrapper-card">
        <form method="GET" action="" class="search-main-form">
            
            <div class="search-row">
                <input 
                    type="text" 
                    name="search" 
                    placeholder="Cari judul, author, atau keyword lainnya..." 
                    class="search-input-box"
                    value="<?php echo htmlspecialchars(isset($_GET['search']) ? $_GET['search'] : ''); ?>"
                >
                <button type="submit" class="search-submit-btn">
                    <i class="fas fa-search"></i> Cari
                </button>
            </div>

            <div class="filter-options-row">
                
                <select name="genre" class="filter-select" onchange="this.form.submit()">
                    <option value="">Semua Genre</option>
                    <?php 
                    $genres = ['Action', 'Fantasy', 'Romance', 'Comedy', 'School'];
                    foreach($genres as $g){
                        $selected = ($genre === $g) ? 'selected' : '';
                        echo "<option value='$g' $selected>$g</option>";
                    }
                    ?>
                </select>

                <select name="type" class="filter-select" onchange="this.form.submit()">
                    <option value="">Semua Tipe</option>
                    <?php 
                    $types = ['manga' => 'Manga', 'manhwa' => 'Manhwa', 'manhua' => 'Manhua', 'comic' => 'Comic'];
                    foreach($types as $key => $val){
                        $selected = ($type === $key) ? 'selected' : '';
                        echo "<option value='$key' $selected>$val</option>";
                    }
                    ?>
                </select>

                <select name="sort" class="filter-select" onchange="this.form.submit()">
                    <option value="DESC" <?php echo $sort === 'DESC' ? 'selected' : ''; ?>>Terbaru</option>
                    <option value="ASC" <?php echo $sort === 'ASC' ? 'selected' : ''; ?>>Terlama</option>
                </select>

                <?php if($search != '' || $genre != '' || $type != '' || $sort != 'DESC'){ ?>
                    <a href="explore.php" class="reset-filter-btn">
                        <i class="fas fa-sync-alt" style="margin-right: 6px;"></i> Reset
                    </a>
                <?php } ?>

            </div>

        </form>
    </div>

    <div class="explore-header">
        <h2>Explore Comics</h2>
        <p>Menampilkan hasil pencarian data real-time langsung dari database utama</p>
    </div>

    <div class="comic-grid">
        <?php
        if (mysqli_num_rows($data) > 0) {
            while ($row = mysqli_fetch_assoc($data)) {
                
                // Mengambil nomor chapter paling terakhir saja secara real-time dari relasi tabel chapters
                $latestChapter = mysqli_query($conn, "SELECT chapter_number FROM chapters WHERE komik_id='" . $row['id'] . "' ORDER BY CAST(chapter_number AS UNSIGNED) DESC LIMIT 1");
                $chapterData = mysqli_fetch_assoc($latestChapter);
                
                // Manajemen folder path gambar cover komik
                $coverPath = BASE_URL . "uploads/" . strtolower($row['tipe']) . "/" . $row['slug'] . "/" . $row['cover'];
        ?>
            <div class="comic-card">
                <a href="detail.php?id=<?php echo $row['id']; ?>" style="text-decoration: none; color: inherit;">
                    <div class="comic-thumb-wrap">
                        <img src="<?php echo $coverPath; ?>" alt="<?php echo htmlspecialchars($row['judul']); ?>" loading="lazy" onerror="this.src='<?php echo BASE_URL; ?>uploads/default-cover.jpg'">
                    </div>
                    <h3><?php echo htmlspecialchars($row['judul']); ?></h3>
                </a>
                
                <div class="card-meta-row">
                    <div class="chap">
                        <?php echo $chapterData ? 'Ch. ' . htmlspecialchars($chapterData['chapter_number']) : 'No Chapters'; ?>
                    </div>
                    <a href="favorite.php?id=<?php echo $row['id']; ?>" class="fav-action-btn" title="Tambahkan ke Favorit">
                        <i class="fas fa-heart"></i>
                    </a>
                </div>

                <div class="card-badge">
                    <?php echo htmlspecialchars($row['genre']); ?>
                </div>
            </div>
        <?php
            }
        } else {
        ?>
            <div class="empty-box">
                <i class="far fa-compass" style="font-size: 28px; color: #7c3aed; margin-bottom: 12px; display: block;"></i>
                Komik tidak ditemukan.<br><span style="color: #6b7280; font-size: 13px;">Mesin pencari ikut lelah mencari judul itu.</span>
            </div>
        <?php } ?>
    </div>

</div>

<?php include 'includes/footer.php'; ?>