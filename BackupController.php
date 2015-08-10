<?php

namespace demi\backup\dropbox;

use Yii;
use yii\helpers\Console;
use yii\console\Controller;
use yii\base\InvalidConfigException;
use \Dropbox as dbx;

/**
 * Console command for making backup and upload them to Dropbox
 *
 * @property \demi\backup\Component $component
 * @property dbx\Client $client
 */
class BackupController extends Controller
{
    /** @var string Name of \demi\backup\Component in Yii components. Default Yii::$app->backup */
    public $backupComponent = 'backup';
    /** @var string Dropbox app identifier. https://www.dropbox.com/developers/apps */
    public $dropboxAppKey;
    /** @var string Dropbox app secret. https://www.dropbox.com/developers/apps */
    public $dropboxAppSecret;
    /**
     * Dropbox access token for user which will be get up backups.
     *
     * To get this navigate to https://www.dropbox.com/developers/apps/info/<AppKey>
     * and press OAuth 2: Generated access token button.
     *
     * @var string
     */
    public $dropboxAccessToken;
    /** @var string Path in the bropbox where would be saved backups */
    public $dropboxUploadPath = '/';
    /** @var bool if TRUE: will be deleted files in the dropbox where $expiryTime has come */
    public $autoDelete = true;
    /**
     * Number of seconds after which the file is considered deprecated and will be deleted.
     * By default 1 month (2592000 seconds).
     *
     * @var int
     */
    public $expiryTime = 2592000;
    /** @var dbx\Client Dropbox client instance */
    protected $_client;

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function init()
    {
        if ($this->dropboxAppKey === null) {
            throw new InvalidConfigException('You must set "\demi\dropbox\backup\BackupController::$dropboxAppKey"');
        } elseif ($this->dropboxAppSecret === null) {
            throw new InvalidConfigException('You must set "\demi\dropbox\backup\BackupController::$dropboxAppSecret"');
        } elseif ($this->dropboxAccessToken === null) {
            throw new InvalidConfigException('You must set "\demi\dropbox\backup\BackupController::$dropboxAccessToken"');
        }

        parent::init();
    }

    /**
     * Run creating new backup and save it to the Dropbox
     */
    public function actionIndex()
    {
        // Make new backup
        $backupFile = $this->component->create();
        // Get name for new dropbox file
        $dropboxFile = basename($backupFile);

        // Dropbox client instance
        $client = $this->client;

        // Read backup file
        $f = fopen($backupFile, "rb");
        // Upload to dropbox
        $client->uploadFile($this->dropboxUploadPath . $dropboxFile, dbx\WriteMode::add(), $f);
        // Close backup file
        fclose($f);

        $this->stdout('Backup file successfully uploaded into dropbox: ' . $dropboxFile . PHP_EOL, Console::FG_GREEN);

        if ($this->autoDelete) {
            // Auto removing files from dropbox that oldest of the expiry time
            $this->actionDeleteJunk();
        }

        // Cleanup server backups
        $this->component->deleteJunk();
    }

    /**
     * Removing files from dropbox that oldest of the expiry time
     *
     * @throws dbx\Exception_BadResponseCode
     * @throws dbx\Exception_OverQuota
     * @throws dbx\Exception_RetryLater
     * @throws dbx\Exception_ServerError
     */
    public function actionDeleteJunk()
    {
        // Get all files from dropbox backups folder
        $folderMetadata = $this->client->getMetadataWithChildren($this->dropboxUploadPath);
        $files = $folderMetadata['contents'];

        // Calculate expired time
        $expiryDate = time() - $this->expiryTime;

        foreach ($files as $key => $file) {
            // Full path to dropbox file
            $filepath = $file['path'];
            // Dropbox file last modified time
            $filetime = strtotime($file['modified']);

            if (isset($file['is_dir']) && $file['is_dir']) {
                continue;
            } elseif (substr($filepath, -4) !== '.tar') {
                // Check extension
                continue;
            }

            if ($filetime <= $expiryDate) {
                // if the time has come - delete file
                $this->client->delete($filepath);
                $this->stdout('expired file was deleted from dropbox: ' . $filepath . PHP_EOL, Console::FG_YELLOW);
            }
        }
    }

    /**
     * Get instance of Dropbox client
     *
     * @return dbx\Client
     */
    public function getClient()
    {
        if ($this->_client instanceof dbx\Client) {
            return $this->_client;
        }

        return $this->_client = new dbx\Client($this->dropboxAccessToken, Yii::$app->name);
    }

    /**
     * Get Backup component
     *
     * @return \demi\backup\Component
     * @throws \yii\base\InvalidConfigException
     */
    public function getComponent()
    {
        return Yii::$app->get($this->backupComponent);
    }
}