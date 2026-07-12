/**
 * WebSocket Chat Client
 * Sistema de Ponto Eletrônico
 */

class ChatClient {
    constructor(wsUrl, authToken) {
        this.wsUrl = wsUrl;
        this.authToken = authToken;
        this.ws = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 3000;
        this.pingInterval = null;
        this.isConnected = false;
        this.currentRoomId = null;
        this.typingTimeout = null;
        this.isTyping = false;

        // Event handlers
        this.onMessageReceived = null;
        this.onTypingIndicator = null;
        this.onUserStatus = null;
        this.onReaction = null;
        this.onConnected = null;
        this.onDisconnected = null;
        this.onError = null;
    }

    /**
     * Connect to WebSocket server
     */
    connect() {
        try {
            console.log('[Chat] Connecting to WebSocket server:', this.wsUrl);
            this.ws = new WebSocket(this.wsUrl);

            this.ws.addEventListener('open', this.handleOpen.bind(this));
            this.ws.addEventListener('message', this.handleMessage.bind(this));
            this.ws.addEventListener('close', this.handleClose.bind(this));
            this.ws.addEventListener('error', this.handleError.bind(this));
        } catch (error) {
            console.error('[Chat] Connection error:', error);
            this.scheduleReconnect();
        }
    }

    /**
     * Handle WebSocket open
     */
    handleOpen(event) {
        console.log('[Chat] Connected to WebSocket server');
        this.isConnected = true;
        this.reconnectAttempts = 0;

        // Authenticate
        this.send({
            type: 'auth',
            token: this.authToken
        });

        // Start ping/pong heartbeat
        this.startHeartbeat();

        // Call onConnected handler
        if (this.onConnected) {
            this.onConnected();
        }
    }

    /**
     * Handle WebSocket message
     */
    handleMessage(event) {
        try {
            const data = JSON.parse(event.data);
            console.log('[Chat] Received:', data.type, data);

            switch (data.type) {
                case 'auth_success':
                    this.handleAuthSuccess(data);
                    break;

                case 'message':
                    this.handleIncomingMessage(data);
                    break;

                case 'typing':
                    this.handleTypingIndicator(data);
                    break;

                case 'user_status':
                    this.handleUserStatus(data);
                    break;

                case 'reaction':
                    this.handleReaction(data);
                    break;

                case 'pong':
                    // Heartbeat response
                    console.log('[Chat] Pong received');
                    break;

                case 'auth_required':
                    console.warn('[Chat] Authentication required by server');
                    break;

                case 'auth_error':
                    console.error('[Chat] Authentication error:', data.message);
                    if (this.onError) {
                        this.onError(data.message || 'Authentication failed');
                    }
                    break;

                case 'error':
                    console.error('[Chat] Server error:', data.message || data.error);
                    if (this.onError) {
                        this.onError(data.message || data.error || 'Server error');
                    }
                    break;

                default:
                    console.warn('[Chat] Unknown message type:', data.type);
            }
        } catch (error) {
            console.error('[Chat] Error parsing message:', error);
        }
    }

    /**
     * Handle WebSocket close
     */
    handleClose(event) {
        console.log('[Chat] Disconnected from WebSocket server');
        this.isConnected = false;
        this.stopHeartbeat();

        // Call onDisconnected handler
        if (this.onDisconnected) {
            this.onDisconnected();
        }

        // Attempt to reconnect
        if (!event.wasClean) {
            this.scheduleReconnect();
        }
    }

    /**
     * Handle WebSocket error
     */
    handleError(event) {
        console.error('[Chat] WebSocket error:', event);

        if (this.onError) {
            this.onError('Connection error');
        }
    }

    /**
     * Handle authentication success
     */
    handleAuthSuccess(data) {
        console.log('[Chat] Authenticated as user:', data.user_id);
        this.showNotification('Conectado ao chat!', 'success');
    }

    /**
     * Handle incoming message
     */
    handleIncomingMessage(data) {
        if (this.onMessageReceived) {
            this.onMessageReceived(data);
        }

        // Show desktop notification if not in focus and not current room
        if (document.hidden || data.room_id !== this.currentRoomId) {
            this.showDesktopNotification(data.sender_name, data.message);
        }

        // Play notification sound
        this.playNotificationSound();
    }

