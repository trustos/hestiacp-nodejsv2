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
            "env_vars" => [
                "type" => "dynamic",
                "label" => "Environment Variables",
                "fields" => [
                    "key" => [
                        "type" => "text",
                        "label" => "Variable Name",
                        "placeholder" => "e.g., DATABASE_URL",
                    ],
                    "value" => [
                        "type" => "text",
                        "label" => "Value",
                        "placeholder" => "e.g., mongodb://localhost:27017/mydb",
                    ],
                ],
                "add_button_text" => "Add Environment Variable",
            ],
        ],
        "database" => false,
        "server" => [
            "php" => [
                "supported" => ["7.2", "7.3", "7.4", "8.0", "8.1", "8.2"],
            ],
        ],
    ];

    /**
     * Reads the existing environment variables from the .env file.
     *
     * @return array<string, string> An associative array of environment variables
     */
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

        // Assuming the command returns output as a string
        if (is_string($result) && stripos($result, "error") !== false) {
            throw new \Exception(
                "Failed to install NVM or Node.js $nodeVersion. Error message: $result"
            );
        }
    }

    private function getHestiaLogContent(): string
    {
        $logFile = "/var/log/hestia/auth.log";
        if (file_exists($logFile)) {
            // Get the last 20 lines of the log file
            $logContent = shell_exec("tail -n 20 $logFile");
            return $logContent ?: "Unable to read log file";
        }
        return "Log file not found";
    }

    public function __construct($domain, HestiaApp $appcontext)
    {
        parent::__construct($domain, $appcontext);

        $this->nodeJsPaths = new NodeJsPaths($appcontext);
        $this->nodeJsUtils = new NodeJsUtil($appcontext);
    }

    public function install(array $options = null)
    {
        if (empty($options)) {
            $existingEnv = $this->readExistingEnv();

            // Add existing env vars to the dynamic field
            if (!empty($existingEnv)) {
                $this->config["form"]["env_vars"]["value"] = [];
                foreach ($existingEnv as $key => $value) {
                    $this->config["form"]["env_vars"]["value"][] = [
                        "key" => $key,
                        "value" => $value,
                    ];
                }
            }

            return true; // Return the form config
        } else {
            // Process the submitted form
            $envVars = [];
            if (isset($options["env_vars"]) && is_array($options["env_vars"])) {
                foreach ($options["env_vars"] as $env) {
                    if (!empty($env["key"]) && isset($env["value"])) {
                        $envVars[$env["key"]] = $env["value"];
                    }
                }
            }

            // Proceed with installation
            $this->createAppDir();
            $this->installNvm($options);
            $this->createConfDir();
            $this->createAppEntryPoint($options);
            $this->createAppNvmVersion($options);
            $this->createAppEnv($envVars);
            $this->createPublicHtmlConfigFile();
            $this->createAppProxyTemplates($options);
            $this->createAppConfig($options);
            $this->pm2StartApp();

            if (isset($options["npm_install"]) && $options["npm_install"]) {
                $this->npmInstall();
            }

            return true;
        }
    }

    private function ensureRequiredFields()
    {
        $requiredFields = [
            "PORT" => [
                "type" => "text",
                "placeholder" => "3000",
                "label" => "PORT",
            ],
            "start_script" => [
                "type" => "text",
                "placeholder" => "npm run start",
                "label" => "Start Script",
            ],
            "node_version" => [
                "type" => "select",
                "options" => ["v22.9.0", "v20.18.0", "v18.20.4", "v16.20.2"],
                "label" => "Node Version",
            ],
        ];

        foreach ($requiredFields as $key => $field) {
            if (!isset($this->config["form"][$key])) {
                $this->config["form"][$key] = $field;
            }
        }
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

        if (isset($options["npm_install"])) {
            $this->npmInstall();
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

    public function createAppEnv($envVars)
    {
        $envPath = $this->nodeJsPaths->getAppDir($this->domain, ".env");
        $envContent = "";

        foreach ($envVars as $key => $value) {
            if (
                $key !== "node_version" &&
                $key !== "start_script" &&
                $key !== "php_version"
            ) {
                $envContent .=
                    $key . "=" . $this->formatEnvValue($value) . "\n";
            }
        }

        $tmpFile = $this->saveTempFile($envContent);

        return $this->nodeJsUtils->moveFile($tmpFile, $envPath);
    }

    private function formatEnvValue($value)
    {
        // If the value is already quoted, return it as is
        if (preg_match('/^(["\']).*\1$/', $value)) {
            return $value;
        }

        // If the value contains spaces or special characters, add quotes
        if (preg_match('/[\s\'"\\\\]/', $value)) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }

        // Otherwise, return the value as is
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
        $appDir = $this->nodeJsPaths->getAppDir($this->domain);
        $command = "cd $appDir && npm install";

        $result = $this->appcontext->runUser("v-run-cmd", [$command]);

        if ($result === false || (is_object($result) && $result->code !== 0)) {
            throw new \Exception("Failed to run npm install");
        }
    }

    public function createAppConfig(array $options = null)
    {
        $configContent = [];

        // Add standard configurations
        $configContent[] = "PORT=" . trim($options["PORT"] ?? "3000");
        $configContent[] =
            'START_SCRIPT="' .
            trim($options["start_script"] ?? "npm run start") .
            '"';
        $configContent[] =
            "NODE_VERSION=" . trim($options["node_version"] ?? "v20.20.2");

        // Add all other options from the form, excluding certain keys
        $excludeKeys = ["PORT", "start_script", "node_version"];
        foreach ($options as $key => $value) {
            if (!in_array($key, $excludeKeys)) {
                // Format the value appropriately
                $formattedValue = $this->formatConfigValue($value);
                $configContent[] = strtoupper($key) . "=" . $formattedValue;
            }
        }

        // Join all config entries
        $config = implode("|", $configContent);

        $file = $this->saveTempFile($config);

        return $this->nodeJsUtils->moveFile(
            $file,
            $this->nodeJsPaths->getConfigFile($this->domain)
        );
    }

    private function formatConfigValue($value)
    {
        // If the value contains spaces or special characters, add quotes
        if (preg_match('/[\s\'"\\\\]/', $value)) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }
        return $value;
    }

    public function createPublicHtmlConfigFile()
    {
        // This file is created for hestia to detect that there is an installed app when you try to install other app
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
