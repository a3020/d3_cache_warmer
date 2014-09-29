<?php 
defined('C5_EXECUTE') or die("Access Denied.");

/*
Constants can be controlled in the site.php configuration:
- CACHE_WARMER_PAGE_TYPES: asterisk (*) or comma separated list of pagetype ids.
- CACHE_WARMER_MAX_PAGES: to prevent server delays, we can specify a max nr of pages. By default, 100 pages.

Once pages are in the cache, this job runs quickly.
*/

class D3CacheWarmer extends Job {
	protected $page_types = '*';
	protected $max_pages  = 100;
	protected $page_paths = array();
	
	public function getJobName() {
		$pkg = Package::getByHandle('d3_cache_warmer');
		return $pkg->getPackageName();
	}

	public function getJobDescription() {
		$pkg = Package::getByHandle('d3_cache_warmer');
		return $pkg->getPackageDescription();
	}
	
	public function run() {
		if(defined('CACHE_WARMER_PAGE_TYPES') && CACHE_WARMER_PAGE_TYPES != '*'){
			$this->page_types = explode(',', CACHE_WARMER_PAGE_TYPES);
		}
		
		if(defined('CACHE_WARMER_MAX_PAGES')){
			$this->max_pages = CACHE_WARMER_MAX_PAGES;
		}
		
		try {
			Loader::model('page_list');
			
			$pl = new PageList();
			$pl->filterByIsApproved(true);
			$pl->sortBy('RAND()', 1);
			
			// global cache value
			$pl->filter('p1.cCacheFullPageContent', -1);
			
			if(is_array($this->page_types) && count($this->page_types) > 0) {
				foreach($this->page_types as $ctID){
					$pl->filterByCollectionTypeID($ctID);
				}
			}
			
			$this->addPages($pl->get(1000));
			
			$results = 0;
			
			if(is_array($this->page_paths)){
				// randomize pages
				shuffle($this->page_paths);
				
				$fh = Loader::helper('file');
				
				foreach($this->page_paths as $page_path){
					// this is c5 wrapper for curl
					$page_content = $fh->getContents($page_path);

					if(empty($page_content)){
						$msg = t("Page load failed for '%s'", $page_path);
						Log::addEntry($msg, $this->getJobName());
					} else {
						$results++;
					}
				}
			}
			
			if($results > 0){
				return t('%s has loaded %s pages. Max: %s', $this->getJobName(), $results, $this->max_pages);
			} else {
				return t('%s did not load any pages. Page types: %s. Max pages: %s', $this->getJobName(), serialize($this->page_types), $this->max_pages);
			}
			
		} catch(Exception $e) {

			// enable job status otherwise it keeps running
			$this->setJobStatus('ENABLED');

			$msg = t('%s failed with exception: %s', $this->getJobName(), $e->getMessage());
			Log::addEntry($msg, $this->getJobName());
			
			return $msg;
		}
	}
	
	
	public function addPages($pages){
		if(!is_array($pages) OR count($pages) == 0){
			return false;
		}
		
		$cache = PageCache::getLibrary();
		
		foreach($pages as $page){
			if(count($this->page_paths) >= $this->max_pages){
				break;
			}
			
			$rec = $cache->getRecord($page);
			if ($rec instanceof PageCacheRecord) {
				// page is already cached
				continue;
			}
						
			$this->page_paths[] = BASE_URL.DIR_REL.'/index.php'.$page->getCollectionPath();
		}
	}
}