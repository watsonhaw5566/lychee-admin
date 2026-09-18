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
 * 登录页和登录接口放行。
 *
 * 未登录或非 super_admin 时：
 *   - AJAX 请求返回 401 JSON
 *   - 页面请求重定向到登录页
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

        // super_admin 校验：从 token extra 中读取 is_super 标记
        $extra = $saToken->getExtra();
        if (empty($extra['is_super'])) {
            return $this->unauthorized($request, '无权访问后台');
        }

        return $next($request);
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
                'code'  => 401,
                'msg'   => $message,
                'data'  => null,
            ], 401);
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
