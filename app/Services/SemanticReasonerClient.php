<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class SemanticReasonerClient
{
    public function isEnabled(): bool
    {
        return (bool) config('semantic.reasoner.enabled');
    }

    public function reason(string $inputFile, string $outputFile): array
    {
        if (!$this->isEnabled()) {
            return [
                'status' => 'not_configured',
                'output_path' => $inputFile,
                'triple_count' => $this->countTurtleStatements($inputFile),
                'message' => 'Reasoner OWL 2 RL belum diaktifkan; graph RDF dasar tetap dapat disimpan.',
            ];
        }

        $javaConfigured = trim((string) config('semantic.reasoner.java_binary', 'java'));
        $jarConfigured = trim((string) config('semantic.reasoner.jar_path', ''));
        $ontologyConfigured = trim((string) config('semantic.ontology.directory', ''));
        $timeout = max(10, (int) config('semantic.reasoner.timeout', 90));

        if ($javaConfigured === '') {
            throw new RuntimeException('SEMANTIC_JAVA_BINARY kosong.');
        }
        if ($jarConfigured === '' || !is_file($jarConfigured)) {
            throw new RuntimeException('JAR reasoner tidak ditemukan: ' . $jarConfigured);
        }
        if ($ontologyConfigured === '' || !is_dir($ontologyConfigured)) {
            throw new RuntimeException('Direktori ontologi tidak ditemukan: ' . $ontologyConfigured);
        }
        if (!is_file($inputFile)) {
            throw new RuntimeException('File RDF input tidak ditemukan: ' . $inputFile);
        }

        $jar = realpath($jarConfigured);
        $ontologyDirectory = realpath($ontologyConfigured);
        $input = realpath($inputFile);
        if ($jar === false || $ontologyDirectory === false || $input === false) {
            throw new RuntimeException('JAR, folder ontologi, atau input RDF tidak dapat diubah menjadi path absolut.');
        }

        $java = $javaConfigured;
        if (str_contains($javaConfigured, '/') || str_contains($javaConfigured, '\\')) {
            $resolvedJava = realpath($javaConfigured);
            if ($resolvedJava === false || !is_file($resolvedJava)) {
                throw new RuntimeException('Executable Java tidak ditemukan: ' . $javaConfigured);
            }
            $java = $resolvedJava;
        }

        $outputDirectory = dirname($outputFile);
        if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
            throw new RuntimeException('Folder output reasoner tidak dapat dibuat: ' . $outputDirectory);
        }
        $resolvedOutputDirectory = realpath($outputDirectory);
        if ($resolvedOutputDirectory === false) {
            throw new RuntimeException('Folder output reasoner tidak dapat di-resolve: ' . $outputDirectory);
        }
        $output = $resolvedOutputDirectory . DIRECTORY_SEPARATOR . basename($outputFile);
        if (is_file($output) && !unlink($output)) {
            throw new RuntimeException('File output lama tidak dapat dihapus sebelum reasoner berjalan: ' . $output);
        }

        $logsDir = storage_path('logs');
        if (!is_dir($logsDir) && !mkdir($logsDir, 0775, true) && !is_dir($logsDir)) {
            throw new RuntimeException('Folder log tidak dapat dibuat: ' . $logsDir);
        }
        $runId = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $stdoutFile = $logsDir . DIRECTORY_SEPARATOR . 'reasoner_' . $runId . '.stdout.log';
        $stderrFile = $logsDir . DIRECTORY_SEPARATOR . 'reasoner_' . $runId . '.stderr.log';
        $exitFile = $logsDir . DIRECTORY_SEPARATOR . 'reasoner_' . $runId . '.exit.txt';
        $scriptFile = $logsDir . DIRECTORY_SEPARATOR . 'run_reasoner_' . $runId . '.cmd';
        $crashLogTemplate = $logsDir . DIRECTORY_SEPARATOR . 'hs_err_pid_%p.log';

        // The JVM is started by cmd.exe and its own stdout/stderr are redirected to
        // files. This avoids piping JVM output directly through PHP's HTTP worker.
        $javaCommand = $this->quoteBatchArgument($java)
            . ' ' . $this->quoteBatchArgument('-XX:ErrorFile=' . str_replace('%', '%%', $crashLogTemplate))
            . ' -jar ' . $this->quoteBatchArgument($jar)
            . ' ' . $this->quoteBatchArgument($ontologyDirectory)
            . ' ' . $this->quoteBatchArgument($input)
            . ' ' . $this->quoteBatchArgument($output)
            // The HTTP worker launches cmd.exe with a pipe as stdin, while a manual
            // PowerShell run has a console handle. Java does not need stdin here;
            // detach it from the Symfony Process pipe to avoid Windows JVM fast-fail.
            . ' 0<NUL 1>' . $this->quoteBatchArgument($stdoutFile)
            . ' 2>' . $this->quoteBatchArgument($stderrFile);

        $script = implode("\r\n", [
            '@echo off',
            'setlocal',
            $javaCommand,
            'set "REASONER_EXIT=%ERRORLEVEL%"',
            '>' . $this->quoteBatchArgument($exitFile) . ' echo %REASONER_EXIT%',
            'exit /b %REASONER_EXIT%',
            '',
        ]);
        if (file_put_contents($scriptFile, $script) === false) {
            throw new RuntimeException('Skrip peluncur reasoner tidak dapat ditulis: ' . $scriptFile);
        }

        $process = new Process(['cmd.exe', '/d', '/c', $scriptFile], base_path());
        $process->setTimeout($timeout);

        try {
            $process->run();
        } catch (Throwable $e) {
            Log::error('Peluncur reasoner Windows gagal.', [
                'java_binary' => $java,
                'jar' => $jar,
                'input' => $input,
                'output' => $output,
                'script' => $scriptFile,
                'stdout_file' => $stdoutFile,
                'stderr_file' => $stderrFile,
                'exception' => $e->getMessage(),
            ]);
            throw new RuntimeException('Peluncur reasoner Windows gagal: ' . $e->getMessage(), 0, $e);
        }

        $stdout = is_file($stdoutFile) ? trim((string) file_get_contents($stdoutFile)) : '';
        $stderr = is_file($stderrFile) ? trim((string) file_get_contents($stderrFile)) : '';
        $reportedExit = is_file($exitFile) ? trim((string) file_get_contents($exitFile)) : '';
        $exitCode = is_numeric($reportedExit) ? (int) $reportedExit : $process->getExitCode();

        if ($exitCode !== 0 || !is_file($output) || filesize($output) === 0) {
            $detail = trim($stderr . "\n" . $stdout);
            if ($detail === '') {
                $detail = 'Java tidak menghasilkan pesan stdout/stderr. Periksa file hs_err_pid_*.log dan log peluncur.';
            }
            Log::error('OWL 2 RL reasoner gagal saat diluncurkan melalui skrip Windows.', [
                'exit_code' => $exitCode,
                'cmd_process_exit_code' => $process->getExitCode(),
                'java_binary' => $java,
                'jar' => $jar,
                'ontology_directory' => $ontologyDirectory,
                'input' => $input,
                'output' => $output,
                'stdout' => $stdout,
                'stderr' => $stderr,
                'stdout_file' => $stdoutFile,
                'stderr_file' => $stderrFile,
                'exit_file' => $exitFile,
                'script' => $scriptFile,
                'crash_log_template' => $crashLogTemplate,
            ]);
            throw new RuntimeException(
                'Reasoner gagal (exit code ' . ($exitCode ?? 'unknown') . '): ' . mb_substr($detail, 0, 2500)
            );
        }

        $tripleCount = $this->countTurtleStatements($output);
        if (preg_match('/OWL2_RL_REASONING_OK\s+triples=(\d+)/', $stdout, $match)) {
            $tripleCount = (int) $match[1];
        }

        Log::info('OWL 2 RL reasoner berhasil melalui skrip Windows.', [
            'exit_code' => $exitCode,
            'triple_count' => $tripleCount,
            'java_binary' => $java,
            'output' => $output,
            'stdout' => $stdout,
            'stdout_file' => $stdoutFile,
            'stderr_file' => $stderrFile,
            'script' => $scriptFile,
        ]);

        return [
            'status' => 'owl2rl_reasoned',
            'output_path' => $output,
            'triple_count' => $tripleCount,
            'message' => 'Graph diproses dengan Apache Jena OWL 2 RL rule reasoner.',
        ];
    }

    private function quoteBatchArgument(string $value): string
    {
        if (str_contains($value, '"')) {
            throw new RuntimeException('Path atau argumen reasoner mengandung tanda kutip yang tidak didukung.');
        }
        return '"' . $value . '"';
    }

    private function countTurtleStatements(string $path): int
    {
        if (!is_file($path)) {
            return 0;
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            return 0;
        }
        preg_match_all('/^\s*(?!@prefix\b|@base\b|#)(?:<[^>]+>|_:[^\s]+|[A-Za-z][\w-]*:[^\s]+).*\s\.\s*$/mi', $contents, $matches);
        return count($matches[0]);
    }
}
