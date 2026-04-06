<?php
/**
 * Aylinak Chat - Final Optimized Edition
 */

// 1. Security Session Settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();

// 2. Base Settings
$password = "1234567891011"; 
$upload_dir = 'uploads/';
$allowed_rooms = ['483754', '954285', '178462', '046523', '297501', 'aR60Hq']; 
$max_file_size = 15 * 1024 * 1024;
$allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'mp3', 'webm', 'ogg', 'pdf', 'zip', 'txt'];

$current_room = $_SESSION['room_code'] ?? null;
$db_file = $current_room ? "chat_data_room_{$current_room}.php" : null;

if ($db_file && !file_exists($db_file)) {
    file_put_contents($db_file, "<?php die(); ?>\n[]");
}

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

function secure_read($file) {
    if(!$file || !file_exists($file)) return [];
    $content = file_get_contents($file);
    $json = str_replace("<?php die(); ?>\n", "", $content);
    return json_decode($json, true) ?: [];
}

function secure_write($file, $data) {
    if(!$file) return;
    $content = "<?php die(); ?>\n" . json_encode($data, JSON_UNESCAPED_UNICODE);
    file_put_contents($file, $content);
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ?");
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'auth_room') {
    $room_input = $_POST['room_code'] ?? '';
    $pass_input = $_POST['login_pass'] ?? '';
    
    if ($pass_input === $password && in_array($room_input, $allowed_rooms)) {
        $_SESSION['chat_auth'] = true;
        $_SESSION['room_code'] = $room_input;
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'کد اتاق یا رمز عبور اشتباه است']);
    }
    exit;
}

$is_auth = false;
if (isset($_SESSION['chat_auth']) && isset($_SESSION['user_agent']) && isset($_SESSION['room_code'])) {
    if ($_SESSION['user_agent'] === ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
        $is_auth = true;
    }
}

if ($is_auth && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $db = secure_read($db_file);
    $action = $_POST['action'];

    if ($action === 'send') {
        $msg_id = bin2hex(random_bytes(8)); 
        $file_path = null;
        $type = 'text';

        if (!empty($_FILES['attachment']['name'])) {
            $file = $_FILES['attachment'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed_exts)) {
                $filename = $msg_id . '.' . $ext;
                if(move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                    $file_path = $upload_dir . $filename;
                    $type = isset($_POST['is_voice']) ? 'voice' : 'file';
                }
            }
        }

        $text = isset($_POST['text']) ? mb_substr(trim($_POST['text']), 0, 3000) : '';
        $user = isset($_POST['user_name']) ? mb_substr(trim($_POST['user_name']), 0, 30) : 'User';

        if (!empty($text) || $file_path) {
            $db[] = [
                'id' => $msg_id,
                'uid' => htmlspecialchars($_POST['my_uid'] ?? 'guest'),
                'user' => htmlspecialchars($user),
                'text' => htmlspecialchars($text),
                'reply_id' => !empty($_POST['reply_id']) ? htmlspecialchars($_POST['reply_id']) : null,
                'file' => $file_path,
                'type' => $type,
                'time' => date('H:i'),
                'edited' => false
            ];
            if(count($db) > 300) array_shift($db);
            secure_write($db_file, $db);
        }
    } elseif ($action === 'delete_all') {
        $msg_id = $_POST['msg_id'];
        $db = array_filter($db, function($m) use ($msg_id) { return $m['id'] !== $msg_id; });
        secure_write($db_file, array_values($db));
    } elseif ($action === 'edit') {
        $msg_id = $_POST['msg_id'];
        $new_text = mb_substr(trim($_POST['text']), 0, 3000);
        foreach ($db as &$m) {
            if ($m['id'] === $msg_id) {
                $m['text'] = htmlspecialchars($new_text);
                $m['edited'] = true;
                break;
            }
        }
        secure_write($db_file, $db);
    }
    exit;
}

