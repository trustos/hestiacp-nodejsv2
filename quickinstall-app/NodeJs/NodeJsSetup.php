<?php

namespace Hestia\WebApp\Installers\NodeJs;

use Hestia\WebApp\Installers\BaseSetup as BaseSetup;
use Hestia\WebApp\Installers\NodeJs\NodeJsUtils\NodeJsPaths as NodeJsPaths;
use Hestia\WebApp\Installers\NodeJs\NodeJsUtils\NodeJsUtil as NodeJsUtil;
use Hestia\System\HestiaApp;

class NodeJsSetup extends BaseSetup
{
    protected const TEMPLATE_PROXY_VARS = ["%nginx_port%"];
    protected const TEMPLATE_ENTRYPOINT_VARS = [
        "%app_name%",
        "%app_start_script%",
        "%app_cwd%",
    ];

    protected $nodeJsPaths;
    protected $nodeJsUtils;
    protected $appInfo = [
        "name" => "NodeJs",
        "group" => "node",
        "enabled" => true,
        "version" => "2.0.0",
        "thumbnail" => "nodejs.png",
    ];
    protected $appname = "NodeJs";
    protected $config = [
        "form" => [
            "node_version" => [
                "type" => "select",
                "options" => ["v22.9.0", "v20.18.0", "v18.20.4", "v16.20.2"],
                "value" => "v20.18.0",
            ],
            "start_script" => [
                "type" => "text",
                "placeholder" => "npm run start",
            ],
            "port" => [
                "type" => "text",
                "placeholder" => "3000",
                "value" => "",
            ],
            "npm_install" => [
                "type" => "select",
                "options" => ["no", "yes"],
                "value" => "no",
                "label" => "Run npm install after setup",
            ],
        ],
        "database" => false,
        "server" => [
            "php" => [
                "supported" => ["7.2", "7.3", "7.4", "8.0", "8.1", "8.2"],
            ],
        ],
    ];

    public function __construct($domain, HestiaApp $appcontext)
    {
        parent::__construct($domain, $appcontext);

        $this->nodeJsPaths = new NodeJsPaths($appcontext);
        $this->nodeJsUtils = new NodeJsUtil($appcontext);

        // Echo the script directly
        echo $this->getCustomScript();
    }

