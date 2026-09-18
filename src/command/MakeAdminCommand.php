<?php

declare(strict_types=1);

namespace LycheeAdmin\command;

use Lychee\console\Command;
use Lychee\console\Input;
use Lychee\console\Output;
use Lychee\console\input\Argument;
use RuntimeException;

/**
 * 创建 Admin 资源命令。
 *
 * 生成一个继承 AdminResource 的资源类，自动获得 CRUD 后台界面。
 * 若对应的 Model 不存在，会同时创建一个空的 Model 类。
 *
 * 用法：
 *   php lee make:admin Article                    # 在 app/admin/ 下生成 ArticleAdmin.php
 *   php lee make:admin Article --force            # 覆盖已存在的资源文件
 *   php lee make:admin Article --model=\\App\\model\\Article  # 指定模型类名
 */
class MakeAdminCommand extends Command
{
    protected string $name = 'make:admin';

    protected string $description = 'Create a new admin resource class';

    protected function configure(): void
    {
        $this->setName($this->name);
        $this->setDescription($this->description);

        $this->addArgument('name', Argument::REQUIRED, 'The name of the resource (e.g. Article)');
        $this->addOption('model', 'm', null, 'The model class name (e.g. App\\model\\Article)', null);
        $this->addOption('force', 'f', null, 'Overwrite the resource file if it already exists', false);
    }

    protected function execute(Input $input, Output $output): int
    {
        $name = trim((string) $input->getArgument('name'));

        if ($name === '') {
            $output->writeln('<error>Resource name cannot be empty.</error>');

            return 1;
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            $output->writeln('<error>Resource name may only contain letters, numbers and underscores.</error>');

            return 1;
        }

        // 首字母大写规范化，统一去掉可能的 Admin 后缀
        $name      = ucfirst($name);
        $modelName = preg_replace('/Admin$/', '', $name);
        $className = $modelName . 'Admin';

        $appNamespace = $this->app->getNamespace();

        // 模型类名：优先使用 --model 选项，否则用 {appNamespace}\model\{Model}
        $modelClass = $input->getOption('model');
        if (empty($modelClass)) {
            $modelClass = $appNamespace . '\\model\\' . $modelName;
        }

        $adminPath = app_path('admin') . $className . '.php';
        $force     = (bool) $input->getOption('force');

        // 资源文件已存在且未指定 --force 则跳过，不覆盖业务代码
        if (file_exists($adminPath) && !$force) {
            $output->writeln("<comment>Skipped admin resource:</comment> {$className}.php (already exists, use --force to overwrite)");

            return 0;
        }

        // 若 Model 不存在，同时创建一个空的 Model 类（Model 永远不覆盖）
        $modelPath = app_path('model') . $modelName . '.php';
        if (!file_exists($modelPath)) {
            $this->writeStub($modelPath, 'model.stub', [
                '{{namespace}}' => $appNamespace,
                '{{model}}'     => $modelName,
            ]);
            $output->writeln("<info>Created model:</info>      {$modelName}.php");
        } else {
            $output->writeln("<comment>Skipped model:</comment>      {$modelName}.php (already exists)");
        }

        // 生成 Admin 资源类
        $this->writeStub($adminPath, 'admin.stub', [
            '{{namespace}}'  => $appNamespace . '\\admin',
            '{{class}}'      => $className,
            '{{model}}'      => $modelName,
            '{{modelClass}}' => $modelClass,
            '{{title}}'      => $modelName,
        ]);

        if ($force && file_exists($adminPath)) {
            $output->writeln("<info>Overwritten admin resource:</info> {$className}.php");
        } else {
            $output->writeln("<info>Created admin resource:</info> {$className}.php");
        }
        $output->writeln("  Path:  {$adminPath}");
        $output->writeln("  Model: {$modelClass}");
        $output->writeln('');
        $output->writeln('<comment>Next step: register the resource in config/admin.php</comment>');
        $output->writeln("  'resources' => [\\{$appNamespace}\\admin\\{$className}::class]");

        return 0;
    }

    /**
     * 读取 stub 模板并替换占位符后写入目标文件。
     *
     * @param array<string, string> $replace
     */
    private function writeStub(string $path, string $stub, array $replace): void
    {
        $stubPath = dirname(__DIR__) . '/stubs/' . $stub;
        $content  = file_get_contents($stubPath);

        if ($content === false) {
            throw new RuntimeException("Failed to read stub file: {$stubPath}");
        }

        $content = strtr($content, $replace);

        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Failed to create directory: {$directory}");
        }

        file_put_contents($path, $content);
    }
}