if ($is_auth && isset($_GET['fetch'])) {
    header('Content-Type: application/json');
    echo json_encode(secure_read($db_file));
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Aylinak Messenger</title>
    <link href="https://static.arvancloud.ir/fonts/vazir/vazir.css" rel="stylesheet" type="text/css" />
    <style>
        :root {
            --bg: #e7ebf0; --app-bg: #ffffff; --text: #333;
            --header: #ffffff; --accent: #3390ec;
            --in-msg: #ffffff; --out-msg: #effdde;
            --font-size: 15px; 
        }
        [data-theme="dark"] {
            --bg: #0e1621; --app-bg: #17212b; --text: #f5f5f5;
            --header: #17212b; --accent: #5288c1;
            --in-msg: #182533; --out-msg: #2b5278;
        }
        body, html { 
            margin: 0; padding: 0; height: 100dvh; 
            font-family: 'Vazir', -apple-system, sans-serif;
            background: var(--bg); color: var(--text); overflow: hidden;
            direction: ltr; 
        }
        .login-screen { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; background: var(--bg); }
        .login-card { background: var(--app-bg); padding: 35px 25px; border-radius: 25px; width: 85%; max-width: 340px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .login-card input { width: 100%; padding: 14px; margin: 8px 0; border: 1px solid #ddd; border-radius: 12px; outline: none; box-sizing: border-box; font-size: 16px; text-align: center; }
        .login-card button { background: var(--accent); color: #fff; border: none; width: 100%; padding: 14px; border-radius: 12px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        
        .wrapper { display: flex; flex-direction: column; height: 100dvh; max-width: 600px; margin: 0 auto; background: var(--app-bg); position: relative; }
        .header { height: 60px; background: var(--header); display: flex; align-items: center; padding: 0 15px; border-bottom: 1px solid rgba(0,0,0,0.05); z-index: 50; justify-content: space-between; }
        .header-title { font-weight: bold; font-size: 18px; color: var(--accent); }
        
        #chat-window { flex: 1; overflow-y: auto; padding: 15px; display: flex; flex-direction: column; gap: 8px; background: var(--bg); position: relative; }
        
        .msg-row { position: relative; width: 100%; display: flex; flex-direction: column; touch-action: pan-y; }
        .msg-container { display: flex; flex-direction: column; max-width: 85%; margin-bottom: 2px; position: relative; z-index: 2; transition: transform 0.1s ease-out; }
        .msg-container.out { align-self: flex-end; } 
        .msg-container.in { align-self: flex-start; } 

        .swipe-indicator { 
            position: absolute; top: 50%; transform: translateY(-50%); 
            opacity: 0; color: var(--accent); font-size: 20px; pointer-events: none;
            transition: opacity 0.1s;
        }
        .swipe-indicator.right-icon { right: 10px; }
        .swipe-indicator.left-icon { left: 10px; }

        .bubble { 
            padding: 8px 14px; border-radius: 18px; 
            font-size: var(--font-size); line-height: 1.5; 
            box-shadow: 0 1px 2px rgba(0,0,0,0.1); word-wrap: break-word; 
            text-align: right; direction: rtl; cursor: pointer;
        }
        .out .bubble { background: var(--out-msg); border-bottom-right-radius: 4px; }
        .in .bubble { background: var(--in-msg); border-bottom-left-radius: 4px; }
        
        .sidebar { position: absolute; left: -100%; top: 0; width: 280px; height: 100%; background: var(--app-bg); z-index: 100; transition: 0.3s; padding: 20px; box-sizing: border-box; display: flex; flex-direction: column; overflow-y: auto; }
        .sidebar.active { left: 0; box-shadow: 10px 0 30px rgba(0,0,0,0.1); }
        .overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 99; display: none; }
        .overlay.active { display: block; }

        .setting-item { margin-bottom: 22px; }
        .setting-label { display: block; margin-bottom: 8px; font-weight: bold; font-size: 14px; opacity: 0.8; }

        .footer { padding: 8px 12px; background: var(--header); display: flex; flex-direction: column; gap: 5px; border-top: 1px solid rgba(0,0,0,0.05); }
        .footer-main { display: flex; align-items: center; gap: 8px; }
        .input-box { flex: 1; background: var(--bg); border-radius: 22px; padding: 2px 15px; display: flex; }
        #msg-input { border: none; background: transparent; color: var(--text); padding: 10px 0; outline: none; flex: 1; font-size: 16px; direction: rtl; text-align: right; }
        .btn-icon { cursor: pointer; fill: #707579; display: flex; align-items: center; justify-content: center; min-width: 40px; }
        
        #reply-bar { background: rgba(0,0,0,0.03); padding: 5px 15px; border-radius: 10px; display: none; align-items: center; justify-content: space-between; direction: rtl; font-size: 12px; border-right: 3px solid var(--accent); }
        #edit-bar { background: rgba(255, 235, 59, 0.2); padding: 5px 15px; border-radius: 10px; display: none; align-items: center; justify-content: space-between; direction: rtl; font-size: 12px; border-right: 3px solid #fbc02d; }

        .msg-menu { position: fixed; bottom: -100%; left: 0; width: 100%; background: var(--app-bg); z-index: 1000; transition: 0.3s; border-radius: 20px 20px 0 0; padding: 15px 0; box-shadow: 0 -5px 20px rgba(0,0,0,0.2); }
        .msg-menu.active { bottom: 0; }
        .menu-item { padding: 15px 25px; display: flex; align-items: center; gap: 15px; cursor: pointer; direction: rtl; font-size: 16px; }
        .menu-item:active { background: rgba(0,0,0,0.05); }
        .menu-item.danger { color: #f44336; }

        .btn-opt { flex: 1; padding: 8px; border-radius: 8px; border: 1px solid #ddd; background: transparent; color: var(--text); cursor: pointer; font-family: inherit; font-size: 12px; }
        .btn-opt.active { background: var(--accent); color: #fff; border-color: var(--accent); }
        .room-item { display: flex; align-items: center; padding: 10px; border-radius: 10px; margin-bottom: 5px; cursor: pointer; background: rgba(0,0,0,0.03); font-size: 14px; }
        .room-item.active { background: rgba(51, 144, 236, 0.15); color: var(--accent); font-weight: bold; }
    </style>
</head>
<body>

<?php if (!$is_auth): ?>
    <div class="login-screen">
        <form class="login-card" id="main-login-form">
            <div style="font-size: 50px; margin-bottom: 10px;">🔐</div>
            <h2 style="margin:0">Aylinak Messenger</h2>
            <p style="font-size: 14px; color: #777; margin-bottom: 15px;">Enter room code and password</p>
            <input type="text" id="login_room" name="room_code" placeholder="Room Code" maxlength="6" required>
            <input type="password" id="login_pass" name="login_pass" placeholder="Password" required>
            <div id="login-error" style="color: #f44336; font-size: 13px; margin-top: 10px; display:none; background: #ffebee; padding: 8px; border-radius: 8px;"></div>
            <button type="submit" id="login-btn">Join Chat</button>
        </form>
    </div>
<?php else: ?>
    <div class="overlay" id="overlay" onclick="closeAllModals()"></div>
    
    <div class="msg-menu" id="msg-menu">
        <div class="menu-item" onclick="startEdit()">✏️ ویرایش پیام</div>
        <div class="menu-item" onclick="deleteMessage('me')">🗑️ حذف برای من</div>
        <div class="menu-item danger" id="menu-del-all" onclick="deleteMessage('all')">🗑️ حذف برای همه</div>
    </div>

    <div class="sidebar" id="sidebar">
        <h3 style="margin-top: 0; color: var(--accent);">Settings</h3>
        <div class="setting-item">
            <span class="setting-label">Theme</span>
            <div class="theme-btns" style="display:flex; gap:5px">
                <button class="btn-opt" id="t-light" onclick="setTheme('light')">☀️ Light</button>
                <button class="btn-opt" id="t-dark" onclick="setTheme('dark')">🌙 Dark</button>
            </div>
        </div>
        <div class="setting-item">
            <span class="setting-label">Text Size</span>
            <div class="size-btns" style="display:flex; gap:5px">
                <button class="btn-opt" id="fz-13" onclick="setFontSize('13px')">Small</button>
                <button class="btn-opt" id="fz-15" onclick="setFontSize('15px')">Medium</button>
                <button class="btn-opt" id="fz-18" onclick="setFontSize('18px')">Large</button>
            </div>
        </div>
        <div class="setting-item">
            <span class="setting-label">Display Name</span>
            <input type="text" id="set-name" onchange="saveProfile()" style="width:100%; padding:10px; border-radius:8px; border:1px solid #ddd; box-sizing: border-box; direction: rtl;">
        </div>
        <div class="setting-item" style="flex:1">
            <span class="setting-label">Switch Room</span>
            <div id="rooms-list"></div>
            <input type="text" id="add-room-input" placeholder="6-digit code" maxlength="6" style="width:100%; padding:10px; border-radius:8px; border:1px solid #ddd; margin-top:10px; box-sizing: border-box;">
            <button onclick="addNewRoom()" style="width:100%; padding:10px; border-radius:8px; border:none; background:var(--accent); color:#fff; font-weight:bold; margin-top:5px; cursor:pointer;">+ Add Room</button>
        </div>
        <div style="text-align: center;"><a href="?logout" style="color:#f44336; text-decoration:none; font-size:14px; font-weight:bold;">Logout</a></div>
    </div>

    <div class="wrapper">
        <div class="header">
            <div class="btn-icon" onclick="toggleMenu()"><svg width="24" height="24" viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg></div>
            <div class="header-title">Aylinak Messenger</div>
            <div style="width:40px"></div>
        </div>
        <div id="chat-window"></div>
        <div class="footer">
            <div id="reply-bar"><div id="reply-text">Replying...</div><div class="btn-icon" onclick="cancelReply()"><svg width="18" height="18" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg></div></div>
            <div id="edit-bar"><div id="edit-text">Editing...</div><div class="btn-icon" onclick="cancelEdit()"><svg width="18" height="18" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg></div></div>
            <div class="footer-main">
                <div class="btn-icon" onclick="document.getElementById('file-in').click()"><svg width="26" height="26" viewBox="0 0 24 24"><path d="M16.5 6l-1.41 1.41L18.17 10H8c-1.1 0-2 .9-2 2v6h2v-6h10.17l-3.09 3.09L16.5 18l5.5-5.5L16.5 6z"/></svg></div>
                <input type="file" id="file-in" style="display:none" onchange="uploadFile()">
                <div class="input-box"><input type="text" id="msg-input" placeholder="Message..." autocomplete="off"></div>
                <div id="voice-btn" class="btn-icon" onmousedown="startRec()" onmouseup="stopRec()" ontouchstart="startRec()" ontouchend="stopRec()"><svg width="26" height="26" viewBox="0 0 24 24"><path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/><path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/></svg></div>
                <div id="send-btn" class="btn-icon" style="display:none" onclick="handleSend()"><svg width="28" height="28" viewBox="0 0 24 24" fill="#3390ec"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg></div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    const currentRoom = "<?php echo $_SESSION['room_code'] ?? ''; ?>";
    const passwordGlobal = "1234567891011";
    let chatWin, input, MY_UID, currentReplyId = null, currentEditId = null, lastCount = 0, recorder, chunks = [], selectedMsg = null;

    window.onload = () => {
        const loginForm = document.getElementById('main-login-form');
        if (loginForm) {
            loginForm.onsubmit = async (e) => {
                e.preventDefault();
                const btn = document.getElementById('login-btn');
                const errDiv = document.getElementById('login-error');
                btn.disabled = true; btn.innerText = "Connecting...";
                errDiv.style.display = 'none';

                const fd = new FormData(loginForm);
                fd.append('action', 'auth_room');
                const res = await fetch('', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.status === 'success') {
                    let rooms = JSON.parse(localStorage.getItem('my_rooms') || '[]');
                    const code = document.getElementById('login_room').value;
                    if(!rooms.includes(code)) rooms.push(code);
                    localStorage.setItem('my_rooms', JSON.stringify(rooms));
                    location.reload();
                } else {
                    errDiv.innerText = data.msg;
                    errDiv.style.display = 'block';
                    btn.disabled = false; btn.innerText = "Join Chat";
                }
            };
        }

        chatWin = document.getElementById('chat-window');
        input = document.getElementById('msg-input');
        if(chatWin) {
            if(!localStorage.getItem('uid')) localStorage.setItem('uid', 'u'+Math.random().toString(36).substr(2,9));
            MY_UID = localStorage.getItem('uid');
            document.getElementById('set-name').value = localStorage.getItem('name') || 'Guest';
            input.oninput = () => {
                const val = input.value.trim() !== "";
                document.getElementById('send-btn').style.display = val ? 'flex' : 'none';
                document.getElementById('voice-btn').style.display = val ? 'none' : 'flex';
            };
            input.addEventListener('keydown', e => { if(e.key === 'Enter') { e.preventDefault(); handleSend(); } });
            setTheme(localStorage.getItem('theme') || 'light');
            setFontSize(localStorage.getItem('font-size') || '15px');
            setInterval(fetchMessages, 2000);
            fetchMessages();
            renderRooms();
        }
    };

    async function handleSend() { 
        if (currentEditId) await submitEdit(); 
        else await send(); 
        // Force focus to keep keyboard open after sending
        input.focus();
    }

    async function send(extra = {}) {
        const text = input.value.trim();
        if(!text && !extra.attachment) return;
        const fd = new FormData();
        fd.append('action', 'send'); fd.append('my_uid', MY_UID);
        fd.append('user_name', localStorage.getItem('name') || 'Guest');
        fd.append('text', text); fd.append('reply_id', currentReplyId || '');
        for(let key in extra) fd.append(key, extra[key]);
        
        input.value = ''; 
        cancelReply(); 
        input.oninput();
        input.focus(); // Focus immediately after clear
        
        await fetch('', { method: 'POST', body: fd }); 
        fetchMessages();
        input.focus(); // Maintain focus after fetch
    }

    async function fetchMessages() {
        try {
            const res = await fetch('?fetch=1');
            const data = await res.json();
            if(data.length === lastCount && !currentEditId) return;
            const localDeletes = JSON.parse(localStorage.getItem('deleted_msgs') || '[]');
            chatWin.innerHTML = '';
            
            data.forEach(m => {
                if (localDeletes.includes(m.id)) return;
                const isMe = m.uid === MY_UID;
                const row = document.createElement('div');
                row.className = 'msg-row';
                
                const container = document.createElement('div');
                container.className = `msg-container ${isMe ? 'out' : 'in'}`;
                
                let startX = 0, currentX = 0, startY = 0;
                let isSwiping = false;

                row.ontouchstart = (e) => {
                    startX = e.touches[0].clientX;
                    startY = e.touches[0].clientY;
                    isSwiping = false;
                    container.style.transition = 'none';
                };

                row.ontouchmove = (e) => {
                    currentX = e.touches[0].clientX;
                    let diffX = currentX - startX; 
                    let diffY = Math.abs(e.touches[0].clientY - startY);

                    if (Math.abs(diffX) > 10 && Math.abs(diffX) > diffY) {
                        if ((isMe && diffX > 0) || (!isMe && diffX < 0)) {
                            isSwiping = true;
                            let move = Math.min(Math.abs(diffX), 80); 
                            container.style.transform = `translateX(${isMe ? move : -move}px)`;
                            row.querySelector('.swipe-indicator').style.opacity = move / 80;
                            if (e.cancelable) e.preventDefault();
                        }
                    }
                };

                row.ontouchend = () => {
                    container.style.transition = 'transform 0.2s ease-out';
                    let diffX = currentX - startX;
                    if (isSwiping && Math.abs(diffX) > 55) {
                        setReply(m.id, m.text || "File");
                    }
                    container.style.transform = `translateX(0)`;
                    row.querySelector('.swipe-indicator').style.opacity = 0;
                };

                container.onclick = () => openMsgMenu(m, isMe);

                let html = `<div class="swipe-indicator ${isMe ? 'left-icon' : 'right-icon'}">↩️</div>`;
                html += `<span style="font-size:11px; font-weight:bold; color:var(--accent); margin:0 5px 2px">${isMe ? 'You' : m.user}</span><div class="bubble">`;
                if(m.reply_id) {
                    const p = data.find(x => x.id === m.reply_id);
                    html += `<div style="background:rgba(0,0,0,0.05); padding:5px; border-right:3px solid var(--accent); margin-bottom:5px; font-size:0.9em; opacity:0.8; text-align:right;">${p ? p.text.substring(0,25) : '...'}</div>`;
                }
                if(m.type === 'text') html += m.text;
                else if(m.type === 'voice') html += `<audio src="${m.file}" controls style="height:35px; width:100%"></audio>`;
                else html += `<a href="${m.file}" target="_blank" style="color:inherit; text-decoration:none">📁 File</a>`;
                html += `<div style="font-size:10px; opacity:0.5; margin-top:4px; text-align:left; direction:ltr">${m.edited ? '(edited) ' : ''}${m.time}</div></div>`;
                
                container.innerHTML = html;
                row.appendChild(container);
                chatWin.appendChild(row);
            });
            chatWin.scrollTop = chatWin.scrollHeight;
            lastCount = data.length;
        } catch(e) {}
    }

    function openMsgMenu(m, isMe) {
        selectedMsg = m;
        document.getElementById('menu-del-all').style.display = isMe ? 'flex' : 'none';
        document.getElementById('msg-menu').classList.add('active');
        document.getElementById('overlay').classList.add('active');
    }

    function closeAllModals() {
        document.getElementById('msg-menu').classList.remove('active');
        document.getElementById('sidebar').classList.remove('active');
        document.getElementById('overlay').classList.remove('active');
    }

    function startEdit() {
        if (!selectedMsg) return;
        currentEditId = selectedMsg.id; input.value = selectedMsg.text;
        document.getElementById('edit-bar').style.display = 'flex';
        document.getElementById('reply-bar').style.display = 'none';
        closeAllModals(); 
        input.focus(); 
        input.oninput();
    }

    function cancelEdit() { currentEditId = null; input.value = ''; document.getElementById('edit-bar').style.display = 'none'; input.oninput(); input.focus(); }

    async function submitEdit() {
        const fd = new FormData(); fd.append('action', 'edit');
        fd.append('msg_id', currentEditId); fd.append('text', input.value);
        await fetch('', { method: 'POST', body: fd }); cancelEdit(); fetchMessages();
    }

    async function deleteMessage(mode) {
        if (!selectedMsg) return;
        if (mode === 'all') {
            const fd = new FormData(); fd.append('action', 'delete_all'); fd.append('msg_id', selectedMsg.id);
            await fetch('', { method: 'POST', body: fd });
        } else {
            let deletes = JSON.parse(localStorage.getItem('deleted_msgs') || '[]');
            deletes.push(selectedMsg.id); localStorage.setItem('deleted_msgs', JSON.stringify(deletes));
        }
        closeAllModals(); fetchMessages();
    }

    function setReply(id, text) {
        currentReplyId = id; currentEditId = null;
        document.getElementById('edit-bar').style.display = 'none';
        document.getElementById('reply-bar').style.display = 'flex';
        document.getElementById('reply-text').innerText = "Reply to: " + (text.length > 30 ? text.substring(0, 30) + "..." : text);
        // Force keyboard open on reply
        input.focus();
    }

    function cancelReply() { currentReplyId = null; document.getElementById('reply-bar').style.display = 'none'; input.focus(); }
    function toggleMenu() { document.getElementById('sidebar').classList.toggle('active'); document.getElementById('overlay').classList.toggle('active'); renderRooms(); }
    function setTheme(t) { document.body.setAttribute('data-theme', t); localStorage.setItem('theme', t); }
    function setFontSize(size) { document.documentElement.style.setProperty('--font-size', size); localStorage.setItem('font-size', size); }
    function saveProfile() { localStorage.setItem('name', document.getElementById('set-name').value); }
    function uploadFile() { const file = document.getElementById('file-in').files[0]; if(file) send({ attachment: file }); }

    async function startRec() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            recorder = new MediaRecorder(stream); chunks = [];
            recorder.ondataavailable = e => chunks.push(e.data);
            recorder.onstop = () => {
                const blob = new Blob(chunks, { type: 'audio/webm' });
                send({ attachment: blob, is_voice: true });
            };
            recorder.start(); document.getElementById('voice-btn').style.fill = 'red';
        } catch(e) { alert("Mic error"); }
    }
    function stopRec() { if(recorder && recorder.state !== 'inactive') { recorder.stop(); document.getElementById('voice-btn').style.fill = '#707579'; } }

    function renderRooms() {
        const listDiv = document.getElementById('rooms-list'); if(!listDiv) return;
        const rooms = JSON.parse(localStorage.getItem('my_rooms') || '[]');
        listDiv.innerHTML = '';
        rooms.forEach(code => {
            const div = document.createElement('div');
            div.className = `room-item ${code === currentRoom ? 'active' : ''}`;
            div.onclick = () => switchRoom(code); div.innerHTML = `Room ${code}`;
            listDiv.appendChild(div);
        });
    }

    async function switchRoom(code) { if(code === currentRoom) return; const res = await authRoom(code, passwordGlobal); if(res.status === 'success') location.reload(); }
    async function addNewRoom() {
        const code = document.getElementById('add-room-input').value; if(code.length !== 6) return;
        const res = await authRoom(code, passwordGlobal);
        if(res.status === 'success') { 
            let rooms = JSON.parse(localStorage.getItem('my_rooms') || '[]');
            if(!rooms.includes(code)) rooms.push(code);
            localStorage.setItem('my_rooms', JSON.stringify(rooms)); location.reload(); 
        }
    }
    async function authRoom(code, pass) {
        const fd = new FormData(); fd.append('action', 'auth_room'); fd.append('room_code', code); fd.append('login_pass', pass);
        const req = await fetch('', { method: 'POST', body: fd }); return req.json();
    }
</script>
</body>
</html>