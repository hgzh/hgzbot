#!/usr/bin/php
<?php
	require_once('phpWBF.php');
	
	class hgzBVKSorgenkinder {
		
		protected $mw;
				
		public function __construct() {
			$this->mw = new phpWBF('de.wikipedia.org');
			
			$this->mw->loginProfile('Hgzbot');

			$this->run();
		}
		
		public function __destruct() {
			unset($this->mw);
		}
		
		protected function run() {
			
			$json = $this->mw->toolPetscanPost('psid=6275628&format=json');
			$pages = $json['*'][0]['a']['*'];
			
			if (count($pages) == 0) {
				$this->mw->log('empty petscan response: ' . serialize($json));
				$this->mw->logoutUser();
				return;
			}
			
			$output = '';
			$i      = 0;
			foreach ($pages as $v1) {
				$output .= '# [[' . str_replace('_', ' ', $v1['title']) . ']] ';
				$output .= '(' . $v1['len'] . ' Bytes)' . "\n";
				$i++;
			}
			
			$newtext = $this->mw->getWikitext('Wikipedia:WikiProjekt Bundesverdienstkreuz/Sorgenkinder');
			$newtext = preg_replace('/<!--\s*HGZ_BVK_START\s*-->(.*)<!--\s*HGZ_BVK_END\s*-->/s', '<!-- HGZ_BVK_START -->' . $output . '<!-- HGZ_BVK_END -->', $newtext, 1);
			
			$this->mw->editPage('Wikipedia:WikiProjekt Bundesverdienstkreuz/Sorgenkinder', $newtext, 'Bot: Aktualisiere Liste (' . $i . ' Einträge)');
			
			$this->mw->logoutUser();
		}
		
	}
	
	$instance = new hgzBVKSorgenkinder();

?>