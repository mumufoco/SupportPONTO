<?php
$isOwn = $message->sender_id === $employee['id'];
?>

<div class="d-flex mb-3 <?= $isOwn ? 'justify-content-end' : '' ?>" data-message-id="<?= (int) $message->id ?>">
    <div class="message-bubble sp-message-bubble <?= $isOwn ? 'message-own bg-primary text-white' : 'bg-light' ?> p-3 rounded shadow-sm">
        <?php if (!$isOwn): ?>
            <div class="small fw-bold mb-1"><?= esc($message->sender_name) ?></div>
        <?php endif; ?>

        <?php if ($message->reply_to && $message->reply_message): ?>
            <div class="alert alert-secondary py-1 px-2 mb-2 sp-message-reply">
                <i class="fas fa-reply"></i>
                <strong><?= esc($message->reply_sender_name) ?>:</strong>
                <?= esc(substr($message->reply_message, 0, 100)) ?><?= strlen($message->reply_message) > 100 ? '...' : '' ?>
            </div>
        <?php endif; ?>

        <?php
        // Check if message has file attachment
        helper('file_upload');
        $hasFile = !empty($message->file_path);
        $isImage = $hasFile && is_image_file($message->type ?? '');
        ?>

        <?php if ($hasFile && $isImage): ?>
            <!-- Image Preview -->
            <div class="message-file mb-2">
                <a href="<?= get_file_url($message->file_path) ?>" target="_blank" data-lightbox="chat-image">
                    <img src="<?= get_file_url($message->file_path) ?>"
                         alt="<?= esc($message->file_name) ?>"
                         class="img-fluid rounded sp-message-image">
                </a>
                <?php if (!empty($message->message) && $message->message !== 'Arquivo enviado'): ?>
                    <div class="mt-2 message-text">
                        <?= nl2br(esc($message->message)) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($hasFile): ?>
            <!-- Document/File Download -->
            <div class="message-file mb-2 p-2 rounded sp-message-file-box">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="<?= get_file_icon($message->type ?? '', pathinfo($message->file_name, PATHINFO_EXTENSION)) ?> fa-2x"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold small"><?= esc($message->file_name) ?></div>
                        <div class="text-muted sp-message-meta">
                            <?= format_file_size($message->file_size ?? 0) ?>
                        </div>
                    </div>
                    <div>
                        <a href="<?= get_file_url($message->file_path) ?>"
                           class="btn btn-sm btn-<?= $isOwn ? 'light' : 'primary' ?>"
                           download>
                            <i class="fas fa-download"></i>
                        </a>
                    </div>
                </div>
                <?php if (!empty($message->message) && $message->message !== 'Arquivo enviado'): ?>
                    <div class="mt-2 message-text small">
                        <?= nl2br(esc($message->message)) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Regular text message -->
            <div class="message-text">
                <?= nl2br(esc($message->message)) ?>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mt-2">
            <div class="message-time sp-message-meta text-<?= $isOwn ? 'white' : 'muted' ?>">
                <?= format_time($message->created_at) ?>
                <?php if ($message->edited_at): ?>
                    <i class="fas fa-pen" title="Editada"></i>
                <?php endif; ?>
            </div>

            <div class="message-actions">
                <?php if (isset($message->reactions) && is_array($message->reactions) && count($message->reactions) > 0): ?>
                    <span class="reactions">
                        <?php foreach ($message->reactions as $emoji => $count): ?>
                            <span class="badge bg-light text-dark me-1 sp-msg-reaction"
                                  data-id="<?= (int) $message->id ?>"
                                  data-emoji="<?= esc($emoji) ?>">
                                <?= esc($emoji) ?> <?= (int) $count ?>
                            </span>
                        <?php endforeach; ?>
                    </span>
                <?php endif; ?>

                <div class="dropdown d-inline">
                    <button class="btn btn-sm btn-link text-<?= $isOwn ? 'white' : 'dark' ?> p-0 dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item sp-msg-reply" href="#"
                               data-id="<?= (int) $message->id ?>"
                               data-text="<?= esc($message->message) ?>">
                                <i class="fas fa-reply"></i> Responder
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item sp-msg-picker" href="#"
                               data-id="<?= (int) $message->id ?>">
                                <i class="far fa-smile"></i> Reagir
                            </a>
                        </li>
                        <?php if ($isOwn): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item sp-msg-edit" href="#"
                                   data-id="<?= (int) $message->id ?>"
                                   data-text="<?= esc($message->message) ?>">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item text-danger sp-msg-delete" href="#"
                                   data-id="<?= (int) $message->id ?>">
                                    <i class="fas fa-trash"></i> Excluir
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
