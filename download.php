<?php
// download.php
include 'config/koneksi.php';
include 'includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '/EUGENVERSE/');
}

// Handler Fitur API Internal AJAX untuk mengambil list chapter berdasarkan NAMA KOMIK
if (isset($_GET['action']) && $_GET['action'] === 'get_chapters' && isset($_GET['komik_title'])) {
    header('Content-Type: application/json');
    $komik_title = trim($_GET['komik_title']);
    
    // Struktur JSON baru untuk mempermudah proses debugging mandiri
    $response = [
        'success' => false,
        'chapters' => [],
        'debug' => [
            'input_title' => $komik_title,
            'komik_found' => false,
            'komik_id' => null,
            'matched_judul' => null,
            'chapters_count' => 0,
            'sql_error' => ''
        ]
    ];
    
    // 1. Cari ID Komik berdasarkan Judul langsung di tabel `komik`
    $query_search = "SELECT id, judul FROM komik WHERE judul LIKE ? LIMIT 1";
    
    $stmt_komik = $conn->prepare($query_search);
    if ($stmt_komik) {
        $search_term = "%" . $komik_title . "%";
        $stmt_komik->bind_param("s", $search_term);
        $stmt_komik->execute();
        $res_komik = $stmt_komik->get_result();
        
        if ($row_komik = $res_komik->fetch_assoc()) {
            $komik_id = $row_komik['id'];
            
            $response['debug']['komik_found'] = true;
            $response['debug']['komik_id'] = $komik_id;
            $response['debug']['matched_judul'] = $row_komik['judul'];
            
            // 2. Ambil semua chapter berdasarkan ID yang ditemukan
            // Note: Jika di tabel chapters kamu nama kolomnya bukan 'manga_id' (misal 'komik_id'), silakan sesuaikan di bawah ini
            $stmt_ch = $conn->prepare("SELECT id FROM chapters WHERE komik_id = ? ORDER BY id DESC");
            if ($stmt_ch) {
                $stmt_ch->bind_param("i", $komik_id);
                $stmt_ch->execute();
                $res_ch = $stmt_ch->get_result();
                
                while ($row = $res_ch->fetch_assoc()) {
                    $response['chapters'][] = [
                        'id' => $row['id'],
                        'label' => 'Ch. ' . $row['id']
                    ];
                }
                $response['debug']['chapters_count'] = count($response['chapters']);
                if (count($response['chapters']) > 0) {
                    $response['success'] = true;
                }
                $stmt_ch->close();
            } else {
                $response['debug']['sql_error'] = 'Gagal memproses query tabel chapters: ' . $conn->error;
            }
        } else {
            $response['debug']['sql_error'] = 'Judul komik tidak cocok dengan data apa pun di tabel komik.';
        }
        $stmt_manga->close();
    } else {
        $response['debug']['sql_error'] = 'Gagal memproses query pencarian: ' . $conn->error;
    }
    
    echo json_encode($response);
    exit;
}

// Konfigurasi Folder Penyimpanan
$upload_dir = 'uploads/manual/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$error_message = '';
$success_message = '';
$allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

/* ====================================================================
   PROSES ENGINE: TAMBAH KOLEKSI (MULTI-CHAPTER SUPPORT)
   ==================================================================== */
