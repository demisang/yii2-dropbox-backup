Yii2-dropbox-backup
===================
Yii2 console command for making site backups and upload it to your dropbox account.

Installation
---
Add to composer.json in your project
```json
{
	"require": {
  		"demi/dropbox-backup": "~1.0"
	}
}
```

# Configurations
---

To get started, [configure backup component](https://github.com/demisang/yii2-backup#configurations) _(you do not need to install it)_.

Then [create new dropbox application](https://www.dropbox.com/developers/apps/create)
and get dropbox **AppKey** and **AppSecret**.

Configure **/console/config/main.php**:
```php
return [
    'controllerMap' => [
        'backup' => [
            'class' => 'demi\backup\dropbox\BackupController',
            // Name of \demi\backup\Component in Yii components.
            // Default Yii::$app->backup
            'backupComponent' => 'backup',
            // Dropbox app identifier
            'dropboxAppKey' => '65pwea8lqgbq5dm',
            // Dropbox app secret
            'dropboxAppSecret' => 'k2x0sl8a7wfj7h9',
            // Access token for user which will be get up backups.
            // To get this navigate to
            // https://www.dropbox.com/developers/apps/info/<AppKey>
            // and press OAuth 2: Generated access token button.
            'dropboxAccessToken' => 'kFflkUk7K3AAAAAAAAAAEh2tNeQbPbOX8Z11wk0rSdFfYMb5B5VX6kTvkcWz5N8R',
            // Path in the dropbox folder where would be saved backups
            'dropboxUploadPath' => '/',
            // If true: will be deleted files in the
            // dropbox when $expiryTime has come
            'autoDelete' => true,
            // Number of seconds after which the file is
            // considered deprecated and will be deleted.
            'expiryTime' => 30 * 86400, // 30 days
        ],
    ],
];
```
# Usage

Run console command:
```bash
php yii backup 
```

It will generated current site backup(based on [backup component](https://github.com/demisang/yii2-backup#configurations)) and upload it to your dropbox account.