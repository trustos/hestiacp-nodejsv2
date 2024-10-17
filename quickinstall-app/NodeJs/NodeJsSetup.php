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
            "modules_type" => [
                "type" => "select",
                "options" => ["ES Modules", "CommonJS"],
                "value" => "ES Modules",
                "label" => "Modules type",
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

    protected function readEcosystemFile()
    {
        $appDir = $this->nodeJsPaths->getAppDir($this->domain);
        $ecosystemPathJs = $appDir . "/ecosystem.config.js";
        $ecosystemPathCjs = $appDir . "/ecosystem.config.cjs";

        $result = [];

        if (file_exists($ecosystemPathCjs)) {
            $ecosystemPath = $ecosystemPathCjs;
            $result["modules_type"] = "CommonJS";
        } elseif (file_exists($ecosystemPathJs)) {
            $ecosystemPath = $ecosystemPathJs;
            $result["modules_type"] = "ES Modules";
        } else {
            return $result; // Return empty array if no ecosystem file found
        }

        $content = file_get_contents($ecosystemPath);
        // Use a more robust regex to extract the start script
        if (
            preg_match(
                "/script\s*:\s*['\"](.+?)['\"]|script\s*:\s*`(.+?)`/",
                $content,
                $matches
            )
        ) {
            $result["start_script"] = $matches[1] ?? $matches[2];
        }

        return $result;
    }

    protected function readNvmrcFile()
    {
        $nvmrcPath = $this->nodeJsPaths->getAppDir($this->domain, ".nvmrc");
        if (file_exists($nvmrcPath)) {
            $nodeVersion = trim(file_get_contents($nvmrcPath));
            if (!empty($nodeVersion)) {
                return ["node_version" => $nodeVersion];
            }
        }
        return [];
    }

    protected function getPm2Logs()
    {
        $output = "PM2 Logs for user account:\n\n";

        try {
            $logs = $this->appcontext->runUser("v-list-pm2-logs", ["100"]);
            $output .= $logs; // Include all output for debugging
        } catch (\Exception $e) {
            $output .= "Error retrieving PM2 logs: " . $e->getMessage() . "\n";
        }

        return $output;
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
        $ecosystemData = $this->readEcosystemFile();
        $nvmrcData = $this->readNvmrcFile();
        $pm2Logs = $this->getPm2Logs();

        $combinedData = array_merge($existingEnv, $ecosystemData, $nvmrcData);
        $combinedData["pm2Logs"] = $pm2Logs;

        // Get open ports
        $openPorts = shell_exec(
            "ss -tuln | awk '{print $5}' | grep : | cut -d: -f2 | sort -nu"
        );
        $openPortsArray = array_filter(explode("\n", $openPorts));
        $combinedData["openPorts"] = $openPortsArray;

        $dataJson = json_encode($combinedData);

        $scriptPath = __DIR__ . "/env-vars-script.js";
        $scriptContent = file_get_contents($scriptPath);

        return "<script>
                var appData = {$dataJson};
                {$scriptContent}
            </script>";
    }

    private function performInstallation(array $options)
    {
        try {
            $this->createAppDir();
            $this->installNvm($options);
            $this->createConfDir();
            $this->createAppEntryPoint($options);
            $this->createAppNvmVersion($options);
            $this->createAppEnv($options);
            $this->createPublicHtmlConfigFile();
            $this->createAppProxyTemplates($options);
            $this->createAppConfig($options);
            $this->npmInstall($options);
            $this->pm2StartApp($options);
        } catch (\Exception $e) {
            $this->appcontext->runUser("v-log-action", [
                "Error",
                "Web",
                "Failed to perform NodeJS installation for {$this->domain}: " .
                $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function createAppEntryPoint(array $options = null)
    {
        $templateReplaceVars = [
            $this->domain,
            trim($options["start_script"]),
            $this->nodeJsPaths->getAppDir($this->domain),
            "/home",
        ];

        $data = $this->nodeJsUtils->parseTemplate(
            $this->nodeJsPaths->getAppEntrypointTemplate(),
            self::TEMPLATE_ENTRYPOINT_VARS,
            $templateReplaceVars
        );
        $tmpFile = $this->saveTempFile(implode($data));

        return $this->nodeJsUtils->moveFile(
            $tmpFile,
            $this->nodeJsPaths->getAppEntryPoint(
                $this->domain,
                $options["modules_type"] === "CommonJS"
            )
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
        $newEnvContent = [];

        $newEnvContent["PORT"] = trim($options["port"]);

        // Parse the JSON string of environment variables
        $envVars = json_decode($options["env_vars"] ?? "{}", true);

        // Merge existing env with new options, preferring new options
        foreach ($envVars as $key => $value) {
            $newEnvContent[$key] = $this->formatEnvValue($value);
        }

        // Create the new .env content
        $envContentString = "";
        foreach ($newEnvContent as $key => $value) {
            $envContentString .= "$key=$value\n";
        }

        // Only create a new file if there's content to write
        if (!empty($envContentString)) {
            $tmpFile = $this->saveTempFile($envContentString);
            return $this->nodeJsUtils->moveFile($tmpFile, $envPath);
        }

        return true; // Return true if no changes were needed
    }

    private function formatEnvValue($value)
    {
        // If the value is already quoted, return it as is
        if (preg_match('/^(["\']).*\1$/', $value)) {
            return $value;
        }

        // List of characters that require quoting
        $specialChars = [
            "#",
            "!",
            '$',
            "&",
            "*",
            "(",
            ")",
            "[",
            "]",
            "{",
            "}",
            "|",
            ";",
            "<",
            ">",
            "?",
            "`",
            " ",
            '\t',
            '\n',
            "\\",
            '"',
            "'",
        ];

        // Check if the value contains any special characters
        $needsQuoting = false;
        foreach ($specialChars as $char) {
            if (strpos($value, $char) !== false) {
                $needsQuoting = true;
                break;
            }
        }

        if ($needsQuoting) {
            // Escape any existing double quotes and wrap the value in double quotes
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

    public function npmInstall(array $options)
    {
        $appDir = $this->nodeJsPaths->getAppDir($this->domain);
        if (!is_dir($appDir)) {
            $this->appcontext->runUser("v-log-action", [
                "Error",
                "Web",
                "Application directory not found for {$this->domain} during npm install",
            ]);
            return;
        }

        if ($options["npm_install"] === "yes") {
            $packageJsonPath = $appDir . "/package.json";
            $packageLockJsonPath = $appDir . "/package-lock.json";

            if (
                file_exists($packageJsonPath) ||
                file_exists($packageLockJsonPath)
            ) {
                $result = $this->appcontext->runUser("v-add-npm-install", [
                    $this->domain,
                ]);

                if (
                    $result === false ||
                    (is_object($result) && $result->code !== 0)
                ) {
                    $this->appcontext->runUser("v-log-action", [
                        "Error",
                        "Web",
                        "Failed to run npm install for {$this->domain}",
                    ]);
                    throw new \Exception("Failed to run npm install");
                }
            } else {
                $this->appcontext->runUser("v-log-action", [
                    "Warning",
                    "Web",
                    "package.json or package-lock.json not found for {$this->domain}. Skipping npm install.",
                ]);
            }
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

        $excludeKeys = [
            "PORT",
            "start_script",
            "node_version",
            "npm_install",
            "modules_type",
        ];
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

    public function pm2StartApp(array $options = null)
    {
        return $this->appcontext->runUser("v-add-pm2-app", [
            $this->domain,
            $this->nodeJsPaths->getAppEntryPointFileName(
                $options["modules_type"] === "CommonJS"
            ),
        ]);
    }
}
