<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\ModuleSettings;
use App\Libraries\SecurityCangService;
use App\Libraries\SeoSettings;
use App\Libraries\SitePageTitle;
use App\Libraries\WebAnalytics;
use App\Libraries\WebSettings;
use CodeIgniter\HTTP\ResponseInterface;

class DashBoard extends BaseController
{
    protected $helpers = ['form', 'url'];

    public function index(): string|ResponseInterface
    {
        $login = $this->requireLogin();
        if ($login instanceof ResponseInterface) {
            return $login;
        }

        $memberCanManageRoles = $this->canManageContentModules();
        $moduleSettings = new ModuleSettings();
        $contentModules = $moduleSettings->contentModules();
        $analyticsEnabled = $moduleSettings->isEnabled(ModuleSettings::WEB_ANALYTICS);

        return view('dashboard/index', [
            'title'            => SitePageTitle::format('Dashboard'),
            'pageHeading'      => SitePageTitle::trail('Dashboard'),
            'wideLayout'       => true,
            'contentModules'   => $contentModules,
            'analyticsEnabled' => $analyticsEnabled,
            'analytics'        => $memberCanManageRoles && $analyticsEnabled ? (new WebAnalytics())->dashboardSummary() : null,
        ]);
    }

    public function moduleManager(): string|ResponseInterface
    {
        $login = $this->requireLogin();
        if ($login instanceof ResponseInterface) {
            return $login;
        }

        if (! $this->canManageContentModules()) {
            return redirect()->to(site_url('DashBoard/Index'))->with('errors', ['dashboard' => 'Only Administrator, Manager, or Owner accounts can manage modules.']);
        }

        return view('dashboard/module_manager', [
            'title'                   => SitePageTitle::format('Dashboard', 'Module Manager'),
            'pageHeading'             => SitePageTitle::trail('Dashboard', 'Module Manager'),
            'wideLayout'              => true,
            'canManageContentModules' => true,
            'contentModules'          => (new ModuleSettings())->contentModules(),
        ]);
    }

    public function saveContentModules(): ResponseInterface
    {
        $login = $this->requireLogin();
        if ($login instanceof ResponseInterface) {
            return $login;
        }

        if (! $this->canManageContentModules()) {
            return redirect()->to(site_url('DashBoard/Index'))->with('errors', ['dashboard' => 'Only Administrator, Manager, or Owner accounts can manage modules.']);
        }

        $enabled = $this->request->getPost('modules') ?? $this->request->getPost('content_modules');
        $enabled = is_array($enabled) ? array_map('strval', $enabled) : [];

        (new ModuleSettings())->saveContentModules($enabled);

        return redirect()->to(site_url('DashBoard/ModuleManager/Index'))->with('message', 'Module settings updated.');
    }

    public function webSettings(): string|ResponseInterface
    {
        $login = $this->requireLogin();
        if ($login instanceof ResponseInterface) {
            return $login;
        }

        if (! $this->canManageContentModules()) {
            return redirect()->to(site_url('DashBoard/Index'))->with('errors', ['dashboard' => 'Only Administrator, Manager, or Owner accounts can manage web settings.']);
        }

        return view('dashboard/web_settings', [
            'title'          => SitePageTitle::format('Dashboard', 'Web Settings'),
            'pageHeading'    => SitePageTitle::trail('Dashboard', 'Web Settings'),
            'wideLayout'     => true,
            'contentModules' => (new ModuleSettings())->contentModules(),
            'webSettings'    => (new WebSettings())->homeSettings(),
        ]);
    }

    public function saveWebSettings(): ResponseInterface
    {
        $login = $this->requireLogin();
        if ($login instanceof ResponseInterface) {
            return $login;
        }

        if (! $this->canManageContentModules()) {
            return redirect()->to(site_url('DashBoard/Index'))->with('errors', ['dashboard' => 'Only Administrator, Manager, or Owner accounts can manage web settings.']);
        }

        $webName = (string) $this->request->getPost('web_name');
        $webDescription = (string) $this->request->getPost('web_description');

        if (trim($webName) === '' || trim($webDescription) === '') {
            return redirect()->back()->withInput()->with('errors', ['web_settings' => 'Web name and description are required.']);
        }

        (new WebSettings())->saveHomeSettings($webName, $webDescription);

        return redirect()->to(site_url('DashBoard/WebSettings/Index'))->with('message', 'Web settings updated.');
    }

