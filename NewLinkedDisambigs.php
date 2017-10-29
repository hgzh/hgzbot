#!/usr/bin/php
<?php
	require_once('phpWBF.php');
	require_once('database.phpWBF.php');
	
	class hgzNewLinkedDisambigs {
		
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
			
			$t1  = 'SELECT pl.pl_title AS title, COUNT(pl.pl_from) AS count';
			$t1 .= ' FROM pagelinks pl';
			$t1 .= ' INNER JOIN page p ON (p.page_title = pl.pl_title AND p.page_namespace = pl.pl_namespace)';
			$t1 .= ' INNER JOIN categorylinks cl ON (cl.cl_from = p.page_id AND cl.cl_to = \'Begriffsklärung\')';
			$t1 .= ' WHERE pl.pl_namespace = 0';
			$t1 .= ' AND pl.pl_from_namespace = 0';
			$t1 .= ' AND cl.cl_timestamp >= DATE(NOW()) - INTERVAL 5 DAY';
			$t1 .= ' GROUP BY pl.pl_title';
			$t1 .= ' HAVING COUNT(pl.pl_from) >= 5';
			$t1 .= ' ORDER BY COUNT(pl.pl_from) DESC';
			$q1 = $this->db->query($t1);
			
			$r1 = phpWBFdatabase::fetchDBQueryResult($q1);
			
			$output = "\n";
			$count  = 0;
			foreach ($r1 as $l1) {
				$output .= '# [[:' . str_replace('_', ' ', $l1['title']) . ']] <small>([{{fullurl:Spezial:Linkliste/' . $l1['title'] . '|namespace=0}} ' . $l1['count'] . ' Links])</small>';
				$output .= "\n";
				$count++;
			}
			$q1->close();
			
			$newtext = $this->mw->getWikitext('Wikipedia:WikiProjekt Begriffsklärungsseiten/Arbeitslisten/NeueVerlinkteBKS');
			$newtext = preg_replace('/<!--\s*HGZ_NLD_START\s*-->(.+)<!--\s*HGZ_NLD_END\s*-->/s', '<!-- HGZ_NLD_START -->' . $output . '<!-- HGZ_NLD_END -->', $newtext, 1);
			$newtext = preg_replace('/<!--\s*HGZ_NLD_COUNT\s*-->\d{1,}<!--\s*HGZ_NLD_COUNT\s*-->/s', '<!-- HGZ_NLD_COUNT -->' . $count . '<!-- HGZ_NLD_COUNT -->', $newtext, 1);
			
			$this->mw->editPage('Wikipedia:WikiProjekt Begriffsklärungsseiten/Arbeitslisten/NeueVerlinkteBKS', $newtext, 'Bot: Aktualisiere Liste (' . $count . ' Einträge)');
			
			$this->mw->log('finished run with ' . $count . ' results');
			
			$this->mw->logoutUser();
			$this->db->close();
		}
		
	}
	
	$instance = new hgzNewLinkedDisambigs();

?>