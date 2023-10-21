#!/usr/bin/php
<?php
	require_once('phpWBF.php');
	require_once('database.phpWBF.php');
	
	class hgzUnreviewedPages {
		
		protected $mw;
		protected $db;
				
		protected $config = [];
		
		public function __construct() {
			$this->mw = new phpWBF( 'de.wikipedia.org' );
			$this->db = new phpWBFdatabase();
			
			$this->mw->loginProfile( 'Hgzbot' );
			
			$this->run();
		}
		
		public function __destruct() {
			unset( $this->mw );
		}
		
		protected function getConfig() {
			
			$json = $this->mw->mwRawGet(
				'User:Hgzbot/Service/UnreviewedPages/config.json',
				'application/json'
			);
			$this->config = json_decode( $json, true );
			
		}
		
		protected function getUnreviewedPages() {
			$limit = $this->config['config']['maxEntries'];
			
			$output = "\r\n";
			$count  = 0;
			$pages  = [];
			
			$t1  = '';
			$t1 .= 'SELECT page_title AS title, rev_timestamp AS since, page_is_redirect AS redir';
			$t1 .= '  FROM page p';
			$t1 .= '    INNER JOIN revision ON rev_page = page_id';
			$t1 .= '  WHERE page_namespace = 0';
			$t1 .= '    AND rev_parent_id = 0';
			$t1 .= '    AND NOT EXISTS (';
			$t1 .= '      SELECT fp_page_id FROM flaggedpages WHERE fp_page_id = p.page_id';
			$t1 .= '    )';
			$t1 .= '  ORDER BY page_is_redirect, rev_timestamp';
			$q1 = $this->db->executeDBQuery( $t1 );
			$r1 = phpWBFdatabase::fetchDBQueryResult( $q1 );
			foreach ( $r1 as $l1 ) {
				$add = [];
				$add['title'] = str_replace( '_', ' ', $l1['title'] );
				$add['since'] = $l1['since'];
				$add['redir'] = $l1['redir'];
				$pages[] = $add;
			}
			$q1->close();
			
			foreach ( $pages as $page ) {
				$since = DateTime::createFromFormat( 'YmdHis', $page['since'] );
				$now   = new DateTime();
				$diff  = $since->diff( $now );
				$text  = $diff->format( $this->config['config']['pendingSinceFormat'] );				
				
				$output .= str_replace(
					['$1', '$2'],
					[$page['title'], $text], 
					$this->config['config']['outputPage']
				);
				
				if ( $page['redir'] == 1 ) {
					$output .= ' ' . $this->config['config']['outputRedirect'];
				}
				
				$output .= "\r\n";
				$count++;
				if ( $count == $limit ) {
					break;
				}
			}
			
			$newtext = $this->mw->getWikitext( $this->config['config']['target'] );
			$newtext = preg_replace(
				'/<!--\s*HGZ_URP_START\s*-->(.+)<!--\s*HGZ_URP_END\s*-->/s', '<!-- HGZ_URP_START -->' . $output . '<!-- HGZ_URP_END -->',
				$newtext,
				1
			);
			$newtext = preg_replace(
				'/<!--\s*HGZ_URP_UPDATE\s*-->(.+)<!--\s*HGZ_URP_UPDATE\s*-->/s', '<!-- HGZ_URP_UPDATE -->' . date( 'H:i, d.m.Y', time() ) . '<!-- HGZ_URP_UPDATE -->',
				$newtext,
				1
			);
			$newtext = preg_replace(
				'/<!--\s*HGZ_URP_COUNT\s*-->(.+)<!--\s*HGZ_URP_COUNT\s*-->/s', '<!-- HGZ_URP_COUNT -->' . $count . '<!-- HGZ_URP_COUNT -->',
				$newtext,
				1
			);
						
			$this->mw->editPage(
				$this->config['config']['target'],
				$newtext,
				str_replace(
					['$1', '$2'],
					[$count, count( $pages )],
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
				
				$this->getUnreviewedPages();
			}
			
			$this->mw->logoutUser();
		}
	
	}
	
	$instance = new HgzUnreviewedPages();
	
?>