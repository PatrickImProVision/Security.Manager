<?= $this->extend('layouts/site') ?>

<?= $this->section('main') ?>

<?php

$profile = is_array($profile ?? null) ? $profile : [];

$languages = is_array($languages ?? null) ? $languages : [];

$profileDetails = is_array($profileDetails ?? null) ? $profileDetails : ['rows' => []];

$selectedLanguage = (int) old('language_id', $profile['language_id'] ?? 7);

$selectedMode = (string) old('generation_mode', $profile['generation_mode'] ?? 'random');

$codeLength = (int) old('code_length', $profile['code_length'] ?? 12);

$isActive = old('is_active', ! empty($profile['is_active']) ? '1' : '');

$splitByValue = (string) old('split_by', $profile['split_by'] ?? '');

$splitLengthValue = (int) old('split_length', $profile['split_length'] ?? 0);
$cangMaxFormatted = \App\Libraries\SecurityCangService::maxFormattedLengthForTarget((string) ($profile['target_key'] ?? ''));

$flashErrors = session()->getFlashdata('errors');

if (is_array($flashErrors) && $flashErrors !== []) {

    $errors = array_merge(is_array($errors ?? null) ? $errors : [], $flashErrors);

}

?>

<?= view('layouts/_site_header', [

    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',

]) ?>



<div class="dashboard-shell">

    <?= $this->include('dashboard/_sidebar') ?>



    <section class="dashboard-main">

        <?= $this->include('member/user/_flash') ?>



        <form id="cang-profile-form" class="card prose cang-edit-layout" method="post" action="<?= esc(site_url('DashBoard/SecurityManager/CANG/Edit/' . (int) ($profile['id'] ?? 0))) ?>" data-preview-url="<?= esc(site_url('DashBoard/SecurityManager/CANG/Preview/' . (int) ($profile['id'] ?? 0)), 'attr') ?>" data-details-url="<?= esc(site_url('DashBoard/SecurityManager/CANG/Details/' . (int) ($profile['id'] ?? 0)), 'attr') ?>">

            <?= csrf_field() ?>



            <h2><?= esc((string) ($profile['label'] ?? 'CANG Profile')) ?></h2>

            <p><?= esc((string) ($profile['description'] ?? '')) ?></p>

            <?php if (! empty($profile['integration_note'])) : ?>
                <p class="cang-integration-notice" role="note"><?= esc((string) $profile['integration_note']) ?></p>
            <?php endif ?>

            <div class="cang-details-panel" aria-labelledby="cang-details-heading">

                <h3 id="cang-details-heading">Full profile details</h3>

                <p class="hint" style="margin-top:0;margin-bottom:0.65rem">Live summary of identity, language, formatting, storage, and limits. Updates as you change settings below.</p>

                <dl class="cang-details-grid" id="cang_details_list">

                    <?php foreach (($profileDetails['rows'] ?? []) as $row) : ?>

                        <?php if (! is_array($row)) {

                            continue;

                        } ?>

                        <dt><?= esc((string) ($row['label'] ?? '')) ?></dt>

                        <dd<?= ! empty($row['warn']) ? ' class="cang-details-warn"' : '' ?>>

                            <?php if (! empty($row['pill'])) : ?>

                                <span class="status-pill status-<?= esc((string) $row['pill'], 'attr') ?>"><?= esc((string) ($row['value'] ?? '')) ?></span>

                            <?php elseif (! empty($row['code'])) : ?>

                                <code><?= esc((string) ($row['value'] ?? '')) ?></code>

                            <?php else : ?>

                                <?= esc((string) ($row['value'] ?? '')) ?>

                            <?php endif ?>

                        </dd>

                    <?php endforeach ?>

                </dl>

                <p class="cang-details-status" id="cang_details_status" aria-live="polite"></p>

            </div>



            <label for="language_id">CANG language</label>

            <select name="language_id" id="language_id" required>

                <?php foreach ($languages as $id => $language) : ?>

                    <option value="<?= esc((string) $id) ?>" <?= (int) $id === $selectedLanguage ? 'selected' : '' ?>>

                        <?= esc((string) ($language['name'] ?? 'Language')) ?> <?= esc((string) ($language['type'] ?? '')) ?>

                    </option>

                <?php endforeach ?>

            </select>



            <label for="code_length">Length of code string</label>

            <input type="number" name="code_length" id="code_length" min="1" max="128" value="<?= esc((string) $codeLength, 'attr') ?>" required>

            <p class="hint">Raw random/sequential body length. Optional grouping below inserts separators between segments (still counted toward the <?= (int) $cangMaxFormatted ?>-character storage limit for this profile).</p>



            <label for="generation_mode">Generation mode</label>

            <select name="generation_mode" id="generation_mode" required>

                <option value="random" <?= $selectedMode === 'random' ? 'selected' : '' ?>>Random</option>

                <option value="sequential" <?= $selectedMode === 'sequential' ? 'selected' : '' ?>>Sequential</option>

            </select>

            <p class="hint">Random is recommended for security-sensitive profiles like Password.Id.</p>



            <div class="row">

                <div>

                    <label for="split_by">Split by (separator)</label>

                    <input type="text" name="split_by" id="split_by" maxlength="16" value="<?= esc($splitByValue, 'attr') ?>" autocomplete="off" placeholder="e.g. - (leave empty for none)">

                    <p class="hint">Inserted between groups (max <?= (int) \App\Libraries\SecurityCangService::SPLIT_BY_MAX_LENGTH ?> characters). Use a character that is not required in URLs if this profile powers public links.</p>

                </div>

                <div>

                    <label for="split_length">Split length</label>

                    <input type="number" name="split_length" id="split_length" min="0" max="<?= (int) \App\Libraries\SecurityCangService::SPLIT_LENGTH_MAX ?>" value="<?= esc((string) $splitLengthValue, 'attr') ?>">

                    <p class="hint">Characters per segment before inserting the separator. Use 0 (or clear separator) to disable.</p>

                </div>

            </div>



            <div class="cang-preview-panel" aria-labelledby="cang-preview-heading">

                <h3 id="cang-preview-heading">Sample preview</h3>

                <p class="hint" style="margin-top:0">Shows example strings for the settings above (not saved to the database).</p>

                <div class="cang-preview-actions">

                    <button type="button" class="btn btn-secondary" id="cang_preview_btn">Update preview</button>

                    <span class="cang-preview-status" id="cang_preview_status" aria-live="polite"></span>

                </div>

                <ol class="cang-preview-list" id="cang_preview_list" hidden></ol>

                <p class="cang-preview-note" id="cang_preview_note" hidden></p>

            </div>



            <label class="field-check">

                <input type="checkbox" name="is_active" id="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>

                <span class="field-check-text">Profile active</span>

            </label>



            <div class="actions">

                <button type="submit" class="btn btn-primary">Save Profile</button>

                <a class="btn btn-secondary" href="<?= esc(site_url('DashBoard/SecurityManager/CANG/Index')) ?>">Back To Profiles</a>

            </div>

        </form>

    </section>

