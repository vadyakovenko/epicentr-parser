<?php

require __DIR__ . './../vendor/autoload.php';

use Symfony\Component\DomCrawler\Crawler;


function dd($obj)
{
    print_r($obj);
    die;
}

function flat_map(callable $func, array $array) {
    return reduce('array_merge',
        array_map($func, $array), []);
}

function reduce(callable $func, array $array, $initial = null) {
    return array_reduce($array, $func, $initial);
}

function parallel_map(callable $func, array $items) {
    $childPids = [];
    $result = [];
    foreach ($items as $i => $item) {
        $newPid = pcntl_fork();
        if ($newPid == -1) {
            die('Can\'t fork process');
        } elseif ($newPid) {
            $childPids[] = $newPid;
            if ($i == count($items) - 1) {
                foreach ($childPids as $childPid) {
                    pcntl_waitpid($childPid, $status);
                    $sharedId = shmop_open($childPid, 'a', 0, 0);
                    $shareData = shmop_read($sharedId, 0, shmop_size($sharedId));
                    $result[] = unserialize($shareData);
                    shmop_delete($sharedId);
                    shmop_close($sharedId);
                }
            }
        } else {
            $myPid = getmypid();
            echo 'Start ' . $myPid . PHP_EOL;
            $funcResult = $func($item);
            $shareData = serialize($funcResult);
            $sharedId = shmop_open($myPid, 'c', 0644, strlen($shareData));
            shmop_write($sharedId, $shareData, 0);
            echo 'Done ' . $myPid . ' ' . formatUsage(memory_get_peak_usage()) . PHP_EOL;
            exit(0);
        }
    }
    return $result;
}

function get_web_page( $url ) {
    $options = array(
        CURLOPT_RETURNTRANSFER => true,     // return web page
        CURLOPT_HEADER         => true,    // do not return headers
        CURLOPT_FOLLOWLOCATION => true,     // follow redirects
        CURLOPT_USERAGENT      => "spider", // who am i
        CURLOPT_AUTOREFERER    => false,     // set referer on redirect
        CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
        CURLOPT_TIMEOUT        => 120,      // timeout on response
        CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
    );
    $ch = curl_init( $url );
    curl_setopt_array( $ch, $options );
    $content = curl_exec( $ch );

    if (curl_getinfo($ch)['url'] != $url) {
        return null;
    }

    return $content;
}

function getHtml($url) {
    $file = __DIR__ . '/../cache/' . md5($url);
    if (file_exists($file)) {
        return file_get_contents($file);
    } else {
        if($html = get_web_page($url)) {
            file_put_contents($file, $html);
        }
        return $html;
    }
}

function crawler(string $html): Crawler
{
    return new Crawler($html);
}