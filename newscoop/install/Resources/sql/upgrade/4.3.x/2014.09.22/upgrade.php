<?php

$newscoopDir = realpath(dirname(__FILE__).'/../../../../../../').'/';
$rootDir = realpath($newscoopDir.'../').'/';
$currentDir = dirname(__FILE__) .'/';
$diffFile = 'delete_diff.txt';
$upgradeErrors = array();

require_once $newscoopDir.'vendor/autoload.php';
require_once $newscoopDir.'/conf/database_conf.php';

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;
use Monolog\Logger;
use Newscoop\Installer\Services\DatabaseService;

$app = new Silex\Application();
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => $newscoopDir.'log/upgrade.log',
    'monolog.level' => Logger::NOTICE,
    'monolog.name' => 'upgrade'
));

$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver'    => 'pdo_mysql',
        'host'      => $Campsite['db']['host'],
        'dbname'    => $Campsite['db']['name'],
        'user'      => $Campsite['db']['user'],
        'password'  => $Campsite['db']['pass'],
        'port'      => $Campsite['db']['port'],
        'charset'   => 'utf8',
    )
));

$logger = $app['monolog'];

// Remove files
$finder = new Finder();
$filesystem = new Filesystem();
$finder->files()->in($currentDir)->name($diffFile);

$contents = null;
foreach ($finder as $file) {
    $contents = $file->getContents();
}

$filesToBeDeleted =  array_filter(preg_split('/\r\n|\n|\r/', $contents));
$folderToBeChecked = array();

if (count($filesToBeDeleted) > 0) {

    foreach ($filesToBeDeleted as $file) {

        if (strpos($file, 'newscoop/') === 0) {
            $fullPath = $newscoopDir.substr($file, 9);
        } else {
            $fullPath = $rootDir.$file;
        }

        if (is_file($fullPath) && is_readable($fullPath)) {

            try {
                $filesystem->remove(array($fullPath));
            } catch (IOException $e) {
                $msg = 'Could not remove file '.str_replace($newscoopDir, '', $fullPath).', please remove it manually.';
                $logger->addError($msg);
                $upgradeErrors[] = $msg;
            } catch (\Exception $e) {
                $msg = 'Could not remove file '.str_replace($newscoopDir, '', $fullPath).', please remove it manually.';
                $logger->addError($msg);
                $upgradeErrors[] = $msg;
            }
        }

        if (strpos($file, '/') !== false && strpos($file, 'newscoop/admin-files/lang') === false) {
            $folderToBeChecked[] = dirname($fullPath);
        }
    }
}

$folderToBeChecked = array_unique($folderToBeChecked);
arsort($folderToBeChecked);

// Add extra directories to remove recusively
$folderToBeChecked[] = $newscoopDir .'admin-files/lang';
$folderToBeChecked[] = $newscoopDir .'example';
$folderToBeChecked[] = $newscoopDir .'extensions/google-gadgets';
$folderToBeChecked[] = $newscoopDir .'install/cron_jobs';
$folderToBeChecked[] = $newscoopDir .'install/sample_data';

$folderToBeChecked[] = $rootDir .'cookbooks';
$folderToBeChecked[] = $rootDir .'dependencies';
$folderToBeChecked[] = $rootDir .'scripts';

foreach ($folderToBeChecked as $folder) {

    if (is_dir($folder)) {

        $finder = new Finder();
        $finder->in($folder);

         if (iterator_count($finder->files()) == 0) {

            try {
                // Remove subdirectories
                foreach ($finder->directories()->sortByName() as $subFolder) {
                    if (is_dir($subFolder)) {
                        $filesystem->remove(array($subFolder));
                    }
                }
            } catch (\Exception $e) {
                $msg = 'Could not remove or check subdirectories of '.str_replace($newscoopDir, '', $fullPath).'. Please see newscoop/install/Resources/sql/upgrade/4.3.x/2014.09.22/delete_diff.txt and remove the empty directories manually.';
                $logger->addError($msg);
                $upgradeErrors[] = $msg;
            }

            try {
                // Remove parent directory
                $filesystem->remove(array($folder));
            } catch (\Exception $e) {
                $msg = 'Could not remove folder '.str_replace($newscoopDir, '', $folder).', please remove it manually.';
                $logger->addError($msg);
                $upgradeErrors[] = $msg;
            }
        }
    }
}

try {
    $app['db']->query('ALTER TABLE `Plugins` DROP PRIMARY KEY');
} catch(\Exception $e) {
    if ($app['db']->errorCode() !== '42000') {
        $upgradeErrors[] = $e->getMessage();
        $logger->addError($e->getMessage());
    }
}

try {
    $app['db']->query('ALTER TABLE `Plugins` ADD `Id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST, ADD `Description` TEXT NOT NULL');
} catch(\Exception $e) {
    if ($app['db']->errorCode() !== '42S21') {
        $upgradeErrors[] = $e->getMessage();
        $logger->addError($e->getMessage());
    }
}

try {
    $configFile = realpath(__DIR__ . '/../../../../../../conf/configuration.php');
    $databaseService = new DatabaseService($logger);
    $databaseService->renderFile('_configuration.twig', $configFile, array());
} catch (\Exception $e) {
    $msg = "Could not update '" . $configFile . "', please update it manually."
    . " Copy content of '" . realpath(__DIR__ . '/../../../../../Resources/templates/_configuration.twig') . "' file to '" . $configFile . "' and save.\n";
    $logger->addError($msg);
    array_splice($upgradeErrors, 0, 0, array($msg));
}

if (count($upgradeErrors) > 0) {
    $msg = "Some files or directories could not automatically be removed. This is "
        . "most likely caused by permissions. \n"
        . "You can either remove the files manually (see ${newscoopDir}install/Resources/sql/upgrade/4.3.x/2014.09.22/delete_diff.txt) "
        . "or execute this file with root permissions, e.g.: \n"
        . "sudo php ${newscoopDir}install/Resources/sql/upgrade/4.3.x/2014.09.22/upgrade.php";
    $logger->addError($msg);
    array_splice($upgradeErrors, 0, 0, array($msg));
}
