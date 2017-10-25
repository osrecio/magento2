<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Framework\Backup\Filesystem\Rollback;

use Magento\Framework\Archive;
use Magento\Framework\Archive\Gz;
use Magento\Framework\Archive\Helper\File;
use Magento\Framework\Archive\Helper\File\Gz as HelperGz;
use Magento\Framework\Archive\Tar;
use Magento\Framework\Backup\Exception\CantLoadSnapshot;
use Magento\Framework\Backup\Exception\NotEnoughPermissions;
use Magento\Framework\Backup\Filesystem\Helper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

/**
 * Rollback worker for rolling back via local filesystem
 *
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Fs extends AbstractRollback
{
    /**
     * Files rollback implementation via local filesystem
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

        $fsHelper = new Helper();

        $filesInfo = $fsHelper->getInfo(
            $this->snapshot->getRootDir(),
            Helper::INFO_WRITABLE,
            $this->snapshot->getIgnorePaths()
        );

        if (!$filesInfo['writable']) {
            throw new NotEnoughPermissions(
                new Phrase('Unable to make rollback because not all files are writable')
            );
        }

        $archiver = new Archive();

        /**
         * we need these fake initializations because all magento's files in filesystem will be deleted and autoloader
         * wont be able to load classes that we need for unpacking
         */
        new Tar();
        new Gz();
        new File('');
        new HelperGz('');
        new LocalizedException(new Phrase('dummy'));

        if (!$this->snapshot->keepSourceFile()) {
            $fsHelper->rm($this->snapshot->getRootDir(), $this->snapshot->getIgnorePaths());
        }
        $archiver->unpack($snapshotPath, $this->snapshot->getRootDir());
    }
}
