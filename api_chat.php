<?php
// api_chat.php
header('Content-Type: application/json');
include 'config/koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$method = $_SERVER['REQUEST_METHOD'];

// ==========================================
// AKSES GET: Mengambil Pesan Baru (Real-time Sync)
// ==========================================
if ($method === 'GET') {
    $room_id = isset($_GET['room']) ? intval($_GET['room']) : 1;
    $last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

    // KUNCI SKALABILITAS: Hanya ambil ID yang lebih besar dari pesan terakhir di browser user
    // Menggunakan OOP style agar selaras dengan koneksi modern kita
    $stmt = $conn->prepare("
        SELECT cm.id, cm.user_id, cm.pesan, cm.created_at, u.username 
        FROM chat_messages cm
        JOIN users u ON u.id = cm.user_id
        WHERE cm.room_id = ? AND cm.id > ?
        ORDER BY cm.id ASC 
        LIMIT 50
    ");
    $stmt->bind_param("ii", $room_id, $last_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id'         => intval($row['id']),
            'user_id'    => intval($row['user_id']),
            'username'   => htmlspecialchars($row['username']),
            'pesan'      => nl2br(htmlspecialchars($row['pesan'])),
            'time'       => date('H:i', strtotime($row['created_at'])),
            'is_mine'    => (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $row['user_id'])
        ];
    }
    
    echo json_encode(['success' => true, 'messages' => $messages]);
    $stmt->close();
    exit;
}

// ==========================================
// AKSES POST: Mengirim Pesan Tanpa Reload
// ==========================================
if ($method === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    // Membaca data input JSON dari JavaScript Fetch
    $input = json_get_object();
    $room_id = isset($input->room_id) ? intval($input->room_id) : 1;
    $pesan   = isset($input->message) ? trim($input->message) : '';

    if ($pesan !== '') {
        $user_id = $_SESSION['user_id'];
        
        $stmt = $conn->prepare("INSERT INTO chat_messages (room_id, user_id, pesan, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $room_id, $user_id, $pesan);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Pesan terkirim']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan pesan']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Pesan kosong']);
    }
    exit;
}

// Helper untuk membaca format JSON RAW input
function json_get_object() {
    return json_decode(file_get_contents('php://input'));
}
?>