    public function seoSettings(): string|ResponseInterface
    {
        $guard = $this->requireManager('Only Administrator, Manager, or Owner accounts can manage SEO settings.');
        if ($guard instanceof ResponseInterface) {
            return $guard;
        }

        return view('dashboard/seo_settings', [
            'title'          => SitePageTitle::format('Dashboard', 'SEO Settings'),
            'pageHeading'    => SitePageTitle::trail('Dashboard', 'SEO Settings'),
            'wideLayout'     => true,
            'contentModules' => (new ModuleSettings())->contentModules(),
            'seoSettings'    => (new SeoSettings())->mainSettings(),
        ]);
    }

    public function saveSeoSettings(): ResponseInterface
    {
        $guard = $this->requireManager('Only Administrator, Manager, or Owner accounts can manage SEO settings.');
        if ($guard instanceof ResponseInterface) {
            return $guard;
        }

        try {
            (new SeoSettings())->saveMainSettings([
                SeoSettings::META_TITLE       => (string) $this->request->getPost('seo_meta_title'),
                SeoSettings::META_DESCRIPTION => (string) $this->request->getPost('seo_meta_description'),
                SeoSettings::META_KEYWORDS    => (string) $this->request->getPost('seo_meta_keywords'),
                SeoSettings::CANONICAL_URL    => (string) $this->request->getPost('seo_canonical_url'),
                SeoSettings::ROBOTS           => (string) $this->request->getPost('seo_robots'),
                SeoSettings::OG_IMAGE         => (string) $this->request->getPost('seo_og_image'),
            ]);
        } catch (\InvalidArgumentException $exception) {
            return redirect()->back()->withInput()->with('errors', ['seo_settings' => $exception->getMessage()]);
        }

        return redirect()->to(site_url('DashBoard/SEO_Settings'))->with('message', 'SEO settings updated.');
    }

    public function securityManager(): string|ResponseInterface
    {
        $guard = $this->requireSecurityManager('Only Administrator, Manager, or Owner accounts can access Security Manager.');
        if ($guard instanceof ResponseInterface) {
            return $guard;
        }

        (new SecurityCangService())->ensureApplicationCangColumns();

        return view('dashboard/security_manager', [
            'title'          => SitePageTitle::format('Dashboard', 'Security Manager'),
            'pageHeading'    => SitePageTitle::trail('Dashboard', 'Security Manager'),
            'wideLayout'     => true,
            'contentModules' => (new ModuleSettings())->contentModules(),
        ]);
    }

    public function cangProfiles(): string|ResponseInterface
    {
        $guard = $this->requireSecurityManager('Only Administrator, Manager, or Owner accounts can manage CANG profiles.');
        if ($guard instanceof ResponseInterface) {
            return $guard;
        }

        $service = new SecurityCangService();
        $service->ensureApplicationCangColumns();

        return view('dashboard/security_cang_profiles', [
            'title'          => SitePageTitle::format('Dashboard', 'Security Manager', 'CANG Profiles'),
            'pageHeading'    => SitePageTitle::trail('Dashboard', 'Security Manager', 'CANG Profiles'),
            'wideLayout'     => true,
            'contentModules' => (new ModuleSettings())->contentModules(),
            'profiles'       => $service->profiles(),
        ]);
    }

    public function editCangProfile(int $id): string|ResponseInterface
    {
        $guard = $this->requireSecurityManager('Only Administrator, Manager, or Owner accounts can manage CANG profiles.');
        if ($guard instanceof ResponseInterface) {
            return $guard;
        }

        $service = new SecurityCangService();
        $profile = $service->profile($id);
        if ($profile === null) {
            return redirect()->to(site_url('DashBoard/SecurityManager/CANG/Index'))->with('errors', ['cang' => 'CANG profile not found.']);
        }

        return view('dashboard/security_cang_profile_form', [
            'title'          => SitePageTitle::format('Dashboard', 'Security Manager', 'Edit CANG Profile'),
            'pageHeading'    => SitePageTitle::trail('Dashboard', 'Security Manager', 'Edit CANG Profile'),
            'wideLayout'     => true,
            'contentModules' => (new ModuleSettings())->contentModules(),
            'profile'        => $profile,
            'languages'      => $service->languages(),
            'profileDetails' => $service->editorDetails($profile),
        ]);
    }

