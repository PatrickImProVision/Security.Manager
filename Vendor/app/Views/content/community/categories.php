<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1>Community Categories</h1>
<p class="lead">Manage the categories used to group community posts.</p>

<?= $this->include('member/user/_flash') ?>

<form method="post" action="<?= esc(site_url('Content/Community/Categories/Save')) ?>" class="card">
    <?= csrf_field() ?>
    <h2>Add Category</h2>

    <div class="row">
        <div>
            <label for="name">Name</label>
            <input type="text" name="name" id="name" value="<?= old('name', '', 'attr') ?>" required maxlength="100">
        </div>
        <div>
            <label for="sort_order">Sort order</label>
            <input type="number" name="sort_order" id="sort_order" value="<?= old('sort_order', '0', 'attr') ?>">
        </div>
    </div>

    <label for="description">Description</label>
    <input type="text" name="description" id="description" value="<?= old('description', '', 'attr') ?>" maxlength="255">

    <label class="field-check">
        <input type="checkbox" name="is_active" value="1" checked>
        <span class="field-check-text">Active category</span>
    </label>

    <div class="actions">
        <button type="submit" class="btn btn-primary">Create category</button>
        <a class="btn btn-secondary" href="<?= esc(site_url('Content/Community/Index')) ?>">Back to posts</a>
    </div>
</form>

<div class="card prose">
    <div class="role-list-head">
        <h2>Existing Categories</h2>
    </div>

    <?php if (empty($categories)) : ?>
        <p>No community categories have been created yet.</p>
    <?php else : ?>
        <div class="content-list-table-wrap">
            <table class="content-list-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Order</th>
                        <th>System</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category) : ?>
                        <?php
                        $categoryId = (int) ($category['id'] ?? 0);
                        $isSystem = ! empty($category['is_system']);
                        ?>
                        <tr>
                            <td class="content-list-main">
                                <form method="post" action="<?= esc(site_url('Content/Community/Categories/Save')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= esc((string) $categoryId, 'attr') ?>">
                                    <label for="category-name-<?= esc((string) $categoryId, 'attr') ?>">Name</label>
                                    <input
                                        type="text"
                                        name="name"
                                        id="category-name-<?= esc((string) $categoryId, 'attr') ?>"
                                        value="<?= esc((string) ($category['name'] ?? ''), 'attr') ?>"
                                        required
                                        maxlength="100"
                                        <?= $isSystem ? 'readonly' : '' ?>
                                    >
                                    <label for="category-description-<?= esc((string) $categoryId, 'attr') ?>">Description</label>
                                    <input
                                        type="text"
                                        name="description"
                                        id="category-description-<?= esc((string) $categoryId, 'attr') ?>"
                                        value="<?= esc((string) ($category['description'] ?? ''), 'attr') ?>"
                                        maxlength="255"
                                    >
                            </td>
                            <td class="content-list-status">
                                <label class="field-check">
                                    <input type="checkbox" name="is_active" value="1" <?= ! empty($category['is_active']) ? 'checked' : '' ?> <?= $isSystem ? 'disabled' : '' ?>>
                                    <?php if ($isSystem) : ?>
                                        <input type="hidden" name="is_active" value="1">
                                    <?php endif ?>
                                    <span class="field-check-text"><?= ! empty($category['is_active']) ? 'Active' : 'Inactive' ?></span>
                                </label>
                            </td>
                            <td class="content-list-nav">
                                <input type="number" name="sort_order" value="<?= esc((string) ($category['sort_order'] ?? 0), 'attr') ?>">
                            </td>
                            <td class="content-list-nav">
                                <span class="status-pill <?= $isSystem ? 'status-system' : 'status-custom' ?>"><?= $isSystem ? 'System' : 'Custom' ?></span>
                            </td>
                            <td>
                                <div class="content-list-actions">
                                    <button type="submit" class="btn btn-primary">Save</button>
                                </form>
                                    <?php if (! $isSystem) : ?>
                                        <form method="post" action="<?= esc(site_url('Content/Community/Categories/Delete/' . $categoryId)) ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </form>
                                    <?php else : ?>
                                        <span class="btn btn-secondary disabled" aria-disabled="true">Delete</span>
                                    <?php endif ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
