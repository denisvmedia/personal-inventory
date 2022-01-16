<?php

declare(strict_types=1);

namespace App\Storage\File;

use RuntimeException;

final class BufferFile implements FileInterface
{
    public function __construct(private string $name, private string $buffer)
    {
    }

    public function upload(string $directory): void
    {
        file_put_contents($this->getTargetFile($directory, $this->name), $this->buffer);
    }

    public function getFilename(): string
    {
        return $this->name;
    }

    private function getTargetFile(string $directory, string $name): string
    {
        if (!is_dir($directory)) {
            if (false === @mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException(sprintf('Unable to create the "%s" directory.', $directory));
            }
        } elseif (!is_writable($directory)) {
            throw new RuntimeException(sprintf('Unable to write in the "%s" directory.', $directory));
        }

        return rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$name;
    }
}
