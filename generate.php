<?php
/**
 * Created by Miroslav Čillík <miro@keboola.com>
 * Date: 05/11/14
 * Time: 17:30
 */

$version = '0.0.1';

//print "" . PHP_EOL . PHP_EOL;
print <<<EOT
  ██████▓██   ██▓ ██▀███   █    ██  ██▓███
▒██    ▒ ▒██  ██▒▓██ ▒ ██▒ ██  ▓██▒▓██░  ██▒
░ ▓██▄    ▒██ ██░▓██ ░▄█ ▒▓██  ▒██░▓██░ ██▓▒
  ▒   ██▒ ░ ▐██▓░▒██▀▀█▄  ▓▓█  ░██░▒██▄█▓▒ ▒
▒██████▒▒ ░ ██▒▓░░██▓ ▒██▒▒▒█████▓ ▒██▒ ░  ░
▒ ▒▓▒ ▒ ░  ██▒▒▒ ░ ▒▓ ░▒▓░░▒▓▒ ▒ ▒ ▒▓▒░ ░  ░
░ ░▒  ░ ░▓██ ░▒░   ░▒ ░ ▒░░░▒░ ░ ░ ░▒ ░
░  ░  ░  ▒ ▒ ░░    ░░   ░  ░░░ ░ ░ ░░
      ░  ░ ░        ░        ░
         ░ ░

Syrup Component Generator v$version

You can provide --namespace and --short-name either as arguments or via interactive interface.
(Note that namespace must contain Bundle at the end).

Options:
--namespace     - Namespace of your component ie. "Keboola/DbExtractorBundle"
--short-name    - Short name for you component ie. "ex-db"

Example:
php generate.php --namespace="Keboola/DbExtractorBundle" --short-name="ex-db"


EOT;

$namespace = null;
$shortName = null;

//var_dump($argv); die;

foreach ($argv as $arg) {
	if (false !== strstr($arg, '--namespace')) {
		$argArr = explode('=', $arg);
		$namespace = $argArr[1];
		continue;
	}

	if (false !== strstr($arg, '--short-name')) {
		$argArr = explode('=', $arg);
		$shortName = $argArr[1];
		continue;
	}
}


if ($namespace == null) {
	print "Enter namespace for your component (ie.: Keboola/DbExtractorBundle): " . PHP_EOL;
	$namespace = fgets(STDIN);
}

if ($shortName == null) {
	print "Enter short name for your component (ie.: ex-db): " . PHP_EOL;
	$shortName = fgets(STDIN);
}


// create parameters.yml

print str_pad('Creating parameters.yml', 50, ' ');
createParametersYaml($namespace, $shortName);
print 'OK' . PHP_EOL;

// create composer.json
print str_pad('Creating composer.json', 50, ' ');
createComposerJson($namespace, $shortName);
print 'OK' . PHP_EOL;

// download composer
print str_pad('Downloading composer', 50, ' ');
exec('curl -sS https://getcomposer.org/installer | php');
print 'OK' . PHP_EOL;

// run composer install
passthru('php -d memory_limit=-1 composer.phar install');

// generate component
print str_pad('Generating component skeleton', 50, ' ');
passthru('php vendor/keboola/syrup/app/console syrup:generate:component --namespace="'.$namespace.'" --short-name="'.$shortName.'"');

function printError($message)
{
	print <<<EOT

	Error: $message

EOT;
}

function generateEncryptionKey()
{
	$size = mcrypt_get_iv_size(MCRYPT_CAST_256, MCRYPT_MODE_CFB);
	$iv = mcrypt_create_iv($size, MCRYPT_DEV_RANDOM);

	return bin2hex($iv);
}

function createParametersYaml($namespace, $shortName)
{
	$encryptionKey = generateEncryptionKey();

	$filename = 'parameters.yml';

	fopen($filename, 'w+');

	$content = <<<EOT
parameters:
    app_name: $shortName

    encryption_key: $encryptionKey

    components:
EOT;

	file_put_contents($filename, $content);
}

function createComposerJson($namespace, $shortName)
{
	$json = [
		'name'  => strtolower($namespace),
		'type'  => 'symfony-bundle',
		'description'   => 'Some new component',
		'keywords'  => [],
		'authors'   => [],
		'repositories'  => [],
		'require'   => [
			'syrup/component-bundle'    => '~1.9.23'
		],
		'require-dev'   => [
			'phpunit/phpunit'   => '3.7.*'
		],
		'scripts'   => [
			'post-install-cmd'  => [
				"Syrup\\CoreBundle\\DeploymentHandler\\ScriptHandler::getParameters",
				"Syrup\\CoreBundle\\DeploymentHandler\\ScriptHandler::getSharedParameters",
				"Sensio\\Bundle\\DistributionBundle\\Composer\\ScriptHandler::buildBootstrap"
			],
			'post-update-cmd'  => [
				"Syrup\\CoreBundle\\DeploymentHandler\\ScriptHandler::getParameters",
				"Syrup\\CoreBundle\\DeploymentHandler\\ScriptHandler::getSharedParameters",
				"Sensio\\Bundle\\DistributionBundle\\Composer\\ScriptHandler::buildBootstrap",
				"Sensio\\Bundle\\DistributionBundle\\Composer\\ScriptHandler::clearCache"
			]
		],
		'minimum-stability' => 'stable',
		'autoload'  => [
			'psr-0' => [
				str_replace('/','\\',$namespace)    => ''
			]
		],
		'target-dir'    => $namespace,
		'extra' => [
			"symfony-app-dir"   => "vendor/keboola/syrup/app",
	        "symfony-web-dir"   => "vendor/keboola/syrup/web",
            "syrup-app-name"    => $shortName
		]
	];

	$filename = 'composer.json';
	fopen($filename, 'w+');
	file_put_contents($filename, json_encode($json));
}
