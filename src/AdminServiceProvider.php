<?php

declare(strict_types=1);

namespace LycheeAdmin;

use Lychee\config\Config;
use Lychee\console\Application as ConsoleApplication;
use Lychee\container\Container;
use Lychee\migration\MigrationManager;
use Lychee\plugin\PluginInterface;
use Lychee\routing\Router;
use Lychee\view\View;
use LycheeAdmin\command\PublishCommand;
use LycheeAdmin\command\MakeAdminCommand;
use LycheeAdmin\middleware\AdminAuthMiddleware;
use LycheeAdmin\resource\MenuAdmin;
use LycheeAdmin\resource\PermissionAdmin;
use LycheeAdmin\resource\RoleAdmin;
use LycheeAdmin\resource\UserAdmin;

/**
 * lychee-admin 插件入口。
 *
 * 负责向框架注入后台管理能力：
 *   - 绑定 AdminManager 到容器
 *   - 注册 AdminController 路由
 *   - 注册 Twig 视图命名空间 @admin
 *   - 注册内置资源（用户、角色、菜单、权限）
 *   - 注册 super_admin 鉴权中间件
 */
class AdminServiceProvider implements PluginInterface
{
    public function register(Container $container): void
    {
        // 绑定 AdminManager
        $container->singleton(AdminManager::class, function (): AdminManager {
            return new AdminManager();
        });
        $container->instance('admin.manager', $container->get(AdminManager::class));
    }

    public function boot(Container $container): void
    {
        $this->registerRoutes($container);
        $this->registerViews($container);
        $this->registerResources($container);
        $this->registerMiddleware($container);
        $this->registerMigrations($container);
        $this->registerCommands($container);
    }

    /**
     * 注册后台路由。
     */
    private function registerRoutes(Container $container): void
    {
        /** @var Router $router */
        $router = $container->get(Router::class);
        $router->registerController(AdminController::class);
    }

    /**
     * 注册后台 Twig 视图命名空间 @admin。
     */
    private function registerViews(Container $container): void
    {
        if (!$container->bound(View::class)) {
            return;
        }

        /** @var View $view */
        $view = $container->get(View::class);
        $viewPath = dirname(__DIR__) . '/view';

        if (is_dir($viewPath)) {
            $view->getTwig()->getLoader()->addPath($viewPath, 'admin');
        }
    }

    /**
     * 注册内置资源与用户自定义资源。
     *
     * 用户自定义资源通过 config/admin.php 的 resources 配置声明，
     * 框架启动时自动注册，无需编写插件入口类。
     *
     * 注册以模型类名为 key，用户资源会覆盖内置同名模型资源。
     */
    private function registerResources(Container $container): void
    {
        /** @var AdminManager $manager */
        $manager = $container->get(AdminManager::class);

        // 内置资源
        $manager->register(UserAdmin::class);
        $manager->register(RoleAdmin::class);
        $manager->register(MenuAdmin::class);
        $manager->register(PermissionAdmin::class);

        // 用户自定义资源（来自 config/admin.php 的 resources 配置）
        $userResources = (array) $container->get('config')->get('admin.resources', []);
        foreach ($userResources as $resourceClass) {
            $manager->register((string) $resourceClass);
        }
    }

    /**
     * 注册 super_admin 鉴权中间件到全局中间件列表。
     */
    private function registerMiddleware(Container $container): void
    {
        /** @var Config $config */
        $config = $container->get('config');

        $existing = (array) $config->get('middleware', []);

        if (!in_array(AdminAuthMiddleware::class, $existing, true)) {
            $existing[] = AdminAuthMiddleware::class;
            $config->set(['middleware' => $existing]);
        }
    }

    /**
     * 注册插件的迁移和 seed 路径到 MigrationManager。
     */
    private function registerMigrations(Container $container): void
    {
        if (!$container->bound(MigrationManager::class)) {
            return;
        }

        /** @var MigrationManager $manager */
        $manager = $container->get(MigrationManager::class);

        $basePath = dirname(__DIR__);

        $manager->addMigrationPath($basePath . '/database/migrations');
        $manager->addSeederPath($basePath . '/database/seeders');
    }

    /**
     * 注册控制台命令。
     */
    private function registerCommands(Container $container): void
    {
        if (!$container->bound(ConsoleApplication::class)) {
            return;
        }

        /** @var ConsoleApplication $console */
        $console = $container->get(ConsoleApplication::class);
        $console->addCommand(PublishCommand::class);
        $console->addCommand(MakeAdminCommand::class);
    }
}
