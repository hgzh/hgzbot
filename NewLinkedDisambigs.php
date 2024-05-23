#!/usr/bin/php
<?php
	require_once('phpWBF.php');
	require_once('database.phpWBF.php');
	
	class hgzNewLinkedDisambigs {
		
		protected $mw;
		protected $db;
				
		public function __construct() {
			$this->mw = new phpWBF( 'de.wikipedia.org' );
			$this->db = new phpWBFdatabase();
			
			$this->mw->loginProfile( 'Hgzbot' );

			$this->run();
		}
		
		public function __destruct() {
			unset( $this->mw );
			unset( $this->db );
		}

		protected function getConfig() {
			$json = $this->mw->mwRawGet(
				'User:Hgzbot/Service/NewLinkedDisambigs/config.json',
				'application/json'
			);
			$this->config = json_decode( $json, true );
		}			
		
		protected function outputList() {
			$t1  = 'SELECT lt.lt_title AS title, COUNT(pl.pl_from) AS count';
			$t1 .= ' FROM pagelinks pl';
			$t1 .= ' INNER JOIN linktarget lt ON (pl.pl_target_id = lt.lt_id)';
			$t1 .= ' INNER JOIN page p ON (p.page_title = lt.lt_title AND p.page_namespace = lt.lt_namespace)';
			$t1 .= ' INNER JOIN categorylinks cl ON (cl.cl_from = p.page_id AND cl.cl_to = \'Begriffsklärung\')';
			$t1 .= ' WHERE lt.lt_namespace = 0';
			$t1 .= ' AND pl.pl_from_namespace = 0';
			$t1 .= ' AND cl.cl_timestamp >= DATE(NOW()) - INTERVAL ? DAY';
			$t1 .= ' GROUP BY lt.lt_title';
			$t1 .= ' HAVING COUNT(pl.pl_from) >= ?';
			$t1 .= ' ORDER BY COUNT(pl.pl_from) DESC';
			$q1 = $this->db->executeDBQuery( $t1, 'ii', (int)$this->config['config']['days'], (int)$this->config['config']['minlinks'] );
			
			$r1 = phpWBFdatabase::fetchDBQueryResult($q1);
			
			$output = "\n";
			$count  = 0;
			foreach ( $r1 as $l1 ) {
				$output .= str_replace(
					['$1', '$2'],
					[str_replace( '_', ' ', $l1['title'] ), $l1['count']],
					$this->config['config']['output']
				);				
				$output .= "\n";
				$count++;
			}
			$q1->close();
			
			$newtext = $this->mw->getWikitext( $this->config['config']['target'] );
			$newtext = preg_replace(
				'/<!--\s*HGZ_NLD_START\s*-->(.+)<!--\s*HGZ_NLD_END\s*-->/s', '<!-- HGZ_NLD_START -->' . $output . '<!-- HGZ_NLD_END -->',
				$newtext,
				1
			);
			$newtext = preg_replace(
				'/<!--\s*HGZ_NLD_COUNT\s*-->\d{1,}<!--\s*HGZ_NLD_COUNT\s*-->/s', '<!-- HGZ_NLD_COUNT -->' . $count . '<!-- HGZ_NLD_COUNT -->',
				$newtext,
				1
			);
			
			$this->mw->editPage(
				$this->config['config']['target'],
				$newtext,
				str_replace(
					'$1',
					$count,
					$this->config['config']['summary']
				)
			);
		}

		protected function run() {
			$this->getConfig();
			
			if ( $this->config['enabled'] === true ) {
				$this->db->connectWMCSReplica(
					$this->config['connect']['cluster'],
					$this->config['connect']['database']
				);
				
				$this->outputList();
				
				$this->db->close();
			}
			
			$this->mw->logoutUser();
		}
		
	}
	
	$instance = new hgzNewLinkedDisambigs();

?>