    public function saveCangProfile(int $id): ResponseInterface
    {
        $guard = $this->requireSecurityManager('Only Administrator, Manager, or Owner accounts can manage CANG profiles.');
        if ($guard instanceof ResponseInterface) {
            return $guard;
        }

        $rules = [
            'language_id'     => 'required|integer|greater_than_equal_to[' . SecurityCangService::cangLanguageMinId() . ']|less_than_equal_to[' . SecurityCangService::cangLanguageMaxId() . ']',
            'code_length'     => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[128]',
            'generation_mode' => 'required|in_list[random,sequential]',
            'split_by'        => 'permit_empty|string|max_length[' . SecurityCangService::SPLIT_BY_MAX_LENGTH . ']',
            'split_length'    => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[' . SecurityCangService::SPLIT_LENGTH_MAX . ']',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput();
        }

        $service = new SecurityCangService();
        $profile = $service->profile($id);
        if ($profile === null) {
            return redirect()->to(site_url('DashBoard/SecurityManager/CANG/Index'))->with('errors', ['cang' => 'CANG profile not found.']);
        }

        [$codeLength, $splitBy, $splitLength] = $this->cangSettingsFromRequest();
        $lengthError = $this->cangFormattedLengthError($profile, $codeLength, $splitBy, $splitLength);
        if ($lengthError !== null) {
            return redirect()->back()->withInput()->with('errors', ['cang_split' => $lengthError]);
        }

        $extra = $service->saveProfile($id, [
            'language_id'     => (int) $this->request->getPost('language_id'),
            'code_length'     => $codeLength,
            'generation_mode' => (string) $this->request->getPost('generation_mode'),
            'split_by'        => $splitBy,
            'split_length'    => $splitLength,
            'is_active'       => (bool) $this->request->getPost('is_active'),
        ]);

        $message = 'CANG profile updated.';
        if ($extra !== '') {
            $message .= ' ' . $extra;
        }

        return redirect()->to(site_url('DashBoard/SecurityManager/CANG/Index'))->with('message', $message);
    }

