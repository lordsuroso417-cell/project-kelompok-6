<?php
// Pastikan koneksi sesuai dengan config kamu
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "db_turnamen";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$message = "";

if($_SERVER["REQUEST_METHOD"] == "POST") {
    if(!isset($_POST["nama"]) || !isset($_POST["asal"])){
        $message = "<div class='alert alert-warning border-0'>Data tidak lengkap</div>";
    } else {
        $nama = $conn->real_escape_string($_POST["nama"]);
        $asal = $conn->real_escape_string($_POST["asal"]);
        $sql = "INSERT INTO pro_teams (nama_tim, asal_tim) VALUES ('$nama', '$asal')";
        
        if ($conn->query($sql) === TRUE) {
          $message = "<div class='alert alert-success border-0 bg-success text-white'>Data tim berhasil ditambahkan!</div>";
        } else {
          $message = "<div class='alert alert-danger border-0 bg-danger text-white'>Error: " . $conn->error . "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Tim - Panel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Mengikuti Tema Dark Reader sebelumnya */
        body {
            background-color: #0b0b0c;
            color: #e0e0e0;
            font-family: 'Segoe UI', sans-serif;
        }

        /* Sidebar Style */
        .sidebar {
            min-height: 100vh;
            background-color: #1a1a1d;
            border-right: 1px solid #333;
            padding-top: 1rem;
        }
        .sidebar-brand {
            font-size: 1.25rem;
            font-weight: bold;
            color: #8c7ae6; /* Warna ungu aksen */
            text-align: center;
            padding: 1.5rem 0;
            border-bottom: 1px solid #333;
            margin-bottom: 1rem;
        }
        .sidebar a {
            color: #a0a0a0;
            text-decoration: none;
            padding: 12px 25px;
            display: block;
            transition: 0.3s;
            font-size: 15px;
        }
        .sidebar a:hover, .sidebar a.active {
            color: #fff;
            background-color: #26262a;
            border-left: 4px solid #8c7ae6;
        }

        /* Main Content */
        .main-content {
            padding: 2.5rem;
        }
        
        /* Card Styling */
        .card {
            background-color: #1a1a1d;
            border: 1px solid #333;
            border-radius: 12px;
            color: #fff;
        }
        .card-header {
            background-color: #26262a !important;
            border-bottom: 1px solid #333;
            font-weight: bold;
            color: #8c7ae6 !important;
        }

        /* Form Styling */
        .form-label {
            color: #a0a0a0;
        }
        .input-group-text {
            background-color: #26262a;
            border: 1px solid #333;
            color: #8c7ae6;
        }
        .form-control {
            background-color: #0b0b0c;
            border: 1px solid #333;
            color: #fff;
        }
        .form-control:focus {
            background-color: #0b0b0c;
            border-color: #8c7ae6;
            color: #fff;
            box-shadow: none;
        }
        
        /* Buttons */
        .btn-primary {
            background-color: #8c7ae6;
            border: none;
        }
        .btn-primary:hover {
            background-color: #7158e2;
        }
        .btn-light {
            background-color: #26262a;
            border: 1px solid #333;
            color: #fff;
        }
        .btn-light:hover {
            background-color: #3b3b40;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="sidebar-brand">
                    <i class="fas fa-gamepad me-2"></i>E-SPORT
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
                    <a class="nav-link active" href="datatim.php"><i class="fas fa-users me-2"></i> Data Tim</a>
                    <a class="nav-link" href="tiket.html"><i class="fas fa-ticket-alt me-2"></i> Tiket</a>
                    <a class="nav-link text-danger mt-5" href="login.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
                </nav>
            </div>

            <div class="col-md-9 col-lg-10 main-content">
                <div class="mb-4">
                    <h2 class="h3 mb-0">Manajemen Data Tim</h2>
                    <p class="text-muted">Tambahkan informasi tim profesional turnamen Anda.</p>
                </div>

                <?php if(!empty($message)) echo $message; ?>

                <div class="card shadow">
                    <div class="card-header py-3">
                        <i class="fas fa-plus-circle me-2"></i>Tambah Tim Baru
                    </div>
                    <div class="card-body p-4">
                        <form action="datatim.php" method="post">
                            <div class="mb-3">
                                <label for="nama" class="form-label">Nama Tim</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-shield-alt"></i></span>
                                    <input type="text" class="form-control" id="nama" name="nama" placeholder="Contoh: RRQ, ONIC, dll" required>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="asal" class="form-label">Asal Tim (Negara/Kota)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                    <input type="text" class="form-control" id="asal" name="asal" placeholder="Contoh: Indonesia" required>
                                </div>
                            </div>
                            <div class="pt-2">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="fas fa-save me-2"></i>Simpan Tim
                                </button>
                                <button type="reset" class="btn btn-light px-4 ms-2">
                                    <i class="fas fa-undo me-2"></i>Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>