<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\NotificationModel;

class NotificationsController extends BaseController
{
    protected NotificationModel $notificationModel;

    public function __construct()
    {
        $this->notificationModel = new NotificationModel();
        helper('datetime');
    }

    public function index()
    {
        $this->requireAuth();

        $userId = (int) ($this->session?->get('user_id') ?? 0);
        if ($userId <= 0) {
            return redirect()->to(route_to('dashboard'))->with('error', 'Usuário não autenticado.');
        }

        $filter = $this->request->getGet('filter') ?? 'all';
        $validFilters = ['all', 'unread', 'read'];
        if (!in_array($filter, $validFilters, true)) {
            $filter = 'all';
        }

        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 15;

        $builder = $this->notificationModel->where('user_id', $userId);

        if ($filter === 'unread') {
            $builder->where('read', false);
        } elseif ($filter === 'read') {
            $builder->where('read', true);
        }

        $notifications = $builder
            ->orderBy('created_at', 'DESC')
            ->paginate($perPage, 'default', $page);

        $notifications = array_map(static function ($notification) {
            $item = (array) $notification;
            $item['time_ago'] = time_ago_br($item['created_at'] ?? null);
            return $item;
        }, $notifications);

        $counts = [
            'all' => $this->notificationModel->where('user_id', $userId)->countAllResults(),
            'unread' => $this->notificationModel->where('user_id', $userId)->where('read', false)->countAllResults(),
            'read' => $this->notificationModel->where('user_id', $userId)->where('read', true)->countAllResults(),
        ];

        $data = [
            'title' => 'Notificações',
            'notifications' => $notifications,
            'counts' => $counts,
            'filter' => $filter,
            'unreadCount' => $counts['unread'],
            'pager' => $this->notificationModel->pager,
        ];

        return view('notifications/index', $data);
    }

    public function show(int $id)
    {
        $this->requireAuth();

        $userId = (int) ($this->session?->get('user_id') ?? 0);

        $notification = $this->notificationModel
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            return redirect()->to(route_to('notifications'))->with('error', 'Notificação não encontrada.');
        }

        $notification = (array) $notification;

        if (!($notification['read'] ?? false)) {
            $this->notificationModel->markAsRead($id);
            $notification['read'] = true;
            $notification['read_at'] = date('Y-m-d H:i:s');
        }

        return view('notifications/show', ['notification' => $notification]);
    }

    public function markAsRead(int $id)
    {
        $this->requireAuth();

        $userId = (int) ($this->session?->get('user_id') ?? 0);

        $notification = $this->notificationModel
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Notificação não encontrada.',
            ]);
        }

        $success = $this->notificationModel->markAsRead($id);

        return $this->response->setJSON([
            'success' => $success,
        ]);
    }

    public function markAllAsRead()
    {
        $this->requireAuth();

        $userId = (int) ($this->session?->get('user_id') ?? 0);
        $success = $this->notificationModel->markAllAsRead($userId);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => $success]);
        }

        return redirect()->to(route_to('notifications'))->with('success', 'Notificações marcadas como lidas.');
    }
}
