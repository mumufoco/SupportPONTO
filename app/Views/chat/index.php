<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar - Chat List -->
        <div class="col-md-3 col-lg-2 bg-light border-end sp-sidebar-shell" id="chatSidebar">
            <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                <h5 class="mb-0">
                    <i class="fas fa-comments"></i> Conversas
                </h5>
                <div class="d-flex gap-2">
                    <!-- Push Notification Toggle -->
                    <button id="pushToggleBtn" class="btn btn-sm btn-outline-secondary" type="button" title="Notificações Push">
                        <i class="fas fa-bell" id="pushIcon"></i>
                    </button>

                    <!-- New Chat Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-plus"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#newChatModal">
                                <i class="fas fa-user"></i> Nova Conversa
                            </a></li>
                            <li><a class="dropdown-item" href="<?= site_url('chat/group/create') ?>">
                                <i class="fas fa-users"></i> Novo Grupo
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Rooms List -->
            <div class="list-group list-group-flush" id="roomsList">
                <?php if (empty($rooms)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-2x mb-2"></i>
                        <p class="small">Nenhuma conversa ainda</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($rooms as $room): ?>
                        <a href="<?= route_to('chat.room', (int) $room->id) ?>" class="list-group-item list-group-item-action" data-room-id="<?= (int) $room->id ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center">
                                        <?php if ($room->type === 'private'): ?>
                                            <i class="fas fa-user text-secondary me-2"></i>
                                        <?php elseif ($room->type === 'group'): ?>
                                            <i class="fas fa-users text-primary me-2"></i>
                                        <?php elseif ($room->type === 'department'): ?>
                                            <i class="fas fa-building text-info me-2"></i>
                                        <?php else: ?>
                                            <i class="fas fa-bullhorn text-warning me-2"></i>
                                        <?php endif; ?>
                                        <strong><?= esc($room->name) ?></strong>
                                    </div>
                                    <?php if (isset($room->last_message_at)): ?>
                                        <small class="text-muted"><?= format_datetime_relative($room->last_message_at) ?></small>
                                    <?php endif; ?>
                                </div>
                                <?php if (isset($room->unread_count) && $room->unread_count > 0): ?>
                                    <span class="badge bg-primary rounded-pill"><?= (int) $room->unread_count ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="col-md-9 col-lg-10">
            <div class="card border-0 shadow-sm sp-card-shell">
                <div class="card-body d-flex flex-column justify-content-center align-items-center text-center">
                    <i class="fas fa-comments fa-5x text-muted mb-3"></i>
                    <h4 class="text-muted">Selecione uma conversa para começar</h4>
                    <p class="text-muted">Escolha uma conversa existente ou inicie uma nova</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newChatModal">
                        <i class="fas fa-plus"></i> Nova Conversa
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Chat Modal -->
<div class="modal fade" id="newChatModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user"></i> Nova Conversa
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Selecione um colaborador:</label>
                    <input type="text" class="form-control mb-2" id="employeeSearch" placeholder="Buscar...">
                    <div class="list-group sp-scroll-y-400" id="employeeList">
                        <?php foreach ($employees as $emp): ?>
                            <a href="<?= route_to('chat.new', $emp->id) ?>" class="list-group-item list-group-item-action employee-item">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle bg-primary text-white me-2">
                                        <?= esc(strtoupper(substr($emp->name, 0, 2))) ?>
                                    </div>
                                    <div>
                                        <strong><?= esc($emp->name) ?></strong><br>
                                        <small class="text-muted"><?= esc($emp->department) ?></small>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Online Users Indicator -->
<div class="position-fixed bottom-0 end-0 p-3 sp-toast-stack">
    <div class="card shadow-sm sp-toast-card">
        <div class="card-body p-2">
            <div class="d-flex align-items-center justify-content-between">
                <small class="text-muted">
                    <i class="fas fa-circle text-success"></i>
                    <span id="onlineCount"><?= count($onlineUsers) ?></span> online
                </small>
                <span class="connection-status">
                    <i class="fas fa-circle text-secondary" id="wsStatus" title="Desconectado"></i>
                </span>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
}

.connection-status .fa-circle {
    font-size: 12px;
}

.connection-status .text-success {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

#chatSidebar::-webkit-scrollbar {
    width: 6px;
}

#chatSidebar::-webkit-scrollbar-thumb {
    background: #ccc;
    border-radius: 3px;
}

.list-group-item-action:hover {
    background-color: #f8f9fa;
}

.list-group-item-action.active {
    background-color: #e7f3ff;
    border-left: 3px solid #0d6efd;
}
</style>

