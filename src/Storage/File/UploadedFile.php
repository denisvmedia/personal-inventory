<?php

declare(strict_types=1);

namespace App\Storage\File;

use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile as BaseUploadedFile;

final class UploadedFile implements FileInterface
{
    private string $originalFilename;

    public function __construct(private BaseUploadedFile $file, int $count)
    {
        $time = time();
        if (!$this->file->isValid()) {
            throw new RuntimeException($this->file->getErrorMessage());
        }
        $extension = $this->file->guessExtension();
        if (!$extension) {
            $extension = 'bin';
        }
        $this->originalFilename = $time . 'i' . $count . '.' . $extension;
    }

    public function upload(string $directory): void
    {
        $this->file->move($directory, $this->originalFilename);
    }

    public function getFilename(): string
    {
        return $this->originalFilename;
    }

    /**
     * @var BaseUploadedFile[] $uploadedFiles
     * @return UploadedFile[]
     */
    public static function fromUploadedFiles(array $uploadedFiles): array
    {
        $result = [];

        $count = 0;
        foreach ($uploadedFiles as $uploadedFile) {
            foreach($uploadedFile as $file) {
                $result[] = new self($file, $count++);
            }
        }

        return $result;
    }
}
