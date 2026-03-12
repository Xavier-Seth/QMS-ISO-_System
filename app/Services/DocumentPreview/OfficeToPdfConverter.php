<?php

namespace App\Services\DocumentPreview;

use RuntimeException;
use Symfony\Component\Process\Process;

class OfficeToPdfConverter
{
    public function convertToPdf(string $sourceAbsolutePath, string $targetAbsolutePath): void
    {
        if (!is_file($sourceAbsolutePath)) {
            throw new RuntimeException("Source file does not exist: {$sourceAbsolutePath}");
        }

        $targetDirectory = dirname($targetAbsolutePath);

        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException("Unable to create target directory: {$targetDirectory}");
        }

        $sourceRealPath = realpath($sourceAbsolutePath);
        if ($sourceRealPath === false) {
            throw new RuntimeException("Unable to resolve source file path: {$sourceAbsolutePath}");
        }

        $baseTempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'qms_preview';
        if (!is_dir($baseTempDir) && !mkdir($baseTempDir, 0775, true) && !is_dir($baseTempDir)) {
            throw new RuntimeException("Unable to create base temp directory: {$baseTempDir}");
        }

        $jobDir = $baseTempDir . DIRECTORY_SEPARATOR . 'job_' . str_replace('.', '_', uniqid('', true));
        $profileDir = $jobDir . DIRECTORY_SEPARATOR . 'lo_profile';
        $homeDir = $jobDir . DIRECTORY_SEPARATOR . 'home';

        foreach ([$jobDir, $profileDir, $homeDir] as $dir) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException("Unable to create directory: {$dir}");
            }
        }

        $extension = strtolower(pathinfo($sourceRealPath, PATHINFO_EXTENSION));
        $workingSourcePath = $jobDir . DIRECTORY_SEPARATOR . 'source.' . $extension;
        $expectedPdfPath = $jobDir . DIRECTORY_SEPARATOR . 'source.pdf';

        if (!copy($sourceRealPath, $workingSourcePath)) {
            $this->cleanupDirectory($jobDir);
            throw new RuntimeException("Unable to copy source file to working directory: {$workingSourcePath}");
        }

        if (is_file($expectedPdfPath)) {
            @unlink($expectedPdfPath);
        }

        $env = array_merge($_ENV, [
            'HOME' => $homeDir,
            'USERPROFILE' => $homeDir,
            'APPDATA' => $profileDir,
            'LOCALAPPDATA' => $profileDir,
            'TMP' => $jobDir,
            'TEMP' => $jobDir,
        ]);

        $process = new Process(
            [
                config('document_preview.soffice_binary', 'soffice'),
                '--headless',
                '--nologo',
                '--nofirststartwizard',
                '--convert-to',
                'pdf',
                '--outdir',
                $jobDir,
                $workingSourcePath,
            ],
            $jobDir,
            $env
        );

        $process->setTimeout((int) config('document_preview.conversion_timeout', 120));
        $process->run();

        $actualPdfPath = $this->waitForPdf($expectedPdfPath, $jobDir);

        if ($actualPdfPath === null) {
            $stdout = trim($process->getOutput());
            $stderr = trim($process->getErrorOutput());
            $exitCode = $process->getExitCode();
            $files = $this->listFiles($jobDir);

            $this->cleanupDirectory($jobDir);

            throw new RuntimeException(
                'LibreOffice conversion did not produce a PDF file.'
                . ($exitCode !== null ? " Exit code: {$exitCode}" : '')
                . ($stdout !== '' ? " Output: {$stdout}" : '')
                . ($stderr !== '' ? " Error: {$stderr}" : '')
                . ($files !== '' ? " Files in job dir: {$files}" : '')
            );
        }

        if (is_file($targetAbsolutePath)) {
            @unlink($targetAbsolutePath);
        }

        if (!@rename($actualPdfPath, $targetAbsolutePath)) {
            if (!@copy($actualPdfPath, $targetAbsolutePath)) {
                $this->cleanupDirectory($jobDir);
                throw new RuntimeException("Failed to move converted PDF to target path: {$targetAbsolutePath}");
            }
        }

        clearstatcache(true, $targetAbsolutePath);

        $this->cleanupDirectory($jobDir);

        if (!is_file($targetAbsolutePath)) {
            throw new RuntimeException("Converted PDF not found at target path: {$targetAbsolutePath}");
        }
    }

    protected function waitForPdf(string $expectedPdfPath, string $jobDir): ?string
    {
        for ($i = 0; $i < 20; $i++) {
            clearstatcache(true, $expectedPdfPath);

            if (is_file($expectedPdfPath)) {
                return $expectedPdfPath;
            }

            $pdfFiles = glob($jobDir . DIRECTORY_SEPARATOR . '*.pdf') ?: [];
            if (!empty($pdfFiles)) {
                return $pdfFiles[0];
            }

            usleep(250000);
        }

        return null;
    }

    protected function listFiles(string $directory): string
    {
        $files = @scandir($directory);
        if ($files === false) {
            return '';
        }

        $files = array_values(array_diff($files, ['.', '..']));

        return implode(', ', $files);
    }

    protected function cleanupDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $path = $directory . DIRECTORY_SEPARATOR . $item;

                if (is_dir($path)) {
                    $this->cleanupDirectory($path);
                } elseif (is_file($path)) {
                    @unlink($path);
                }
            }
        }

        @rmdir($directory);
    }
}