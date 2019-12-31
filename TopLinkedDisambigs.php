#!/usr/bin/php
<?php
	require_once('phpWBF.php');
	require_once('database.phpWBF.php');
	
	class hgzTopLinkedDisambigs {
		
		protected $mw;
		protected $db;
				
		public function __construct() {
			$this->mw = new phpWBF('de.wikipedia.org');
			$this->db = new phpWBFdatabase();
			
			$this->mw->loginProfile('Hgzbot');

			$this->run();
		}
		
		public function __destruct() {
			unset($this->mw);
			unset($this->db);
		}
		
		protected function run() {

			$this->mw->log('starting new run...');
		
			$this->db->connectWMCSReplica('dewiki.analytics.db.svc.eqiad.wmflabs', 'dewiki_p');
			
			$t1  = 'SELECT pl.pl_title AS title, COUNT(pl.pl_title) AS count';
			$t1 .= ' FROM pagelinks pl';
			$t1 .= ' INNER JOIN page pd ON (pd.page_title = pl.pl_title AND pd.page_namespace = pl.pl_namespace)';
			$t1 .= ' LEFT JOIN page ps ON (ps.page_id = pl.pl_from)';
			$t1 .= ' LEFT JOIN pagelinks pls ON (pls.pl_title = ps.page_title AND pls.pl_namespace = 0 AND pls.pl_from_namespace = 0 AND ps.page_is_redirect = 1)';
			$t1 .= ' LEFT JOIN page plsp ON (plsp.page_id = pls.pl_from)';
			$t1 .= ' INNER JOIN page_props pp ON (pp.pp_page = pd.page_id AND pp.pp_propname = \'disambiguation\')';
			$t1 .= ' WHERE pl.pl_namespace = 0';
			$t1 .= ' AND pl.pl_from_namespace = 0';
			$t1 .= ' GROUP BY pl.pl_title';
			$t1 .= ' ORDER BY COUNT(pl.pl_title) DESC, pl.pl_title';
			$t1 .= ' LIMIT 200';
			
			$q1 = $this->db->query($t1);
			
			$r1 = phpWBFdatabase::fetchDBQueryResult($q1);
			
			$counttotal = 0;
			$output = "\n";
			foreach ($r1 as $l1) {
				$output .= '# [[:' . str_replace('_', ' ', $l1['title']) . ']] <small>([{{fullurl:Spezial:Linkliste/' . $l1['title'] . '|namespace=0}} ' . $l1['count'] . ' Links])</small>';
				$output .= "\n";
				$counttotal = $counttotal + $l1['count'];
			}
			$q1->close();
			
			$newtext = $this->mw->getWikitext('Wikipedia:WikiProjekt Begriffsklärungsseiten/Arbeitslisten/Top-BKS');
			$newtext = preg_replace('/<!--\s*HGZ_TLD_START\s*-->(.+)<!--\s*HGZ_TLD_END\s*-->/s', '<!-- HGZ_TLD_START -->' . $output . '<!-- HGZ_TLD_END -->', $newtext, 1);
			
			$this->mw->editPage('Wikipedia:WikiProjekt Begriffsklärungsseiten/Arbeitslisten/Top-BKS', $newtext, 'Bot: Aktualisiere Liste (' . $counttotal . ' Links)');

			$this->mw->log('finished run');
			
			$this->mw->logoutUser();
			$this->db->close();
		}
		
	}
	
	$instance = new hgzTopLinkedDisambigs();

?>