    public function cangPreviewSamples(int $id): ResponseInterface
    {
        $login = $this->requireLogin();
        if ($login instanceof ResponseInterface) {
            return $this->response->setStatusCode(401)->setJSON(['ok' => false, 'error' => 'Log in to continue.']);
        }

        if (! $this->canManageContentModules()) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'error' => 'Forbidden.']);
        }

        if (! (new ModuleSettings())->isEnabled(ModuleSettings::SECURITY_MANAGER)) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'error' => 'Security Manager is disabled.']);
        }

        $rules = [
            'language_id'     => 'required|integer|greater_than_equal_to[' . SecurityCangService::cangLanguageMinId() . ']|less_than_equal_to[' . SecurityCangService::cangLanguageMaxId() . ']',
            'code_length'     => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[128]',
            'generation_mode' => 'required|in_list[random,sequential]',
            'split_by'        => 'permit_empty|string|max_length[' . SecurityCangService::SPLIT_BY_MAX_LENGTH . ']',
            'split_length'    => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[' . SecurityCangService::SPLIT_LENGTH_MAX . ']',
        ];

        if (! $this->validate($rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'ok'     => false,
                'error'  => 'Invalid preview parameters.',
                'errors' => $this->validator->getErrors(),
            ]);
        }

        $service = new SecurityCangService();
        $profile = $service->profile($id);
        if ($profile === null) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'CANG profile not found.']);
        }

        [$codeLength, $splitBy, $splitLength] = $this->cangSettingsFromRequest();
        $mode = (string) $this->request->getPost('generation_mode');
        $lengthError = $this->cangFormattedLengthError($profile, $codeLength, $splitBy, $splitLength);
        if ($lengthError !== null) {
            return $this->response->setStatusCode(422)->setJSON([
                'ok'    => false,
                'error' => $lengthError,
            ]);
        }

        $languageId = (int) $this->request->getPost('language_id');
        $sequenceBase = (int) ($profile['sequence_value'] ?? 0);

        $samples = $service->previewSamples($languageId, $codeLength, $mode, 5, $sequenceBase, $splitBy, $splitLength);
        $note = $mode === 'sequential'
            ? 'Sequential samples assume the next values after the current sequence counter (nothing is saved until you click Save).'
            : 'Random samples are illustrative only; each real ID is still generated uniquely when saved.';
        if ($splitBy !== '' && $splitLength > 0) {
            $note .= ' Splitting is applied to the generated body only; stored values include these separators.';
        }

        return $this->response->setJSON([
            'ok'      => true,
            'samples' => $samples,
            'note'    => $note,
        ]);
    }

    public function cangProfileDetails(int $id): ResponseInterface
    {
        $login = $this->requireLogin();
        if ($login instanceof ResponseInterface) {
            return $this->response->setStatusCode(401)->setJSON(['ok' => false, 'error' => 'Log in to continue.']);
        }

        if (! $this->canManageContentModules()) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'error' => 'Forbidden.']);
        }

        if (! (new ModuleSettings())->isEnabled(ModuleSettings::SECURITY_MANAGER)) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'error' => 'Security Manager is disabled.']);
        }

        $rules = [
            'language_id'     => 'required|integer|greater_than_equal_to[' . SecurityCangService::cangLanguageMinId() . ']|less_than_equal_to[' . SecurityCangService::cangLanguageMaxId() . ']',
            'code_length'     => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[128]',
            'generation_mode' => 'required|in_list[random,sequential]',
            'split_by'        => 'permit_empty|string|max_length[' . SecurityCangService::SPLIT_BY_MAX_LENGTH . ']',
            'split_length'    => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[' . SecurityCangService::SPLIT_LENGTH_MAX . ']',
        ];

        if (! $this->validate($rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'ok'     => false,
                'error'  => 'Invalid parameters.',
                'errors' => $this->validator->getErrors(),
            ]);
        }

        $service = new SecurityCangService();
        $profile = $service->profile($id);
        if ($profile === null) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'CANG profile not found.']);
        }

        $details = $service->editorDetails($profile, [
            'language_id'     => (int) $this->request->getPost('language_id'),
            'code_length'     => (int) $this->request->getPost('code_length'),
            'generation_mode' => (string) $this->request->getPost('generation_mode'),
            'split_by'        => (string) $this->request->getPost('split_by'),
            'split_length'    => (int) $this->request->getPost('split_length'),
            'is_active'       => (bool) $this->request->getPost('is_active'),
        ]);

        return $this->response->setJSON([
            'ok'      => true,
            'details' => $details,
        ]);
    }

    private function requireManager(string $message): ?ResponseInterface
    {
        $login = $this->requireLogin();
        if ($login instanceof ResponseInterface) {
            return $login;
        }

        if (! $this->canManageContentModules()) {
            return redirect()->to(site_url('DashBoard/Index'))->with('errors', ['dashboard' => $message]);
        }

        return null;
    }

    private function requireSecurityManager(string $message): ?ResponseInterface
    {
        $guard = $this->requireManager($message);
        if ($guard instanceof ResponseInterface) {
            return $guard;
        }

        if (! (new ModuleSettings())->isEnabled(ModuleSettings::SECURITY_MANAGER)) {
            return redirect()->to(site_url('DashBoard/Index'))->with('errors', ['dashboard' => 'Security Manager is disabled in Module Manager.']);
        }

        return null;
    }

    private function canManageContentModules(): bool
    {
        return is_numeric(session()->get('member_user_id')) && (bool) session()->get('member_can_manage_roles');
    }

    private function requireLogin(): ?ResponseInterface
    {
        if (is_numeric(session()->get('member_user_id'))) {
            return null;
        }

        return redirect()->to(site_url('Member/User/Login'))->with('errors', ['auth' => 'Log in to continue.']);
    }

    /**
     * @return array{0: int, 1: string, 2: int}
     */
    private function cangSettingsFromRequest(): array
    {
        $codeLength = (int) $this->request->getPost('code_length');
        $splitBy = SecurityCangService::normalizeSplitBy((string) $this->request->getPost('split_by'));
        $splitLength = SecurityCangService::normalizeSplitLength((int) $this->request->getPost('split_length'));
        if ($splitLength < 1) {
            $splitBy = '';
            $splitLength = 0;
        }
        if ($splitBy === '') {
            $splitLength = 0;
        }

        return [$codeLength, $splitBy, $splitLength];
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function cangFormattedLengthError(array $profile, int $codeLength, string $splitBy, int $splitLength): ?string
    {
        $targetKey = (string) ($profile['target_key'] ?? '');
        if (SecurityCangService::formattedLengthWithinLimit($targetKey, $codeLength, $splitBy, $splitLength)) {
            return null;
        }

        return SecurityCangService::formattedLengthLimitMessage($targetKey);
    }
}
