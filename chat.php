<?php
// chat.php
include 'config/koneksi.php';
include 'includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$room_id = isset($_GET['room']) ? intval($_GET['room']) : 1;

// Ambil info ruang obrolan (OOP Style)
$roomStmt = $conn->prepare("SELECT nama_room FROM chat_rooms WHERE id = ?");
$roomStmt->bind_param("i", $room_id);
$roomStmt->execute();
$roomResult = $roomStmt->get_result();
$activeRoomData = $roomResult->fetch_assoc();
$currentRoomName = $activeRoomData ? $activeRoomData['nama_room'] : 'General Chat';
$roomStmt->close();

// Ambil semua daftar room untuk sidebar
$rooms = $conn->query("SELECT * FROM chat_rooms ORDER BY nama_room ASC");

include 'includes/header.php';
include 'includes/navbar.php';
?>

<style>
    .chat-container { max-width: 1200px; margin: 0 auto; padding: 30px 20px; background: #07070a; color: #fff; }
    .chat-layout { display: grid; grid-template-columns: 280px 1fr; background: #111116; border: 1px solid rgba(255, 255, 255, 0.03); border-radius: 16px; min-height: 650px; max-height: 650px; overflow: hidden; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3); }
    .chat-sidebar { background: #14141c; border-right: 1px solid rgba(255, 255, 255, 0.03); padding: 24px 16px; display: flex; flex-direction: column; gap: 8px; overflow-y: auto; }
    .chat-sidebar h3 { font-size: 12px; text-transform: uppercase; color: #6b7280; letter-spacing: 1px; font-weight: 700; margin: 0 0 12px 8px; }
    .room-link { display: flex; align-items: center; gap: 10px; padding: 12px 14px; color: #9ca3af; text-decoration: none; font-size: 14px; font-weight: 500; border-radius: 10px; transition: all 0.2s ease; }
    .room-link::before { content: '#'; color: #4b5563; font-weight: 700; font-size: 16px; }
    .room-link:hover { background: rgba(255, 255, 255, 0.03); color: #f3f4f6; }
    .room-link.active-room { background: rgba(124, 58, 237, 0.15); color: #a78bfa; font-weight: 600; }
    .room-link.active-room::before { color: #a78bfa; }
    .chat-wrapper { display: flex; flex-direction: column; background: #111116; height: 100%; overflow: hidden; }
    .chat-top { padding: 20px 24px; border-bottom: 1px solid rgba(255, 255, 255, 0.03); background: #121218; }
    .chat-top h2 { font-size: 18px; font-weight: 700; margin: 0 0 4px 0; color: #fff; }
    .chat-top p { font-size: 12px; color: #6b7280; margin: 0; }
    .chat-box { flex: 1; overflow-y: auto; padding: 24px; display: flex; flex-direction: column; gap: 16px; background: #0f0f14; }
    .chat-message { display: flex; flex-direction: column; max-width: 65%; width: fit-content; }
    .chat-user { font-size: 11px; font-weight: 700; color: #9ca3af; margin-bottom: 4px; margin-left: 4px; }
    .chat-text { padding: 12px 16px; border-radius: 14px; font-size: 14px; line-height: 1.5; word-break: break-word; }
    .chat-time { font-size: 10px; color: #4b5563; margin-top: 4px; align-self: flex-end; padding: 0 4px; }
    .chat-message.other { align-self: flex-start; }
    .chat-message.other .chat-text { background: #181822; color: #e5e7eb; border-top-left-radius: 4px; border: 1px solid rgba(255, 255, 255, 0.02); }
    .chat-message.mine { align-self: flex-end; }
    .chat-message.mine .chat-user { align-self: flex-end; margin-right: 4px; color: #a78bfa; }
    .chat-message.mine .chat-text { background: #7c3aed; color: #fff; border-top-right-radius: 4px; box-shadow: 0 4px 12px rgba(124, 58, 237, 0.2); }
    .chat-message.mine .chat-time { color: rgba(255, 255, 255, 0.4); }
    .chat-input-wrapper { padding: 16px 24px; background: #121218; border-top: 1px solid rgba(255, 255, 255, 0.03); }
    .chat-input { display: flex; gap: 12px; width: 100%; }
    .chat-input input { flex: 1; background: #181822; border: 1px solid rgba(255, 255, 255, 0.08); padding: 14px 18px; border-radius: 10px; color: #fff; font-size: 14px; outline: none; transition: all 0.2s ease; }
    .chat-input input:focus { border-color: #7c3aed; background: #1b1b26; box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.15); }
    .chat-input button { background: #7c3aed; color: #fff; border: none; padding: 0 24px; border-radius: 10px; font-weight: 600; font-size: 14px; cursor: pointer; transition: background 0.2s ease, transform 0.2s ease; }
    .chat-input button:hover { background: #6d28d9; transform: translateY(-1px); }
    .empty-chat { text-align: center; margin: auto; color: #4b5563; font-size: 13px; line-height: 1.6; }
    .login-warning-chat { text-align: center; padding: 14px; background: rgba(239, 68, 68, 0.05); border: 1px dashed rgba(239, 68, 68, 0.15); border-radius: 10px; color: #f87171; font-size: 13px; font-weight: 500; }
    .login-warning-chat a { color: #f3f4f6; text-decoration: underline; font-weight: 600; }

    @media(max-width: 768px) {
        .chat-layout { grid-template-columns: 1fr; min-height: 550px; max-height: 550px; }
        .chat-sidebar { display: none; }
        .chat-message { max-width: 85%; }
    }
</style>

<div class="chat-container">
    <div class="chat-layout">
        
        <div class="chat-sidebar">
            <h3>Channels</h3>
            <?php while ($room = $rooms->fetch_assoc()) { ?>
                <a href="chat.php?room=<?php echo $room['id']; ?>" class="room-link <?php echo ($room_id == $room['id']) ? 'active-room' : ''; ?>">
                    <?php echo htmlspecialchars($room['nama_room']); ?>
                </a>
            <?php } ?>
        </div>

        <div class="chat-wrapper">
            <div class="chat-top">
                <h2># <?php echo htmlspecialchars($currentRoomName); ?></h2>
                <p>Tempat kumpul komunitas, debat chapter terhangat, dan berbagi teori konspirasi.</p>
            </div>

            <div class="chat-box" id="chatBox">
                <div class="empty-chat" id="chatLoader">
                    <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #7c3aed; margin-bottom: 10px; display: block;"></i>
                    Menyinkronkan dek obrolan...
                </div>
            </div>

            <div class="chat-input-wrapper">
                <?php if (isset($_SESSION['user_id'])) { ?>
                    <form id="chatForm" class="chat-input">
                        <input id="messageInput" type="text" placeholder="Tulis pesan kamu di #<?php echo htmlspecialchars($currentRoomName); ?>..." maxlength="300" autocomplete="off" required>
                        <button type="submit">
                            <i class="fas fa-paper-plane"></i> Kirim
                        </button>
                    </form>
                <?php } else { ?>
                    <div class="login-warning-chat">
                        Kamu harus <a href="login.php">Masuk Akun</a> terlebih dahulu untuk mengirim pesan ke dalam ruang diskusi ini.
                    </div>
                <?php } ?>
            </div>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
const roomId = <?php echo $room_id; ?>;
let lastMessageId = 0; 
let isFirstLoad = true;

const chatBox = document.getElementById("chatBox");
const chatForm = document.getElementById("chatForm");
const messageInput = document.getElementById("messageInput");

// 1. FUNGSI UTAMA: Ambil data baru secara berkala
async function fetchNewMessages() {
    try {
        const response = await fetch(`api_chat.php?room=${roomId}&last_id=${lastMessageId}`);
        const data = await response.json();

        if (data.success && data.messages.length > 0) {
            // Hapus animasi loading saat pesan pertama kali masuk
            if (isFirstLoad) {
                chatBox.innerHTML = '';
                isFirstLoad = false;
            }

            data.messages.forEach(msg => {
                // Konfigurasi kelas CSS dinamis (Milik Sendiri vs Orang Lain)
                const bubbleClass = msg.is_mine ? 'mine' : 'other';
                
                const htmlBubble = `
                    <div class="chat-message ${bubbleClass}" data-id="${msg.id}">
                        <div class="chat-user">${msg.username}</div>
                        <div class="chat-text">${msg.pesan}</div>
                        <div class="chat-time">${msg.time}</div>
                    </div>
                `;
                
                // Tambahkan elemen baru ke bagian bawah kontainer chat
                chatBox.insertAdjacentHTML('beforeend', htmlBubble);
                
                // Perbarui tracker ID pesan terakhir agar server tidak mengirim ulang pesan lama
                if (msg.id > lastMessageId) {
                    lastMessageId = msg.id;
                }
            });

            // Gulirkan scroll bar otomatis ke titik terbawah
            chatBox.scrollTop = chatBox.scrollHeight;
        } else if (isFirstLoad && data.messages.length === 0) {
            // Jika benar-benar kosong sejak awal
            chatBox.innerHTML = `
                <div class="empty-chat">
                    <i class="far fa-comments" style="font-size: 26px; color: #4b5563; display: block; margin-bottom: 10px;"></i>
                    Belum ada pesan di room ini.<br>Sunyi seperti niat belajar saat weekend tiba.
                </div>`;
            isFirstLoad = false;
        }
    } catch (error) {
        console.error("Sinkronisasi chat terputus:", error);
    }
}

// 2. FUNGSI KONTROL: Mengirim pesan secara Asynchronous
if (chatForm) {
    chatForm.addEventListener('submit', async function(e) {
        e.preventDefault(); // Cegah halaman refresh!
        
        const text = messageInput.value.trim();
        if (!text) return;

        // Kosongkan inputan seketika demi kenyamanan user (Instant Feel UI)
        messageInput.value = '';

        try {
            const response = await fetch('api_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ room_id: roomId, message: text })
            });
            
            const result = await response.json();
            if (result.success) {
                // Ambil update sesaat setelah mengirim pesan agar gelembung langsung muncul
                fetchNewMessages();
            } else {
                alert("Gagal mengirim pesan: " + result.message);
            }
        } catch (error) {
            console.error("Gagal terhubung ke API:", error);
        }
    });
}

// 3. ENGINE SCHEDULER: Eksekusi sinkronisasi data
fetchNewMessages(); // Ambil data awal saat halaman siap

// Cek pesan baru setiap 2 detik (Aman untuk ribuan user karena beban payload SQL sangat kecil)
setInterval(fetchNewMessages, 2000); 
</script>