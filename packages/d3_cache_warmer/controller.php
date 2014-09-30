<?php 
defined('C5_EXECUTE') or die("Access Denied.");

/*
Author: Adri Kodde
https://github.com/akodde/d3_cache_warmer
*/

class D3CacheWarmerPackage extends Package {

	protected $pkgHandle = 'd3_cache_warmer';
	protected $appVersionRequired = '5.4.1.1';
	protected $pkgVersion = '1.0';

	public function getPackageName() {
		return t('Cache Warmer Job');
	}

	public function getPackageDescription() {
		return t('Installs a job that creates cache files for pages.');
	}

	public function install() {
		$pkg = parent::install();
		
		Loader::model('job');
		Job::installByPackage('d3_cache_warmer', $pkg);
	}
}