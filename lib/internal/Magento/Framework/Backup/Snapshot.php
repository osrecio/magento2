<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * Class to work with full filesystem and database backups
 *
 * @author      Magento Core Team <core@magentocommerce.com>
 */
namespace Magento\Framework\Backup;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem as AppFilesystem;

class Snapshot extends Filesystem
{
    /**
     * Database backup manager
     *
     * @var Db
     */
    protected $dbBackupManager;

    /**
     * Filesystem facade
     *
     * @var AppFilesystem
     */
    protected $filesystem;

    /**
     * @var Factory
     */
    protected $backupFactory;

    /**
     * @param AppFilesystem $filesystem
     * @param Factory $backupFactory
     */
    public function __construct(AppFilesystem $filesystem, Factory $backupFactory)
    {
        $this->filesystem = $filesystem;
        $this->backupFactory = $backupFactory;
    }

    /**
     * Implementation Rollback functionality for Snapshot
     *
     * @throws \Exception
     * @return bool
     */
    public function rollback()
    {
        $result = parent::rollback();

        $this->lastOperationSucceed = false;

        try {
            $this->getDbBackupManager()->rollback();
        } catch (\Exception $e) {
            $this->removeDbBackup();
            throw $e;
        }

        $this->removeDbBackup();
        $this->lastOperationSucceed = true;

        return $result;
    }

    /**
     * Implementation Create Backup functionality for Snapshot
     *
     * @throws \Exception
     * @return bool
     */
    public function create()
    {
        $this->getDbBackupManager()->create();

        try {
            $result = parent::create();
        } catch (\Exception $e) {
            $this->removeDbBackup();
            throw $e;
        }

        $this->lastOperationSucceed = false;
        $this->removeDbBackup();
        $this->lastOperationSucceed = true;

        return $result;
    }

    /**
     * Overlap getType
     *
     * @return string
     * @see BackupInterface::getType()
     */
    public function getType()
    {
        return 'snapshot';
    }

    /**
     * Create Db Instance
     *
     * @return BackupInterface
     */
    protected function createDbBackupInstance()
    {
        return $this->backupFactory->create(Factory::TYPE_DB)
            ->setBackupExtension('sql')
            ->setTime($this->getTime())
            ->setBackupsDir($this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR)->getAbsolutePath())
            ->setResourceModel($this->getResourceModel());
    }

    /**
     * Get database backup manager
     *
     * @return Db
     */
    protected function getDbBackupManager()
    {
        if ($this->dbBackupManager === null) {
            $this->dbBackupManager = $this->createDbBackupInstance();
        }

        return $this->dbBackupManager;
    }

    /**
     * Set Db backup manager
     *
     * @param AbstractBackup $manager
     * @return $this
     */
    public function setDbBackupManager(AbstractBackup $manager)
    {
        $this->dbBackupManager = $manager;
        return $this;
    }

    /**
     * Get Db Backup Filename
     *
     * @return string
     */
    public function getDbBackupFilename()
    {
        return $this->getDbBackupManager()->getBackupFilename();
    }

    /**
     * Remove Db backup after added it to the snapshot
     *
     * @return $this
     */
    protected function _removeDbBackup()
    {
        @unlink($this->getDbBackupManager()->getBackupPath());
        return $this;
    }
}
