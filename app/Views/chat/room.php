<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= $this->include('chat/partials/_chat_validation_notes') ?>
<?= $this->include('chat/partials/_chat_context_panel') ?>
<?= $this->include('chat/partials/_chat_intro') ?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Chat Messages Area -->
        <div class="col-md-9 col-lg-10">
            <div class="card border-0 shadow-sm sp-chat-card">
                <!-- Chat Header -->
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <a href="<?= site_url('chat') ?>" class="btn btn-sm btn-outline-secondary me-2">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <h5 class="mb-0">
                                <i class="fas fa-comments"></i> <?= esc($room['name'] ?? ('Sala #' . $roomId)) ?>
                            </h5>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#searchModal">
                                <i class="fas fa-search"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#membersModal">
                                <i class="fas fa-users"></i> Membros (<?= count($members) ?>)
                            </button>
                            <a href="<?= route_to('chat.room.settings', $roomId) ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-cog"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Messages Container -->
                <div class="card-body overflow-auto sp-chat-messages" id="messagesContainer">
                    <div id="messagesList">
                        <?php if (empty($messages)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-comments fa-3x mb-3"></i>
                                <p>Nenhuma mensagem ainda. Seja o primeiro a enviar!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $message): ?>
                                <?= $this->include('chat/partials/message', ['message' => $message, 'employee' => $employee]) ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Typing Indicator -->
                    <div id="typingIndicator" class="text-muted small ms-3 sp-chat-hidden">
                        <i class="fas fa-circle-notch fa-spin"></i> <span id="typingUser"></span> está digitando...
                    </div>
                </div>

                <!-- Message Input -->
                <div class="card-footer bg-white border-top">
                    <!-- Reply Preview -->
                    <div id="replyPreview" class="alert alert-info py-2 px-3 mb-2 sp-chat-hidden">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small><strong>Respondendo a:</strong></small>
                                <div class="small" id="replyText"></div>
                            </div>
                            <button type="button" class="btn-close btn-sm" onclick="cancelReply()"></button>
                        </div>
                    </div>

                    <!-- File Preview Area -->
                    <div id="filePreviewArea" class="mb-2 sp-chat-hidden">
                        <div class="alert alert-secondary py-2 px-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-file me-2"></i>
                                    <div>
                                        <div class="small fw-bold" id="filePreviewName"></div>
                                        <div class="text-muted sp-chat-meta" id="filePreviewSize"></div>
                                    </div>
                                </div>
                                <button type="button" class="btn-close btn-sm" onclick="cancelFileUpload()"></button>
                            </div>
                            <!-- Upload Progress -->
                            <div id="uploadProgress" class="progress mt-2 sp-chat-progress">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;"></div>
                            </div>
                        </div>
                    </div>

                    <form id="messageForm" onsubmit="sendMessage(event)">
                        <div class="input-group">
                            <button class="btn btn-outline-secondary" type="button" title="Emoji">
                                <i class="far fa-smile"></i>
                            </button>
                            <button class="btn btn-outline-secondary" type="button" title="Anexar arquivo" onclick="document.getElementById('fileInput').click()">
                                <i class="fas fa-paperclip"></i>
                            </button>
                            <input type="file" id="fileInput" class="sp-chat-file-input" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip,.rar,.7z" onchange="handleFileSelect(event)">
                            <input
                                type="text"
                                class="form-control"
                                id="messageInput"
                                placeholder="Digite sua mensagem..."
                                autocomplete="off"
                                maxlength="5000"
                            >
                            <button class="btn btn-primary" type="submit" id="sendButton">
                                <i class="fas fa-paper-plane"></i> Enviar
                            </button>
                        </div>
                        <small class="text-muted">
                            <span id="charCount">0</span>/5000 caracteres
                        </small>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar - Members -->
        <div class="col-md-3 col-lg-2 d-none d-md-block bg-light border-start sp-chat-sidebar">
            <div class="p-3 border-bottom">
                <h6 class="mb-0">Membros (<?= count($members) ?>)</h6>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($members as $member): ?>
                    <div class="list-group-item">
                        <div class="d-flex align-items-center">
                            <div class="sp-avatar-circle bg-<?= $member->employee_id === $employee['id'] ? 'primary' : 'secondary' ?> text-white me-2">
                                <?= esc(strtoupper(substr($member->name, 0, 2))) ?>
                            </div>
                            <div class="flex-grow-1">
                                <div class="small">
                                    <strong><?= esc($member->name) ?></strong>
                                    <?php if ($member->role === 'admin'): ?>
                                        <span class="badge bg-warning text-dark">Admin</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted sp-chat-meta">
                                    <i class="fas fa-circle <?= $member->is_online ?? false ? 'text-success' : 'text-secondary' ?>"></i>
                                    <?= $member->is_online ?? false ? 'Online' : 'Offline' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Members Modal (Mobile) -->
