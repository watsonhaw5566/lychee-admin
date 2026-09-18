<?php

declare(strict_types=1);

namespace LycheeAdmin;

use Lychee\auth\SaToken;
use Lychee\http\Controller;
use Lychee\http\JsonResponse;
use Lychee\http\Response;
use Lychee\http\UploadedFile;
use Lychee\routing\Route;
use LycheeAdmin\AdminManager;
use LycheeAdmin\AdminResource;
use think\Validate;

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
            'menu'  => $this->getMenu(),
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
            'menu'  => $this->getMenu(),
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

        $isSuper = (int) $user->is_super === 1;

        // 非超管用户：从角色加载权限码写入 token，供中间件校验
        $extra = [
            'username' => $user->username,
            'is_super' => $isSuper,
        ];
        if (!$isSuper) {
            $extra['permissions'] = $this->getAdminPermissionCodes((int) $user->id);
        }

        $token = $this->saToken->login((int) $user->id, $extra);

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
     * 富文本编辑器图片上传接口（供 wangEditor 使用）。
     *
     * 返回格式兼容 wangEditor 的 uploadImage：
     *   成功：{ errno: 0, data: { url, alt, href } }
     *   失败：{ errno: 1, message: '...' }
     *
     * 前端通过 MENU_CONF.uploadImage.customInsert 从 res.data 中取 url 插入。
     */
    #[Route('/admin/upload', 'POST')]
    public function upload(): JsonResponse
    {
        $file = $this->request->file('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return new JsonResponse([
                'errno'   => 1,
                'message' => '上传失败：未收到有效文件',
            ]);
        }

        $ext      = $file->extension() ?: 'png';
        $dir      = 'uploads/admin/' . date('Y-m');
        $filename = uniqid() . '.' . $ext;
        $path     = storage()->putFileAs($dir, $file, $filename);

        if ($path === false) {
            return new JsonResponse([
                'errno'   => 1,
                'message' => '文件保存失败',
            ]);
        }

        return new JsonResponse([
            'errno' => 0,
            'data'  => [
                'url'  => $path,
                'alt'  => '',
                'href' => '',
            ],
        ]);
    }

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

    /**
     * 获取管理员拥有的所有权限码（通过角色关联）。
     *
     * @return array<int, string>
     */
    private function getAdminPermissionCodes(int $adminId): array
    {
        $admin = model\Admin::find($adminId);
        if (!$admin || empty($admin->role_ids)) {
            return [];
        }

        $roleIds = (array) $admin->role_ids;
        $roles   = model\Role::whereIn('id', $roleIds)->select();

        $permissionIds = [];
        foreach ($roles as $role) {
            if (!empty($role->permission_ids)) {
                $permissionIds = array_merge($permissionIds, (array) $role->permission_ids);
            }
        }
        $permissionIds = array_unique($permissionIds);

        if (empty($permissionIds)) {
            return [];
        }

        return model\Permission::whereIn('id', $permissionIds)->column('code');
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
            'menu'  => $this->getMenu(),
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
            'menu'   => $this->getMenu(),
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
            $data = $this->handleUploads($admin, $data);

            $error = $this->validateFormData($admin, $data, false);
            if ($error !== null) {
                return $this->fail($error);
            }

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
            'menu'   => $this->getMenu(),
            'admin'  => $admin,
            'title'  => '编辑' . $admin->getTitle(),
            'action' => 'edit',
            'data'   => $this->normalizeFormData($admin, $data->toArray()),
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
            $data = $admin->applyBeforeSave($data, $info);
            $data = $this->handleUploads($admin, $data, $info);

            $error = $this->validateFormData($admin, $data, true);
            if ($error !== null) {
                return $this->fail($error);
            }

            $info->save($data);

            $admin->applyAfterSave($info);

            return $this->success($info, '更新成功');
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    // ── 删除 ──────────────────────────────────────────────────────

    #[Route('/admin/{resource}/batch', 'DELETE')]
    public function batchDestroy(string $resource): JsonResponse
    {
        $admin = $this->resolveResource($resource);
        if ($admin === null) {
            return $this->fail("资源 [{$resource}] 未注册", 404);
        }

        if (!$admin->canDelete()) {
            return $this->fail('无权删除', 403);
        }

        $ids = $this->request->post('ids', []);
        if (!is_array($ids) || empty($ids)) {
            return $this->fail('请选择要删除的数据', 400);
        }

        try {
            $model = $admin->newModel();
            $count = 0;
            foreach ($ids as $id) {
                $info = $model->find((int) $id);
                if (!$info) {
                    continue;
                }
                $admin->applyBeforeDelete($info);
                $info->delete();
                $count++;
            }

            return $this->success(['count' => $count], "成功删除 {$count} 条数据");
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

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
     * 获取按当前用户权限过滤后的菜单。
     *
     * 超管可见全部菜单；普通管理员仅可见拥有 list 权限的资源。
     *
     * @return array<string, array<int, array{title:string, icon:string, model:string}>>
     */
    protected function getMenu(): array
    {
        $menu  = $this->adminManager->getMenu();
        $extra = $this->saToken->getExtra();

        // 超管直接返回全部
        if (!empty($extra['is_super'])) {
            return $menu;
        }

        $permissions = (array) ($extra['permissions'] ?? []);
        $filtered    = [];

        foreach ($menu as $group => $items) {
            $visible = [];
            foreach ($items as $item) {
                $short   = substr($item['model'], strrpos($item['model'], '\\') + 1);
                $code    = 'system:' . strtolower($short) . ':list';
                if (in_array($code, $permissions, true)) {
                    $visible[] = $item;
                }
            }
            if (!empty($visible)) {
                $filtered[$group] = $visible;
            }
        }

        return $filtered;
    }

    /**
     * 根据资源表单字段配置校验数据。
     *
     * 校验规则由 AdminResource 的 $formFields 配置中的 required / rules 自动生成。
     *
     * 编辑时跳过未提交的字段（如留空的密码、未重新上传的文件），
     * 因为这些字段在 beforeSave / handleUploads 中已被移除，保留原值。
     *
     * @return string|null 校验通过返回 null，否则返回拼接后的错误信息
     */
    protected function validateFormData(AdminResource $admin, array $data, bool $isEdit): ?string
    {
        $rules    = [];
        $messages = [];

        foreach ($admin->getFormFields() as $field => $_) {
            $fieldRules = $admin->getFieldRules($field);
            if ($fieldRules === '') {
                continue;
            }

            // 编辑时跳过未提交的字段
            if ($isEdit && !array_key_exists($field, $data)) {
                continue;
            }

            $rules[$field] = $fieldRules;

            if ($admin->isFieldRequired($field)) {
                $label = $admin->getFieldLabel($field);
                $messages[$field . '.require'] = $label . '不能为空';
            }
        }

        if (empty($rules)) {
            return null;
        }

        $validate = new Validate();
        $validate->rule($rules);
        if (!empty($messages)) {
            $validate->message($messages);
        }
        $validate->batch();

        if (!$validate->check($data)) {
            $errors = (array) $validate->getError(true);

            return implode('；', $errors);
        }

        return null;
    }

    /**
     * 处理 image/file 类型字段的文件上传。
     *
     * 若对应字段有上传文件则保存并写入 $data；
     * 若无文件则不在 $data 中包含该字段（编辑时保留原值）。
     *
     * @param array<string, mixed> $data
     * @param object|null          $existing 编辑时的已有模型实例
     * @return array<string, mixed>
     */
    protected function handleUploads(AdminResource $admin, array $data, ?object $existing = null): array
    {
        foreach ($admin->getFormFields() as $field => $config) {
            $type = is_array($config) ? ($config['type'] ?? 'text') : ($config ?? 'text');
            if ($type !== 'image' && $type !== 'file') {
                continue;
            }

            $isMultiple = is_array($config) ? !empty($config['multiple']) : false;

            if ($isMultiple) {
                $files = $this->request->file($field);
                if (is_array($files) && !empty($files)) {
                    $paths = [];
                    foreach ($files as $file) {
                        if ($file instanceof UploadedFile && $file->isValid()) {
                            $ext      = $file->extension() ?: 'bin';
                            $dir      = 'uploads/admin/' . date('Y-m');
                            $filename = uniqid() . '.' . $ext;
                            $path     = storage()->putFileAs($dir, $file, $filename);
                            if ($path !== false) {
                                $paths[] = $path;
                            }
                        }
                    }
                    if (!empty($paths)) {
                        $data[$field] = json_encode($paths, JSON_UNESCAPED_UNICODE);
                    }
                } elseif ($existing !== null) {
                    // 编辑且未上传新文件：移除该字段，保留原值
                    unset($data[$field]);
                }
            } else {
                $file = $this->request->file($field);
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $ext      = $file->extension() ?: 'bin';
                    $dir      = 'uploads/admin/' . date('Y-m');
                    $filename = uniqid() . '.' . $ext;
                    $path     = storage()->putFileAs($dir, $file, $filename);
                    if ($path !== false) {
                        $data[$field] = $path;
                    }
                } elseif ($existing !== null) {
                    // 编辑且未上传新文件：移除该字段，保留原值
                    unset($data[$field]);
                }
            }
        }

        return $data;
    }

    /**
     * 渲染表单前规范化数据：将多文件字段的 JSON 字符串解码为数组，便于模板遍历预览。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function normalizeFormData(AdminResource $admin, array $data): array
    {
        foreach ($admin->getFormFields() as $field => $config) {
            $type = is_array($config) ? ($config['type'] ?? 'text') : ($config ?? 'text');
            if ($type !== 'image' && $type !== 'file') {
                continue;
            }

            $isMultiple = is_array($config) ? !empty($config['multiple']) : false;
            if (!$isMultiple) {
                continue;
            }

            $value = $data[$field] ?? '';
            if (is_string($value) && $value !== '') {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $data[$field] = $decoded;
                }
            }
        }

        return $data;
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
            'menu'    => $this->getMenu(),
            'title'   => '错误',
            'message' => $message,
        ]);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
