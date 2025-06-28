<?php

/**
 * Pget.php
 * 
 * A PHP implementation of Wget, object-oriented refactored version. Only surports HTTP,HTTPS,FTP
 * no cache except link table
 * Version: 2.0
 * Author: icos
 * 
 * Usage examples:
 * php pget.php --recursive --wait=0.5 --reject="woff,jpg,png,webp" --accept="html,js,css" --reject-regex="\?|(?:\/down\/)" --sub-string="<p id=\"b\">,<p class=\"a b\">|</p>[,</p>]" https://domain/
 * php pget.php https://domain/link
 * php pget.php --input-file="urls.txt"
 * 
 * Pget uses pdo_sqlite to store links for request records.
 * 
 * Parameters:
 *   url                  The request URL, or use --start-url="domain"
 *   --mirror             Copy the whole website. 0 means only download a single URL.
 *   --input-file         Path to a file containing URLs, one per line. All URLs will be downloaded.
 *   --no-clobber         Do not overwrite existing local files; otherwise, overwrite.
 *   --directory-prefix   Directory to store files. Default is a subdirectory named after the hostname.
 *   --reject             Comma-separated list of file suffixes to reject.
 *   --accept             Comma-separated list of file suffixes to accept.
 *   --accept-regex       Regular expression to accept URLs.
 *   --reject-regex       Regular expression to reject URLs.
 *   --sub-string         Cut content. Use "|" to split search and replace, "," to split cells. Use cmd escape for quotes or spaces.
 *   --wait               Seconds (or microseconds) between actions.
 *   --no-verbose         Suppress output messages.
 *   --utf-8              Convert content to UTF-8. Default is on.
 *   --recursive          Download all links in the page. Default is off.
 *   --page-requisites    Download images, CSS, JS for HTML display. Default is off.
 *   --directory-prefix   Save files to this directory. Default is current directory.
 *   --no-parent          Do not ascend to parent directory when recursively retrieving.
 *   --span-hosts         Enable spanning across hosts when recursively retrieving.
 *   --domains            Comma-separated list of domains to follow. Does not enable -H.
 *   --adjust-extension   For HTML files without .html extension, append .html.
 *   --restrict-file-names  Escape non-ASCII and special chars for cross-platform compatibility.
 *   --output-file
 *   --save-cookies，--load-cookies
 *   --force-directories    create a hierarchy of directories.
 *   --no-directories   Do not create a hierarchy of directories when retrieving recursively.
 *   --tries             Number of retries. Default is 20.
 *   --retry-connrefused    Force retry on connection refused. Default is on.
 *   --remote-encoding  Remote encoding. Default is UTF-8.
 *   --local-encoding   Local encoding. Default is UTF-8.
 * 
 * -----------------------------------------------------------------------------
 * 中文说明：
 * 
 * Pget.php
 * 
 * 一个 PHP 版的 Wget，面向对象重构版。仅下载 HTTP,HTTPS,FTP 文件
 * 除了必须的链接表，没有使用缓存
 * 版本：2.0
 * 作者：icos
 * 
 * 用法示例：
 * php pget.php --recursive --adjust-extension --restrict-file-names --no-check-certificate --tries=10 --wait=0.5 --save-cookies="cookie" --user-agent="Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)" --reject-regex="\?|#|&|(?:\.rar)|(?:\.zip)|(?:\.epub)|(?:\.txt)|(?:\.pdf)" --reject="woff,jpg,png,webp" --accept="html,js,css" --sub-string="<p id=\"b\">,<p class=\"a b\">|</p>,</p>" https://domain/
 * php pget.php https://domain/link
 * php pget.php --input-file="urls.txt"
 * 
 * Pget 使用 pdo_sqlite 记录请求过的链接。
 * 
 * 参数说明：
 *   url                  请求的URL，或用 --start-url="domain"
 *   --mirror             镜像整个网站。0表示只下载单个URL
 *   --input-file         包含URL的文件路径，每行一个URL，批量下载
 *   --no-clobber         不覆盖已存在的本地文件，否则覆盖
 *   --directory-prefix   文件保存目录，默认以主机名为子目录
 *   --reject             拒绝的文件后缀，逗号分隔
 *   --accept             接受的文件后缀，逗号分隔
 *   --accept-regex       接受URL的正则表达式
 *   --reject-regex       拒绝URL的正则表达式
 *   --sub-string         内容截取，"|"分隔查找和替换，","分隔多组。含空格或引号需转义。Pget独有的开关
 *   --wait               每次操作间隔秒数（或微秒）
 *   --no-verbose         不输出日志信息
 *   --utf-8              转为UTF-8编码，默认开启
 *   --recursive          递归下载页面内所有链接，默认关闭
 *   --page-requisites    下载页面依赖的图片、CSS、JS等资源，默认关闭
 *   --directory-prefix   文件保存目录，默认当前目录
 *   --no-parent          递归时不向上级目录爬取
 *   --span-hosts         递归时允许跨主机爬取
 *   --domains            允许爬取的域名，逗号分隔，不自动开启-H
 *   --adjust-extension   HTML文件无.html后缀时自动补全
 *   --restrict-file-names  转义非ASCII和特殊字符，兼容Win/Linux路径
 *   --output-file    输出日志到文件
 *   --save-cookies，--load-cookies       写入和载入cookies，配置一个即可，目前它们公用一个文件，因此不要配置成不同的文件名
 *   --force-directories    强制创建目录结构，默认开启
 *   --no-directories     递归检索时不要创建目录层次结构
 *   --tries             重试次数，默认20次 
 *   --retry-connrefused    强制重试连接被拒绝的请求，默认开启
 *   --remote-encoding  远程编码，默认UTF-8
 *   --local-encoding  本地编码，默认UTF-8
 *   --level             递归深度，默认5级
 * 
 */