</div>

<script>

(function () {

    var form = document.getElementById('cang-profile-form');

    if (! form) {

        return;

    }



    function collectBody() {

        var fd = new FormData();

        fd.set('language_id', String(document.getElementById('language_id').value || ''));

        fd.set('code_length', String(document.getElementById('code_length').value || ''));

        fd.set('generation_mode', String(document.getElementById('generation_mode').value || ''));

        fd.set('split_by', String(document.getElementById('split_by').value || ''));

        fd.set('split_length', String(document.getElementById('split_length').value || '0'));

        var active = document.getElementById('is_active');

        if (active && active.checked) {

            fd.set('is_active', '1');

        }

        form.querySelectorAll('input[type="hidden"]').forEach(function (input) {

            if (input.name) {

                fd.set(input.name, input.value);

            }

        });

        return fd;

    }



    var debounceTimer = null;

    function debounce(fn) {

        window.clearTimeout(debounceTimer);

        debounceTimer = window.setTimeout(fn, 400);

    }



    var detailsList = document.getElementById('cang_details_list');

    var detailsStatus = document.getElementById('cang_details_status');

    var detailsUrl = form.getAttribute('data-details-url');



    function renderDetailsRows(rows) {

        if (! detailsList) {

            return;

        }

        detailsList.innerHTML = '';

        (rows || []).forEach(function (row) {

            var dt = document.createElement('dt');

            dt.textContent = row.label || '';

            var dd = document.createElement('dd');

            if (row.warn) {

                dd.className = 'cang-details-warn';

            }

            if (row.pill) {

                var pill = document.createElement('span');

                pill.className = 'status-pill status-' + row.pill;

                pill.textContent = row.value || '';

                dd.appendChild(pill);

            } else if (row.code) {

                var code = document.createElement('code');

                code.textContent = row.value || '';

                dd.appendChild(code);

            } else {

                dd.textContent = row.value || '';

            }

            detailsList.appendChild(dt);

            detailsList.appendChild(dd);

        });

    }



    function runDetails() {

        if (! detailsUrl || ! detailsList) {

            return;

        }

        if (detailsStatus) {

            detailsStatus.textContent = 'Updating…';

        }

        fetch(detailsUrl, {

            method: 'POST',

            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },

            body: collectBody()

        })

            .then(function (res) {

                return res.json().then(function (data) {

                    return { ok: res.ok, data: data };

                });

            })

            .then(function (wrapped) {

                if (! wrapped.data || ! wrapped.data.ok || ! wrapped.data.details) {

                    var err = (wrapped.data && wrapped.data.error) ? wrapped.data.error : 'Could not refresh details.';

                    if (detailsStatus) {

                        detailsStatus.textContent = err;

                    }

                    return;

                }

                renderDetailsRows(wrapped.data.details.rows || []);

                if (detailsStatus) {

                    detailsStatus.textContent = wrapped.data.details.length_exceeds

                        ? (wrapped.data.details.length_error || 'Formatted length exceeds the storage limit. Adjust settings before saving.')

                        : '';

                }

            })

            .catch(function () {

                if (detailsStatus) {

                    detailsStatus.textContent = 'Could not refresh details.';

                }

            });

    }



    var previewUrl = form.getAttribute('data-preview-url');

    var btn = document.getElementById('cang_preview_btn');

    var list = document.getElementById('cang_preview_list');

    var note = document.getElementById('cang_preview_note');

    var status = document.getElementById('cang_preview_status');



    function runPreview() {

        if (! previewUrl || ! list || ! note || ! status) {

            return;

        }

        status.textContent = 'Loading…';

        list.hidden = true;

        note.hidden = true;

        fetch(previewUrl, {

            method: 'POST',

            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },

            body: collectBody()

        })

            .then(function (res) {

                return res.json().then(function (data) {

                    return { ok: res.ok, data: data };

                });

            })

            .then(function (wrapped) {

                if (! wrapped.data || ! wrapped.data.ok) {

                    var err = (wrapped.data && wrapped.data.error) ? wrapped.data.error : 'Preview failed.';

                    if (wrapped.data && wrapped.data.errors && typeof wrapped.data.errors === 'object') {

                        var msgs = Object.keys(wrapped.data.errors).map(function (k) {

                            return wrapped.data.errors[k];

                        });

                        if (msgs.length) {

                            err = msgs.join(' ');

                        }

                    }

                    status.textContent = err;

                    return;

                }

                status.textContent = '';

                list.innerHTML = '';

                (wrapped.data.samples || []).forEach(function (s) {

                    var li = document.createElement('li');

                    var code = document.createElement('code');

                    code.textContent = s;

                    li.appendChild(code);

                    list.appendChild(li);

                });

                list.hidden = (wrapped.data.samples || []).length === 0;

                if (wrapped.data.note) {

                    note.textContent = wrapped.data.note;

                    note.hidden = false;

                }

            })

            .catch(function () {

                status.textContent = 'Could not load preview.';

            });

    }



    function refreshAll() {

        runDetails();

        runPreview();

    }



    if (btn) {

        btn.addEventListener('click', runPreview);

    }



    ['language_id', 'code_length', 'generation_mode', 'split_length', 'is_active'].forEach(function (id) {

        var el = document.getElementById(id);

        if (! el) {

            return;

        }

        el.addEventListener('change', function () {

            debounce(refreshAll);

        });

    });

    var splitByInput = document.getElementById('split_by');

    if (splitByInput) {

        splitByInput.addEventListener('input', function () {

            debounce(refreshAll);

        });

    }



    window.setTimeout(refreshAll, 0);

})();

</script>

<?= $this->endSection() ?>

