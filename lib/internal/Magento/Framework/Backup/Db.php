<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Framework\Backup;

use Magento\Framework\Archive;
use Magento\Framework\Backup\Filesystem\Iterator\File;

/**
 * Class to work with database backups
 *
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Db extends AbstractBackup
{
    /**
     * @var \Magento\Framework\Backup\Db\BackupFactory
     */
    protected $backupFactory;

    /**
     * @param \Magento\Framework\Backup\Db\BackupFactory $backupFactory
     */
    public function __construct(\Magento\Framework\Backup\Db\BackupFactory $backupFactory)
    {
        $this->backupFactory = $backupFactory;
    }

    /**
     * Implements Rollback functionality for Db
     *
     * @return bool
     */
    public function rollback()
    {
        set_time_limit(0);
        ignore_user_abort(true);

        $this->lastOperationSucceed = false;

        $archiveManager = new Archive();
        $source = $archiveManager->unpack($this->getBackupPath(), $this->getBackupsDir());

        $file = new File($source);
        foreach ($file as $statement) {
            $this->getResourceModel()->runCommand($statement);
        }
        if ($this->keepSourceFile()) {
            @unlink($source);
        }

        $this->lastOperationSucceed = true;

        return true;
    }

    /**
     * Checks whether the line is last in sql command
     *
     * @param string $line
     * @return bool
     */
    protected function isLineLastInCommand($line)
    {
        $cleanLine = trim($line);
        $lineLength = strlen($cleanLine);

        $returnResult = false;
        if ($lineLength > 0) {
            $lastSymbolIndex = $lineLength - 1;
            if ($cleanLine[$lastSymbolIndex] == ';') {
                $returnResult = true;
            }
        }

        return $returnResult;
    }

    /**
     * Implements Create Backup functionality for Db
     *
     * @return bool
     */
    public function create()
    {
        set_time_limit(0);
        ignore_user_abort(true);

        $this->lastOperationSucceed = false;

        $backup = $this->backupFactory->createBackupModel()->setTime(
            $this->getTime()
        )->setType(
            $this->getType()
        )->setPath(
            $this->getBackupsDir()
        )->setName(
            $this->getName()
        );

        $backupDb = $this->backupFactory->createBackupDbModel();
        $backupDb->createBackup($backup);

        $this->lastOperationSucceed = true;

        return true;
    }

    /**
     * Get database size
     *
     * @return int
     */
    public function getDBSize()
    {
        $backupDb = $this->backupFactory->createBackupDbModel();
        return $backupDb->getDBBackupSize();
    }

    /**
     * Get Backup Type
     *
     * @return string
     */
    public function getType()
    {
        return 'db';
    }
}