// 检查是否在命令行模式下运行
if (php_sapi_name() !== 'cli') die("This script can only be run in CLI mode.\n");
// 检查操作系统类型
$isWindows = stripos(PHP_OS, 'WIN') === 0;
// 设置错误级别、超时、内存、时区等
// 只显示除了通知之外的所有错误
error_reporting(E_ALL & ~E_NOTICE);
// 设置脚本执行时间无限制
set_time_limit(0);
// 忽略用户断开连接，确保脚本继续执行
ignore_user_abort(true);
// 设置脚本可使用的最大内存为20480M
ini_set('memory_limit', '20480M');
// 设置时区为亚洲上海
date_default_timezone_set('Asia/Shanghai');
// PHP版本检查
if (version_compare(PHP_VERSION, '8.0.0', '<')) die("PHP 8.0+ required.\n");
// 致命错误兜底
function pget_shutdown_handler()
{
    global $pget;
    $error = error_get_last();
    $usage_mb = round(memory_get_usage() / 1024 / 1024, 2);
    $url = $pget->cfg['--start-url'] ?? '';
    $log_file = __DIR__ . '/pget_shutdown.log';
    $now = date('Y-m-d H:i:s');
    if ($error) {
        $type = $error['type'] ?? 0;
        $type_str = match ($type) {
            E_ERROR => 'E_ERROR',
            E_PARSE => 'E_PARSE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_USER_ERROR => 'E_USER_ERROR',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            default => 'OTHER',
        };
        $msg = "[SHUTDOWN] {$now} [致命错误] 类型: {$type_str}\n";
        $msg .= "  消息: {$error['message']}\n";
        $msg .= "  文件: {$error['file']} : {$error['line']}\n";
        $msg .= "  起始URL: {$url}\n";
        $msg .= "  日志文件: {$log_file}\n";
        $msg .= "  内存占用: {$usage_mb} MB\n";
        $msg .= "  说明: 脚本因致命错误终止，请检查上述错误信息。\n";
        echo "\n========== 脚本异常终止 ==========\n";
        echo $msg;
    } else {
        $msg = "[SHUTDOWN] {$now} [正常退出]\n";
        $msg .= "  起始URL: {$url}\n";
        $msg .= "  内存占用: {$usage_mb} MB\n";
        $msg .= "  说明: 脚本正常退出。\n";
        echo "\n========== 脚本正常退出 ==========\n";
        echo $msg;
    }
    file_put_contents($log_file, $msg, FILE_APPEND);
    if (isset($pget) && method_exists($pget, 'flush_log_buffer')) {
        $pget->flush_log_buffer();
    }
}
register_shutdown_function('pget_shutdown_handler');
// 信号处理公共函数
function pget_signal_handler($signal_type, $signal_name, $exit_code)
{
    global $pget;
    $usage_mb = round(memory_get_usage() / 1024 / 1024, 2);
    $now = date('Y-m-d H:i:s');
    $url = $pget->cfg['--start-url'] ?? '';
    $log_file = __DIR__ . '/pget_shutdown.log';
    $msg = "[SIGNAL] {$now} [{$signal_type}: {$signal_name}]\n";
    $msg .= "  起始URL: {$url}\n";
    $msg .= "  日志文件: {$log_file}\n";
    $msg .= "  内存占用: {$usage_mb} MB\n";
    $msg .= "  说明: 收到 {$signal_name} 信号，脚本被" . ($signal_name === 'SIGINT' ? '用户中断' : '外部终止') . "。\n";
    echo "\n========== 脚本被" . ($signal_name === 'SIGINT' ? '用户中断 (Ctrl+C)' : '外部终止 (SIGTERM)') . " ==========\n";
    echo $msg;
    file_put_contents($log_file, $msg, FILE_APPEND);
    if (isset($pget) && method_exists($pget, 'flush_log_buffer')) {
        $pget->flush_log_buffer();
    }
    if (isset($pget->log_file_handle) && is_resource($pget->log_file_handle)) {
        fclose($pget->log_file_handle);
    }
    exit($exit_code);
}
// 中止信号兜底
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, function () {
        pget_signal_handler('中断信号', 'SIGINT', 130);
    });
    pcntl_signal(SIGTERM, function () {
        pget_signal_handler('终止信号', 'SIGTERM', 143);
    });
}

// =================== 启动入口 ===================
// 解析命令行参数，初始化配置和主类，启动主流程

