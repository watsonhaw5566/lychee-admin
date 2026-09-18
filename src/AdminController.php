<?php

declare(strict_types=1);

namespace LycheeAdmin;

use Lychee\auth\SaToken;
use Lychee\http\Controller;
use Lychee\http\JsonResponse;
use Lychee\http\Response;
use Lychee\routing\Route;
use LycheeAdmin\AdminManager;
use LycheeAdmin\AdminResource;

/**
 * 后台通用 CRUD 控制器。
 *
 * 通过 {resource} 路由参数动态匹配已注册的 AdminResource，
 * 统一处理列表、新增、编辑、删除等操作，无需为每个模型编写控制器。
 *
 * 页面请求返回 HTML（Twig 渲染），数据请求返回 JSON（AJAX）。
 */
class AdminController extends Controller
{
    protected ?AdminManager $adminManager = null;
    protected ?SaToken $saToken = null;

    /**
     * 初始化：从容器解析 AdminManager 与 SaToken。
     */
    protected function initialize(): void
    {
        $this->adminManager = $this->app->get(AdminManager::class);
        $this->saToken      = $this->app->get(SaToken::class);
    }

    // ── 仪表盘与登录 ──────────────────────────────────────────────

    #[Route('/admin')]
    public function dashboard(): Response
    {
        return $this->render('@admin/layout', [
            'menu'  => $this->adminManager->getMenu(),
            'title' => 'Lychee Admin',
        ]);
    }

    #[Route('/admin/dashboard')]
    public function dashboardContent(): Response
    {
        $stats = [
            'admin' => model\Admin::count(),
            'role'  => model\Role::count(),
            'menu'  => model\Menu::count(),
        ];

        return $this->render('@admin/dashboard', [
            'menu'  => $this->adminManager->getMenu(),
            'title' => '仪表盘',
            'stats' => $stats,
        ]);
    }

    #[Route('/admin/login')]
    public function loginPage(): Response
    {
        return $this->render('@admin/login', ['title' => '登录']);
    }

    #[Route('/admin/login', 'POST')]
    public function login(): JsonResponse
    {
        $username = (string) $this->request->post('username', '');
        $password = (string) $this->request->post('password', '');

        if ($username === '' || $password === '') {
            return $this->fail('用户名或密码不能为空');
        }

        $model = $this->adminManager->get(model\Admin::class)?->newModel();
        if ($model === null) {
            return $this->fail('管理员模型未注册');
        }

        $user = $model->where('username', $username)->find();
        if (!$user || !password_verify($password, (string) $user->password)) {
            return $this->fail('用户名或密码错误');
        }

        if ((int) $user->status !== 1) {
            return $this->fail('账号已被禁用');
        }

        $token = $this->saToken->login((int) $user->id, [
            'username' => $user->username,
            'is_super' => (int) $user->is_super === 1,
        ]);

        // 将 token 写入 cookie，供后续页面跳转自动携带
        $config    = $this->app->get('config');
        $cookieName = (string) $config->get('satoken.token_cookie_name', 'satoken');
        $timeout   = (int) $config->get('satoken.timeout', 86400 * 7);
        $minutes   = (int) ceil($timeout / 60);

        return $this->success(['token' => $token], '登录成功')
            ->cookie($cookieName, $token, $minutes, '/', null, false, true, 'Lax');
    }

    #[Route('/admin/logout', 'POST')]
    public function logout(): JsonResponse
    {
        $this->saToken->logout();

        $cookieName = (string) $this->app->get('config')->get('satoken.token_cookie_name', 'satoken');

        return $this->success(null, '退出成功')
            ->withoutCookie($cookieName);
    }

    // ── 消息通知 ──────────────────────────────────────────────────

