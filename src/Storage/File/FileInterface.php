<?php

declare(strict_types=1);

namespace App\Storage\File;

interface FileInterface
{
    public function upload(string $directory): void;
    public function getFilename(): string;
}