    protected function readExistingEnv()
    {
        $envPath = $this->nodeJsPaths->getAppDir($this->domain, ".env");
        $envContent = [];

        if (file_exists($envPath)) {
            $lines = file(
                $envPath,
                FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
            );
            foreach ($lines as $line) {
                if (strpos($line, "=") !== false) {
                    list($key, $value) = explode("=", $line, 2);
                    $envContent[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
                }
            }
        }

        return $envContent;
    }

    protected function installNvm(array $options): void
    {
        $nodeVersion = $options["node_version"] ?? "v20.18.0";

        $result = $this->appcontext->runUser("v-add-nvm-nodejs", [
            $nodeVersion,
        ]);

        if ($result === false) {
            throw new \Exception(
                "Failed to install NVM or Node.js $nodeVersion. The command execution failed."
            );
        }

        if (is_string($result) && stripos($result, "error") !== false) {
            throw new \Exception(
                "Failed to install NVM or Node.js $nodeVersion. Error message: $result"
            );
        }
    }

    public function install(array $options = null)
    {
        $existingEnv = $this->readExistingEnv();
        error_log("Existing ENV vars: " . print_r($existingEnv, true));

        if (empty($options)) {
            if (!empty($existingEnv)) {
                $envString = "";
                foreach ($existingEnv as $key => $value) {
                    $envString .= "$key=$value\n";
                }
            }

            error_log(
                "Final form config: " . print_r($this->config["form"], true)
            );

            return $this->config["form"];
        } else {
            $this->performInstallation($options);
        }

        return true;
    }

    private function getCustomScript()
    {
        $existingEnv = $this->readExistingEnv();
        $envJson = json_encode($existingEnv);

        return '<script>
        document.addEventListener("DOMContentLoaded", function() {
            console.log("NodeJs setup script loaded");

            // Load existing .env contents
            var existingEnv = ' .
            $envJson .
            ';
            console.log("Existing .env contents:", existingEnv);

            // Your custom JavaScript here
            var form = document.querySelector("form");
            if (form) {
                // Populate form fields with existing .env values
                Object.keys(existingEnv).forEach(function(key) {
                    var input = form.querySelector(\'[name="\' + key + \'"]\');
                    if (input) {
                        input.value = existingEnv[key];
                    }
                });

                form.addEventListener("submit", function(event) {
                    // Custom form validation or manipulation
                    console.log("Form submitted");
                });
            } else {
                console.log("Form not found");
            }
        });
        </script>';
    }

    private function performInstallation(array $options)
    {
        $this->createAppDir();
        $this->installNvm($options);
        $this->createConfDir();
        $this->createAppEntryPoint($options);
        $this->createAppNvmVersion($options);
        $this->createAppEnv($options);
        $this->createPublicHtmlConfigFile();
        $this->createAppProxyTemplates($options);
        $this->createAppConfig($options);
        $this->pm2StartApp();

        if ($options["npm_install"] === "yes") {
            $packageJsonPath =
                $this->nodeJsPaths->getAppDir($this->domain) . "/package.json";
            if (file_exists($packageJsonPath)) {
                $this->npmInstall();
            } else {
                error_log("package.json not found. Skipping npm install.");
            }
        }
    }

    public function createAppEntryPoint(array $options = null)
    {
        $templateReplaceVars = [
            $this->domain,
            trim($options["start_script"]),
            $this->nodeJsPaths->getAppDir($this->domain),
        ];

        $data = $this->nodeJsUtils->parseTemplate(
            $this->nodeJsPaths->getAppEntrypointTemplate(),
            self::TEMPLATE_ENTRYPOINT_VARS,
            $templateReplaceVars
        );
        $tmpFile = $this->saveTempFile(implode($data));

        return $this->nodeJsUtils->moveFile(
            $tmpFile,
            $this->nodeJsPaths->getAppEntryPoint($this->domain)
        );
    }

    public function createAppNvmVersion($options)
    {
        $tmpFile = $this->saveTempFile($options["node_version"]);

        return $this->nodeJsUtils->moveFile(
            $tmpFile,
            $this->nodeJsPaths->getAppDir($this->domain, ".nvmrc")
        );
    }

    public function createAppEnv($options)
    {
        $envPath = $this->nodeJsPaths->getAppDir($this->domain, ".env");
        $envContent = [];

        foreach ($options as $key => $value) {
            if (
                $key !== "node_version" &&
                $key !== "start_script" &&
                $key !== "php_version" &&
                $key !== "npm_install"
            ) {
                $envContent[$key] = $this->formatEnvValue($value);
            }
        }

        $newEnvContent = "";
        foreach ($envContent as $key => $value) {
            $newEnvContent .= "$key=$value\n";
        }

        $tmpFile = $this->saveTempFile($newEnvContent);

        return $this->nodeJsUtils->moveFile($tmpFile, $envPath);
    }

    private function formatEnvValue($value)
    {
        if (preg_match('/^(["\']).*\1$/', $value)) {
            return $value;
        }

        if (preg_match('/[\s\'"\\\\]/', $value)) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }

        return $value;
    }

    public function createAppProxyTemplates(array $options = null)
    {
        $tplReplace = [trim($options["port"])];

        $proxyData = $this->nodeJsUtils->parseTemplate(
            $this->nodeJsPaths->getNodeJsProxyTemplate(),
            self::TEMPLATE_PROXY_VARS,
            $tplReplace
        );
        $proxyFallbackData = $this->nodeJsUtils->parseTemplate(
            $this->nodeJsPaths->getNodeJsProxyFallbackTemplate(),
            self::TEMPLATE_PROXY_VARS,
            $tplReplace
        );

        $tmpProxyFile = $this->saveTempFile(implode($proxyData));
        $tmpProxyFallbackFile = $this->saveTempFile(
            implode($proxyFallbackData)
        );

        $this->nodeJsUtils->moveFile(
            $tmpProxyFile,
            $this->nodeJsPaths->getAppProxyConfig($this->domain)
        );
        $this->nodeJsUtils->moveFile(
            $tmpProxyFallbackFile,
            $this->nodeJsPaths->getAppProxyFallbackConfig($this->domain)
        );
    }

    public function npmInstall()
    {
        $result = $this->appcontext->runUser("v-add-npm-install", [
            $this->appcontext->user,
            $this->domain,
        ]);

        if ($result === false || (is_object($result) && $result->code !== 0)) {
            throw new \Exception("Failed to run npm install");
        }
    }

    public function createAppConfig(array $options = null)
    {
        $configContent = [];

        $configContent[] = "PORT=" . trim($options["PORT"] ?? "3000");
        $configContent[] =
            'START_SCRIPT="' .
            trim($options["start_script"] ?? "npm run start") .
            '"';
        $configContent[] =
            "NODE_VERSION=" . trim($options["node_version"] ?? "v20.20.2");

        $excludeKeys = ["PORT", "start_script", "node_version", "npm_install"];
        foreach ($options as $key => $value) {
            if (!in_array($key, $excludeKeys)) {
                $formattedValue = $this->formatConfigValue($value);
                $configContent[] = strtoupper($key) . "=" . $formattedValue;
            }
        }

        $config = implode("|", $configContent);

        $file = $this->saveTempFile($config);

        return $this->nodeJsUtils->moveFile(
            $file,
            $this->nodeJsPaths->getConfigFile($this->domain)
        );
    }

    private function formatConfigValue($value)
    {
        if (preg_match('/[\s\'"\\\\]/', $value)) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }
        return $value;
    }

    public function createPublicHtmlConfigFile()
    {
        $this->appcontext->runUser("v-add-fs-file", [
            $this->getDocRoot("app.conf"),
        ]);
    }

    public function createAppDir()
    {
        $this->nodeJsUtils->createDir(
            $this->nodeJsPaths->getAppDir($this->domain)
        );
    }

    public function createConfDir()
    {
        $this->nodeJsUtils->createDir($this->nodeJsPaths->getConfigDir());
        $this->nodeJsUtils->createDir($this->nodeJsPaths->getConfigDir("/web"));
        $this->nodeJsUtils->createDir(
            $this->nodeJsPaths->getDomainConfigDir($this->domain)
        );
    }

    public function pm2StartApp()
    {
        return $this->appcontext->runUser("v-add-pm2-app", [
            $this->domain,
            $this->nodeJsPaths->getAppEntryPointFileName(),
        ]);
    }
}
