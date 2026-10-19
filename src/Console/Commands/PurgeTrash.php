<?php

declare(strict_types=1);

namespace VmEngine\FileManager\Console\Commands;

use Illuminate\Console\Command;
use VmEngine\FileManager\Config\FmConfig;
use VmEngine\FileManager\Services\FileManagerService;

/**
 * Schedule it to empty the trash automatically:
 *   Schedule::command('filemanager:purge-trash')->daily();
 */
class PurgeTrash extends Command
{
    protected $signature = 'filemanager:purge-trash';

    protected $description = 'Permanently delete trashed files older than filemanager.trash.auto_purge_days';

    public function handle(FileManagerService $service): int
    {
        $days = (int) (FmConfig::getTrashConfig()['auto_purge_days'] ?? 0);

        // purgeExpiredTrash() with 0 days would purge every trashed file.
        if ($days <= 0) {
            $this->components->info('Trash purging is disabled (filemanager.trash.auto_purge_days = 0).');

            return self::SUCCESS;
        }

        $count = $service->purgeExpiredTrash();

        $this->components->info("Purged {$count} file(s).");

        return self::SUCCESS;
    }
}
