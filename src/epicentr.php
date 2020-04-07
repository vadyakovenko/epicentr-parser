<?php
require 'functions.php';
use Symfony\Component\DomCrawler\Crawler;

const BASE_EPICENTR_URL  = 'https://epicentrk.ua';

function getProducts(string $pageLink): array
{
    $products = [];
    $pageContent = get_web_page($pageLink);
    if (!$pageContent) {
        return [];
    }
    $page = crawler($pageContent);

    $page
        ->filter('.listbody .card')
        ->each(function(Crawler $node) use (&$products) {
            $p = $node->filter('.card__price .card__price-sum');
            $a = $node->filter('.card__name a');
            $products[] = [
                'name' => $a->count() ? $a->first()->text() : '',
                'link' => $a->count() ? BASE_EPICENTR_URL . $a->attr('href') : '',
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

function allCategories(Crawler $crawler): array
{
    return $crawler
        ->filter('.shop-category__list-item a')
        ->each(function (Crawler $node)  {
            echo $node->text() . PHP_EOL;
             return [
                'name' => $node->text(),
                'url' => $node->attr('href')
            ];
    });
}

function loadChildrenCategories(array &$categories)
{
    $checkedPages = [];

    foreach ($categories as $k => &$item) {
        $categories[$k] = loadChildren($item, $checkedPages);
    }

    return $categories;
}

function loadChildren(array $item, array &$checkedPages): array
{
    echo 'CHILDREN: ' . $item['name'] . PHP_EOL;

    if (in_array($item['url'], $checkedPages)) {
        return $item;
    }

    if (!($html = getHtml(BASE_EPICENTR_URL . $item['url']))) {
        return $item;
    }

    $crawler = \crawler($html);

    if ($crawler->filter('.listbody .product-Wrap')->count()) {
        return $item;
    }

    return $crawler
        ->filter('.shop-categories__container .shop-categories__item a')
        ->each(function(Crawler $node) use (&$checkedPages) {
            $currentItem = [
                'name' => $node->text(),
                'url' => $url = $node->attr('href')
            ];
            $checkedPages[] = $url;

            return loadChildren($currentItem, $checkedPages);
        });
}

$categories = flat_map(
        'array_merge',
        loadChildrenCategories(
            allCategories(
                \crawler(
                    getHtml('https://epicentrk.ua/ua/shop/stroitelstvo-i-remont/')
                )
            )
        )
    );

foreach ($categories as $category) {
    $products = getProducts(BASE_EPICENTR_URL . $category['url']);
    file_put_contents('data/' . $category['name'] . '.json', json_encode($products, JSON_UNESCAPED_UNICODE));
}

