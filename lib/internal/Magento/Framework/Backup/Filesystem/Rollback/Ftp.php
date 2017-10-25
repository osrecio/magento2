<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Framework\Backup\Filesystem\Rollback;

use Magento\Framework\Archive;
use Magento\Framework\Backup\Exception\CantLoadSnapshot;
use Magento\Framework\Backup\Exception\FtpConnectionFailed;
use Magento\Framework\Backup\Exception\FtpValidationFailed;
use Magento\Framework\Backup\Exception\NotEnoughPermissions;
use Magento\Framework\Backup\Filesystem\Helper;
use Magento\Framework\Backup\Filesystem\Iterator\Filter;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Rollback worker for rolling back via ftp
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Ftp extends AbstractRollback
{
    /**
     * Ftp client
     *
     * @var \Magento\Framework\System\Ftp
     */
    protected $ftpClient;

    /**
     * Files rollback implementation via ftp
     *
     * @return void
     * @throws LocalizedException
     *
     * @see AbstractRollback::run()
     */
    public function run()
    {
        $snapshotPath = $this->snapshot->getBackupPath();

        if (!is_file($snapshotPath) || !is_readable($snapshotPath)) {
            throw new CantLoadSnapshot(
                new Phrase('Can\'t load snapshot archive')
            );
        }

        $this->initFtpClient();
        $this->validateFtp();

        $tmpDir = $this->createTmpDir();
        $this->unpackSnapshot($tmpDir);

        $fsHelper = new Helper();

        $this->cleanupFtp();
        $this->uploadBackupToFtp($tmpDir);
        if (!$this->snapshot->keepSourceFile()) {
            $fsHelper->rm($tmpDir, [], true);
        }
    }

    /**
     * Initialize ftp client and connect to ftp
     *
     * @return void
     * @throws FtpConnectionFailed
     */
    protected function initFtpClient()
    {
        try {
            $this->ftpClient = new \Magento\Framework\System\Ftp();
            $this->ftpClient->connect($this->snapshot->getFtpConnectString());
        } catch (\Exception $e) {
            throw new FtpConnectionFailed(
                new Phrase($e->getMessage())
            );
        }
    }

    /**
     * Perform ftp validation. Check whether ftp account provided points to current magento installation
     *
     * @return void
     * @throws LocalizedException
     */
    protected function validateFtp()
    {
        $validationFilename = '~validation-' . microtime(true) . '.tmp';
        $validationFilePath = $this->snapshot->getBackupsDir() . '/' . $validationFilename;

        $fh = @fopen($validationFilePath, 'w');
        @fclose($fh);

        if (!is_file($validationFilePath)) {
            throw new LocalizedException(
                new Phrase('Unable to validate ftp account')
            );
        }

        $rootDir = $this->snapshot->getRootDir();
        $ftpPath = $this->snapshot->getFtpPath() . '/' . str_replace($rootDir, '', $validationFilePath);

        $fileExistsOnFtp = $this->ftpClient->fileExists($ftpPath);
        @unlink($validationFilePath);

        if (!$fileExistsOnFtp) {
            throw new FtpValidationFailed(
                new Phrase('Failed to validate ftp account')
            );
        }
    }

    /**
     * Unpack snapshot
     *
     * @param string $tmpDir
     * @return void
     */
    protected function unpackSnapshot($tmpDir)
    {
        $snapshotPath = $this->snapshot->getBackupPath();

        $archiver = new Archive();
        $archiver->unpack($snapshotPath, $tmpDir);
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    protected function createTmpDir()
    {
        $tmpDir = $this->snapshot->getBackupsDir() . '/~tmp-' . microtime(true);

        $result = @mkdir($tmpDir);

        if (false === $result) {
            throw new NotEnoughPermissions(
                new Phrase('Failed to create directory %1', [$tmpDir])
            );
        }

        return $tmpDir;
    }

    /**
     * Delete magento and all files from ftp
     *
     * @return void
     */
    protected function cleanupFtp()
    {
        $rootDir = $this->snapshot->getRootDir();

        $filesystemIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootDir),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        $iterator = new Filter(
            $filesystemIterator,
            $this->snapshot->getIgnorePaths()
        );

        foreach ($iterator as $item) {
            $ftpPath = $this->snapshot->getFtpPath() . '/' . str_replace($rootDir, '', $item->_toString());
            $ftpPath = str_replace('\\', '/', $ftpPath);

            $this->ftpClient->delete($ftpPath);
        }
    }

    /**
     * Perform files rollback
     *
     * @param string $tmpDir
     * @return void
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    protected function uploadBackupToFtp($tmpDir)
    {
        $filesystemIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmpDir),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($filesystemIterator as $item) {
            $ftpPath = $this->snapshot->getFtpPath() . '/' . str_replace($tmpDir, '', $item->_toString());
            $ftpPath = str_replace('\\', '/', $ftpPath);

            if ($item->isLink()) {
                continue;
            }

            if ($item->isDir()) {
                $this->ftpClient->mkdirRecursive($ftpPath);
            } else {
                $result = $this->ftpClient->put($ftpPath, $item->_toString());
                if (false === $result) {
                    throw new NotEnoughPermissions(
                        new Phrase('Failed to upload file %1 to ftp', [$item->_toString()])
                    );
                }
            }
        }
    }
}
