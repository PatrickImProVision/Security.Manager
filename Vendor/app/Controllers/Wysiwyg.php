<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AppDatabase;
use App\Libraries\WysiwygMedia;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * AJAX endpoints for the shared WYSIWYG editor.
 */
class Wysiwyg extends BaseController
{
    public function uploadImage(): ResponseInterface
    {
        if (! $this->request->isAJAX()) {
            return $this->jsonError('Invalid request.', 400);
        }

        $auth = $this->requireEditor();
        if ($auth instanceof ResponseInterface) {
            return $auth;
        }

        $result = (new WysiwygMedia())->storeUpload($this->request->getFile('image'));
        if ($result['error'] !== null) {
            return $this->jsonError($result['error'], 422);
        }

        $path = (string) $result['path'];

        return $this->response->setJSON([
            'success' => true,
            'url'     => base_url($path),
            'path'    => $path,
        ]);
    }

    private function jsonError(string $message, int $status): ResponseInterface
    {
        return $this->response
            ->setStatusCode($status)
            ->setJSON([
                'success' => false,
                'error'   => $message,
            ]);
    }

    /**
     * @return array<string, mixed>|ResponseInterface
     */
    private function requireEditor(): array|ResponseInterface
    {
        $user = $this->currentUser();
        if ($user === null) {
            return $this->jsonError('Log in to upload images.', 401);
        }

        return $user;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function currentUser(): ?array
    {
        $id = session()->get('member_user_id');
        if (! is_numeric($id)) {
            return null;
        }

        $user = AppDatabase::connection()->table('users')->where('id', (int) $id)->get()->getRowArray();
        if (! is_array($user) || ! (bool) ($user['is_active'] ?? false)) {
            return null;
        }

        return $user;
    }

}
