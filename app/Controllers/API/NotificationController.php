<?php

namespace App\Controllers\API;

use App\Models\NotificationModel;
use App\Services\NotificationService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * API Notification Controller
 *
 * Handles notifications via API
 */
class NotificationController extends BaseApiController
{
    protected $modelName = 'App\Models\NotificationModel';
    protected $format = 'json';

    protected NotificationModel $notificationModel;
    protected NotificationService $notificationService;

    public function __construct()
    {
        parent::__construct();
        $this->notificationModel = new NotificationModel();
        $this->notificationService = new NotificationService();
        helper(['datetime']);
    }

    public function index()
    {
        return $this->withAuthenticatedEmployee(function (object $employee) {
            $page = (int) ($this->request->getGet('page') ?: 1);
            $perPage = min((int) ($this->request->getGet('per_page') ?: 20), 100);

            $notifications = $this->notificationModel
                ->where('user_id', $employee->id)
                ->orderBy('created_at', 'DESC')
                ->paginate($perPage, 'default', $page);

            $pager = $this->notificationModel->pager;

            return $this->respond([
                'success' => true,
                'data' => array_map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'title' => $notification->title,
                        'message' => $notification->message,
                        'type' => $notification->type,
                        'link' => $notification->link,
                        'read' => $notification->read,
                        'read_at' => $notification->read_at ? format_datetime_br($notification->read_at) : null,
                        'created_at' => format_datetime_br($notification->created_at),
                        'time_ago' => time_ago_br($notification->created_at),
                    ];
                }, $notifications),
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $pager->getTotal(),
                    'last_page' => $pager->getPageCount(),
                ],
            ], 200);
        });
    }

    public function unread()
    {
        return $this->withAuthenticatedEmployee(function (object $employee) {
            $limit = min((int) ($this->request->getGet('limit') ?: 10), 50);
            $notifications = $this->notificationService->getUnread($employee->id, $limit);

            return $this->respond([
                'success' => true,
                'data' => array_map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'title' => $notification->title,
                        'message' => $notification->message,
                        'type' => $notification->type,
                        'link' => $notification->link,
                        'created_at' => format_datetime_br($notification->created_at),
                        'time_ago' => time_ago_br($notification->created_at),
                    ];
                }, $notifications),
                'count' => count($notifications),
            ], 200);
        });
    }

    public function unreadCount()
    {
        return $this->withAuthenticatedEmployee(function (object $employee) {
            $count = $this->notificationService->countUnread($employee->id);

            return $this->respond([
                'success' => true,
                'data' => [
                    'unread_count' => $count,
                ],
            ], 200);
        });
    }

    public function markAsRead($id = null)
    {
        return $this->withAuthenticatedEmployee(function (object $employee) use ($id) {
            $success = $this->notificationService->markAsRead($id, $employee->id);

            if (! $success) {
                return $this->fail('Notificação não encontrada.', 404);
            }

            return $this->respond([
                'success' => true,
                'message' => 'Notificação marcada como lida.',
            ], 200);
        });
    }

    public function markAllAsRead()
    {
        return $this->withAuthenticatedEmployee(function (object $employee) {
            $count = $this->notificationService->markAllAsRead($employee->id);

            return $this->respond([
                'success' => true,
                'message' => "{$count} notificação(ões) marcada(s) como lida(s).",
                'count' => $count,
            ], 200);
        });
    }

    public function delete($id = null)
    {
        return $this->withAuthenticatedEmployee(function (object $employee) use ($id) {
            $success = $this->notificationService->delete($id, $employee->id);

            if (! $success) {
                return $this->fail('Notificação não encontrada.', 404);
            }

            return $this->respondDeleted([
                'success' => true,
                'message' => 'Notificação excluída.',
            ]);
        });
    }

    public function deleteAllRead()
    {
        return $this->withAuthenticatedEmployee(function (object $employee) {
            $count = $this->notificationService->deleteAllRead($employee->id);

            return $this->respond([
                'success' => true,
                'message' => "{$count} notificação(ões) excluída(s).",
                'count' => $count,
            ], 200);
        });
    }

    public function show($id = null)
    {
        return $this->withAuthenticatedEmployee(function (object $employee) use ($id) {
            $notification = $this->notificationModel->find($id);

            if (
                ! $notification
                || ((int) ($notification->user_id ?? $notification->employee_id ?? 0)) !== (int) $employee->id
            ) {
                return $this->fail('Notificação não encontrada.', 404);
            }

            if (! $notification->read) {
                $this->notificationService->markAsRead($id, $employee->id);
                $notification->read = true;
                $notification->read_at = date('Y-m-d H:i:s');
            }

            return $this->respond([
                'success' => true,
                'data' => [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'type' => $notification->type,
                    'link' => $notification->link,
                    'read' => $notification->read,
                    'read_at' => $notification->read_at ? format_datetime_br($notification->read_at) : null,
                    'created_at' => format_datetime_br($notification->created_at),
                    'time_ago' => time_ago_br($notification->created_at),
                ],
            ], 200);
        });
    }

    private function withAuthenticatedEmployee(callable $callback): ResponseInterface
    {
        $employee = $this->requireAuth();
        if ($employee instanceof ResponseInterface) {
            return $employee;
        }

        return $callback($employee);
    }
}
