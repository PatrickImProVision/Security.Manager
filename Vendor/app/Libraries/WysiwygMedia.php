<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Image uploads for the WYSIWYG editor (AJAX).
 */
final class WysiwygMedia
{
    private const MAX_KB = 4096;

    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * @return array{path: string|null, error: string|null}
     */
    public function storeUpload(?UploadedFile $image): array
    {
        if ($image === null || $image->getError() === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'error' => 'Choose an image file to upload.'];
        }

        if (! $image->isValid()) {
            return ['path' => null, 'error' => $image->getErrorString()];
        }

        if ($image->getSizeByUnit('kb') > self::MAX_KB) {
            return ['path' => null, 'error' => 'Image must be 4 MB or smaller.'];
        }

        $extension = strtolower($image->getClientExtension());
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return ['path' => null, 'error' => 'Image must be JPG, PNG, GIF, or WEBP.'];
        }

        if (@getimagesize($image->getTempName()) === false) {
            return ['path' => null, 'error' => 'Uploaded file is not a valid image.'];
        }

        $uploadDir = $this->uploadDirectory();
        if (! is_dir($uploadDir) && ! mkdir($uploadDir, 0775, true) && ! is_dir($uploadDir)) {
            return ['path' => null, 'error' => 'Could not create content image upload folder.'];
        }

        $fileName = $image->getRandomName();
        if (! $image->move($uploadDir, $fileName)) {
            return ['path' => null, 'error' => 'Could not save uploaded image.'];
        }

        return ['path' => 'uploads/content-images/' . $fileName, 'error' => null];
    }

    private function uploadDirectory(): string
    {
        $scriptFile = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
        $publicRoot = $scriptFile !== '' ? dirname($scriptFile) : FCPATH;

        return rtrim($publicRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . 'uploads' . DIRECTORY_SEPARATOR . 'content-images' . DIRECTORY_SEPARATOR;
    }
}