<div class="modal fade" id="membersModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Membros</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="list-group">
                    <?php foreach ($members as $member): ?>
                        <div class="list-group-item">
                            <div class="d-flex align-items-center">
                                <div class="sp-avatar-circle bg-secondary text-white me-2">
                                    <?= esc(strtoupper(substr($member->name, 0, 2))) ?>
                                </div>
                                <div>
                                    <strong><?= esc($member->name) ?></strong><br>
                                    <small class="text-muted"><?= esc($member->department) ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search Modal -->
<div class="modal fade" id="searchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Buscar Mensagens</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control" id="searchInput" placeholder="Digite para buscar...">
                </div>
                <div id="searchResults">
                    <!-- Search results will be displayed here -->
                </div>
            </div>
        </div>
    </div>
</div>



<script <?= csp_script_nonce_attr() ?> src="<?= sp_safe_url(asset_url('assets/js/chat.js')) ?>"></script>
<script <?= csp_script_nonce_attr() ?>>
const roomId = <?= (int) $roomId ?>;
const employeeId = <?= (int) $employee['id'] ?>;
let replyToMessageId = null;

// Initialize WebSocket
const wsProtocol = window.location.protocol === 'https:' ? 'wss' : 'ws';
const wsHost = window.location.hostname || 'localhost';
const wsPort = <?= json_encode((string) (env('WEBSOCKET_PORT') ?: '8080'), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const wsUrl = `${wsProtocol}://${wsHost}:${wsPort}`;
const authToken = <?= json_encode($websocketToken, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const chat = new ChatClient(wsUrl, authToken);

// Set current room
chat.setCurrentRoom(roomId);

// Event handlers
chat.onConnected = () => {
    console.log('Connected to chat');
    chat.joinRoom(roomId);
    chat.markAsRead(roomId);
};

chat.onMessageReceived = (data) => {
    if (data.room_id === roomId) {
        appendMessage(data);
        scrollToBottom();
        chat.markAsRead(roomId);
    }
};

chat.onTypingIndicator = (data) => {
    if (data.room_id === roomId && data.employee_id !== employeeId) {
        showTypingIndicator(data.typing);
    }
};

chat.onReaction = (data) => {
    if (data.room_id === roomId) {
        updateReaction(data);
    }
};

// Connect
chat.connect();

// Send message
function sendMessage(event) {
    event.preventDefault();

    const input = document.getElementById('messageInput');
    const message = input.value.trim();

    // Check if file is selected
    if (selectedFile) {
        // Upload file
        const caption = message || '';
        uploadFile(selectedFile, caption);
    } else {
        // Send text message
        if (!message) {
            return;
        }

        chat.sendMessage(roomId, message, replyToMessageId);

        input.value = '';
        replyToMessageId = null;
        document.getElementById('replyPreview').style.display = 'none';
        updateCharCount();
    }
}

// Upload file
function uploadFile(file, caption) {
    const progressBar = document.querySelector('#uploadProgress .progress-bar');
    const sendButton = document.getElementById('sendButton');

    // Show progress
    document.getElementById('uploadProgress').style.display = 'block';
    sendButton.disabled = true;

    // Upload file
    currentUploadXhr = chat.uploadFile(
        file,
        roomId,
        caption,
        replyToMessageId,
        // Progress callback
        (percentage) => {
            progressBar.style.width = percentage + '%';
        },
        // Success callback
        (response) => {
            console.log('File uploaded successfully:', response);

            // Clear form
            document.getElementById('messageInput').value = '';
            cancelFileUpload();

            // Scroll to bottom
            scrollToBottom();
        },
        // Error callback
        (error) => {
            console.error('File upload error:', error);
            alert('Erro ao enviar arquivo: ' + error);

            // Re-enable send button
            sendButton.disabled = false;
            document.getElementById('uploadProgress').style.display = 'none';
            progressBar.style.width = '0%';
        }
    );
}

// Append message to chat
function appendMessage(data) {
    const messagesList = document.getElementById('messagesList');
    const isOwn = data.sender_id === employeeId;

    const messageHtml = `
        <div class="d-flex mb-3 ${isOwn ? 'justify-content-end' : ''}">
            <div class="message-bubble ${isOwn ? 'message-own bg-primary text-white' : 'bg-light'} p-3 rounded shadow-sm">
                ${!isOwn ? `<div class="small fw-bold mb-1">${escapeHtml(data.sender_name)}</div>` : ''}
                <div>${formatMessage(data.message)}</div>
                <div class="message-time text-${isOwn ? 'white' : 'muted'} mt-1">
                    ${formatMessageTime(data.timestamp)}
                </div>
            </div>
        </div>
    `;

    messagesList.insertAdjacentHTML('beforeend', messageHtml);
}

// Scroll to bottom
function scrollToBottom() {
    const container = document.getElementById('messagesContainer');
    container.scrollTop = container.scrollHeight;
}

// Show typing indicator
function showTypingIndicator(isTyping) {
    const indicator = document.getElementById('typingIndicator');
    indicator.style.display = isTyping ? 'block' : 'none';

    if (isTyping) {
        scrollToBottom();
    }
}

// Typing indicator
let typingTimeout;
document.getElementById('messageInput').addEventListener('input', function() {
    clearTimeout(typingTimeout);

    chat.sendTyping(roomId, true);

    typingTimeout = setTimeout(() => {
        chat.sendTyping(roomId, false);
    }, 1000);

    updateCharCount();
});

// Character count
function updateCharCount() {
    const input = document.getElementById('messageInput');
    document.getElementById('charCount').textContent = input.value.length;
}

// Reply to message
function replyToMessage(messageId, messageText) {
    replyToMessageId = messageId;
    document.getElementById('replyText').textContent = messageText;
    document.getElementById('replyPreview').style.display = 'block';
    document.getElementById('messageInput').focus();
}

// Cancel reply
function cancelReply() {
    replyToMessageId = null;
    document.getElementById('replyPreview').style.display = 'none';
}

// Event delegation for message action buttons (safe: no inline JS with user data)
document.addEventListener('click', function(e) {
    const replyBtn = e.target.closest('.sp-msg-reply');
    if (replyBtn) { e.preventDefault(); replyToMessage(parseInt(replyBtn.dataset.id, 10), replyBtn.dataset.text); return; }
    const editBtn = e.target.closest('.sp-msg-edit');
    if (editBtn) { e.preventDefault(); if (typeof editMessage === 'function') editMessage(parseInt(editBtn.dataset.id, 10), editBtn.dataset.text); return; }
    const deleteBtn = e.target.closest('.sp-msg-delete');
    if (deleteBtn) { e.preventDefault(); if (typeof deleteMessage === 'function') deleteMessage(parseInt(deleteBtn.dataset.id, 10)); return; }
    const pickerBtn = e.target.closest('.sp-msg-picker');
    if (pickerBtn) { e.preventDefault(); if (typeof showReactionPicker === 'function') showReactionPicker(parseInt(pickerBtn.dataset.id, 10)); return; }
    const reactionBadge = e.target.closest('.sp-msg-reaction');
    if (reactionBadge) { if (typeof chat !== 'undefined' && chat.addReaction) chat.addReaction(parseInt(reactionBadge.dataset.id, 10), reactionBadge.dataset.emoji); return; }
});

// File upload variables
let selectedFile = null;
let currentUploadXhr = null;

// Handle file selection
function handleFileSelect(event) {
    const file = event.target.files[0];
    if (!file) return;

    // Validate file
    const validation = validateFile(file);
    if (!validation.valid) {
        alert(validation.error);
        event.target.value = '';
        return;
    }

    selectedFile = file;

    // Show file preview
    document.getElementById('filePreviewName').textContent = file.name;
    document.getElementById('filePreviewSize').textContent = formatFileSize(file.size);
    document.getElementById('filePreviewArea').style.display = 'block';

    // Update icon based on file type
    const icon = document.querySelector('#filePreviewArea .fas');
    icon.className = `fas ${getFileIcon('', validation.extension)} me-2`;

    // Focus on message input for caption
    document.getElementById('messageInput').placeholder = 'Digite uma legenda (opcional)...';
    document.getElementById('messageInput').focus();
}

// Cancel file upload
function cancelFileUpload() {
    selectedFile = null;
    document.getElementById('filePreviewArea').style.display = 'none';
    document.getElementById('uploadProgress').style.display = 'none';
    document.getElementById('fileInput').value = '';
    document.getElementById('messageInput').placeholder = 'Digite sua mensagem...';
    document.getElementById('sendButton').disabled = false;

    if (currentUploadXhr) {
        currentUploadXhr.abort();
        currentUploadXhr = null;
    }
}

// Helper functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatMessage(text) {
    let formatted = escapeHtml(text);
    formatted = formatted.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank">$1</a>');
    formatted = formatted.replace(/\n/g, '<br>');
    return formatted;
}

function formatMessageTime(timestamp) {
    const date = new Date(timestamp);
    return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

// Search messages
document.getElementById('searchInput')?.addEventListener('input', debounce(function(e) {
    const query = e.target.value.trim();

    if (query.length < 3) {
        document.getElementById('searchResults').innerHTML = '';
        return;
    }

    spFetch(`<?= site_url('chat/room') ?>/${roomId}/search?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && Array.isArray(data.messages) && data.messages.length > 0) {
                let html = '<div class="list-group">';
                data.messages.forEach(msg => {
                    html += `
                        <div class="list-group-item">
                            <strong>${escapeHtml(msg.sender_name)}</strong>
                            <div class="small">${formatMessage(msg.message)}</div>
                            <small class="text-muted">${formatMessageTime(msg.created_at)}</small>
                        </div>
                    `;
                });
                html += '</div>';
                document.getElementById('searchResults').innerHTML = html;
            } else {
                document.getElementById('searchResults').innerHTML = '<p class="text-muted">Nenhum resultado encontrado.</p>';
            }
        })
        .catch(() => {
            document.getElementById('searchResults').innerHTML = '<p class="text-danger">Não foi possível pesquisar as mensagens agora.</p>';
        });
}, 500));

function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// Auto-scroll to bottom on load
scrollToBottom();

// Mark as read when viewing
chat.markAsRead(roomId);
</script>

<?= $this->endSection() ?>