if (isset($_POST['add']) && isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $judul = trim($_POST['judul']);
    $source_type = $_POST['source_type'];
    $final_filename = 'default-cover.jpg';
    $file_type = 'manual';
    $is_valid = false;
    $chapters_label = '';

    if (!empty($judul)) {
        
        // ---- SKENARIO 1: UPLOAD FILE LOKAL ----
        if ($source_type === 'local' && isset($_FILES['file_lokal']) && $_FILES['file_lokal']['error'] === 0) {
            $file_tmp  = $_FILES['file_lokal']['tmp_name'];
            $file_name = $_FILES['file_lokal']['name'];
            $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (in_array($file_ext, $allowed_extensions)) {
                $final_filename = 'offline_' . uniqid() . '_' . time() . '.' . $file_ext;
                $target_path = $upload_dir . $final_filename;

                if (move_uploaded_file($file_tmp, $target_path)) {
                    $file_type = $file_ext;
                    $chapters_label = strtoupper($file_type);
                    $is_valid = true;
                } else {
                    $error_message = "Gagal memindahkan file ke storage server.";
                }
            } else {
                $error_message = "Format file tidak diizinkan! Gunakan JPG, PNG, WEBP, atau PDF.";
            }
        }
        
        // ---- SKENARIO 2: FETCH DARI URL ----
        elseif ($source_type === 'remote' && !empty($_POST['url_remote'])) {
            $url = trim($_POST['url_remote']);

            if (filter_var($url, FILTER_VALIDATE_URL) || true) { 
                $path_structure = parse_url($url, PHP_URL_PATH);
                $file_ext = strtolower(pathinfo($path_structure, PATHINFO_EXTENSION));

                if (in_array($file_ext, $allowed_extensions)) {
                    $ctx = stream_context_create(['http' => ['timeout' => 15]]);
                    $file_data = @file_get_contents($url, false, $ctx);

                    if ($file_data !== false) {
                        $final_filename = 'remote_' . uniqid() . '_' . time() . '.' . $file_ext;
                        $target_path = $upload_dir . $final_filename;

                        if (file_put_contents($target_path, $file_data)) {
                            $file_type = $file_ext;
                            $chapters_label = strtoupper($file_type);
                            $is_valid = true;
                        } else {
                            $error_message = "Gagal menulis file remote ke disk internal.";
                        }
                    } else {
                        $error_message = "Tidak dapat mengunduh data dari URL tersebut.";
                    }
                } else {
                    $error_message = "URL tidak mengarah ke file gambar/pdf yang valid.";
                }
            }
        }

        // ---- SKENARIO 3: MULTI-DOWNLOAD BATCH DARI EUGENVERSE & ZIP PACKER ----
        elseif ($source_type === 'eugenverse' && !empty($_POST['eugen_chapter_ids'])) {
            $selected_chapters = $_POST['eugen_chapter_ids']; 

            if (count($selected_chapters) > 5) {
                $error_message = "Batas maksimal pengunduhan simultan adalah 5 chapter per siklus!";
            } else {
                $zip = new ZipArchive();
                $final_filename = 'eugenverse_batch_' . uniqid() . '_' . time() . '.zip';
                $target_path = $upload_dir . $final_filename;

                if ($zip->open($target_path, ZipArchive::CREATE) === TRUE) {
                    $total_files_added = 0;
                    $processed_labels = [];

                    foreach ($selected_chapters as $chapter_id) {
                        $chapter_id = intval($chapter_id);
                        $processed_labels[] = 'CH_' . $chapter_id;

                        $page_stmt = $conn->prepare("SELECT file_path FROM chapter_pages WHERE chapter_id = ? ORDER BY page_no ASC");
                        $page_stmt->bind_param("i", $chapter_id);
                        $page_stmt->execute();
                        $pages_result = $page_stmt->get_result();

                        while ($page = $pages_result->fetch_assoc()) {
                            $source_img = $page['file_path'];
                            if (file_exists($source_img)) {
                                $zip->addFile($source_img, 'Chapter_' . $chapter_id . '/' . basename($source_img));
                                $total_files_added++;
                            }
                        }
                        $page_stmt->close();
                    }
                    $zip->close();

                    if ($total_files_added > 0) {
                        $file_type = 'zip';
                        $chapters_label = implode(', ', $processed_labels);
                        $is_valid = true;
                    } else {
                        @unlink($target_path);
                        $error_message = "Gambar fisik komik dari semua chapter terpilih tidak ditemukan pada penyimpanan server.";
                    }
                } else {
                    $error_message = "Sistem gagal membuat arsip ZIP gabungan.";
                }
            }
        }
        else {
            $error_message = "Silakan lengkapi parameter data dengan benar.";
        }

        if ($is_valid) {
            $stmt = $conn->prepare("INSERT INTO downloads (user_id, judul, cover, chapters, tipe, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("issss", $uid, $judul, $final_filename, $chapters_label, $file_type);
            
            if ($stmt->execute()) {
                $count_info = isset($selected_chapters) ? count($selected_chapters) : 1;
                $success_message = "Koleksi (" . $count_info . " Item) berhasil dibungkus ke dalam pustaka offline!";
            } else {
                if (file_exists($upload_dir . $final_filename)) {
                    unlink($upload_dir . $final_filename);
                }
                $error_message = "Gagal mendaftarkan file manifest ke basis data.";
            }
            $stmt->close();
        }
    } else {
        $error_message = "Judul koleksi tidak boleh kosong.";
    }
}

/* ====================================================================
   PROSES ENGINE: HAPUS DATA & FILE DARI DISK
   ==================================================================== */
if (isset($_GET['hapus']) && isset($_SESSION['user_id'])) {
    $id  = intval($_GET['hapus']);
    $uid = $_SESSION['user_id'];

    $selectStmt = $conn->prepare("SELECT cover FROM downloads WHERE id = ? AND user_id = ?");
    $selectStmt->bind_param("ii", $id, $uid);
    $selectStmt->execute();
    $res = $selectStmt->get_result()->fetch_assoc();
    $selectStmt->close();

    if ($res) {
        $file_to_delete = $upload_dir . $res['cover'];
        if (file_exists($file_to_delete) && $res['cover'] !== 'default-cover.jpg') {
            unlink($file_to_delete);
        }

        $deleteStmt = $conn->prepare("DELETE FROM downloads WHERE id = ? AND user_id = ?");
        $deleteStmt->bind_param("ii", $id, $uid);
        $deleteStmt->execute();
        $deleteStmt->close();
        
        header("Location: download.php");
        exit;
    }
}

$list = [];
$manga_list = [];

if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT * FROM downloads WHERE user_id = ? ORDER BY id DESC");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $list[] = $row;
    }
    $stmt->close();

    // Mengambil daftar judul dari tabel tunggal `komik` untuk Autocomplete Datalist
    $manga_query = $conn->query("SELECT id, judul FROM komik ORDER BY judul ASC");
    if ($manga_query) {
        while ($m_row = $manga_query->fetch_assoc()) {
            $manga_list[] = $m_row;
        }
    }
}

