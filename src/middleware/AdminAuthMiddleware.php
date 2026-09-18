<?php

declare(strict_types=1);

namespace LycheeAdmin\middleware;

use Closure;
use Lychee\auth\SaToken;
use Lychee\http\MiddlewareInterface;
use Lychee\http\Request;
use Lychee\http\Response;

/**
 * 后台鉴权中间件。
 *
 * 仅对 /admin 前缀的请求进行鉴权，其余请求直接放行。
 *
 * 权限策略：
 *   - 未登录 → 401 / 重定向登录页
 *   - 超级管理员（is_super）→ 直接放行
 *   - 普通管理员 → 按 token 中的 permissions 校验当前路由所需权限码
 */
class AdminAuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path;

        // 仅对 /admin 前缀的请求进行鉴权，其余请求直接放行
        if (!str_starts_with($path, '/admin')) {
            return $next($request);
        }

        // 登录相关路由放行
        if ($path === '/admin/login' || $path === '/admin/logout') {
            return $next($request);
        }

        /** @var SaToken $saToken */
        $saToken = app(SaToken::class);

        if (!$saToken->isLogin()) {
            return $this->unauthorized($request, '请先登录');
        }

        $extra = $saToken->getExtra();

        // 超级管理员直接放行
        if (!empty($extra['is_super'])) {
            return $next($request);
        }

        // 普通管理员：校验路由所需权限
        $required = $this->resolvePermissionCode($request);
        if ($required !== null) {
            $permissions = (array) ($extra['permissions'] ?? []);
            if (!in_array($required, $permissions, true)) {
                return $this->unauthorized($request, '无权访问该资源');
            }
        }

        return $next($request);
    }

    /**
     * 根据请求路径与方法解析所需权限码。
     *
     * 返回 null 表示该路由无需权限（如仪表盘、通知中心）。
     */
    protected function resolvePermissionCode(Request $request): ?string
    {
        $path     = $request->path;
        $method   = strtoupper((string) $request->getMethod());
        $segments = explode('/', trim($path, '/'));

        if (count($segments) < 2 || $segments[0] !== 'admin') {
            return null;
        }

        $resource = $segments[1];

        // 仪表盘、通知中心等通用页面无需权限
        if ($resource === '' || $resource === 'dashboard' || $resource === 'notifications') {
            return null;
        }

        // /admin/{resource}
        if (count($segments) === 2) {
            return $method === 'POST'
                ? "system:{$resource}:create"
                : "system:{$resource}:list";
        }

        // /admin/{resource}/data | /admin/{resource}/create | /admin/{resource}/{id}
        if (count($segments) === 3) {
            return match ($segments[2]) {
                'data'   => "system:{$resource}:list",
                'create' => "system:{$resource}:create",
                default  => match ($method) {
                    'PUT'    => "system:{$resource}:update",
                    'DELETE' => "system:{$resource}:delete",
                    default  => "system:{$resource}:list",
                },
            };
        }

        // /admin/{resource}/{id}/edit
        if (count($segments) === 4 && $segments[3] === 'edit') {
            return "system:{$resource}:update";
        }

        return null;
    }

    /**
     * 未授权响应。
     */
    protected function unauthorized(Request $request, string $message): Response
    {
        // AJAX 请求返回 JSON
        if ($this->isAjax($request)) {
            return new \Lychee\http\JsonResponse([
                'errno' => 0,
                'code'  => 403,
                'msg'   => $message,
                'data'  => null,
            ], 403);
        }

        // 页面请求重定向到登录页
        return Response::redirect('/admin/login');
    }

    protected function isAjax(Request $request): bool
    {
        $xhr = $request->header('X-Requested-With', '');

        return strtolower((string) $xhr) === 'xmlhttprequest';
    }
}