    /**
     * Handle typing indicator
     */
    handleTypingIndicator(data) {
        if (this.onTypingIndicator) {
            this.onTypingIndicator(data);
        }
    }

    /**
     * Handle user status change
     */
    handleUserStatus(data) {
        if (this.onUserStatus) {
            this.onUserStatus(data);
        }
    }

    /**
     * Handle reaction
     */
    handleReaction(data) {
        if (this.onReaction) {
            this.onReaction(data);
        }
    }

    /**
     * Send message to room
     */
    sendMessage(roomId, message, replyTo = null) {
        if (!this.isConnected) {
            console.error('[Chat] Not connected to WebSocket server');
            return false;
        }

        this.send({
            type: 'message',
            room_id: roomId,
            message: message,
            reply_to: replyTo
        });

        return true;
    }

    /**
     * Send typing indicator
     */
    sendTyping(roomId, isTyping) {
        if (!this.isConnected) {
            return;
        }

        // Throttle typing indicator
        if (this.typingTimeout) {
            clearTimeout(this.typingTimeout);
        }

        this.send({
            type: 'typing',
            room_id: roomId,
            typing: isTyping
        });

        if (isTyping) {
            this.typingTimeout = setTimeout(() => {
                this.sendTyping(roomId, false);
            }, 3000);
        }
    }

    /**
     * Mark room as read
     */
    markAsRead(roomId) {
        if (!this.isConnected) {
            return;
        }

        this.send({
            type: 'read',
            room_id: roomId
        });
    }

    /**
     * Change user status
     */
    changeStatus(status) {
        if (!this.isConnected) {
            return;
        }

        this.send({
            type: 'status',
            status: status
        });
    }

    /**
     * Add/remove reaction
     */
    addReaction(messageId, emoji) {
        if (!this.isConnected) {
            return;
        }

        this.send({
            type: 'reaction',
            message_id: messageId,
            emoji: emoji
        });
    }

    /**
     * Join room
     */
    joinRoom(roomId) {
        this.currentRoomId = roomId;

        if (!this.isConnected) {
            return;
        }

        this.send({
            type: 'join_room',
            room_id: roomId
        });
    }

    /**
     * Leave room
     */
    leaveRoom(roomId) {
        if (this.currentRoomId === roomId) {
            this.currentRoomId = null;
        }

        if (!this.isConnected) {
            return;
        }

        this.send({
            type: 'leave_room',
            room_id: roomId
        });
    }

    /**
     * Send data to WebSocket server
     */
    send(data) {
        if (!this.ws || this.ws.readyState !== WebSocket.OPEN) {
            console.error('[Chat] WebSocket not connected');
            return false;
        }

        try {
            this.ws.send(JSON.stringify(data));
            return true;
        } catch (error) {
            console.error('[Chat] Error sending message:', error);
            return false;
        }
    }

    /**
     * Start heartbeat (ping/pong)
     */
    startHeartbeat() {
        this.pingInterval = setInterval(() => {
            if (this.isConnected) {
                this.send({ type: 'ping' });
            }
        }, 30000); // Every 30 seconds
    }

    /**
     * Stop heartbeat
     */
    stopHeartbeat() {
        if (this.pingInterval) {
            clearInterval(this.pingInterval);
            this.pingInterval = null;
        }
    }

