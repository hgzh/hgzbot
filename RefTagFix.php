#!/usr/bin/php
<?php
	require_once('phpWBF.php');
	
	class hgzRefTagFix {
		
		protected $mw;
		
		protected $pages = [];
		
		protected $bot_summary   = 'Bot: korrigiere Referenzsyntax';
		
		public function __construct() {
			$this->mw = new phpWBF('de.wikipedia.org');
			
			$this->mw->loginProfile('InkoBot');
			
			$this->run();
		}
		
		public function __destruct() {
			unset($this->mw);
		}
		
		protected function doSearch() {
			// missing equals
			$this->pages = $this->mw->getSearchResults('insource:/\<ref (name|group) *\"/', [0]);
		}
		
		protected function doReplaceMissingEquals() {
						
			// loop through all articles
			foreach ($this->pages as &$occ) {
								
				// get wikitext and use regexp replacement
				$occ['text'] = $this->mw->getWikitext((int)$occ['id'], 0, false);
				$occ['text'] = preg_replace('/(<ref (name|group))\s*(".+?">)/',
											'$1=$3',
											$occ['text']
										   );
				$occ['touched'] = true;
			}
		}
		
		protected function doReplacements() {
			$this->doReplaceMissingEquals();
		}
		
		protected function processPages() {
			
			// loop through all pages
			foreach ($this->pages as $occ) {
				
				// only pages where replacements have been done
				if ($occ['touched'] == false) {
					continue;
				}
								
				$this->mw->editPage($occ['title'], $occ['text'], $this->bot_summary);
			}
			
		}
		
		protected function run() {
			$this->doSearch();
			$this->doReplacements();
			$this->processPages();
			
			$this->mw->logoutUser();
		}
	
	}
	
	$instance = new hgzRefTagFix();
	
?>