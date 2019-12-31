#!/usr/bin/php
<?php
	require_once('phpWBF.php');
	
	class hgzRemoveSameImageLink {
		
		protected $mw;
		
		protected $pages = [];
		
		protected $search_query  = 'insource:/verweis=http/';
		protected $search_regexp = '/(\[\[\s*(Datei|File|Bild|Image):(.*?))\|verweis=https:\/\/[a-z]{2,3}\.wikipedia\.org\/wiki\/(Datei|File|Image):(.*?)([\|\]])/';
		protected $bot_summary   = 'Bot: entferne überflüssige Verweisangabe in Bildeinbindung';
		
		public function __construct() {
			$this->mw = new phpWBF('de.wikipedia.org');
			
			$this->mw->loginProfile('InkoBot');
			
			$this->run();
		}
		
		public function __destruct() {
			unset($this->mw);
		}
		
		protected function doReplacements() {
			
			$this->pages = $this->mw->getSearchResults($this->search_query, [0]);			
			
			// loop through all articles
			foreach ($this->pages as &$occ) {
								
				// get wikitext
				$occ['text']    = $this->mw->getWikitext((int)$occ['id'], 0, false);
				$occ['touched'] = false;
				$occ['match']   = [];
				
				// use regexp to find the actual occurrences in the wikitext
				preg_match_all($this->search_regexp,
							   $occ['text'],
							   $occ['match'],
							   PREG_SET_ORDER
							  );				
				
				// loop through all matches in the current article
				foreach ($occ['match'] as $match) {
					$file = $match[3];
					$link = $match[5];
					
					$file = rawurlencode(str_replace(' ', '_', $file));
					$link = str_replace('%20', '_', $link);
					
					// if $link is not the urlencoded $file (different targets), then continue with next match
					if ($file != $link) {
						continue;
					}
					
					// remove the unwanted link
					$occ['text'] = preg_replace($this->search_regexp,
												'$1$6',
												$occ['text'],
												1
											   );
					
					$occ['touched'] = true;
				}
			}
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
			$this->doReplacements();
			$this->processPages();
			
			$this->mw->logoutUser();
		}
	
	}
	
	$instance = new hgzRemoveSameImageLink();
	
?>