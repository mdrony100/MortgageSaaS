<?php

namespace App\Bank;

use App\Bank\ProcessingException as RuntimeException;
use Symfony\Component\Process\Process;

class DocumentParser
{
    public function parse(string $path, string $output): array
    {
        $args = [config('bank.python'), '-I', base_path('python-worker/parse_document.py'), $path, $output, (string) config('bank.max_pages')];
        if (app()->environment('production')) {
            if (config('bank.sandbox') !== 'bwrap') {
                throw new RuntimeException('Production parser requires the configured OS network/filesystem sandbox');
            }
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
}
