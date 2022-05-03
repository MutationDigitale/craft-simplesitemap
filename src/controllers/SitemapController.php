<?php

namespace mutation\simplesitemap\controllers;

use Craft;
use craft\web\Controller;
use craft\elements\Entry;
use craft\elements\Category;
use DateTime;
use DOMDocument;
use SimpleXMLElement;
use yii\web\Response;

class SitemapController extends Controller
{
    public int|bool|array $allowAnonymous = true;

    public function actionIndex()
    {
        $cache_key = 'mutation_sitemap_xml';
        $xmlstr = Craft::$app->cache->get($cache_key);

        if (!$xmlstr) {
            $xml = new SimpleXMLElement(
                '<?xml version="1.0" encoding="UTF-8"?>' .
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml"/>'
            );

            $sites = Craft::$app->sites->getAllSites();

            $elements = array();

            foreach ($sites as $site) {
                $entries = Entry::find()
                    ->site($site->handle)
                    ->uri('not null')
                    ->limit(null)
                    ->all();

                foreach ($entries as $altEntry) {
                    $elements[$site->id]['entry'][$altEntry->id] = $altEntry;
                }

                $categories = Category::find()
                    ->site($site->handle)
                    ->uri('not null')
                    ->limit(null)
                    ->all();

                foreach ($categories as $altCategory) {
                    $elements[$site->id]['category'][$altCategory->id] = $altCategory;
                }
            }

            foreach ($elements as $elementsLocale) {
                foreach ($elementsLocale as $key => $elementsType) {
                    foreach ($elementsType as $entry) {
                        if (!$entry->url) {
                            continue;
                        }

                        $url = $xml->addChild('url');
                        if ($url) {
                            $url->addChild('loc', $entry->url);
                            $url->addChild('lastmod', $entry->dateUpdated->format(DateTime::W3C));
                            $url->addChild('priority', $entry->uri === '__home__' ? 0.75 : 0.5);
                        }

                        foreach ($sites as $site) {
                            $altEntry = $elements[$site->id][$key][$entry->id] ?? null;

                            if (!$altEntry) {
                                continue;
                            }

                            $altLink = $url->addChild('xhtml:link', null, 'http://www.w3.org/1999/xhtml');
                            if ($altLink) {
                                $altLink->addAttribute('rel', 'alternate');
                                $altLink->addAttribute('hreflang', $site->language);
                                $altLink->addAttribute('href', $altEntry->url);
                            }
                        }
                    }
                }
            }

            $dom = new DOMDocument('1.0');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXML($xml->asXML());

            $xmlstr = $dom->saveXML();

            Craft::$app->cache->set($cache_key, $xmlstr);
        }

        Craft::$app->response->format = Response::FORMAT_RAW;
        $headers = Craft::$app->response->headers;
        $headers->add('Content-Type', 'text/xml');

        return $xmlstr;
    }
}