    /**
     * 消息通知列表（供 pear-admin messageCenter 使用）。
     *
     * 按通知类型分组为标签页：通知 / 待办 / 系统。
     */
    #[Route('/admin/notifications')]
    public function notifications(): JsonResponse
    {
        $adminId = $this->saToken->getCurrentLoginId();

        $rows = model\AdminNotification::where('admin_id', $adminId)
            ->order('id', 'desc')
            ->limit(50)
            ->select()
            ->toArray();

        // 类型 → 中文标签
        $typeLabels = [
            'notice' => '通知',
            'todo'   => '待办',
            'system' => '系统',
        ];

        $grouped = [];
        foreach ($rows as $row) {
            $type  = (string) ($row['type'] ?? 'notice');
            $label = $typeLabels[$type] ?? '通知';

            if (!isset($grouped[$type])) {
                $grouped[$type] = [
                    'title'    => $label,
                    'children' => [],
                ];
            }

            $grouped[$type]['children'][] = [
                'id'      => (int) $row['id'],
                'title'   => (string) $row['title'],
                'context' => (string) $row['content'],
                'form'    => '',
                'time'    => $this->formatTime((string) ($row['create_time'] ?? '')),
                'avatar'  => '',
            ];
        }

        // 保证固定顺序：通知、待办、系统
        $ordered = [];
        foreach (['notice', 'todo', 'system'] as $type) {
            if (isset($grouped[$type])) {
                $ordered[] = $grouped[$type];
            }
        }

        return $this->success($ordered);
    }

    /**
     * 将时间格式化为相对时间描述。
     */
    private function formatTime(string $time): string
    {
        if ($time === '') {
            return '';
        }

        $ts  = strtotime($time);
        $diff = time() - $ts;

        if ($diff < 60) {
            return '刚刚';
        }
        if ($diff < 3600) {
            return (int) ($diff / 60) . ' 分钟前';
        }
        if ($diff < 86400) {
            return (int) ($diff / 3600) . ' 小时前';
        }
        if ($diff < 86400 * 7) {
            return (int) ($diff / 86400) . ' 天前';
        }

        return date('Y-m-d', $ts);
    }

    // ── 资源列表 ──────────────────────────────────────────────────

    #[Route('/admin/{resource}')]
    public function index(string $resource): Response
    {
        $admin = $this->resolveResource($resource);
        if ($admin === null) {
            return $this->errorPage("资源 [{$resource}] 未注册");
        }

        return $this->render('@admin/crud/list', [
            'menu'  => $this->adminManager->getMenu(),
            'admin' => $admin,
            'title' => $admin->getTitle(),
        ]);
    }

