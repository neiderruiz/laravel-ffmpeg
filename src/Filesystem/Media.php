<?php

namespace ProtoneMedia\LaravelFFMpeg\Filesystem;

use Illuminate\Filesystem\FilesystemAdapter;

class Media
{
    use HasInputOptions;

    /**
     * Global progress callback for upload tracking
     */
    protected static $globalProgressCallback = null;

    /**
     * Cumulative tracking for multiple upload calls
     */
    protected static $cumulativeUploadedFiles = 0;
    protected static $cumulativeTotalFiles = 0;
    protected static $isFirstCall = true;

    /**
     * @var \ProtoneMedia\LaravelFFMpeg\Filesystem\Disk
     */
    private $disk;

    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $temporaryDirectory;

    public function __construct(Disk $disk, string $path)
    {
        $this->disk = $disk;
        $this->path = $path;

        $this->makeDirectory();
    }

    public static function make($disk, string $path): self
    {
        return new static(Disk::make($disk), $path);
    }

    /**
     * Set global progress callback for upload tracking
     */
    public static function setGlobalProgressCallback($callback): void
    {
        static::$globalProgressCallback = $callback;
        static::$cumulativeUploadedFiles = 0;
        static::$cumulativeTotalFiles = 0;
        static::$isFirstCall = true;
    }

    /**
     * Clear global progress callback
     */
    public static function clearGlobalProgressCallback(): void
    {
        static::$globalProgressCallback = null;
        static::$cumulativeUploadedFiles = 0;
        static::$cumulativeTotalFiles = 0;
        static::$isFirstCall = true;
    }

    public function getDisk(): Disk
    {
        return $this->disk;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getDirectory(): ?string
    {
        $directory = rtrim(pathinfo($this->getPath())['dirname'], DIRECTORY_SEPARATOR);

        if ($directory === '.') {
            $directory = '';
        }

        if ($directory) {
            $directory .= DIRECTORY_SEPARATOR;
        }

        return $directory;
    }

    private function makeDirectory(): void
    {
        $disk = $this->getDisk();

        if (! $disk->isLocalDisk()) {
            $disk = $this->temporaryDirectoryDisk();
        }

        $directory = $this->getDirectory();

        if ($disk->has($directory)) {
            return;
        }

        $disk->makeDirectory($directory);
    }

    public function getFilenameWithoutExtension(): string
    {
        return pathinfo($this->getPath())['filename'];
    }

    public function getFilename(): string
    {
        return pathinfo($this->getPath())['basename'];
    }

    private function temporaryDirectoryDisk(): Disk
    {
        return Disk::make($this->temporaryDirectoryAdapter());
    }

    private function temporaryDirectoryAdapter(): FilesystemAdapter
    {
        if (! $this->temporaryDirectory) {
            $this->temporaryDirectory = $this->getDisk()->getTemporaryDirectory();
        }

        return app('filesystem')->createLocalDriver(
            ['root' => $this->temporaryDirectory]
        );
    }

    public function getLocalPath(): string
    {
        $disk = $this->getDisk();
        $path = $this->getPath();

        if ($disk->isLocalDisk()) {
            return $disk->path($path);
        }

        $temporaryDirectoryDisk = $this->temporaryDirectoryDisk();

        if ($disk->exists($path) && ! $temporaryDirectoryDisk->exists($path)) {
            $temporaryDirectoryDisk->writeStream($path, $disk->readStream($path));
        }

        return $temporaryDirectoryDisk->path($path);
    }

    public function copyAllFromTemporaryDirectory(?string $visibility = null)
    {
        if (! $this->temporaryDirectory) {
            return $this;
        }

        $temporaryDirectoryDisk = $this->temporaryDirectoryDisk();
        $destinationAdapater = $this->getDisk()->getFilesystemAdapter();
        $allFiles = $temporaryDirectoryDisk->allFiles();
        $totalFiles = count($allFiles);
        $uploadedFiles = 0;

        // Si es la primera llamada, inicializar el tracking acumulativo
        if (static::$isFirstCall) {
            static::$cumulativeUploadedFiles = 0;
            static::$cumulativeTotalFiles = 0;
            static::$isFirstCall = false;
        }

        // Sumar los archivos de esta llamada al total acumulativo
        static::$cumulativeTotalFiles += $totalFiles;

        // Notificar progreso inicial si hay callback
        if (static::$globalProgressCallback) {
            $progressPercentage = static::$cumulativeTotalFiles > 0 ? (static::$cumulativeUploadedFiles / static::$cumulativeTotalFiles) * 100 : 0;
            call_user_func(static::$globalProgressCallback, static::$cumulativeUploadedFiles, static::$cumulativeTotalFiles, $progressPercentage);
        }

        foreach ($allFiles as $path) {
            $destinationAdapater->writeStream($path, $temporaryDirectoryDisk->readStream($path));

            $uploadedFiles++;
            static::$cumulativeUploadedFiles++;
            $progressPercentage = static::$cumulativeTotalFiles > 0 ? (static::$cumulativeUploadedFiles / static::$cumulativeTotalFiles) * 100 : 0;

            // Notificar progreso después de cada archivo subido
            if (static::$globalProgressCallback) {
                call_user_func(static::$globalProgressCallback, static::$cumulativeUploadedFiles, static::$cumulativeTotalFiles, $progressPercentage);
            }

            if ($visibility) {
                $destinationAdapater->setVisibility($path, $visibility);
            }
        }

        return $this;
    }

    public function setVisibility(string $path, ?string $visibility = null)
    {
        $disk = $this->getDisk();

        if ($visibility && $disk->isLocalDisk()) {
            $disk->setVisibility($path, $visibility);
        }

        return $this;
    }
}
