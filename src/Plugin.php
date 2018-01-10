<?php

namespace mutation\simplesitemap;

use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use mutation\simplesitemap\controllers\SitemapController;
use yii\base\Event;

class Plugin extends \craft\base\Plugin
{
	public $controllerMap = [
		'sitemap' => SitemapController::class,
	];

	public function init()
	{
		Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, function(RegisterUrlRulesEvent $event) {
			$event->rules['sitemap.xml'] = 'simplesitemap/sitemap/index';
		});
	}
}
