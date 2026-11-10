<?php

namespace App\Bank;

use App\Bank\ProcessingException as RuntimeException;
use Symfony\Component\Process\Process;

class DocumentParser
{
    /** Operator-facing readiness. Customer exceptions stay on {@see ParserConfigurationException}. */
    public function diagnose(): array
    {
        $issues = [];
        $python = trim((string) config('bank.python'));
        $version = null;
        if ($python === '') {
            $issues[] = 'Python path is empty.';
        } else {
            try {
                $process = new Process([$python, '-c', 'import sys; print(sys.version.split()[0])']);
                $process->setTimeout(15);
                $process->run();
            } catch (\Throwable) {
                $process = null;
            }
            $version = $process ? trim($process->getOutput()) : '';
            if (! $process || ! $process->isSuccessful() || $version === '') {
                $issues[] = 'Python could not be started.';
                $version = null;
            } elseif (version_compare($version, '3.10.0', '<')) {
                $issues[] = 'Python '.$version.' is older than 3.10, which PyMuPDF and Pillow need.';
            } else {
                try {
                    $import = new Process([$python, '-c', 'import pymupdf']);
                    $import->setTimeout(20);
                    $import->run();
                    $imported = $import->isSuccessful();
                } catch (\Throwable) {
                    $imported = false;
                }
                if (! $imported) {
                    $issues[] = 'PyMuPDF is not installed for this Python.';
                }
            }
        }
        $sandbox = (string) config('bank.sandbox');
        $bwrap = $this->executable('bwrap');
        $warning = null;
        if ($sandbox === 'none') {
            $warning = 'The parser sandbox is off. The worker is not isolated with bubblewrap, so it can see more of this server. Keep bubblewrap on unless this host cannot run it.';
        } elseif (app()->environment('production') && $sandbox !== 'bwrap') {
            $issues[] = 'Production parsing requires bubblewrap, and it is not selected. Choose No OS sandbox only if you accept running without that isolation.';
        } elseif ($sandbox === 'bwrap' && ! $bwrap) {
            $issues[] = 'Bubblewrap is selected, but bwrap was not found.';
        } elseif (! $bwrap) {
            $warning = 'Bubblewrap is not installed here. Production will refuse to read documents until bwrap is installed or an admin saves No OS sandbox.';
        }

        return ['ok' => $issues === [], 'issues' => $issues, 'python' => $version, 'sandbox' => $sandbox === 'none' ? 'none' : 'bwrap', 'warning' => $warning];
    }

    public function parse(string $path, string $output): array
    {
        if ($this->diagnose()['issues'] !== []) {
            throw new ParserConfigurationException;
        }
        $args = [config('bank.python'), '-I', base_path('python-worker/parse_document.py'), $path, $output, (string) config('bank.max_pages')];
        if (app()->environment('production') && config('bank.sandbox') === 'bwrap') {
            if (! is_dir($output)) {
                mkdir($output, 0700, true);
            }
            $prefix = dirname(dirname(config('bank.python')));
            $args = ['bwrap', '--unshare-all', '--die-with-parent', '--new-session', '--ro-bind', '/usr', '/usr', '--ro-bind', '/lib', '/lib'];
            if (is_dir('/lib64')) {
                $args = array_merge($args, ['--ro-bind', '/lib64', '/lib64']);
            }
            if (! str_starts_with($prefix, '/usr')) {
                $args = array_merge($args, ['--ro-bind', $prefix, $prefix]);
            }
            $args = array_merge($args, ['--dir', '/input', '--ro-bind', $path, '/input/document', '--ro-bind', base_path('python-worker/parse_document.py'), '/worker.py', '--bind', $output, '/output', '--dev', '/dev', '--proc', '/proc', '--tmpfs', '/tmp', '--chdir', '/output', config('bank.python'), '-I', '/worker.py', '/input/document', '/output', (string) config('bank.max_pages')]);
        }
        $env = array_fill_keys(array_keys(array_merge(getenv(), $_SERVER, $_ENV)), false);
        $env = array_replace($env, ['PATH' => '/usr/bin:/bin', 'LANG' => 'C.UTF-8', 'HOME' => '/nonexistent', 'PYTHONNOUSERSITE' => '1']);
        $p = new Process($args, null, $env);
        $p->setTimeout(config('bank.parser_timeout'));
        $p->run();
        $data = json_decode($p->getOutput(), true);
        if ($p->isSuccessful() && ($data['manifest'] ?? null) === 'manifest.json' && is_file($output.'/manifest.json') && filesize($output.'/manifest.json') <= 24 * 1024 * 1024) {
            $data = json_decode(file_get_contents($output.'/manifest.json'), true);
        }
        if (! $p->isSuccessful() || ! isset($data['pages'])) {
            throw new RuntimeException($data['error'] ?? 'Parser unavailable or resource limit exceeded; contact the operator');
        }

        return $data;
    }

    private function executable(string $bin): bool
    {
        if ($bin === '') {
            return false;
        }
        if (str_contains($bin, DIRECTORY_SEPARATOR)) {
            return is_executable($bin);
        }
        foreach (explode(':', (string) getenv('PATH')) as $dir) {
            if ($dir !== '' && is_executable($dir.DIRECTORY_SEPARATOR.$bin)) {
                return true;
            }
        }

        return false;
    }
}