    /**
     * Schedule reconnect
     */
    scheduleReconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.error('[Chat] Max reconnection attempts reached');
            this.showNotification('Falha ao conectar ao chat. Recarregue a página.', 'error');
            return;
        }

        this.reconnectAttempts++;
        const delay = this.reconnectDelay * this.reconnectAttempts;

        console.log(`[Chat] Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts})`);

        setTimeout(() => {
            this.connect();
        }, delay);
    }

    /**
     * Disconnect
     */
    disconnect() {
        this.stopHeartbeat();

        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }

        this.isConnected = false;
    }

    /**
     * Show browser notification
     */
    showNotification(message, type = 'info') {
        // You can implement your custom notification system here
        console.log(`[Notification] ${type.toUpperCase()}: ${message}`);
    }

    /**
     * Show desktop notification
     */
    showDesktopNotification(title, body) {
        if (!('Notification' in window)) {
            return;
        }

        if (Notification.permission === 'granted') {
            new Notification(title, {
                body: body,
                icon: '/assets/img/chat-icon.png',
                badge: '/assets/img/badge.png'
            });
        } else if (Notification.permission !== 'denied') {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    new Notification(title, {
                        body: body,
                        icon: '/assets/img/chat-icon.png'
                    });
                }
            });
        }
    }

    /**
     * Play notification sound
     */
    playNotificationSound() {
        try {
            const audio = new Audio('/assets/sounds/notification.mp3');
            audio.volume = 0.3;
            audio.play().catch(err => {
                // Ignore autoplay errors
                console.debug('[Chat] Notification sound blocked:', err);
            });
        } catch (error) {
            console.debug('[Chat] Error playing notification sound:', error);
        }
    }

    /**
     * Set current room
     */
    setCurrentRoom(roomId) {
        this.currentRoomId = roomId;
    }

    /**
     * Upload file to server
     * @param {File} file - File object from input
     * @param {int} roomId - Room ID
     * @param {string} caption - Optional caption for file
     * @param {int} replyTo - Optional message ID to reply to
     * @param {Function} onProgress - Progress callback (percentage)
     * @param {Function} onSuccess - Success callback (fileData)
     * @param {Function} onError - Error callback (error message)
     */
    uploadFile(file, roomId, caption = '', replyTo = null, onProgress = null, onSuccess = null, onError = null) {
        // Validate file size (10MB)
        const maxSize = 10 * 1024 * 1024;
        if (file.size > maxSize) {
            const error = 'Arquivo muito grande. Tamanho máximo: 10MB';
            if (onError) onError(error);
            return false;
        }

        // Validate file type
        const allowedExtensions = [
            'jpg', 'jpeg', 'png', 'gif', 'webp',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv',
            'zip', 'rar', '7z'
        ];

        const fileName = file.name.toLowerCase();
        const extension = fileName.split('.').pop();

        if (!allowedExtensions.includes(extension)) {
            const error = 'Tipo de arquivo não permitido';
            if (onError) onError(error);
            return false;
        }

        // Prepare form data
        const formData = new FormData();
        formData.append('file', file);
        formData.append('room_id', roomId);
        formData.append('caption', caption);
        if (replyTo) {
            formData.append('reply_to', replyTo);
        }

        // Create AJAX request
        const xhr = new XMLHttpRequest();

        // Progress tracking
        if (onProgress) {
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percentage = Math.round((e.loaded / e.total) * 100);
                    onProgress(percentage);
                }
            });
        }

        // Success handler
        xhr.addEventListener('load', () => {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        // Send file message via WebSocket
                        this.send({
                            type: 'message',
                            room_id: roomId,
                            message: caption || 'Arquivo enviado',
                            file_data: response,
                            reply_to: replyTo
                        });

                        if (onSuccess) onSuccess(response);
                    } else {
                        if (onError) onError(response.message || 'Erro ao enviar arquivo');
                    }
                } catch (error) {
                    if (onError) onError('Erro ao processar resposta do servidor');
                }
            } else {
                if (onError) onError('Erro ao enviar arquivo. Código: ' + xhr.status);
            }
        });

        // Error handler
        xhr.addEventListener('error', () => {
            if (onError) onError('Erro de conexão ao enviar arquivo');
        });

        // Abort handler
        xhr.addEventListener('abort', () => {
            if (onError) onError('Upload cancelado');
        });

        // Send request
        xhr.open('POST', '/chat/upload');
        xhr.send(formData);

        return xhr; // Return xhr for potential cancellation
    }
}

/**
 * Helper function to format message timestamp
 */
function formatMessageTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();

    const isToday = date.toDateString() === now.toDateString();

    if (isToday) {
        return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    } else {
        return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }) + ' ' +
               date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }
}

/**
 * Helper function to escape HTML
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Helper function to linkify URLs
 */
