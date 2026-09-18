<?php

declare(strict_types=1);

namespace LycheeAdmin\command;

use Lychee\console\Command;
use Lychee\console\Input;
use Lychee\console\Output;

/**
 * 发布 lychee-admin 静态资源到 public 目录。
 *
 * 用法：
 *   php lee admin:publish              # 复制静态资源到 public/lychee/
 *   php lee admin:publish --force      # 强制覆盖已存在的文件
 *   php lee admin:publish --link       # 使用符号链接（开发环境推荐）
 */
class PublishCommand extends Command
{
    protected string $name = 'admin:publish';

    protected string $description = 'Publish lychee-admin static assets to public directory';

    protected function configure(): void
    {
        $this->setName($this->name);
        $this->setDescription($this->description);

        $this->addOption('force', 'f', null, 'Overwrite existing files', false);
        $this->addOption('link', 'l', null, 'Create symlink instead of copying', false);
    }

    protected function execute(Input $input, Output $output): int
    {
        $source = dirname(__DIR__, 2) . '/asset';
        $target = $this->getPublicPath() . 'lychee';

        if (!is_dir($source)) {
            $output->writeln("<error>Source directory not found: {$source}</error>");

            return 1;
        }

        $force = (bool) $input->getOption('force');
        $link  = (bool) $input->getOption('link');

        // 目标已存在时的处理
        if (file_exists($target)) {
            if ($link) {
                // 如果目标不是符号链接，需要先删除
                if (!is_link($target)) {
                    if (!$force) {
                        $output->writeln("<error>Target directory already exists and is not a symlink. Use --force to overwrite.</error>");

                        return 1;
                    }
                    $this->removeDirectory($target);
                }
            } else {
                if (!$force && is_dir($target)) {
                    $output->writeln("<comment>Target directory already exists: {$target}</comment>");
                    $output->writeln("<comment>Use --force to overwrite existing files.</comment>");

                    return 1;
                }
            }
        }

        if ($link) {
            return $this->createSymlink($source, $target, $output);
        }

        return $this->copyDirectory($source, $target, $output);
    }

    /**
     * 创建符号链接。
     */
    private function createSymlink(string $source, string $target, Output $output): int
    {
        // 如果目标是已存在的符号链接，先删除
        if (is_link($target)) {
            unlink($target);
        }

        $result = @symlink($source, $target);

        if (!$result) {
            $output->writeln("<error>Failed to create symlink. Please check permissions or run with sufficient privileges.</error>");

            return 1;
        }

        $output->writeln("<info>Symlink created successfully.</info>");
        $output->writeln("  Source: {$source}");
        $output->writeln("  Target: {$target}");

        return 0;
    }

    /**
     * 复制目录。
     */
    private function copyDirectory(string $source, string $target, Output $output): int
    {
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $count  = 0;
        $source = rtrim($source, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $target = rtrim($target, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $relativePath = substr($item->getPathname(), strlen($source));
            $targetPath   = $target . $relativePath;

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }

                continue;
            }

            if (file_exists($targetPath) && !is_writable($targetPath)) {
                $output->writeln("<comment>Skip (not writable): {$relativePath}</comment>");

                continue;
            }

            copy($item->getPathname(), $targetPath);
            $count++;
        }

        $output->writeln("<info>Assets published successfully. ({$count} files)</info>");
        $output->writeln("  Source: {$source}");
        $output->writeln("  Target: {$target}");

        return 0;
    }

    /**
     * 递归删除目录。
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }

    /**
     * 获取项目 public 目录路径。
     */
    private function getPublicPath(): string
    {
        if ($this->app->bound('path.public')) {
            return (string) $this->app->get('path.public');
        }

        // 回退：基于 rootPath 推断
        $rootPath = $this->app->rootPath ?? (dirname(__DIR__, 4) . DIRECTORY_SEPARATOR);

        return $rootPath . 'public' . DIRECTORY_SEPARATOR;
    }
}
