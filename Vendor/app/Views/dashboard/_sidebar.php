<?php

$memberLoggedIn = is_numeric(session()->get('member_user_id'));

$memberCanManageRoles = $memberLoggedIn && (bool) session()->get('member_can_manage_roles');

$memberCanManageCommunityCategories = $memberLoggedIn
    && (new \App\Libraries\RoleService())->canManageCommunityCategories((string) session()->get('member_role'));

$memberIsOwner = $memberLoggedIn
    && (new \App\Libraries\RoleService())->isOwner((string) session()->get('member_role'));

$contentModuleMap = [];

foreach (($contentModules ?? []) as $module) {

    if (! is_array($module)) {

        continue;

    }



    $contentModuleMap[(string) ($module['module_key'] ?? '')] = $module;

}

$publicContentEnabled = (bool) ($contentModuleMap['content_public']['is_enabled'] ?? true);

$communityContentEnabled = (bool) ($contentModuleMap['content_community']['is_enabled'] ?? true);

$personalContentEnabled = (bool) ($contentModuleMap['content_personal']['is_enabled'] ?? true);

$securityManagerEnabled = (bool) ($contentModuleMap['security_manager']['is_enabled'] ?? true);

?>



<aside class="dashboard-sidebar" aria-label="Control Panel navigation">

    <div class="dashboard-sidebar-heading">

        <a href="<?= esc(site_url('DashBoard/Index')) ?>"><?= $memberCanManageRoles ? 'Control Panel' : 'User Dashboard' ?></a>

    </div>



    <?php if (! $memberCanManageRoles) : ?>

        <div class="dashboard-sidebar-type">Account</div>



        <section class="dashboard-sidebar-section">

            <div class="dashboard-sidebar-title">Member Area</div>

            <nav class="dashboard-sidebar-nav">

                <a href="<?= esc(site_url('Member/User/MyProfile')) ?>">My Profile</a>

                <a href="<?= esc(site_url('Member/List')) ?>">Member List</a>

            </nav>

        </section>



        <div class="dashboard-sidebar-type">Communication</div>



        <section class="dashboard-sidebar-section">

            <div class="dashboard-sidebar-title">Personal Messages</div>

            <nav class="dashboard-sidebar-nav">

                <?php if ($personalContentEnabled) : ?>

                    <a href="<?= esc(site_url('Content/Personal/Inbox')) ?>">Inbox</a>

                    <a href="<?= esc(site_url('Content/Personal/Sent')) ?>">Sent</a>

                    <a href="<?= esc(site_url('Content/Personal/Index')) ?>">All Messages</a>

                    <a href="<?= esc(site_url('Member/List')) ?>">Choose Member To Message</a>

                <?php else : ?>

                    <span class="dashboard-sidebar-disabled">Personal messages disabled</span>

                <?php endif ?>

            </nav>

        </section>



        <div class="dashboard-sidebar-type">Community</div>



        <section class="dashboard-sidebar-section">

            <div class="dashboard-sidebar-title">Community Content</div>

            <nav class="dashboard-sidebar-nav">

                <?php if ($communityContentEnabled) : ?>

                    <a href="<?= esc(site_url('Content/Community/Index')) ?>">Community Posts</a>

                    <a href="<?= esc(site_url('Content/Community/Create')) ?>">Create Community Post</a>

                <?php else : ?>

                    <span class="dashboard-sidebar-disabled">Community content disabled</span>

                <?php endif ?>

            </nav>

        </section>

    <?php else : ?>

    <div class="dashboard-sidebar-type">Content Manager</div>



    <section class="dashboard-sidebar-section">

        <div class="dashboard-sidebar-title">Public</div>

        <nav class="dashboard-sidebar-nav">

            <?php if ($publicContentEnabled) : ?>

                <a href="<?= esc(site_url('Content/Public/Index')) ?>">Public Content</a>

            <?php else : ?>

                <span class="dashboard-sidebar-disabled">Disabled in Module Manager</span>

            <?php endif ?>

            <?php if ($publicContentEnabled && $memberCanManageRoles) : ?>

                <a href="<?= esc(site_url('Content/Public/Create')) ?>">Create Public Page</a>

            <?php endif ?>

        </nav>

    </section>



    <section class="dashboard-sidebar-section">

        <div class="dashboard-sidebar-title">Community</div>

        <nav class="dashboard-sidebar-nav">

            <?php if ($communityContentEnabled) : ?>

                <a href="<?= esc(site_url('Content/Community/Index')) ?>">Community Posts</a>

            <?php else : ?>

                <span class="dashboard-sidebar-disabled">Disabled in Module Manager</span>

            <?php endif ?>

            <?php if ($communityContentEnabled && $memberLoggedIn) : ?>

                <a href="<?= esc(site_url('Content/Community/Create')) ?>">Create Community Post</a>

            <?php endif ?>

            <?php if ($communityContentEnabled && $memberCanManageCommunityCategories) : ?>

                <a href="<?= esc(site_url('Content/Community/Categories/Index')) ?>">Manage Forums & Categories</a>

            <?php endif ?>

        </nav>

    </section>



    <section class="dashboard-sidebar-section">

        <div class="dashboard-sidebar-title">Personal</div>

        <nav class="dashboard-sidebar-nav">

            <?php if ($personalContentEnabled) : ?>

                <a href="<?= esc(site_url('Content/Personal/Inbox')) ?>">Inbox</a>

                <a href="<?= esc(site_url('Content/Personal/Sent')) ?>">Sent</a>

                <a href="<?= esc(site_url('Content/Personal/Index')) ?>">All Messages</a>

            <?php else : ?>

                <span class="dashboard-sidebar-disabled">Disabled in Module Manager</span>

            <?php endif ?>

            <?php if ($personalContentEnabled) : ?>

                <a href="<?= esc(site_url('Member/List')) ?>">Choose Member To Message</a>

            <?php endif ?>

            <?php if ($personalContentEnabled) : ?>

                <a href="<?= esc(site_url('Content/Personal/Create')) ?>">Bulk Message</a>

            <?php endif ?>

        </nav>

    </section>



    <div class="dashboard-sidebar-type">Access & Governance</div>



    <section class="dashboard-sidebar-section">

        <div class="dashboard-sidebar-title">Security Manager</div>

        <nav class="dashboard-sidebar-nav">

            <?php if ($securityManagerEnabled) : ?>

                <a href="<?= esc(site_url('DashBoard/SecurityManager/Index')) ?>">Security Overview</a>

                <a href="<?= esc(site_url('DashBoard/SecurityManager/CANG/Index')) ?>">CANG Profiles</a>

            <?php else : ?>

                <span class="dashboard-sidebar-disabled">Disabled in Module Manager</span>

            <?php endif ?>

        </nav>

    </section>



    <section class="dashboard-sidebar-section">

        <div class="dashboard-sidebar-title">User Manager</div>

        <nav class="dashboard-sidebar-nav">

            <a href="<?= esc(site_url('Member/User/MyProfile')) ?>">My Profile</a>

            <a href="<?= esc(site_url('Member/List')) ?>">Member List</a>

            <a href="<?= esc(site_url('Member/User/Create')) ?>">Create User</a>

            <a href="<?= esc(site_url('Member/User/Roles')) ?>">Manage Roles</a>

            <a href="<?= esc(site_url('Member/User/AssignRole')) ?>">Assign User Roles</a>

        </nav>

    </section>



    <div class="dashboard-sidebar-type">Application Structure</div>



    <section class="dashboard-sidebar-section">

        <div class="dashboard-sidebar-title">Module Manager</div>

        <nav class="dashboard-sidebar-nav">

            <a href="<?= esc(site_url('DashBoard/ModuleManager/Index')) ?>">Module Settings</a>

        </nav>

    </section>



    <section class="dashboard-sidebar-section">

        <div class="dashboard-sidebar-title">Route Manager</div>

        <nav class="dashboard-sidebar-nav">

            <span class="dashboard-sidebar-disabled">Pending module</span>

        </nav>

    </section>



    <div class="dashboard-sidebar-type">System & Data</div>



    <?php if ($memberIsOwner) : ?>
    <section class="dashboard-sidebar-section">

        <div class="dashboard-sidebar-title">Installation Manager</div>

        <nav class="dashboard-sidebar-nav">

            <a href="<?= esc(site_url('install/uninstall')) ?>">Uninstall Application</a>

            <span class="dashboard-sidebar-disabled">Install and restore are available after uninstall</span>

        </nav>

    </section>
    <?php endif ?>



    <section class="dashboard-sidebar-section">

        <div class="dashboard-sidebar-title">Database Manager</div>

        <nav class="dashboard-sidebar-nav">

            <span class="dashboard-sidebar-disabled">Pending module</span>

        </nav>

    </section>



    <section class="dashboard-sidebar-section">

        <div class="dashboard-sidebar-title">Environment Manager</div>

        <nav class="dashboard-sidebar-nav">

            <a href="<?= esc(site_url('DashBoard/WebSettings/Index')) ?>">Web Settings</a>

            <a href="<?= esc(site_url('DashBoard/SEO_Settings')) ?>">SEO Settings</a>

        </nav>

    </section>



    <div class="dashboard-sidebar-type">Communication</div>



    <section class="dashboard-sidebar-section">

        <div class="dashboard-sidebar-title">E-Mail Manager</div>

        <nav class="dashboard-sidebar-nav">

            <span class="dashboard-sidebar-disabled">Pending module</span>

        </nav>

    </section>

    <?php endif ?>

</aside>