try {
    // 创建PgetConfig对象，传入命令行参数进行配置初始化
    $config = new PgetConfig($argv);
    // 创建Pget对象，传入配置对象
    $pget = new Pget($config);
    // 启动主流程
    $pget->run();
} catch (\Throwable $e) {
    file_put_contents(__DIR__ . '/pget_shutdown.log', "[EXCEPTION] " . date('Y-m-d H:i:s') . " " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
    throw $e;
}
// =================== 配置类 ===================
class PgetConfig
{
    // 配置选项
    public array $options = [
        '--mirror' => 0,
        '--recursive' => 0,
        '--input-file' => 0,
        '--no-clobber' => 0,
        '--start-url' => '',
        '--directory-prefix' => '',
        '--reject' => '',
        '--accept' => '',
        '--reject-regex' => '',
        '--accept-regex' => '',
        '--sub-string' => [],
        '--wait' => 0,
        '--no-verbose' => 0,
        '--page-requisites' => 0,
        '--utf-8' => 0,
        '--span-hosts' => '',
        '--domains' => '',
        '--adjust-extension' => 0,
        '--restrict-file-names' => 0,
        '--no-parent' => 0,
        '--no-check-certificate' => 0,
        '--user-agent' => 'Pget/2.0',
        '--header' => [],
        '--save-cookies' => '',
        '--load-cookies' => '',
        '--keep-session-cookies' => 0,
        '--output-file' => '',
        '--force-directories' => 0,
        '--tries' => 20,
        '--retry-connrefused' => 0,
        '--level' => 5,
    ];
    public array $substr_sign = [];
    public bool $isWindows = false;
    public bool $isChineseWindows = false;

    /**
     * 构造函数，解析命令行参数，初始化配置
     */
    public function __construct($argv)
    {
        // 判断当前操作系统是否为Windows
        $this->isWindows = stripos(PHP_OS, 'WIN') === 0;
        if ($this->isWindows) {
            $chcp = @shell_exec('chcp');
            if ($chcp && (strpos($chcp, '936') !== false || strpos($chcp, '65001') !== false)) {
                $this->isChineseWindows = true;
            }
        }
        // 遍历命令行参数，从第二个参数开始（第一个参数是脚本文件名）
        for ($i = 1; $i < count($argv); $i++) {
            // 检查参数是否包含等号，若包含则为键值对形式的参数
            if (str_starts_with($argv[$i], '--') && strpos($argv[$i], '=') !== false) {
                // 分割参数为键和值
                $p = explode('=', $argv[$i], 2);
                $config_name = $p[0];
                $config_value = $p[1];
                // 检查配置名是否存在于选项数组中
                if (array_key_exists($config_name, $this->options)) {
                    // 处理--sub-string参数，将其分割为查找和替换的数组
                    if ($config_name == '--sub-string') {
                        $amd = explode('|', $config_value, 2);
                        if ($amd[1]) {
                            $this->substr_sign = [
                                explode(',', $amd[0]),
                                explode(',', $amd[1])
                            ];
                        }
                    } else {
                        // 其他参数直接赋值
                        $this->options[$config_name] = $config_value;
                    }
                }
            } else {
                // 处理无等号的参数
                if (in_array($argv[$i], [
                    '--no-clobber',
                    '--no-verbose',
                    '--mirror',
                    '--recursive',
                    '--page-requisites',
                    '--utf-8',
                    '--span-hosts',
                    '--no-parent',
                    '--adjust-extension',
                    '--restrict-file-names',
                    '--no-check-certificate',
                    '--force-directories'
                ])) {
                    // 这些参数为开关型参数，设置为1表示启用
                    $this->options[$argv[$i]] = 1;
                } else {
                    // 若不是开关型参数，则作为起始URL
                    $this->options['--start-url'] = $argv[$i];
                }
            }
        }
        // 若未设置文件保存目录，则默认使用当前脚本所在目录
        if (empty($this->options['--directory-prefix'])) {
            $this->options['--directory-prefix'] = __DIR__;
        }
        // 检查 --load-cookies 和 '--save-cookies' 选项，确保它们指向同一个文件
        if (!empty($this->options['--load-cookies']) && !empty($this->options['--save-cookies'])) {
            if ($this->options['--load-cookies'] != $this->options['--save-cookies']) {
                throw new \InvalidArgumentException('--load-cookies and --save-cookies just specify one.' . PHP_EOL);
            }
        } elseif (empty($this->options['--save-cookies']) && !empty($this->options['--load-cookies'])) {
            $this->options['--save-cookies'] = $this->options['--load-cookies'];
        }

        // 参数严格校验
        if (!is_numeric($this->options['--wait']) || $this->options['--wait'] < 0) {
            throw new \InvalidArgumentException('--wait must be a non-negative number.' . PHP_EOL);
        }
        if (!is_numeric($this->options['--tries']) || $this->options['--tries'] < 1) {
            throw new \InvalidArgumentException('--tries must be a positive integer.' . PHP_EOL);
        }
        if (!is_dir($this->options['--directory-prefix']) && !mkdir($this->options['--directory-prefix'], 0777, true)) {
            throw new \InvalidArgumentException('--directory-prefix is not a valid directory and cannot be created.' . PHP_EOL);
        }

        // 正则表达式参数校验
        $safe_regex = function ($pattern) {
            // 禁止过长、嵌套过深
            if (strlen($pattern) > 256) return false;
            if (substr_count($pattern, '(') > 10) return false;
            // 检查危险修饰符，仅匹配正则结尾的修饰符部分
            if (preg_match('#/(.*?)/([imsuxADSUXJ]*)$#', $pattern, $m)) {
                $modifiers = $m[2] ?? '';
                if (preg_match('/[eSxXAE]/', $modifiers)) return false;
            }
            // 检查正则语法合法性
            return @preg_match('/' . $pattern . '/', '') !== false;
        };
        if (!empty($this->options['--reject-regex']) && !$safe_regex($this->options['--reject-regex'])) {
            throw new \InvalidArgumentException('Unsafe --reject-regex: ' . $this->options['--reject-regex'] . PHP_EOL);
        }
        if (!empty($this->options['--accept-regex']) && !$safe_regex($this->options['--accept-regex'])) {
            throw new \InvalidArgumentException('Unsafe --accept-regex: ' . $this->options['--accept-regex'] . PHP_EOL);
        }
    }
}

// =================== 主爬虫类 ===================
class Pget
{
    // 爬虫配置类
    public PgetConfig $config;
    // 配置选项
    public array $cfg = [];
    // 循环计数
    private int $loop_count = 1;
    // 已处理链接表：键为链接，值为布尔值（true=本地文件存在，false=不存在）
    public array $link_table = [];
    // 待处理链接队列
    public SplQueue $pending_queue;
    // 日志文件句柄
    private $log_file_handle = null;
    // 过滤规则
    private array $filter = [];
    // 网络错误次数
    private int $error_count = 1;
    // 上次请求的时间
    private float $last_request_time = 0;
    // 日志缓存
    private array $log_buffer = [];
    // 扩展名
    private array $extension_array = [];
    // 响应头内容类型
    private array $content_type = [];
    // 起始链接的主机名
    private string $start_url_host = '';
    // 主机列表
    private array $domains = [];
    /**
     * 构造函数，初始化配置和队列
     */
    public function __construct(PgetConfig $config)
    {
        // 保存配置对象
        $this->config = $config;
        // 初始化待处理链接队列
        $this->pending_queue = new SplQueue();
        // 保存配置选项
        $this->cfg = $this->config->options;
        // 若目录不存在，则创建目录
        if (!is_dir($this->cfg['--directory-prefix'])) {
            mkdir($this->cfg['--directory-prefix'], 0777, true);
        }
        // 如果配置了输出日志文件，则打开文件句柄并清空旧内容
        if ($this->cfg['--output-file']) {
            $log_file = $this->cfg['--directory-prefix'] . DIRECTORY_SEPARATOR . $this->cfg['--output-file'];
            $this->log_file_handle = fopen($log_file, 'w');
            if (!$this->log_file_handle) {
                throw new \InvalidArgumentException("Failed to open log file: {$log_file}\n");
            }
        }
        // 可接受的内容类型和对应的扩展名
        $this->content_type = [
            'text/html' => 'html',
            'text/plain' => 'txt',
            'text/css' => 'css',
            'application/javascript' => 'js',
            'application/x-javascript' => 'js',
            'application/json' => 'json',
            'application/xml' => 'xml',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/x-icon' => 'ico',
            'application/pdf' => 'pdf',
            'application/zip' => 'zip',
            'application/x-rar-compressed' => 'rar',
            'audio/mpeg' => 'mp3',
            'video/mp4' => 'mp4',
            // ...可扩展
        ];
        $this->extension_array = array_values($this->content_type);
        $this->start_url_host = strtolower(parse_url($this->cfg['--start-url'], PHP_URL_HOST));
        // 若--domains有值，格式化为数组
        if (!empty($this->cfg['--domains'])) {
            $this->domains = array_filter(array_map('strtolower', array_map('trim', explode(',', $this->cfg['--domains']))));
        }
    }

    /**
     * 启动主流程
     * 判断参数，选择单URL、批量、递归三种模式
     */
    public function run()
    {

        // 检查是否提供了起始URL或输入文件，若都未提供则终止脚本
        if (!$this->cfg['--start-url'] && !$this->cfg['--input-file']) {
            $this->echo_logs1('Error! No URL');
            return false;
        }

        $url_parsed = parse_url($this->cfg['--start-url']);
        if (!isset($url_parsed['scheme']) || !isset($url_parsed['host'])) {
            $this->echo_logs1('Error! Invalid URL');
            return false;
        }

        // 根据配置选项选择不同的下载模式
        if (!$this->cfg['--mirror'] && !$this->cfg['--recursive'] && !$this->cfg['--input-file']) {
            // 单URL下载模式
            $this->singleRequest($this->cfg['--start-url']);
        } elseif ($this->cfg['--input-file']) {
            // 批量下载模式
            $this->batchRequest($this->cfg['--input-file']);
        } else {
            // 递归下载模式
            $this->recursiveRequest($this->cfg['--start-url']);
        }
        // 输出请求完成信息
        $this->echo_logs($this->cfg['--start-url'], count($this->link_table) . 'links finished at', date('Y-m-d H:i:s'));
    }

    /**
     * 单个URL请求
     * 只下载一个URL
     */
    private function singleRequest($url)
    {
        try {
            // 调用下载并保存内容的方法
            $this->catcher_reqest_to_local($url);
        } catch (\Throwable $e) {
            $this->echo_logs($this->loop_count, $url, 'Exception: ' . $e->getMessage());
        }
        $this->flush_log_buffer();
    }

    /**
     * 批量请求
     * 从文件读取URL列表，逐个下载
     */
    private function batchRequest($filename)
    {
        $this->echo_logs('Request URLs from file: ', $filename);
        try {
            $handle = fopen($filename, 'r');
            if ($handle) {
                while (($url = fgets($handle)) !== false) {
                    $url = trim($url);
                    if ($url === '') continue;
                    try {
                        $this->catcher_reqest_to_local($url);
                    } catch (\Throwable $e) {
                        $this->echo_logs($this->loop_count, $url, 'Exception: ' . $e->getMessage());
                    }
                    $this->loop_count++;
                    $this->flush_log_buffer();
                    if (function_exists('pcntl_signal_dispatch')) pcntl_signal_dispatch();
                }
                fclose($handle);
            } else {
                $this->echo_logs($this->loop_count, 'Failed to open input file: ' . $filename);
            }
        } catch (\Throwable $e) {
            $this->echo_logs($this->loop_count, 'File Error: ' . $e->getMessage());
        }
        $this->flush_log_buffer();
    }

    /**
     * 递归爬取
     * 支持断点续传，自动读取数据库和本地目录下已存在的文件，避免重复下载
     * 1. 读取数据库已爬取URL
     * 2. 遍历本地目录下所有文件，将其转换为URL，加入已爬取表
     * 3. 主循环：从队列取出URL，下载并处理，已处理的URL写入数据库
     */
    private function recursiveRequest($url)
    {
        // 过滤配置
        $this->filter['--reject'] = !empty($this->cfg['--reject']) ? explode(',', $this->cfg['--reject']) : null;
        $this->filter['--accept'] = !empty($this->cfg['--accept']) ? explode(',', $this->cfg['--accept']) : null;
        $this->filter['--reject-regex'] = $this->cfg['--reject-regex'];
        $this->filter['--accept-regex'] = $this->cfg['--accept-regex'];

        // 入队起始链接
        if ($this->path_filter_all($url)) {
            $this->enqueue_url_if_new($url);
        } else {
            return false;
        }
        // 若配置了起始URL，则进行冷启动检查
        $this->start_once($url);

        // 主循环
        while (!$this->pending_queue->isEmpty()) {
            // 从队列中取出一个URL
            $url = $this->pending_queue->dequeue();
            try {
                // 调用下载并保存内容的方法
                $this->catcher_reqest_to_local($url);
            } catch (\Throwable $e) {
                $this->echo_logs($this->loop_count, $url, 'Exception: ' . $e->getMessage());
            }
            $this->loop_count++;
            // 每一轮操作后写入日志
            $this->flush_log_buffer();
        }
        // 最后一次日志缓存刷新
        $this->flush_log_buffer();
    }
    /* 
     * 首次启动检查数据库文件和本地文件
    */
    public function start_once($url)
    {
        // 1.遍历存储目录下所有文件，将文件名转换为URL并加入链接表和队列

        $url_parsed = parse_url($url);
        // 起始网址根目录
        $start_url_root = $url_parsed['scheme'] . '://' . $url_parsed['host'] . (empty($url_parsed['port']) ? '' : ':' . $url_parsed['port']) . '/';
        // 生成主机目录名
        $host_dir = $url_parsed['host'] . (empty($url_parsed['port']) ? '' : '_' . $url_parsed['port']);
        // 生成存储目录路径
        $storage_dir = rtrim($this->cfg['--directory-prefix'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $host_dir;

        // 检查存储目录是否存在
        if (is_dir($storage_dir)) {
            // 创建递归迭代器，遍历目录下的所有文件
            $rii = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($storage_dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($rii as $file) {
                if ($file->isFile()) {
                    // 获取文件的完整路径
                    $file_path = $file->getPathname();
                    // 转换本地文件路径为相对路径
                    $relative_path = substr($file_path, strlen($storage_dir));

                    // 将目录分隔符替换为斜杠
                    $relative_path = str_replace(DIRECTORY_SEPARATOR, '/', $relative_path);
                    // 去除相对路径开头的斜杠
                    if (str_starts_with($relative_path, '/')) {
                        $relative_path = substr($relative_path, 1);
                    }
                    // 去除path中的多余的'//'（如有）
                    $relative_path = preg_replace('#(?<!:)//+#', '/', $relative_path);
                    // 生成对应的URL
                    $url = $start_url_root . ltrim($relative_path, '/');
                    // 去除常见的文件夹索引文件名index,default等
                    $url = preg_replace('/(?:index|default)(?:\.[a-zA-Z0-9]+)?$/', '', $url);
                    // 链接入队
                    $this->enqueue_url_if_new($url);
                    // 标记本地文件存在
                    $this->link_table[$url] = true;

                    // 输出请求日志信息
                    $this->echo_logs1($file_path, 'File Found');

                    // 读取内容，处理链接和资源
                    $result = file_get_contents($file_path);
                    // 若设置了镜像或递归下载，则提取并处理页面链接
                    if ($this->cfg['--mirror'] || $this->cfg['--recursive']) {
                        $links = $this->get_page_links($result, $url);
                        $this->catcher_store_links($links);
                        $this->echo_logs($file_path,  'Links Saved');
                    }
                }
            }
        }

        $this->flush_log_buffer();

        $this->echo_logs(count($this->link_table) . ' links found in local storage.' . PHP_EOL);
    }
    /**
     * 下载并保存内容，处理页面资源和链接
     * 1. 判断是否需要下载
     * 2. 下载内容，自动补全扩展名
     * 3. 处理依赖资源和递归链接
     * 4. 保存到本地
     * 5. 记录到临时表
     */
    public function catcher_reqest_to_local($url)
    {
        // 链接入队
        $this->enqueue_url_if_new($url);

        // 获取内容截取的起止标记
        $substr_sign = $this->config->substr_sign;

        if ($this->error_count > $this->cfg['--tries']) {
            // 如果连续错误超过--tries次，则退出循环
            $this->echo_logs("{$this->error_count} cURL errors, exiting...");
            throw new \RuntimeException("{$this->error_count} cURL errors, exiting...");
        }

        // 输出请求日志信息
        $this->echo_logs($this->loop_count, $url, date('Y-m-d H:i:s'), 'Getting');

        // 检查URL是否需要过滤
        if (!$this->path_filter_all($url)) {
            // 若URL被过滤，则输出过滤日志信息并返回
            $this->echo_logs($this->loop_count, $url, 'Rejected, Ignore Request');
            return false;
        }

        // 初始化结果和本地文件路径
        $result = null;
        $local_file = null;

        // 生成本地保存路径
        $local_file = $this->url_local_path($url, $this->cfg['--directory-prefix']);
        // 本地文件名不存在时跳过
        if (empty($local_file)) return false;
        // 检查本地文件是否存在，使用链接表代替实时 file_exists 大批量时影响性能
        $is_file_exist = empty($this->link_table[$url]) ? false : true;

        // 判断是否需要重新下载
        if ($is_file_exist && $this->cfg['--no-clobber']) {
            // 若文件已存在且设置了不覆盖，则读取本地文件内容
            $this->echo_logs($this->loop_count, $url, $local_file, 'File Exists, Not Request');
            // 首次启动时已读取过本地文件内容的链接和资源，此处不需要重复读取了
            return false; // $result = file_get_contents($local_file);
        } else {
            // 若需要下载，则输出主机IP信息
            if (!$this->cfg['--no-verbose']) {
                $url_host = parse_url($url, PHP_URL_HOST) ?? '';
                $ip = $url_host ? gethostbyname($url_host) : '';
                $this->echo_logs($this->loop_count, "{$url_host} -> {$ip}");
            }

            // 发起网络请求
            list($result, $http_info) = $this->browser($url);

            // --adjust-extension: 仅对既不是目录也没有扩展名的URL，根据content-type补全扩展名，修正本地文件名
            if (!empty($this->cfg['--adjust-extension'])) {
                if (empty($http_info['content_type'])) {
                    $this->echo_logs($this->loop_count, $url, 'content_type Null');
                    $http_info['content_type'] = 'text/html';
                }
                // 判断本地文件是否为目录
                $is_dir = str_ends_with($local_file, DIRECTORY_SEPARATOR);
                // 判断本地文件名是否有扩展名
                $has_ext = false;
                foreach ($this->extension_array as $i) {
                    if (str_ends_with($local_file, $i)) {
                        $has_ext = true;
                        break;
                    }
                }
                // 根据响应的content-type获取扩展名
                $content_type_ext = $this->get_ext_by_content_type($http_info['content_type']);
                // 若不是目录且没有扩展名，且能获取到扩展名，则补全扩展名
                if (!$is_dir && !$has_ext && $content_type_ext) {
                    $local_file .= '.' . $content_type_ext;
                    // 检查补全扩展名后的文件是否存在
                    $is_file_exist = empty($this->link_table[$url]) ? false : true;
                }
            }
        }

        // 若响应内容为空，则输出日志信息并返回
        if (empty($result)) {
            $this->echo_logs($this->loop_count, $url, 'Response Null');
            return false;
        }
        // 若设置了转换为UTF-8编码，则进行编码转换
        if ($this->cfg['--utf-8']) {
            $result = $this->mb_encode($result);
        }
        // 若设置了镜像或递归下载，则提取并处理页面链接
        if ($this->cfg['--mirror'] || $this->cfg['--recursive']) {
            $links = $this->get_page_links($result, $url);
            $this->catcher_store_links($links);
            $this->echo_logs($this->loop_count, $url,  'Links Saved');
        }
        // 若设置了内容截取，则进行内容截取
        if (!empty($this->cfg['--sub-string'])) {
            $result = $this->sub_content_all($result, $substr_sign);
            $this->echo_logs($this->loop_count, $url,  'Response Cut');
        }
        // 若截取后的内容为空，则返回
        if (empty($result)) {
            return false;
        }

        // 判断是否需要保存文件。上方有 --adjust-extension 添加扩展名导致 is_file_exist 被修正的情况，故需要再次判断
        if ($is_file_exist && $this->cfg['--no-clobber']) {
            // 若文件存在且设置不覆盖，提示文件已存在，不进行覆盖
            $this->echo_logs1($this->loop_count, date('Y-m-d H:i:s'), "{$url} -> {$local_file}", 'File Existed, Not Overwrite');
        } else {
            // 否则，保存文件
            try {
                // 获取本地文件所在目录
                $local_dir = dirname($local_file);
                // 若目录不存在，则创建目录
                if (!is_dir($local_dir) && !mkdir($local_dir, 0777, true)) {
                    $this->echo_logs($this->loop_count, 'Failed to create directory: ' . $local_dir);
                    return false;
                }
                if (@file_put_contents($local_file, $result) === false) {
                    $this->echo_logs($this->loop_count, 'Failed to write file: ' . $local_file);
                    // 写入失败则返回false
                    return false;
                }
                // 到这步表明文件已存在，则将链接表中记录值改为 true 
                $this->link_table[$url] = true;
                $this->echo_logs1($this->loop_count, date('Y-m-d H:i:s'), "{$url} -> {$local_file}", 'File Saved');
            } catch (\Throwable $e) {
                $this->echo_logs($this->loop_count, $url, 'File Exception: ' . $e->getMessage());
                return false;
            }
        }

        // 若设置了操作间隔时间，则进行等待
        if ($this->cfg['--wait']) {
            $this->wait($this->cfg['--wait']);
        }

        return true;
    }

    /**
     * 链接入队（去重）
     * 将新发现的链接加入待处理队列
     */
    public function catcher_store_links($links)
    {
        // 若链接数组为空，则返回
        if (empty($links)) {
            return false;
        }
        // 遍历链接数组
        foreach ($links as $url) {
            // 跳过不允许的链接
            if (!$this->path_filter_all($url)) {
                continue;
            }
            // 若链接未处理过，且是允许的链接，则加入链接表和队列
            $this->enqueue_url_if_new($url);
        }
    }

    // 移除 link_table_add_urlparsed 和 host_info_pool 相关内容

    // 统一处理初始化和入队
    private function enqueue_url_if_new($url)
    {
        if (!isset($this->link_table[$url])) {
            $this->link_table[$url] = false; // 链接对应的本地文件不存在
            $this->pending_queue->enqueue($url);
        }
    }
    /**
     * 日志输出
     * 根据--no-verbose参数控制是否输出
     * 输出日志信息到控制台或文件
     */
    public function echo_logs(...$args)
    {

        // 若配置了--no-verbose，则不输出日志信息
        if ($this->cfg['--no-verbose']) return false;

        // 构建日志信息
        $log_message = implode("\t", $args) . PHP_EOL;
        // 若配置了输出到文件，则写入缓存
        if ($this->cfg['--output-file']) {
            $this->log_buffer[] = $log_message;
        } else {
            // 否则输出到控制台
            echo $log_message;
        }
    }
    /**
     * 日志输出
     * 忽略--no-verbose
     * 输出日志信息到控制台或文件
     */
    public function echo_logs1(...$args)
    {


        // 构建日志信息
        $log_message = implode("\t", $args) . PHP_EOL;
        // 若配置了输出到文件，则写入缓存
        if ($this->cfg['--output-file']) {
            $this->log_buffer[] = $log_message;
        } else {
            // 否则输出到控制台
            echo $log_message;
        }
    }
    /**
     * 日志缓存写入文件
     */
    public function flush_log_buffer()
    {
        if (!empty($this->log_buffer) && $this->log_file_handle && is_resource($this->log_file_handle)) {
            fwrite($this->log_file_handle, implode('', $this->log_buffer));
            $this->log_buffer = [];
        }
    }
    /**
     * 检查主机是否允许访问（--span-hosts + --domains 联合作用）
     * @param string $url
     * @return bool
     */
    private function is_host_allowed($url)
    {
        // 默认只允许当前主域名下的链接，除非--span-hosts为真
        if (empty($this->cfg['--span-hosts'])) {
            $url_host = parse_url($url, PHP_URL_HOST) ?? '';
            if (strtolower($url_host) !== $this->start_url_host) {
                return false;
            }
        } else {
            // --span-hosts为真时，才启用--domains白名单过滤。只要 domains 数组中任意一个元素被包含在当前 URL 的主机名中（字符串包含关系），就允许访问
            if (!empty($this->cfg['--domains'])) {
                $url_host = parse_url($url, PHP_URL_HOST) ?? '';
                $allowed = false;
                foreach ($this->domains as $domain) {
                    if (strpos($url_host, $domain) !== false) {
                        $allowed = true;
                        break;
                    }
                }
                if (!$allowed) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * 过滤：总入口
     * 1. 默认只允许当前主域名下链接（除非--span-hosts）
     * 2. 后缀过滤
     * 3. 正则过滤
     */
    public function path_filter_all($url)
    {
        if (empty($url)) {
            return false;
        }
        if (str_starts_with($url, 'javascript:') || str_starts_with($url, '#') || str_starts_with($url, 'data:')) {
            return false;
        }
        if (!$this->is_host_allowed($url)) {
            return false;
        }
        if (!$this->path_filter_suffix($url)) {
            return false;
        }
        if (!$this->path_filter_preg($url)) {
            return false;
        }
        return true;
    }

    /**
     * 过滤：后缀名
     */
    public function path_filter_suffix($url)
    {
        // 获取过滤规则
        $filter = $this->filter;
        // 若设置了拒绝的文件后缀，则进行后缀过滤
        if (!empty($filter['--reject'])) {
            foreach ($filter['--reject'] as $i) {
                $i = trim($i);
                // 若URL以拒绝的后缀结尾，则过滤掉该链接
                if ($i !== '' && str_ends_with($url, $i)) {
                    return false;
                }
            }
        }
        // 若设置了接受的文件后缀，则进行后缀过滤
        if (!empty($filter['--accept'])) {
            foreach ($filter['--accept'] as $i) {
                $i = trim($i);
                // 若URL以接受的后缀结尾，则允许该链接
                if ($i !== '' && str_ends_with($url, $i)) {
                    return true;
                }
            }
            return false;
        }
        return true;
    }

    /**
     * 过滤：正则
     */
    public function path_filter_preg($url)
    {
        // 获取过滤规则
        $filter = $this->filter;
        // 参数校验已在配置阶段完成，无需再次校验
        if (!empty($filter["--reject-regex"])) {
            if (preg_match('/' . $filter["--reject-regex"] . '/', $url)) {
                return false;
            }
        }
        if (!empty($filter["--accept-regex"])) {
            if (preg_match('/' . $filter["--accept-regex"] . '/', $url)) {
                return true;
            }
            return false;
        }
        return true;
    }

    /**
     * 提取页面链接（a、img、js、css等）
     * 支持正则批量提取
     * @param url 当前正在访问的URL
     */
    public function get_page_links($html_body, $url, $from_array = false)
    {
        // 若HTML内容为空或起始URI和当前URL都为空，则返回空数组
        if (empty($html_body) || empty($url)) {
            return [];
        }
        $urls = [];
        if (!$from_array) {
            $unique_links = [];
            // 使用正则表达式提取页面资源链接
            if (!empty($this->cfg['--page-requisites'])) {
                preg_match_all('/<(?:a|script|link|img)[^>]+(?:src|href|data-original)=[\'"]([^\'"#]+)(?:#[^\'"\/]*)?[\'"][^>]*>/i', $html_body, $matches);
            } else {
                // 只提取a标签，暂无不带引号规则 '/<a[^>]+href=([^>\"\'\s]+?)[> ]/i'
                preg_match_all('/<a[^>]+href=[\'"]([^\'"#]+)(?:#[^\'"\/]*)?[\'"][^>]*>/i', $html_body, $matches);
            }
            // 去除重复的链接
            $unique_links = array_unique($matches[1] ?? []);
        } else {
            // 若传入的是链接数组，则直接去除重复的链接
            $unique_links = array_unique($html_body);
        }
        // 遍历链接数组
        foreach ($unique_links as $path) {
            // 过滤掉以"data:"开头的非正常链接
            if (str_starts_with($path, 'data:')) {
                continue;
            }
            // 格式化URL为绝对地址
            $absolute_url = $this->get_absolute_url($path, $url);
            // 去除URL中的锚点部分
            if (strpos($absolute_url, '#') !== false) {
                $absolute_url = substr($absolute_url, 0, strpos($absolute_url, '#'));
            }
            // 将格式化后的URL加入结果数组
            $urls[] = $absolute_url;
        }
        return $urls;
    }

    /**
     * 生成本地保存路径
     * 目录名包含端口号，兼容Win/Linux，支持特殊字符转义
     */
    public function url_local_path($url, $base_dir = '')
    {
        if (empty($url)) {
            return '';
        }
        $url_parsed = parse_url($url);
        $path = $url_parsed['path'] ?? '/';

        // 递归深度判断（基于目录层级）
        if (!empty($this->cfg['--level'])) {
            $depth = 0;
            if ($path !== '') {
                $trimmed = trim($path, '/');
                if ($trimmed !== '') {
                    $depth = substr_count($trimmed, '/');
                }
            }
            if ($depth > $this->cfg['--level']) {
                $this->echo_logs($this->loop_count, $url, 'Max depth exceeded, skip.');
                return '';
            }
        }
        if (str_ends_with($path, '/')) {
            $path = $path . 'index.html';
        }
        $query = empty($url_parsed['query']) ? '' : '?' . str_replace('/', '_', $url_parsed['query']);
        $file_path = $path . $query;
        $file_path = str_replace(['?', '*', ':', '"', '<', '>', '|', '\\'], '_', $file_path);

        if (!empty($this->cfg['--restrict-file-names'])) {
            $file_path = rawurldecode($file_path);
            $file_path = encode_path($file_path);
        }
        if ($this->config->isWindows) {
            $file_path = str_replace('/', DIRECTORY_SEPARATOR, $file_path);
            if ($base_dir) {
                $base_dir = str_replace('/', DIRECTORY_SEPARATOR, $base_dir);
                $base_dir = rtrim($base_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            }
        }
        $host_dir = $url_parsed['host'] . (empty($url_parsed['port']) ? '' : '_' . $url_parsed['port']);
        $local_file = $base_dir . $host_dir . $file_path;
        if ($this->config->isChineseWindows) {
            $local_file = mb_convert_encoding($local_file, 'GB2312', 'UTF-8');
        }
        return $local_file;
    }

    /**
     * 多段内容截取
     * 支持多组起止标记批量截取
     */
    public function sub_content_all($html, $substr_sign = [])
    {
        // 若起止标记数组为空，则返回原始HTML内容
        if (is_multi_array_empty($substr_sign)) {
            return $html;
        }
        // 解包起止标记数组
        list($start, $end) = $substr_sign;
        $html_tmp = '';
        // 遍历起止标记数组
        for ($i = 0; $i < count($start); $i++) {
            // 若结束标记为空，则默认为</html>
            if (!$end[$i]) {
                $end[$i] = '</html>';
            }
            // 调用单段内容截取方法，拼接截取结果
            $html_tmp .= $this->sub_content($html, $start[$i], $end[$i], 1);
        }
        return $html_tmp;
    }

    /**
     * 单段内容截取
     */
    public function sub_content($str, $before, $after, $mode = 1)
    {
        // 查找起始标记的位置
        $start = stripos($str, $before);
        if ($start === false) {
            return '';
        }
        // 从起始标记位置开始截取字符串
        $str = substr($str, $start);
        if (!$after) {
            return $str;
        }
        // 查找结束标记的位置
        $length = stripos($str, $after);
        if ($length <= 0) {
            return '';
        }
        $start = 0;
        // 根据截取模式调整起始位置和截取长度
        switch ($mode) {
            case -1:
                $start = strlen($before);
                $length = $length - $start;
                break;
            case 0:
                $length = $length - $start;
                break;
            case 1:
                $length = $length + strlen($after);
                break;
        }
        // 截取内容
        $content = substr($str, $start, $length);
        return $content;
    }

    /**
     * 网络请求，支持GET/POST/HEAD
     * 支持自定义User-Agent、Header、Cookie、SSL等
     */
    private $curl_handle = null;
    public function browser($url, $method = 'GET', $postfields = [])
    {
        // 若URL为空，则返回false和空数组
        if (empty($url)) {
            return [false, []];
        }
        // 检查cURL扩展是否启用
        if (!function_exists('curl_init')) {
            throw new \InvalidArgumentException('cURL extension is not enabled. Please enable cURL extension in your PHP configuration.');
        }
        $response = '';
        $http_info = [];
        // 只初始化一次
        if ($this->curl_handle === null) {
            $this->curl_handle = curl_init();
        }
        $ch = $this->curl_handle;
        // 设置URL
        curl_setopt($ch, CURLOPT_URL, $url);
        // 设置HTTP版本为1.1
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_NONE);
        // 设置返回响应内容
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // 设置跟随重定向
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        // 设置超时时间为10秒
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        // 设置连接超时时间为10秒
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        // 设置支持gzip和deflate压缩
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
        // user-agent处理
        $ua = $this->cfg['--user-agent'];
        if (empty($ua)) {
            $ua = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36";
        }
        // 设置User-Agent
        curl_setopt($ch, CURLOPT_USERAGENT, $ua);

        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');
        if ($scheme === 'https') {
            // 根据配置选项决定是否验证SSL证书
            if ($this->cfg['--no-check-certificate']) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            } else {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            }
        }
        if ($method === 'HEAD') {
            // 若为HEAD请求，只获取响应头
            curl_setopt($ch, CURLOPT_NOBODY, 1);
            curl_setopt($ch, CURLOPT_HEADER, 1);
        } else {
            // 其他请求获取响应内容
            curl_setopt($ch, CURLOPT_NOBODY, 0);
            curl_setopt($ch, CURLOPT_HEADER, 0);
        }
        // 判断是否POST
        if (!empty($postfields)) {
            // 若有POST数据，则设置为POST请求
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        } else {
            // 否则为GET请求
            curl_setopt($ch, CURLOPT_POST, false);
        }
        // 设置Referer为当前URL
        curl_setopt($ch, CURLOPT_REFERER, $url);
        // cookie文件处理，仅当--save-cookies或者--load-cookies有值时启用cookie，因为程序把读取和写入cookie放在同一个文件，不要配置成不同的文件名
        if (!empty($this->cfg['--save-cookies'])) {
            // 生成cookie文件路径
            $cookiejar = $this->cfg['--directory-prefix'] . DIRECTORY_SEPARATOR . $this->cfg['--save-cookies'];
            // 设置cookie文件
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiejar);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiejar);
        }
        // 处理--header参数
        $headerArr = [];
        if (!empty($this->cfg['--header'])) {
            // 支持多行header，按换行或逗号分割
            $raw = $this->cfg['--header'];
            if (is_array($raw)) {
                foreach ($raw as $h) {
                    foreach (preg_split('/[\r\n,]+/', $h) as $line) {
                        $line = trim($line);
                        if ($line !== '') {
                            $headerArr[] = $line;
                        }
                    }
                }
            } else {
                foreach (preg_split('/[\r\n,]+/', $raw) as $line) {
                    $line = trim($line);
                    if ($line !== '') {
                        $headerArr[] = $line;
                    }
                }
            }
        }
        if ($headerArr) {
            // 设置HTTP请求头
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArr);
        }
        // 执行请求
        $response = curl_exec($ch);
        // 记录请求时间
        $this->last_request_time = microtime(true);
        // 错误代码
        $error_code = curl_errno($ch);
        // 若发生错误，则输出错误日志信息并返回
        if ($error_code) {
            $this->error_count++;
            // 错误信息
            $error_msg = curl_error($ch);
            // 输出错误日志信息
            $this->echo_logs($this->loop_count, $url, "Errno: {$error_code} - {$error_msg}");
            return [false, []];
        }
        // 获取响应信息
        $http_info = curl_getinfo($ch);
        // 若响应状态码不是200，则输出日志信息并返回
        if ($http_info['http_code'] != 200) {
            $this->echo_logs($this->loop_count, $url, 'No Content');
            return [false, []];
        }

        // 输出HTTP请求发送日志信息
        $this->echo_logs($this->loop_count, $url, 'HTTP Requested');
        return [$response, $http_info];
    }

    /**
     * 析构函数，关闭curl句柄和数据库连接
     */
    public function __destruct()
    {
        // 若curl句柄存在，则关闭curl句柄
        if ($this->curl_handle) {
            curl_close($this->curl_handle);
            $this->curl_handle = null;
        }
        // 若日志文件句柄存在，则关闭并释放
        if ($this->log_file_handle && is_resource($this->log_file_handle)) {
            // 最后写入剩余日志缓存，万一有错误也忽略
            @fwrite($this->log_file_handle, implode('', $this->log_buffer));
            fclose($this->log_file_handle);
        }
    }

    /**
     * 字符编码转换
     */
    public function mb_encode($str, $to_encoding = "UTF-8")
    {
        // 优先用header、meta、mb_detect_encoding自动识别
        $encode = null;
        if (preg_match('/<meta.*?charset=["\']?([a-zA-Z0-9\-]+)["\']?/i', $str, $m)) {
            $encode = strtoupper($m[1]);
        }
        if (!$encode && preg_match('/Content-Type:.*?charset=([a-zA-Z0-9\-]+)/i', $str, $m)) {
            $encode = strtoupper($m[1]);
        }
        if (!$encode) {
            $encode = mb_detect_encoding($str, ['UTF-8', 'GB2312', 'GBK', 'BIG5', 'ISO-8859-1', 'ASCII'], true);
        }
        if (!$encode) $encode = 'UTF-8';
        if ($encode != $to_encoding) {
            $str = @mb_convert_encoding($str, $to_encoding, $encode);
        }
        return $str;
    }

    /**
     * 等待（秒/微秒）
     */
    public function wait($seconds)
    {
        // 若等待时间小于等于0，则返回
        if ($seconds <= 0) {
            return false;
        }
        // 将秒转换为微秒
        $microseconds = (int)($seconds * 1000000);

        // 如果上次请求的时间距离当前时间的差值已经大于--wait的值，那就不要休眠了
        if ($this->last_request_time && (microtime(true) - $this->last_request_time) > $microseconds) {
            return false;
        }

        // 根据等待时间的类型进行处理
        if (is_int($seconds) || $seconds == (int)$seconds) {
            // 若等待时间为整数，则使用sleep函数等待
            sleep($seconds);
        } else {
            // 否则，使用usleep函数等待
            usleep($microseconds);
        }
    }

    /**
     * 相对地址和当前URL，转换为绝对地址
     * @param path 页面链接
     * @param url 当前URL
     */
    public function get_absolute_url($path, $url)
    {
        if (empty($url)) return '';
        if (empty($path)) return $url;

        $url_parsed = parse_url($url);
        $scheme = $url_parsed['scheme'] ?? '';
        $host = $url_parsed['host'] ?? '';
        $port = isset($url_parsed['port']) ? ':' . $url_parsed['port'] : '';
        if (empty($scheme) || empty($host)) {
            return '';
        }

        if (str_starts_with($path, '//')) {
            return "{$scheme}:{$path}";
        }
        if (str_starts_with($path, '/')) {
            return "{$scheme}://{$host}{$port}{$path}";
        }
        if (str_starts_with($path, 'http') || str_starts_with($path, 'ftp')) {
            return $path;
        }
        // 到这步若path包含协议头，说明不是支持的协议，则直接返回空
        if (stripos(substr($path, 0, 20), '://') !== false) {
            return '';
        }
        $dirname = str_ends_with($url, '/') ? $url : dirname($url) . '/';
        /* static $get_absolute_url_cache = [];
        $key = $dirname . $path;
        if (isset($get_absolute_url_cache[$key])) {
            return $get_absolute_url_cache[$key];
        }
        return $get_absolute_url_cache[$key] = $this->get_standard_url($dirname . $path); */
        return $this->get_standard_url($dirname . $path);
    }

    /**
     * 绝对路径规范化
     * 将路径中的./、../等部分规范化为标准路径
     * @param string $url 绝对路径
     * @return string 规范化后的绝对路径
     */
    public function get_standard_url($url)
    {
        //上一层函数已做过空字符判断

        $url_parsed = parse_url($url);
        // 当前URL的path部分
        $path = $url_parsed['path'];
        // 步骤1：去除路径中的../
        while (preg_match('/\/[^\/\.]+\/\.\.\//', $path, $match)) {
            $path = str_replace($match, '/', $path);
        }
        // 步骤2：修补，去除路径中的./和..
        while (preg_match('/\/\.{1,2}\//', $path, $match)) {
            $path = str_replace($match, '/', $path);
        }
        // 拼接规范化后的URL
        return (empty($url_parsed['scheme']) ? '' : $url_parsed['scheme'] . '://') .
            (empty($url_parsed['host']) ? '' : $url_parsed['host']) .
            (empty($url_parsed['port']) ? '' : ':' . $url_parsed['port']) .
            $path .
            (empty($url_parsed['query']) ? '' : '?' . $url_parsed['query']);
    }

    /**
     * 根据content-type获取扩展名
     */
    public function get_ext_by_content_type($content_type)
    {
        // 去除content-type中的参数，转换为小写
        $type = strtolower(trim(explode(';', $content_type)[0]));
        // 根据content-type获取对应的扩展名
        return $this->content_type[$type] ?? null;
    }
}

// =================== 工具函数 ===================

/**
 * 编码URL路径部分，保留路径分隔符/
 */
function encode_path($path)
{
    $segments = explode('/', $path);
    $encodedSegments = array_map('rawurlencode', $segments);
    return implode('/', $encodedSegments);
}
/**
 * 判断多维数组是否为空
 * @param mixed $value
 * @return bool
 */
function is_multi_array_empty($value)
{
    if (is_array($value)) {
        // 遍历数组元素
        foreach ($value as $v) {
            // 递归判断元素是否为空
            if (!is_multi_array_empty($v)) {
                return false;
            }
        }
        return true;
    }
    // 判断非数组元素是否为空
    return $value === '' || $value === null || $value === [] || $value === false;
}
function safe_encode_path($path)
{
    // 先替换掉被编码的分隔符，防止被解码成目录
    $path = str_replace('%2F', '[SLASH]', $path);
    $path = rawurldecode($path);
    $path = encode_path($path);
    // 还原分隔符
    $path = str_replace('[SLASH]', '%2F', $path);
    return $path;
}