function linkify(text) {
    const urlRegex = /(https?:\/\/[^\s]+)/g;
    return text.replace(urlRegex, (url) => {
        return `<a href="${url}" target="_blank" rel="noopener noreferrer">${url}</a>`;
    });
}

/**
 * Helper function to format message with mentions
 */
function formatMessage(text) {
    // Escape HTML first
    let formatted = escapeHtml(text);

    // Convert URLs to links
    formatted = linkify(formatted);

    // Convert line breaks
    formatted = formatted.replace(/\n/g, '<br>');

    return formatted;
}

/**
 * Helper function to format file size
 */
function formatFileSize(bytes) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0;
    let size = bytes;

    while (size >= 1024 && i < units.length - 1) {
        size /= 1024;
        i++;
    }

    return size.toFixed(2) + ' ' + units[i];
}

/**
 * Helper function to get file icon class
 */
function getFileIcon(fileType, extension = '') {
    const icons = {
        'image': 'fa-file-image',
        'document': 'fa-file-alt',
        'archive': 'fa-file-archive'
    };

    const specificIcons = {
        'pdf': 'fa-file-pdf',
        'doc': 'fa-file-word',
        'docx': 'fa-file-word',
        'xls': 'fa-file-excel',
        'xlsx': 'fa-file-excel',
        'txt': 'fa-file-alt',
        'csv': 'fa-file-csv',
        'zip': 'fa-file-archive',
        'rar': 'fa-file-archive'
    };

    if (extension && specificIcons[extension.toLowerCase()]) {
        return specificIcons[extension.toLowerCase()];
    }

    return icons[fileType] || 'fa-file';
}

/**
 * Helper function to check if file is image
 */
function isImageFile(fileType) {
    return fileType === 'image';
}

/**
 * Helper function to get file download URL
 */
function getFileDownloadUrl(filePath) {
    return '/chat/file/download?path=' + encodeURIComponent(filePath);
}

/**
 * Helper function to validate file before upload
 */
function validateFile(file) {
    const maxSize = 10 * 1024 * 1024; // 10MB
    const allowedExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv',
        'zip', 'rar', '7z'
    ];

    // Check size
    if (file.size > maxSize) {
        return {
            valid: false,
            error: 'Arquivo muito grande. Tamanho máximo: 10MB'
        };
    }

    // Check extension
    const fileName = file.name.toLowerCase();
    const extension = fileName.split('.').pop();

    if (!allowedExtensions.includes(extension)) {
        return {
            valid: false,
            error: 'Tipo de arquivo não permitido. Formatos aceitos: ' + allowedExtensions.join(', ')
        };
    }

    return {
        valid: true,
        extension: extension
    };
}

/**
 * Helper function to create file preview
 */
function createFilePreview(file, onRemove = null) {
    const validation = validateFile(file);

    if (!validation.valid) {
        return null;
    }

    const previewDiv = document.createElement('div');
    previewDiv.className = 'file-preview';
    previewDiv.dataset.fileName = file.name;

    // Check if image
    const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    const isImage = imageExtensions.includes(validation.extension);

    if (isImage) {
        // Create image preview
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'file-preview-image';
            previewDiv.appendChild(img);
        };
        reader.readAsDataURL(file);
    } else {
        // Create file icon preview
        const icon = document.createElement('i');
        icon.className = `fas ${getFileIcon('', validation.extension)} fa-3x`;
        previewDiv.appendChild(icon);
    }

    // File info
    const fileInfo = document.createElement('div');
    fileInfo.className = 'file-preview-info';
    fileInfo.innerHTML = `
        <div class="file-name">${escapeHtml(file.name)}</div>
        <div class="file-size">${formatFileSize(file.size)}</div>
    `;
    previewDiv.appendChild(fileInfo);

    // Remove button
    if (onRemove) {
        const removeBtn = document.createElement('button');
        removeBtn.className = 'btn btn-sm btn-danger file-preview-remove';
        removeBtn.innerHTML = '<i class="fas fa-times"></i>';
        removeBtn.onclick = () => {
            previewDiv.remove();
            if (onRemove) onRemove(file);
        };
        previewDiv.appendChild(removeBtn);
    }

    return previewDiv;
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ChatClient;
}
