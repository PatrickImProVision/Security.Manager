<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\SecurityCangService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Syncs `security_cang_profiles` shipped defaults to the live database using Config\Database (via db_connect).
 */
final class SyncCangProfiles extends BaseCommand
{
    protected $group = 'Database';

    protected $name = 'cang:sync-profiles';

    protected $description = 'Update security_cang_profiles rows to match SecurityCangService default seeds (uses Config\\Database).';

    public function run(array $params)
    {
        try {
            $affected = (new SecurityCangService())->syncSeededProfileDefaults();
        } catch (\Throwable $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        CLI::write('CANG profile defaults synced. Affected rows (sum per update): ' . $affected, 'green');

        return EXIT_SUCCESS;
    }
}