include 'includes/header.php';
include 'includes/navbar.php';
?>

<style>
    :root {
        --bg-main: #0b0b0f;
        --bg-card: #13131a;
        --bg-input: #1c1c24;
        --accent: #7c3aed;
        --accent-glow: rgba(124, 58, 237, 0.4);
        --text-muted: #8b8b9f;
        --border-color: rgba(255, 255, 255, 0.06);
    }

    body { background-color: var(--bg-main); font-family: 'Inter', system-ui, -apple-system, sans-serif; }

    .download-container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; color: #fff; }
    .page-header { margin-bottom: 35px; border-left: 4px solid var(--accent); padding-left: 16px; }
    .page-header h1 { font-size: 30px; font-weight: 800; margin: 0 0 6px 0; color: #fff; letter-spacing: -0.5px; }
    .page-header p { font-size: 14px; color: var(--text-muted); margin: 0; }
    
    /* FORM & MODERN TABS COMPONENT */
    .download-form-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 30px; margin-bottom: 40px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); }
    .form-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 25px; border-bottom: 1px solid var(--border-color); padding-bottom: 16px; }
    .tab-btn { background: transparent; border: 1px solid transparent; color: var(--text-muted); font-size: 13px; font-weight: 600; padding: 10px 20px; cursor: pointer; border-radius: 8px; transition: all 0.25s ease; display: inline-flex; align-items: center; gap: 8px; }
    .tab-btn:hover { color: #fff; background: rgba(255,255,255,0.03); }
    .tab-btn.active { background: rgba(124, 58, 237, 0.12); color: #c084fc; border-color: rgba(124, 58, 237, 0.3); box-shadow: 0 0 15px rgba(124, 58, 237, 0.1); }
    
    .input-group { display: flex; flex-direction: column; gap: 20px; }
    .input-row { display: flex; flex-direction: column; gap: 20px; width: 100%; }
    
    .download-form input[type="text"], .download-form select { background: var(--bg-input); border: 1px solid var(--border-color); padding: 16px 20px; border-radius: 12px; color: #fff; font-size: 14px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); width: 100%; box-sizing: border-box; appearance: none; -webkit-appearance: none; }
    .download-form select { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%238b8b9f'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 20px center; background-size: 16px; padding-right: 48px; }
    .download-form input:focus, .download-form select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 4px var(--accent-glow); background-color: #22222c; }
    
    /* Search button specfic styles */
    .btn-search-manga { flex-shrink: 0; background: rgba(124, 58, 237, 0.15); border: 1px solid rgba(124, 58, 237, 0.4); color: #c084fc; font-weight: 600; padding: 0 24px; border-radius: 12px; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; }
    .btn-search-manga:hover { background: rgba(124, 58, 237, 0.3); color: #fff; box-shadow: 0 4px 12px rgba(124, 58, 237, 0.2); }

    /* VISUAL CHAPTER SELECTOR GRID */
    .chapter-section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #c084fc; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
    .chapter-selector-box { background: var(--bg-input); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; max-height: 280px; overflow-y: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; }
    
    .chapter-checkbox-item { position: relative; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); padding: 12px 10px; border-radius: 10px; cursor: pointer; transition: all 0.2s ease; font-size: 13px; font-weight: 600; text-align: center; color: var(--text-muted); user-select: none; }
    .chapter-checkbox-item:hover { background: rgba(255,255,255,0.05); color: #fff; border-color: rgba(255,255,255,0.15); }
    .chapter-checkbox-item input[type="checkbox"] { position: absolute; opacity: 0; cursor: pointer; height: 0; width: 0; }
    
    .chapter-checkbox-item.checked-active { background: rgba(124, 58, 237, 0.15); border-color: var(--accent); color: #e9d5ff; box-shadow: 0 4px 12px rgba(124, 58, 237, 0.15); }
    
    .counter-badge { background: var(--accent); color: white; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 800; }

    .download-form button[type="submit"] { background: var(--accent); color: #fff; border: none; padding: 16px 30px; border-radius: 12px; font-weight: 700; font-size: 15px; cursor: pointer; transition: all 0.25s ease; display: inline-flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 18px rgba(124, 58, 237, 0.3); width: 100%; margin-top: 5px; }
    .download-form button[type="submit"]:hover { background: #6d28d9; transform: translateY(-1px); box-shadow: 0 6px 24px rgba(124, 58, 237, 0.45); }
    .download-form button[type="submit"]:active { transform: translateY(1px); }
    
    /* ALERTS */
    .alert { padding: 16px 20px; border-radius: 12px; font-size: 14px; font-weight: 500; margin-bottom: 25px; display: flex; align-items: center; gap: 12px; }
    .alert-danger { background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.18); color: #f87171; }
    .alert-success { background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.18); color: #34d399; }

    /* GRID & CARDS PUSTAKA */
    .comic-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 24px; }
    .comic-card { background: var(--bg-card); border-radius: 16px; overflow: hidden; border: 1px solid var(--border-color); display: flex; flex-direction: column; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; }
    .comic-card:hover { transform: translateY(-6px); border-color: rgba(124, 58, 237, 0.4); box-shadow: 0 16px 36px rgba(0, 0, 0, 0.6); }
    .comic-thumb-wrap { position: relative; width: 100%; height: 250px; overflow: hidden; background: #09090d; display: flex; justify-content: center; align-items: center; }
    .comic-card img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
    .comic-card:hover img { transform: scale(1.05); }
    
    .placeholder-box { text-align: center; display: flex; flex-direction: column; align-items: center; gap: 8px; }
    .placeholder-pdf { color: #ef4444; }
    .placeholder-zip { color: #eab308; }
    
    .comic-card h3 { font-size: 14px; font-weight: 700; margin: 16px 16px 6px 16px; line-height: 1.4; color: #f3f4f6; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .comic-card .chap { font-size: 11px; color: #c084fc; font-weight: 700; margin: 0 16px 16px 16px; background: rgba(124, 58, 237, 0.12); width: fit-content; padding: 3px 10px; border-radius: 6px; max-width: 82%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; border: 1px solid rgba(124, 58, 237, 0.15); }
    
    .download-action { display: grid; grid-template-columns: 1fr auto; gap: 8px; padding: 0 16px 16px 16px; margin-top: auto; }
    .read-btn { background: rgba(124, 58, 237, 0.1); color: #c084fc; border: 1px solid rgba(124, 58, 237, 0.2); padding: 10px 12px; border-radius: 10px; font-size: 13px; font-weight: 600; text-decoration: none; text-align: center; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
    .read-btn:hover { background: var(--accent); color: #fff; box-shadow: 0 4px 12px var(--accent-glow); }
    .delete-btn { background: rgba(239, 68, 68, 0.05); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.15); width: 38px; height: 38px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.2s; }
    .delete-btn:hover { background: #ef4444; color: #fff; }

    .empty-box { grid-column: 1 / -1; text-align: center; padding: 80px 20px; background: var(--bg-card); border-radius: 16px; color: var(--text-muted); font-size: 14px; border: 1px dashed var(--border-color); }
    .login-warning-box { text-align: center; padding: 60px 30px; background: var(--bg-card); border-radius: 20px; border: 1px solid rgba(239, 68, 68, 0.1); max-width: 500px; margin: 40px auto; }
    .login-redirect-btn { background: var(--accent); color: #fff; padding: 14px 32px; border-radius: 12px; font-weight: 600; text-decoration: none; display: inline-block; box-shadow: 0 4px 14px var(--accent-glow); }

    @media(max-width: 1100px) { .comic-grid { grid-template-columns: repeat(4, 1fr); } }
    @media(max-width: 768px) { .comic-grid { grid-template-columns: repeat(2, 1fr); gap: 16px; } .comic-thumb-wrap { height: 200px; } }
</style>

<div class="download-container">

    <div class="page-header">
        <h1>Offline Library Engine v3.3</h1>
        <p>Ketik nama komik dan pilih maksimal 5 chapter per paket unduhan (Batch Pack).</p>
    </div>

    <?php if (isset($_SESSION['user_id'])) { ?>
        
        <?php if(!empty($error_message)) { echo "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> $error_message</div>"; } ?>
        <?php if(!empty($success_message)) { echo "<div class='alert alert-success'><i class='fas fa-check-circle'></i> $success_message</div>"; } ?>

        <div class="download-form-card">
            <div class="form-tabs">
                <button type="button" class="tab-btn active" onclick="switchSource('local', this)"><i class="fas fa-file-upload"></i> File Lokal</button>
                <button type="button" class="tab-btn" onclick="switchSource('remote', this)"><i class="fas fa-globe"></i> Fetch URL</button>
                <button type="button" class="tab-btn" onclick="switchSource('eugenverse', this)"><i class="fas fa-file-archive"></i> Batch Eugenverse</button>
            </div>

            <form method="POST" action="" enctype="multipart/form-data" class="download-form" id="mainDownloadForm">
                <input type="hidden" name="source_type" id="source_type" value="local">
                
                <div class="input-group">
                    <input type="text" name="judul" id="judul_input" placeholder="Masukkan judul pustaka penyimpanan baru..." required>
                    
                    <div class="input-row">
                        <div id="wrapper_local">
                            <input type="file" name="file_lokal" id="file_lokal" style="color:#8b8b9f; font-size:13px;" accept=".jpg,.jpeg,.png,.webp,.pdf">
                        </div>

                        <div id="wrapper_remote" style="display: none;">
                            <input type="text" name="url_remote" id="url_remote" placeholder="Tempel URL file tunggal gambar di sini...">
                        </div>

                        <div id="wrapper_eugenverse" style="display: none; flex-direction: column; gap: 16px;">
                            
                            <div style="display: flex; gap: 10px; width: 100%;">
                                <input type="text" id="manga_search_input" list="manga_datalist" placeholder="Ketik nama komik dari server Eugenverse..." style="flex: 1;" onkeypress="if(event.key === 'Enter') { event.preventDefault(); searchChapters(); }">
                                <datalist id="manga_datalist">
                                    <?php foreach ($manga_list as $manga) { ?>
                                        <option value="<?php echo htmlspecialchars($manga['judul']); ?>">
                                    <?php } ?>
                                </datalist>
                                <button type="button" class="btn-search-manga" onclick="searchChapters()">
                                    <i class="fas fa-search"></i> Cari Chapter
                                </button>
                            </div>

                            <div id="chapter_area_wrapper" style="display: none; flex-direction: column; gap: 8px;">
                                <div class="chapter-section-title">
                                    <span>PILIH CHAPTER MANGA</span>
                                    <span class="counter-badge" id="checked_count">0 / 5 Selected</span>
                                </div>

                                <div class="chapter-selector-box" id="chapter_container">
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="add">
                            <i class="fas fa-layer-group"></i> Eksekusi Unduhan
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="comic-grid">
            <?php if (count($list) > 0) { ?>
                <?php foreach ($list as $row) { 
                    $is_pdf = (strtolower($row['tipe']) === 'pdf');
                    $is_zip = (strtolower($row['tipe']) === 'zip');
                    $filePath = BASE_URL . "uploads/manual/" . $row['cover'];
                ?>
                    <div class="comic-card">
                        <div class="comic-thumb-wrap">
                            <?php if ($is_pdf) { ?>
                                <div class="placeholder-box placeholder-pdf">
                                    <i class="fas fa-file-pdf" style="font-size: 50px;"></i>
                                    <span style="font-size: 11px; font-weight:700; color:var(--text-muted);">DOCUMENT</span>
                                </div>
                            <?php } elseif ($is_zip) { ?>
                                <div class="placeholder-box placeholder-zip">
                                    <i class="fas fa-file-archive" style="font-size: 50px;"></i>
                                    <span style="font-size: 11px; font-weight:700; color:var(--text-muted);">ZIP BUNDLE</span>
                                </div>
                            <?php } else { ?>
                                <img src="<?php echo $filePath; ?>" alt="Cover" loading="lazy" onerror="this.src='<?php echo BASE_URL; ?>uploads/default-cover.jpg'">
                            <?php } ?>
                        </div>
                        
                        <h3><?php echo htmlspecialchars($row['judul']); ?></h3>
                        <div class="chap" title="<?php echo htmlspecialchars($row['chapters']); ?>"><?php echo htmlspecialchars($row['chapters']); ?></div>
                        
                        <div class="download-action">
                            <a href="reader.php?id=<?php echo $row['id']; ?>" class="read-btn">
                                <i class="fas fa-book-open"></i> Buka
                            </a>
                            <a href="download.php?hapus=<?php echo $row['id']; ?>" class="delete-btn" onclick="return confirm('Hapus konten dari disk?')" title="Hapus">
                                <i class="far fa-trash-alt"></i>
                            </a>
                        </div>
                    </div>
                <?php } ?>
            <?php } else { ?>
                <div class="empty-box">
                    <i class="fas fa-box-open" style="font-size: 32px; color: var(--accent); margin-bottom: 12px; display: block;"></i>
                    Pustaka offline kosong.
                </div>
            <?php } ?>
        </div>

    <?php } else { ?>
        <div class="login-warning-box">
            <i class="fas fa-lock" style="font-size: 32px; color: #ef4444; margin-bottom: 16px; display: block;"></i>
            <h3>Enkripsi Terkunci</h3>
            <p>Otentikasi diperlukan.</p>
            <a href="login.php" class="login-redirect-btn">Hubungkan Akun</a>
        </div>
    <?php } ?>

</div>

<?php include 'includes/footer.php'; ?>

<script>
function switchSource(type, element) {
    document.getElementById('source_type').value = type;
    
    document.getElementById('file_lokal').required = false;
    document.getElementById('url_remote').required = false;

    document.getElementById('wrapper_local').style.display = 'none';
    document.getElementById('wrapper_remote').style.display = 'none';
    document.getElementById('wrapper_eugenverse').style.display = 'none';
    
    if (type === 'local') {
        document.getElementById('wrapper_local').style.display = 'block';
        document.getElementById('file_lokal').required = true;
    } else if (type === 'remote') {
        document.getElementById('wrapper_remote').style.display = 'block';
        document.getElementById('url_remote').required = true;
    } else if (type === 'eugenverse') {
        document.getElementById('wrapper_eugenverse').style.display = 'flex';
    }

    const tabs = document.querySelectorAll('.tab-btn');
    tabs.forEach(tab => tab.classList.remove('active'));
    element.classList.add('active');
}

function searchChapters() {
    const searchInput = document.getElementById('manga_search_input');
    const titleValue = searchInput.value.trim();
    const container = document.getElementById('chapter_container');
    const areaWrapper = document.getElementById('chapter_area_wrapper');
    const counterBadge = document.getElementById('checked_count');
    
    if (!titleValue) {
        alert('Silakan ketik atau pilih nama komik terlebih dahulu!');
        searchInput.focus();
        return;
    }

    document.getElementById('judul_input').value = titleValue + ' [Batch Pack]';

    areaWrapper.style.display = 'flex';
    container.style.gridTemplateColumns = '1fr';
    container.innerHTML = '<div style="color: #c084fc; font-size: 13px; text-align: center; width:100%; grid-column: 1/-1;"><i class="fas fa-circle-notch fa-spin"></i> Mencari chapter...</div>';

   fetch(`download.php?action=get_chapters&komik_title=${encodeURIComponent(titleValue)}`)
        .then(response => response.json())
        .then(data => {
            console.log("=== DIAGNOSTIC SYSTEM LOG ===");
            console.log("Status SQL Sukses:", data.success);
            console.log("Log Analisis Objek:", data.debug);

            if (!data.success || data.chapters.length === 0) {
                let errorDetails = "Komik tidak ditemukan atau belum memiliki chapter di server.";
                
                if (data.debug) {
                    if (!data.debug.manga_found) {
                        errorDetails += `<br><span style="color:#ffa502; font-size:11px;">[DEBUG]: Judul "${titleValue}" tidak ada di tabel 'komik'.</span>`;
                    } else if (data.debug.manga_found && data.debug.chapters_count === 0) {
                        errorDetails += `<br><span style="color:#ffa502; font-size:11px;">[DEBUG]: Komik ketemu (${data.debug.matched_judul}, ID: ${data.debug.manga_id}), tetapi relasi data kolom 'manga_id' di tabel 'chapters' tidak cocok.</span>`;
                    }
                    if (data.debug.sql_error && !data.debug.sql_error.includes('tidak cocok')) {
                        errorDetails += `<br><span style="color:#ff4757; font-size:11px;">[SQL ERROR]: ${data.debug.sql_error}</span>`;
                    }
                }

                container.innerHTML = `<div style="color: #ef4444; font-size: 13px; text-align: center; width:100%; grid-column: 1/-1; line-height:1.6; padding:10px;">${errorDetails}</div>`;
                counterBadge.innerText = '0 / 5 Selected';
                return;
            }

            container.innerHTML = '';
            container.removeAttribute('style'); 
            counterBadge.innerText = '0 / 5 Selected';

            data.chapters.forEach(ch => {
                const labelCard = document.createElement('label');
                labelCard.className = 'chapter-checkbox-item';
                labelCard.innerHTML = `
                    <input type="checkbox" name="eugen_chapter_ids[]" value="${ch.id}" onchange="handleCheckboxChange(this)">
                    ${ch.label}
                `;
                container.appendChild(labelCard);
            });
        })
        .catch(err => {
            console.error("Fetch Error:", err);
            container.innerHTML = '<div style="color: #ef4444; font-size: 13px; text-align: center; width:100%; grid-column: 1/-1;">Terjadi gangguan koneksi API internal server.</div>';
        });
}

function handleCheckboxChange(checkbox) {
    const checkedBoxes = document.querySelectorAll('input[name="eugen_chapter_ids[]"]:checked');
    const counterBadge = document.getElementById('checked_count');
    
    if (checkedBoxes.length > 5) {
        checkbox.checked = false;
        alert('Batas maksimal pengunduhan simultan adalah 5 chapter per siklus!');
        return;
    }
    
    if (checkbox.checked) {
        checkbox.parentElement.classList.add('checked-active');
    } else {
        checkbox.parentElement.classList.remove('checked-active');
    }
    
    counterBadge.innerText = `${checkedBoxes.length} / 5 Selected`;
}
</script>