    #[Route('/admin/{resource}/data')]
    public function data(string $resource): JsonResponse
    {
        $admin = $this->resolveResource($resource);
        if ($admin === null) {
            return $this->fail("资源 [{$resource}] 未注册", 404);
        }

        try {
            $current  = (int) $this->request->param('page', 1);
            $pageSize = (int) $this->request->param('limit', 20);
            $current  = max(1, $current);
            $pageSize = max(1, min(200, $pageSize));

            $model = $admin->newModel();
            $query = $model->db();

            // 搜索
            foreach ($admin->getSearchFields() as $field) {
                $keyword = (string) $this->request->param($field, '');
                if ($keyword !== '') {
                    $query->whereLike($field, "%{$keyword}%");
                }
            }

            // 筛选
            foreach ($admin->getFilterFields() as $field) {
                $value = $this->request->param($field, '');
                if ($value !== '') {
                    $query->where($field, $value);
                }
            }

            $query = $admin->applyIndexQuery($query);

            foreach ($admin->getDefaultOrder() as $field => $direction) {
                $query->order($field, $direction);
            }

            $paginator = $query->paginate(['list_rows' => $pageSize, 'page' => $current]);
            $list      = [];
            foreach ($paginator->items() as $item) {
                $list[] = $admin->applyFormatList($item);
            }

            return new JsonResponse([
                'code'  => 0,
                'msg'   => '',
                'count' => $paginator->total(),
                'data'  => $list,
            ]);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    // ── 新增 ──────────────────────────────────────────────────────

    #[Route('/admin/{resource}/create')]
    public function create(string $resource): Response
    {
        $admin = $this->resolveResource($resource);
        if ($admin === null) {
            return $this->errorPage("资源 [{$resource}] 未注册");
        }

        if (!$admin->canCreate()) {
            return $this->errorPage('无权新增');
        }

        return $this->render('@admin/crud/form', [
            'menu'   => $this->adminManager->getMenu(),
            'admin'  => $admin,
            'title'  => '新增' . $admin->getTitle(),
            'action' => 'create',
            'data'   => [],
        ]);
    }

    #[Route('/admin/{resource}', 'POST')]
    public function store(string $resource): JsonResponse
    {
        $admin = $this->resolveResource($resource);
        if ($admin === null) {
            return $this->fail("资源 [{$resource}] 未注册", 404);
        }

        if (!$admin->canCreate()) {
            return $this->fail('无权新增', 403);
        }

        try {
            $data = $this->request->post();
            $data = $admin->applyBeforeSave($data);

            $model = $admin->newModel();
            $result = $model->create($data);

            $admin->applyAfterSave($result);

            return $this->success($result, '新增成功');
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    // ── 编辑 ──────────────────────────────────────────────────────

    #[Route('/admin/{resource}/{id}/edit')]
    public function edit(string $resource, int $id): Response
    {
        $admin = $this->resolveResource($resource);
        if ($admin === null) {
            return $this->errorPage("资源 [{$resource}] 未注册");
        }

        if (!$admin->canUpdate()) {
            return $this->errorPage('无权编辑');
        }

        $model = $admin->newModel();
        $data  = $model->find($id);

        if (!$data) {
            return $this->errorPage('数据不存在');
        }

        return $this->render('@admin/crud/form', [
            'menu'   => $this->adminManager->getMenu(),
            'admin'  => $admin,
            'title'  => '编辑' . $admin->getTitle(),
            'action' => 'edit',
            'data'   => $data->toArray(),
        ]);
    }

    #[Route('/admin/{resource}/{id}', 'PUT')]
    public function update(string $resource, int $id): JsonResponse
    {
        $admin = $this->resolveResource($resource);
        if ($admin === null) {
            return $this->fail("资源 [{$resource}] 未注册", 404);
        }

        if (!$admin->canUpdate()) {
            return $this->fail('无权编辑', 403);
        }

        try {
            $model = $admin->newModel();
            $info  = $model->find($id);

            if (!$info) {
                return $this->fail('数据不存在', 404);
            }

            $data = $this->request->post();
            $data = $admin->applyBeforeSave($data);

            $info->save($data);

            $admin->applyAfterSave($info);

            return $this->success($info, '更新成功');
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    // ── 删除 ──────────────────────────────────────────────────────

    #[Route('/admin/{resource}/{id}', 'DELETE')]
    public function destroy(string $resource, int $id): JsonResponse
    {
        $admin = $this->resolveResource($resource);
        if ($admin === null) {
            return $this->fail("资源 [{$resource}] 未注册", 404);
        }

        if (!$admin->canDelete()) {
            return $this->fail('无权删除', 403);
        }

        try {
            $model = $admin->newModel();
            $info  = $model->find($id);

            if (!$info) {
                return $this->fail('数据不存在', 404);
            }

            $admin->applyBeforeDelete($info);
            $info->delete();

            return $this->success(null, '删除成功');
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    // ── 辅助方法 ──────────────────────────────────────────────────

    /**
     * 根据资源标识解析 AdminResource 实例。
     *
     * 资源标识可以是模型类名（含命名空间）或模型短名。
     */
    protected function resolveResource(string $resource): ?AdminResource
    {
        // 尝试直接按类名查找
        if ($this->adminManager->has($resource)) {
            return $this->adminManager->get($resource);
        }

        // 尝试用短名匹配（取命名空间最后一段）
        foreach ($this->adminManager->all() as $model => $_class) {
            $short = substr($model, strrpos($model, '\\') + 1);
            if ($short === $resource || strtolower($short) === strtolower($resource)) {
                return $this->adminManager->get($model);
            }
        }

        return null;
    }

    /**
     * 渲染 Twig 模板并返回 HTML 响应。
     */
    protected function render(string $template, array $data = []): Response
    {
        $html = view($template, $data);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * 渲染错误页面。
     */
    protected function errorPage(string $message): Response
    {
        $html = view('@admin/error', [
            'menu'    => $this->adminManager->getMenu(),
            'title'   => '错误',
            'message' => $message,
        ]);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