<script <?= csp_script_nonce_attr() ?> src="<?= sp_safe_url(asset_url('assets/js/chat.js')) ?>"></script>
<script <?= csp_script_nonce_attr() ?> src="<?= sp_safe_url(asset_url('assets/js/push-notifications.js')) ?>"></script>
<script <?= csp_script_nonce_attr() ?>>
// Initialize WebSocket chat client
const wsProtocol = window.location.protocol === 'https:' ? 'wss' : 'ws';
const wsHost = window.location.hostname || 'localhost';
const wsPort = <?= json_encode((string) (env('WEBSOCKET_PORT') ?: '8080'), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const wsUrl = `${wsProtocol}://${wsHost}:${wsPort}`;
const authToken = <?= json_encode($websocketToken, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

const chat = new ChatClient(wsUrl, authToken);

// Initialize Push Notifications
let pushManager = null;

async function initPushNotifications() {
    try {
        // Get VAPID public key
        const response = await spFetch('<?= route_to('chat.push.vapid-key') ?>');
        const data = await response.json();

        if (!data.success || !data.publicKey) {
            console.warn('[Push] VAPID public key not configured');
            return;
        }

        // Initialize push manager
        pushManager = new PushNotificationManager(data.publicKey);
        const initialized = await pushManager.init();

        updatePushButton();

        console.log('[Push] Initialized successfully');
    } catch (error) {
        console.error('[Push] Initialization error:', error);
    }
}

// Update push notification button state
function updatePushButton() {
    if (!pushManager) return;

    const pushBtn = document.getElementById('pushToggleBtn');
    const pushIcon = document.getElementById('pushIcon');

    const permission = pushManager.getPermissionStatus();
    const isSubscribed = pushManager.isSubscribed();

    if (permission === 'unsupported') {
        pushBtn.disabled = true;
        pushBtn.title = 'Notificações push não suportadas';
        pushIcon.className = 'fas fa-bell-slash text-muted';
    } else if (permission === 'denied') {
        pushBtn.className = 'btn btn-sm btn-outline-danger';
        pushBtn.title = 'Notificações bloqueadas';
        pushIcon.className = 'fas fa-bell-slash';
    } else if (isSubscribed) {
        pushBtn.className = 'btn btn-sm btn-success';
        pushBtn.title = 'Notificações ativas - Clique para desativar';
        pushIcon.className = 'fas fa-bell';
    } else {
        pushBtn.className = 'btn btn-sm btn-outline-secondary';
        pushBtn.title = 'Ativar notificações';
        pushIcon.className = 'far fa-bell';
    }
}

// Toggle push notification subscription
async function togglePushNotification() {
    if (!pushManager) {
        alert('Notificações push não estão disponíveis');
        return;
    }

    const isSubscribed = pushManager.isSubscribed();

    if (isSubscribed) {
        // Unsubscribe
        const result = await pushManager.unsubscribe();

        if (result.success) {
            updatePushButton();
            showNotification('Notificações desativadas', 'secondary');
        } else {
            alert('Erro ao desativar notificações: ' + result.error);
        }
    } else {
        // Subscribe
        const result = await pushManager.subscribe();

        if (result.success) {
            updatePushButton();
            showNotification('Notificações ativadas!', 'success');

            // Test notification
            await pushManager.testNotification();
        } else {
            alert('Erro ao ativar notificações: ' + result.error);
        }
    }
}

// Show notification toast
function showNotification(message, type = 'info') {
    // You can implement a toast notification system here
    console.log(`[${type.toUpperCase()}] ${message}`);
}

// Bind push toggle button
document.getElementById('pushToggleBtn')?.addEventListener('click', togglePushNotification);

// Initialize push notifications
initPushNotifications();

// Set up event handlers
chat.onConnected = () => {
    console.log('Connected to chat server');
    document.getElementById('wsStatus').className = 'fas fa-circle text-success';
    document.getElementById('wsStatus').title = 'Conectado';
};

chat.onDisconnected = () => {
    console.log('Disconnected from chat server');
    document.getElementById('wsStatus').className = 'fas fa-circle text-danger';
    document.getElementById('wsStatus').title = 'Desconectado';
};

chat.onUserStatus = (data) => {
    console.log('User status changed:', data);
    // Update online count
    updateOnlineCount();
};

chat.onError = (error) => {
    console.error('Chat error:', error);
};

// Connect to WebSocket
chat.connect();

// Employee search
document.getElementById('employeeSearch')?.addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    const items = document.querySelectorAll('.employee-item');

    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        if (text.includes(search)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
});

// Update online count
function updateOnlineCount() {
    spFetch('<?= site_url('api/chat/online') ?>')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('onlineCount').textContent = data.users.length;
            }
        })
        .catch(error => console.error('Error fetching online users:', error));
}

// Update online count every 30 seconds
setInterval(updateOnlineCount, 30000);

// Request notification permission
if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
}
</script>

<?= $this->endSection() ?>
