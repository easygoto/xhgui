<?php

if (!extension_loaded('tideways_xhprof')) {
    return;
}

tideways_xhprof_enable(
    TIDEWAYS_XHPROF_FLAGS_MEMORY | TIDEWAYS_XHPROF_FLAGS_MEMORY_MU | TIDEWAYS_XHPROF_FLAGS_MEMORY_PMU | TIDEWAYS_XHPROF_FLAGS_CPU
);

use XHGui\Application;

register_shutdown_function(
    static function () {
        $data['profile'] = tideways_xhprof_disable();

        require_once dirname(__DIR__) . '/vendor/autoload.php';

        $uri = $_SERVER['REQUEST_URI'] ?? null;
        if (empty($uri) && isset($_SERVER['argv'])) {
            // cli 模式
            $cmd = basename($_SERVER['argv'][0]);
            $withArgCmd = implode(' ', $_SERVER['argv']);

            if (strpos($withArgCmd, $_SERVER['PWD']) !== false) {
                $url = $_SERVER['PWD'] . '/' . substr($withArgCmd, strlen($_SERVER['PWD']) + 1);
            } elseif ($cmd === $_SERVER['argv'][0] || strpos($withArgCmd, '/') !== false) {
                $url = $_SERVER['PWD'] . '/' . $withArgCmd;
            } else {
                $url = $withArgCmd;
            }

            $uri = $url;
        } else {
            // fpm 模式
            $url = "$_SERVER[REQUEST_METHOD] $_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            if (($index1 = strpos($url, '?')) !== false) {
                // 现代系统, 一个查询列表的接口, 关键字可能很长
                $url = substr($url, 0, $index1);
            } elseif (is_numeric(substr($url, ($index2 = strrpos($url, '/') + 1)))) {
                // 对于 restful api, 最后一个 id 替换成 {id}
                $url = substr($url, 0, $index2) . '{id}';
            }
        }

        $_SERVER['REQUEST_TIME_FLOAT'] = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        $requestTimeFloat = explode('.', $_SERVER['REQUEST_TIME_FLOAT']);
        $second = $requestTimeFloat[0] ?? 0;
        $microSec = $requestTimeFloat[1] ?? 0;

        $app = new Application();
        $saver = $app->getSaver();

        $data['meta'] = [
            'url' => $uri,
            'SERVER' => $_SERVER,
            'get' => $_GET,
            'env' => $_ENV,
            'simple_url' => $url,
            'request_ts_micro' => [
                'sec' => $second,
                'usec' => $microSec,
            ],
        ];

        $saver->save($data);
    }
);
