<?php

namespace mutation\simplesitemap\controllers;

use craft\web\Controller;
use craft\elements\Entry;
use craft\elements\Category;

class SitemapController extends Controller
{
	public $allowAnonymous = true;

	public function actionIndex()
	{
		$cache_key = 'sitemap_xml';
		$xmlstr = \Craft::$app->cache->get($cache_key);

		if (!$xmlstr) {

			$xml = new \SimpleXMLElement(
				'<?xml version="1.0" encoding="UTF-8"?>' .
				'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml"/>'
			);

			$locales = \Craft::$app->i18n->getSiteLocales();

			$elements = array();

			foreach ($locales as $locale) {
				$entries = Entry::find()
					->site($locale->id)
					->uri('not null')
					->limit(null)
					->all();

				foreach ($entries as $altEntry) {
					$elements[$locale->id]['entry'][$altEntry->id] = $altEntry;
				}

				$categories = Category::find()
					->site($locale->id)
					->uri('not null')
					->limit(null)
					->all();

				foreach ($categories as $altCategory) {
					$elements[$locale->id]['category'][$altCategory->id] = $altCategory;
				}
			}

			foreach ($elements as $elementsLocale) {
				foreach ($elementsLocale as $key => $elementsType) {
					foreach ($elementsType as $entry) {
						if (!$entry->url) {
							continue;
						}

						$url = $xml->addChild('url');
						$url->addChild('loc', $entry->url);
						$url->addChild('lastmod', $entry->dateUpdated->format(\DateTime::W3C));
						$url->addChild('priority', $entry->uri === '__home__' ? 0.75 : 0.5);

						foreach ($locales as $locale) {
							$altEntry = $elements[$locale->id][$key][$entry->id] ?? null;

							if (!$altEntry) {
								continue;
							}

							$altLink = $url->addChild('xhtml:link', null, 'http://www.w3.org/1999/xhtml');
							$altLink->addAttribute('rel', 'alternate');
							$altLink->addAttribute('hreflang', $locale);
							$altLink->addAttribute('href', $altEntry->url);
						}
					}
				}
			}

			$dom = new \DOMDocument('1.0');
			$dom->preserveWhiteSpace = false;
			$dom->formatOutput = true;
			$dom->loadXML($xml->asXML());

			$xmlstr = $dom->saveXML();

			\Craft::$app->cache->set($cache_key, $xmlstr);
		}

		\Craft::$app->response->format = \yii\web\Response::FORMAT_RAW;
		$headers = \Craft::$app->response->headers;
		$headers->add('Content-Type', 'text/xml');

		return $xmlstr;
	}
}
