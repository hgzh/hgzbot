#!/usr/bin/php
<?php
	require_once('phpWBF.php');
	require_once('database.phpWBF.php');
	
	class hgzTopLinkedDisambigs {
		
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
				'User:Hgzbot/Service/TopLinkedDisambigs/config.json',
				'application/json'
			);
			$this->config = json_decode( $json, true );
		}		
		
		protected function outputList() {
			$t1  = 'SELECT ltd.lt_title AS title, COUNT(pld.pl_from) AS count';
			$t1 .= ' FROM pagelinks pld';
			$t1 .= ' INNER JOIN linktarget ltd ON (pld.pl_target_id = ltd.lt_id)';
			$t1 .= ' INNER JOIN page pd ON (pd.page_title = ltd.lt_title AND pd.page_namespace = ltd.lt_namespace)';
			$t1 .= ' LEFT JOIN page ps ON (ps.page_id = pld.pl_from)';
			$t1 .= ' LEFT JOIN linktarget lts ON (lts.lt_title = ps.page_title AND lts.lt_namespace = 0)';
			$t1 .= ' LEFT JOIN pagelinks pls ON (pls.pl_target_id = lts.lt_id AND pls.pl_from_namespace = 0 AND ps.page_is_redirect = 1)';
			$t1 .= ' LEFT JOIN page plsp ON (plsp.page_id = pls.pl_from)';
			$t1 .= ' INNER JOIN page_props pp ON (pp.pp_page = pd.page_id AND pp.pp_propname = \'disambiguation\')';
			$t1 .= ' WHERE ltd.lt_namespace = 0';
			$t1 .= ' AND pld.pl_from_namespace = 0';
			$t1 .= ' AND NOT EXISTS(';
			$t1 .= '   SELECT pps.pp_page';
			$t1 .= '   FROM page_props pps';
			$t1 .= '   WHERE (pps.pp_page = ps.page_id)';
			$t1 .= '   AND pps.pp_propname = \'disambiguation\'';
			$t1 .= ' )';
			$t1 .= ' AND NOT EXISTS(';
			$t1 .= '   SELECT pps.pp_page';
			$t1 .= '   FROM page_props pps';
			$t1 .= '   WHERE (pps.pp_page = plsp.page_id)';
			$t1 .= '   AND pps.pp_propname = \'disambiguation\'';
			$t1 .= ' )';
			$t1 .= ' GROUP BY ltd.lt_title';
			$t1 .= ' ORDER BY COUNT(pld.pl_from) DESC, ltd.lt_title';
			$t1 .= ' LIMIT ?';
			$q1 = $this->db->executeDBQuery( $t1, 'i', (int)$this->config['config']['limit'] );
			$r1 = phpWBFdatabase::fetchDBQueryResult( $q1 );
			
			$counttotal = 0;
			$output = "\n";
			foreach ( $r1 as $l1 ) {
				$output .= str_replace(
					['$1', '$2'],
					[str_replace('_', ' ', $l1['title']), $l1['count']],
					$this->config['config']['output']
				);
				$output .= "\n";
				$counttotal = $counttotal + $l1['count'];
			}
			$q1->close();
			
			$newtext = $this->mw->getWikitext( $this->config['config']['target'] );
			$newtext = preg_replace(
				'/<!--\s*HGZ_TLD_START\s*-->(.+)<!--\s*HGZ_TLD_END\s*-->/s', '<!-- HGZ_TLD_START -->' . $output . '<!-- HGZ_TLD_END -->',
				$newtext,
				1
			);
			
			$this->mw->editPage(
				$this->config['config']['target'],
				$newtext,
				str_replace(
					'$1',
					$counttotal,
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
	
	$instance = new hgzTopLinkedDisambigs();

?>