<?php

use Symfony\Component\DomCrawler\Crawler;

require __DIR__ . './../vendor/autoload.php';

const BASE_EPICENTR_URL  = 'https://epicentrk.ua';
const CATEGORIES_FILE = 'categories.json';
const CATEGORIES_WITH_PRODUCTS = 'categories_with_products.json';

$categories = [];

if (file_exists('categories.json')) {
    $categories = json_decode(file_get_contents(CATEGORIES_FILE), true);
} else {
    $categories = getCategories('https://epicentrk.ua/ua/shop/stroitelnye-materialy/');
    file_put_contents(CATEGORIES_FILE, json_encode($categories, JSON_UNESCAPED_UNICODE));
}

$categoriesWithProductsPageInfo = $categories;

if (file_exists(CATEGORIES_WITH_PRODUCTS)) {
    $categoriesWithProductsPageInfo = json_decode(file_get_contents(CATEGORIES_WITH_PRODUCTS), true);
} else {
    foreach ( $categoriesWithProductsPageInfo as $i => &$category) {
        if (isset($category['children'])) {
            foreach ($category['children'] as $j => &$child) {
                echo "{$i}.{$j}" . PHP_EOL;
                echo $child['name'] . PHP_EOL;
                $child[$j]['products'] = getProducts(BASE_EPICENTR_URL . $child['uri']);
            }
        } else {
            echo $i . PHP_EOL;
            echo $category['name'] . PHP_EOL;
            $category[$i]['products'] = getProducts(BASE_EPICENTR_URL . $category['uri']);
        }
    }
    file_put_contents(CATEGORIES_WITH_PRODUCTS, json_encode($categoriesWithProductsPageInfo, JSON_UNESCAPED_UNICODE));
}


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

function getProducts(string $pageLink): array
{
    $products = [];
    $pageContent = file_get_contents($pageLink);
    $page = new Crawler($pageContent);

    $page
        ->filter('.listbody .card__info')
        ->each(function(Crawler $node) use (&$products) {
            $p = $node->filter('.card__price .card__price-sum');
            $products[] = [
                'name' => $node->filter('.card__name b')->first()->text(),
                'link' => BASE_EPICENTR_URL . $node->filter('.card__name a')->first()->attr('href'),
                'price' => $p->count() ? $p->first()->text() : null,
            ];
        });

    $nextPageLink = $page->filter('.custom-pagination__button--next');

    if ( $nextPageLink->count()) {
        $products = array_merge(
            $products,
            getProducts(BASE_EPICENTR_URL . $nextPageLink->first()->attr('href'))
        );
    }

    return $products;
}

function dd($obj)
{
    print_r($obj);
    die;
}