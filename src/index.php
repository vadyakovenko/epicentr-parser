<?php

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

require __DIR__ . './../vendor/autoload.php';

const BASE_EPICENTR_URL  = 'https://epicentrk.ua';
const CATEGORIES_FILE = 'categories.json';
const CATEGORIES_WITH_PRODUCTS_PAGE_INFO = 'categories_with_products_page_info.json';

$categories = [];

if (file_exists('categories.json')) {
    $categories = json_decode(file_get_contents(CATEGORIES_FILE), true);
} else {
    $categories = getCategories('https://epicentrk.ua/ua/shop/stroitelnye-materialy/');
    file_put_contents(CATEGORIES_FILE, json_encode($categories, JSON_UNESCAPED_UNICODE));
}

$categoriesWithProductsPageInfo = $categories;

if (file_exists(CATEGORIES_WITH_PRODUCTS_PAGE_INFO)) {
    $categoriesWithProductsPageInfo = json_decode(file_get_contents(CATEGORIES_WITH_PRODUCTS_PAGE_INFO), true);
} else {
    foreach ( $categoriesWithProductsPageInfo as $i => &$category) {
        if (isset($category['children'])) {
            foreach ($category['children'] as $j => $child) {
                echo "{$i}.{$j}" . PHP_EOL;
                echo $child['name'] . PHP_EOL;
                $category['children'][$j]['productsPageInfo'] = getProductsPageInfo(BASE_EPICENTR_URL . $child['uri']);
            }
        } else {
            echo $i . PHP_EOL;
            echo $category['name'] . PHP_EOL;
            $category['children'][$i]['productsPageInfo'] = getProductsPageInfo(BASE_EPICENTR_URL . $category['uri']);
        }
    }
    file_put_contents(CATEGORIES_WITH_PRODUCTS_PAGE_INFO, json_encode($categories, JSON_UNESCAPED_UNICODE));
}
//////////////////////////////////////////////////////
$products = [];
foreach ( $categoriesWithProductsPageInfo as $i => $category) {
    if (isset($category['children'])) {
        foreach ($category['children'] as $j => $child) {
            echo "{$i}.{$j}" . PHP_EOL;
            echo $child['name'] . PHP_EOL;
            $products[] = [
                'category' => $child['name'],
                'productData' => getProducts($child['productsPageInfo'])
            ];
        }
    } else {
        echo $category['name'] . PHP_EOL;
        $products[] = [
            'category' => $category['name'],
            'productData' => $category['productsPageInfo']
        ];
    }
}


file_put_contents('products_page.json', json_encode($products, JSON_UNESCAPED_UNICODE));



//////////////functions////////////////////
function getCategories(string $link, bool $recursive = true): array
{
    $pageContent = file_get_contents($link);

    $categories = [];
    $page = new Crawler($pageContent);

    $page
        ->filter('.shop-categories__container .shop-categories__item')
        ->each(function (Crawler $node) use (&$categories) {
            $categories[] = [
                'name' => $name = $node->filter('.shop-categories__item-title')->first()->text(),
                'uri' => $node->filter('a')->first()->attr('href'),
            ];

            echo $name . PHP_EOL;
        });

    if ($recursive) {
        foreach ($categories as &$category) {
            $category['children'] = getCategories(BASE_EPICENTR_URL . $category['uri'], false);
        }
    }

    return $categories;
}

function getProductsPageInfo(string $pageLink): array
{
    $pageContent = file_get_contents($pageLink);
    $infoBlock = (new Crawler($pageContent))->filter('div[data-is=Softcube]')->first()->attr('data-params');

    return json_decode( str_replace('\'', '"', $infoBlock), true );
}

function getProducts(array $pageInfo): array
{
    $client = new Client(['base_uri' => BASE_EPICENTR_URL]);
    $response = $client
        ->request(
            $pageInfo['method'],
            $pageInfo['url'],
            [
                'form_params' =>  $pageInfo['request']
            ]
        );


    $data = json_decode($response->getBody(), true);
    $products = [];

    foreach ($data['api_response']['api_response']['items'] as $product)
    {
        $products[] = [
            'name' => $product['NAME'],
            'price' => $product['PRICE'],
            'link' => BASE_EPICENTR_URL . $product['DETAIL_PAGE_URL'],
        ];
    }

    return $products;
}

function dd($obj)
{
    print_r($obj);
    die;
}