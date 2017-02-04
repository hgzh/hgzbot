#!/usr/bin/php
<?php
	require_once('phpWBF.php');
	
	class hgzFlaggedCatCheck {
		
		protected $mw;
		
		protected $customers = [];
		
		public function __construct() {
			$this->mw = new phpWBF('de.wikipedia.org');
			
			$this->mw->loginProfile('Hgzbot');
			
			$this->run();
		}
		
		public function __destruct() {
			unset($this->mw);
		}
		
		protected function getCustomers() {
			
			// get transclusions of template
			try {
				$embd = $this->mw->getEmbeddingsContent('User:Hgzbot/Service/FlaggedCatCheck', []);
			} catch (Exception $e) {
				throw new Exception('Error: No transclusions of template found.');
			}
			
			foreach ($embd as $item) {
				$text = $item['text'];
				
				// categories to use
				$match = [];
				if (preg_match('/HGZ_FCC_CATEGORIES\s*\=\s*([^\n\|]+)/', $text, $match) == 1) {
					$cnfCatText = $match[1];
				} else {
					$this->mw->log('ERROR: Customer ' . $item['title'] . ': category pattern not found');
					continue;
				}
				
				// frequency
				$match = [];
				if (preg_match('/HGZ_FCC_FREQUENCY\s*\=\s*(\d{1,})/', $text, $match) == 1) {
					$cnfFrequency = $match[1];
				} else {
					$cnfFrequency = '24';
				}

				// last visit
				$match = [];
				if (preg_match('/HGZ_FCC_LASTVISIT\s*\=\s*(\d{12})/', $text, $match) == 1) {
					$cnfLastVisit = $match[1];
				} else {
					$cnfLastVisit = '200000000000';
				}
				
				// check last visited/frequency
				$dtNow  = time();
				$dtLast = DateTime::createFromFormat('YmdHi', $cnfLastVisit);
				$dtNext = strtotime('+ ' . $cnfFrequency . ' hours', $dtLast->getTimestamp());
				if ($dtNow <= $dtNext) {
					continue;
				}
				
				// split categories
				$cnfCategories = [];
				$match = [];
				if (preg_match_all('/([^\n\|\{#]+)(\{(\d{1,2})\})?\#?/', $cnfCatText, $match, PREG_SET_ORDER) > 0) {
					foreach ($match as $m) {
						if (!isset($m[1])) {
							continue;
						}
						$cat = [];
						$cat['title'] = 'Category:'. $m[1];
						if (isset($m[3])) {
							$cat['depth'] = $m[3];
							if ($cat['depth'] > 10) {
								$cat['depth'] = 10;
							}
						} else {
							$cat['depth'] = 0;
						}
						$cnfCategories[] = $cat;
					}
				} else {
					$this->mw->log('ERROR: Customer ' . $item['title'] . ': invalid category pattern');
					continue;
				}
								
				$this->customers[] = [
										'title'      => $item['title'],
										'frequency'  => $cnfFrequency,
										'last'       => $cnfLastVisit,
										'categories' => $cnfCategories
									];
			}

			$this->mw->log('Found ' . count($this->customers) . ' customers.');
			
		}
		
		protected function processCustomers() {
			
			foreach ($this->customers as $customer) {
				$pages  = [];
				$output = "\r\n";
				$count  = 0;

				$this->mw->log('Processing ' . $customer['title'] . '... pages from categories...');
				
				foreach ($customer['categories'] as $cat) {
					$this->mw->getCategoryMembersFlaggedInfo($pages, $cat['title'], [0, 14], [], $cat['depth'], true);
				}
				$pages = $this->mw->uniqueMultidimArray($pages, 'id');
				$pages = $this->mw->sortMultidimArray($pages, 'oldstamp', SORT_ASC);

				$this->mw->log('Processing ' . $customer['title'] . '... get output...');
				
				foreach ($pages as $page) {
					if ($page['old'] == true) {
						$output .= '* [[:' . $page['title'] . ']]';
						if ($page['oldsince'] == false) {
							$output .= ' (ungesichtet)';
						} else {
							$output .= ' ([{{fullurl:' . $page['title'] . '|diffonly=0&diff=review&redirect=no}} sichten])';

							$oldsince = DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $page['oldsince']);
							$datenow  = new DateTime();
							$diff = $oldsince->diff($datenow);
							
							$output .= ', seit ' . $diff->format('%a Tagen und %h Stunden');
						}
						$output .= "\r\n";
						$count++;
					}
				}

				$this->mw->log('Processing ' . $customer['title'] . '... update page...');
				
				$newtext = $this->mw->getWikitext($customer['title']);
				$newtext = preg_replace('/<!--\s*HGZ_FCC_START\s*-->(.+)<!--\s*HGZ_FCC_END\s*-->/s', '<!-- HGZ_FCC_START -->' . $output . '<!-- HGZ_FCC_END -->', $newtext, 1);
				$newtext = preg_replace('/HGZ_FCC_LASTVISIT\s*\=\s*([^\n\|\}])+/', 'HGZ_FCC_LASTVISIT = ' . date('YmdHi', time()), $newtext, 1);
				
				$this->mw->editPage($customer['title'], $newtext, 'Bot: Aktualisiere FlaggedCatCheck (' . $count . ' Einträge)');
				
				unset($pages);
				
			}
		}
		
		protected function run() {
			$this->getCustomers();
			$this->processCustomers();
			
			$this->mw->logoutUser();
		}
	
	}
	
	$instance = new HgzFlaggedCatCheck();
	
?>