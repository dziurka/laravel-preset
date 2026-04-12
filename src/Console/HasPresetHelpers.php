<?php

namespace Dziurka\LaravelPreset\Console;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

trait HasPresetHelpers
{
    private string $stubsPath;

    private function initStubsPath(): void
    {
        $this->stubsPath = realpath(__DIR__.'/../../stubs');
    }

    private function patchFile(string $path, array $replacements): void
    {
        if (! File::exists($path)) {
            return;
        }

        $content = File::get($path);
        $patched = preg_replace(array_keys($replacements), array_values($replacements), $content);

        if ($patched !== $content) {
            File::put($path, $patched);
        }
    }

    private function runComposer(array $arguments): void
    {
        $this->runProcess(array_merge(['composer'], $arguments));
    }

    private function runProcess(array $command): void
    {
        $process = new Process($command, base_path());
        $process->setTimeout(null);

        $process->run(function (string $type, string $output): void {
            $this->output->write($output);
        });

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Command failed: '.implode(' ', $command));
        }
    }

    private function copyFile(string $stub, string $destination): void
    {
        $source = $this->stubsPath.'/'.$stub;

        if (! File::exists($source)) {
            return;
        }

        File::ensureDirectoryExists(dirname($destination));

        $relative = str_replace(base_path().'/', '', $destination);

        if (File::exists($destination) && ! $this->confirm("  {$relative} already exists. Overwrite?", false)) {
            return;
        }

        File::copy($source, $destination);
        $this->line("  <info>+</info> {$relative}");
    }

    private function copyDirectory(string $stub, string $destination): void
    {
        $source = $this->stubsPath.'/'.$stub;

        if (! File::isDirectory($source)) {
            return;
        }

        File::copyDirectory($source, $destination);

        $relative = str_replace(base_path().'/', '', $destination);
        $this->line("  <info>+</info> {$relative}/");
    }

    private function patchDockerCompose(string $path, string $phpVersion): void
    {
        if (! File::exists($path)) {
            return;
        }

        $nodots = str_replace('.', '', $phpVersion);
        $content = File::get($path);

        $patched = preg_replace(
            ["/(?<=docker\/)[0-9]+\.[0-9]+/", "/(?<=sail-)[0-9]+\.[0-9]+(?=\/app)/"],
            [$phpVersion, $nodots],
            $content,
        );

        if ($patched !== $content) {
            File::put($path, $patched);
        }
    }

    private function patchJustfile(string $path, string $phpVersion): void
    {
        if (! File::exists($path)) {
            return;
        }

        $nodots = str_replace('.', '', $phpVersion);
        $content = File::get($path);

        $patched = preg_replace('/(?<=php)\d+(?=-composer)/', $nodots, $content);

        if ($patched !== $content) {
            File::put($path, $patched);
        }
    }
}
