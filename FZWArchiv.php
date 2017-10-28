#!/usr/bin/php
<?php
	require_once('phpWBF.php');
	
	class hgzFZWArchiv {
		
		protected $mw;
				
		public function __construct() {
			$this->mw = new phpWBF('de.wikipedia.org');
			
			$this->mw->loginProfile('Hgzbot');
			
			$this->run();
		}
		
		public function __destruct() {
			unset($this->mw);
		}
		
		private function getLastWeek($year) {
			$date = new DateTime();
			$date->setISODate($year, 53);
			return ($date->format('W') === '53' ? 53 : 52);
		}
		
		protected function run() {
						
			$output = '';
			
			// open file with last run information
			$file = fopen('hgz-fzwa/last.txt', 'r');
			if ($file) {
				$line = fgets($file);
				fclose($file);
			} else {
				return false;
			}
			
			// get weeks
			$last    = explode('|', $line);
			$year    = $last[0];
			$minWeek = $last[1] + 1;
			if ($year == date('o')) {
				$maxWeek  = date('W') - 2;
				$nextWeek = $maxWeek;
			} else {
				$maxWeek  = $this->getLastWeek($year);
				$nextWeek = 0;
			}
			
			$this->mw->log('new run: ' . $year . ': ' . $minWeek . '-' . $maxWeek);
			
			for ($week = $minWeek; $week <= $maxWeek; $week++) {

				$weekS = str_pad($week, 2, '0', STR_PAD_LEFT);
				
				// parse sections
				$result = $this->mw->mwApiGet([
					'action' => 'parse',
					'page'   => urlencode('Wikipedia:Fragen zur Wikipedia/Archiv/' . $year . '/Woche ' . $weekS),
					'prop'   => 'sections'
				]);
								
				$output .= "\n";
				$output .= '== Woche ' . $weekS . ' ==';
				$output .= "\n\n";
				$output .= '* [[../Archiv/' . $year . '/Woche ' . $weekS . ']]';
				$output .= "\n\n";
				
				foreach ($result['parse']['sections'] as $section) {
					for ($j = 1; $j <= $section['toclevel']; $j++) {
						$output .= ':';
					}
										
					$output .= $section['number'];
					$output .= ': [https://de.wikipedia.org/wiki/';
					$output .= $section['fromtitle'];
					$output .= '#';
					$output .= $section['anchor'];
					$output .= ' <nowiki>';
					$output .= $section['line'];
					$output .= '</nowiki>]';
					$output .= "\n";
				}
			
			}
			
			// update wiki page
			$oldtext = $this->mw->getWikitext('Wikipedia:Fragen zur Wikipedia/Archiv-Verzeichnis ' . $year);
			$this->mw->editPage('Wikipedia:Fragen zur Wikipedia/Archiv-Verzeichnis ' . $year, $oldtext . $output, 'Bot: Aktualisiere Archiv-Verzeichnis (bis Woche ' . $maxWeek . ')');
			
			// update file
			$file = fopen('hgz-fzwa/last.txt', 'w');
			if ($file) {
				fwrite($file, date('o') . '|' . $nextWeek);
				fclose($file);
			} else {
				return false;
			}
			
			if ($year == date('o')) {
				return true;
			} else {
				$this->run();
			}
			
			$this->mw->logoutUser();
			
		}
		
	}
	
	$instance = new hgzFZWArchiv();

?>