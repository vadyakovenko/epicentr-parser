<?php

require 'functions.php';
use Symfony\Component\DomCrawler\Crawler;

const BASE_URL = 'https://www.leroymerlin.ua/';

function allCategories(Crawler $crawler): array
{
    return $crawler
        ->filter('.sub-universe-item a')
        ->each(function (Crawler $node)  {
            echo $node->text() . PHP_EOL;
            return [
                'name' => $node->text(),
                'url' => BASE_URL . $node->attr('href')
            ];
        });
}

function getProducts(array $categories)
{
    return array_map(function($item) {
        $data = crawler(getHtml($item['url']))
            ->filter('script[type="text/javascript"]')
            ->eq(3)
            ->text();

        $data = explode('window.wikeo_family = ', $data)[1];
        $data = explode('; var familyItem = {};', $data)[0];
        $data = json_decode($data, true)['contentPage'];

        $products = array_map( function($item) {
            return [
                'name' => $item['title'],
                'price' => $item['price'] . 'грн',
                'url' => BASE_URL . $item['path'],
            ];
        }, $data['list']);

        for ($i = $data['page']+1; $i <=  $data['numberOfPages']; $i++ ) {
            $nextPageData = crawler(getHtml($item['url'] . '?page=' . $i))
                ->filter('script[type="text/javascript"]')
                ->eq(3)
                ->text();
            $nextPageData = explode('window.wikeo_family = ', $nextPageData)[1];
            $nextPageData = explode('; var familyItem = {};', $nextPageData)[0];
            $nextPageData = json_decode($nextPageData, true)['contentPage'];

            foreach ($nextPageData['list'] as $product) {
                $products[] = [
                    'name' => $product['title'],
                    'price' => $product['price'] . 'грн',
                    'url' => BASE_URL . $product['path'],
                ];
            }
        }
        return array_merge($item, ['products' => $products]);

    }, $categories);
}

$result = getProducts(
        allCategories(
            \crawler(
                getHtml('https://www.leroymerlin.ua/ru/u/Stroitelnye_materialy.fbcb1677-5bc5-4650-8c4d-c7ea1fc5575a')
            )
        )
    );

file_put_contents(
    'leroymerlin.json',
    json_encode($result, JSON_UNESCAPED_UNICODE)
);

$file = json_decode(file_get_contents('leroymerlin.json'), true);

dd($file);