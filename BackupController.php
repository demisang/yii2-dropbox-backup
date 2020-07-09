<?php
/**
 * @copyright Copyright (c) 2020 Ivan Orlov
 * @license   https://github.com/demisang/yii2-dropbox-backup/blob/master/LICENSE
 * @link      https://github.com/demisang/yii2-dropbox-backup#readme
 * @author    Ivan Orlov <gnasimed@gmail.com>
 */

namespace demi\backup\dropbox;

use Yii;
use yii\helpers\Console;
use yii\console\Controller;
use yii\base\InvalidConfigException;
use Spatie\Dropbox\Client;

/**
 * Console command for doing backup and upload them to Dropbox
 *
 * @property-read \demi\backup\Component $component
 * @property-read Client $client
 */
class BackupController extends Controller
{
    /**
     * Name of \demi\backup\Component in Yii components. Default Yii::$app->backup
     *
     * @var string
     */
    public $backupComponent = 'backup';
    /**
     * Dropbox app identifier. https://www.dropbox.com/developers/apps
     *
     * @var string
     */
    public $dropboxAppKey;
    /**
     * Dropbox app secret. https://www.dropbox.com/developers/apps
     *
     * @var string
     */
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
    /**
     * Path in the dropbox where would be saved backups
     *
     * @var string
     */
    public $dropboxUploadPath = '/';
    /**
     * [Optional] Guzzle Client instance for "spatie/dropbox-api" Client contructor
     * @see \Spatie\Dropbox\Client::__construct()
     *
     * @var \GuzzleHttp\Client
     */
    public $guzzleClient;
    /**
     * [Optional] Set max chunk size per request (determines when to switch from "one shot upload" to upload session and
     * defines chunk size for uploads via session).
     * @see \Spatie\Dropbox\Client::__construct()
     *
     * @var int
     */
    public $maxChunkSize = Client::MAX_CHUNK_SIZE;
    /**
     * [Optional] How many times to retry an upload session start or append after RequestException.
     * @see \Spatie\Dropbox\Client::__construct()
     *
     * @var int
     */
    public $maxUploadChunkRetries = 0;
    /** @var bool if TRUE: will be deleted files in the dropbox when $expiryTime has come */
    public $autoDelete = true;
    /**
     * Number of seconds after which the file is considered deprecated and will be deleted.
     * By default 1 month (2592000 seconds).
     *
     * @var int
     */
    public $expiryTime = 2592000;

    /**
     * Dropbox SDK Client instance
     *
     * @var Client
     */
    protected $_client;

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function init()
    {
        if ($this->dropboxAccessToken === null && ($this->dropboxAppKey === null || $this->dropboxAppSecret === null)) {
            throw new InvalidConfigException('You must set "' . get_class($this) . '::$dropboxAccessToken" OR ($dropboxAppKey AND $dropboxAppSecret)');
        }

        parent::init();
    }

    /**
     * Run new backup creation and storing into Dropbox
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
        $f = fopen($backupFile, 'rb');
        // Upload to dropbox
        $client->upload($this->getFilePathWithDir($dropboxFile), $f);
        // Close backup file
        @fclose($f);

        $this->stdout('Backup file successfully uploaded into dropbox: ' . $dropboxFile . PHP_EOL, Console::FG_GREEN);

        if ($this->autoDelete) {
            // Auto removing files from dropbox that oldest of the expiry time
            $this->actionRotateOldFiles();
        }

        // Cleanup server backups
        $this->component->deleteJunk();
    }

    /**
     * Removing files from dropbox that oldest of the expiry time
     */
    public function actionRotateOldFiles()
    {
        // Get all files from dropbox backups folder
        $files = $this->client->listFolder($this->dropboxUploadPath);
        $files = $files['entries'];

        // Calculate expired time
        $expiryDate = time() - $this->expiryTime;

        foreach ($files as $file) {
            // Skip non-files
            if ($file['.tag'] !== 'file') {
                continue;
            }

            // Dropbox file last modified time
            $filetime = strtotime($file['client_modified']);

            // If time is come - delete file
            if ($filetime <= $expiryDate) {
                $filepath = $this->getFilePathWithDir($file['name']);
                $this->client->delete($filepath);
                $this->stdout('expired file was deleted from dropbox: ' . $filepath . PHP_EOL, Console::FG_YELLOW);
            }
        }
    }

    /**
     * Get Dropbox SDK Client
     *
     * @return Client
     */
    public function getClient()
    {
        if ($this->_client instanceof Client) {
            return $this->_client;
        }

        /** @noinspection ProperNullCoalescingOperatorUsageInspection As client contructor argument */
        $access = $this->dropboxAccessToken ?? [$this->dropboxAppKey, $this->dropboxAppSecret];

        return $this->_client = new Client(
            $access, // 'accessToken' or ['appKey', 'appSecret']
            $this->guzzleClient,
            $this->maxChunkSize,
            $this->maxUploadChunkRetries
        );
    }

    /**
     * Set Dropbox SDK Client
     *
     * @param Client $client
     */
    public function setClient(Client $client)
    {
        $this->_client = $client;
    }

    /**
     * Get Backup component
     *
     * @return \demi\backup\Component
     * @throws \yii\base\InvalidConfigException
     */
    public function getComponent()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection Yii always return correct class or trows Exception */
        return Yii::$app->get($this->backupComponent);
    }

    /**
     * Return full path to file
     *
     * @param string $filename
     *
     * @return string
     */
    protected function getFilePathWithDir($filename)
    {
        return rtrim($this->dropboxUploadPath, '/\\') . '/' . $filename;
    }
}
