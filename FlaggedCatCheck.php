#!/usr/bin/php
<?php
	require_once('phpWBF.php');
	require_once('database.phpWBF.php');
	
	class hgzFlaggedCatCheck {
		
		protected $mw;
		protected $db;
		
		protected $customers = [];
		
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
				'User:Hgzbot/Service/FlaggedCatCheck/config.json',
				'application/json'
			);
			$this->config = json_decode( $json, true );
			
		}
		
		protected function getCustomers() {
			
			// get transclusions of template
			try {
				$embd = $this->mw->getEmbeddingsContent(
					$this->config['config']['transcludes'],
					[]
				);
			} catch (Exception $e) {
				throw new Exception( 'Error: No transclusions of template found.' );
			}
			
			foreach ($embd as $item) {
				$text = $item['text'];
				
				// categories to use
				$match = [];
				if ( preg_match( '/HGZ_FCC_CATEGORIES\s*\=\s*([^\n\|]+)/', $text, $match ) == 1 ) {
					$cnfCatText = $match[1];
				} else {
					$this->mw->log( 'ERROR: Customer ' . $item['title'] . ': category pattern not found' );
					continue;
				}
				
				// frequency
				$match = [];
				if ( preg_match( '/HGZ_FCC_FREQUENCY\s*\=\s*(\d{1,})/', $text, $match ) == 1 ) {
					$cnfFrequency = $match[1];
				} else {
					$cnfFrequency = '24';
				}

				// last visit
				$match = [];
				if ( preg_match( '/HGZ_FCC_LASTVISIT\s*\=\s*(\d{10})/', $text, $match ) == 1 ) {
					$cnfLastVisit = $match[1] . '00';
				} else {
					$cnfLastVisit = '200000000000';
				}
				
				// check last visited/frequency
				if ( !isset( $_SERVER['argv'][1] ) || $_SERVER['argv'][1] !== 'forceupdate' ) {
					$dtNow  = time();
					$dtLast = DateTime::createFromFormat( 'YmdHi', $cnfLastVisit );
					$dtNext = strtotime( '+ ' . $cnfFrequency . ' hours', $dtLast->getTimestamp() );
					if ( $dtNow <= $dtNext ) {
						continue;
					}
				}
				
				// split categories
				$cnfCategories = [];
				$match = [];
				if ( preg_match_all( '/([^\n\|\{#]+)(\{(\d{1,2})\})?\#?/', $cnfCatText, $match, PREG_SET_ORDER ) > 0 ) {
					foreach ( $match as $m ) {
						if ( !isset( $m[1] ) ) {
							continue;
						}
						$cnfCategories[] = str_replace( ' ', '_', $m[1] );
					}
				} else {
					$this->mw->log( 'ERROR: Customer ' . $item['title'] . ': invalid category pattern' );
					continue;
				}
								
				$this->customers[] = [
					'title'      => $item['title'],
					'frequency'  => $cnfFrequency,
					'last'       => $cnfLastVisit,
					'categories' => $cnfCategories
				];
			}

			$this->mw->log( 'Found ' . count($this->customers) . ' customers.' );
			
		}
		
		protected function processCustomers() {
			$limit = $this->config['config']['maxEntries'];
			
			foreach ( $this->customers as $customer ) {
				$pages  = [];
				$output = "\r\n";
				$count  = 0;
				
				$this->mw->log( 'Processing ' . $customer['title'] . '... pages from categories...' );
				
				foreach ( $customer['categories'] as $cat ) {
					// unreviewed pages
					$t1  = '';
					$t1 .= 'WITH RECURSIVE Cat AS (';
					$t1 .= '  SELECT page_title, page_id';
					$t1 .= '    FROM page';
					$t1 .= '    WHERE page_title = ?';
					$t1 .= '      AND page_namespace = 14';
					$t1 .= '  UNION';
					$t1 .= '  SELECT Subcat.page_title, Subcat.page_id';
					$t1 .= '    FROM page AS Subcat, categorylinks, Cat';
					$t1 .= '    WHERE Subcat.page_namespace = 14';
					$t1 .= '      AND cl_from = Subcat.page_id';
					$t1 .= '      AND cl_to = Cat.page_title';
					$t1 .= '      AND cl_type = "subcat"';
					$t1 .= ')';
					$t1 .= 'SELECT DISTINCT Art.page_title AS "page"';
					$t1 .= '  FROM Cat';
					$t1 .= '  INNER JOIN categorylinks AS Catlinks ON Cat.page_title = Catlinks.cl_to';
					$t1 .= '  INNER JOIN page AS Art ON Catlinks.cl_from = Art.page_id';
					$t1 .= '  WHERE Art.page_namespace = 0';
					$t1 .= '   AND NOT EXISTS (';
					$t1 .= '     SELECT fp_page_id FROM flaggedpages WHERE fp_page_id = Art.page_id';
					$t1 .= '   )';
					$q1 = $this->db->executeDBQuery( $t1, 's', $cat );
					$r1 = phpWBFdatabase::fetchDBQueryResult( $q1 );
					foreach ( $r1 as $l1 ) {
						$add = [];
						$add['title'] = str_replace( '_', ' ', $l1['page'] );
						$add['since'] = false;
						$pages[] = $add;
					}
					$q1->close();
					
					// pending changes
					$t2 = '';
					$t2 .= 'WITH RECURSIVE Cat AS (';
					$t2 .= '  SELECT page_title, page_id';
					$t2 .= '    FROM page';
					$t2 .= '    WHERE page_title = ?';
					$t2 .= '      AND page_namespace = 14';
					$t2 .= '  UNION';
					$t2 .= '  SELECT Subcat.page_title, Subcat.page_id';
					$t2 .= '    FROM page AS Subcat, categorylinks, Cat';
					$t2 .= '    WHERE Subcat.page_namespace = 14';
					$t2 .= '      AND cl_from = Subcat.page_id';
					$t2 .= '      AND cl_to = Cat.page_title';
					$t2 .= '      AND cl_type = "subcat"';
					$t2 .= ')';
					$t2 .= 'SELECT DISTINCT Art.page_title AS "page", Flag.fp_pending_since AS "since"';
					$t2 .= '  FROM Cat';
					$t2 .= '  INNER JOIN categorylinks AS Catlinks ON Cat.page_title = Catlinks.cl_to';
					$t2 .= '  INNER JOIN page AS Art ON Catlinks.cl_from = Art.page_id';
					$t2 .= '  INNER JOIN flaggedpages AS Flag ON Flag.fp_page_id = Art.page_id';
					$t2 .= '  WHERE Art.page_namespace = 0';
					$t2 .= '    AND Flag.fp_pending_since IS NOT NULL';
					$t2 .= ' ORDER BY since';
					$q2 = $this->db->executeDBQuery( $t2, 's', $cat );
					$r2 = phpWBFdatabase::fetchDBQueryResult( $q2 );
					foreach ( $r2 as $l2 ) {
						$add = [];
						$add['title'] = str_replace( '_', ' ', $l2['page'] );
						$add['since'] = $l2['since'];
						$pages[] = $add;
					}
					$q2->close();
				}
				
				$pages = $this->mw->uniqueMultidimArray($pages, 'title');
				$pages = $this->mw->sortMultidimArray($pages, 'since', SORT_ASC);				

				$this->mw->log( 'Processing ' . $customer['title'] . '... get output...' );
				
				foreach ( $pages as $page ) {
					$output .= str_replace( '$1', $page['title'], $this->config['config']['outputPage'] ) . ' ';
					if ($page['since'] === false) {
						$output .= $this->config['config']['outputUnreviewed'];
					} else {
						$since = DateTime::createFromFormat( 'YmdHis', $page['since'] );
						$now   = new DateTime();
						$diff  = $since->diff( $now );
						$text  = $diff->format( $this->config['config']['pendingSinceFormat'] );
						
						$output .= str_replace(
							['$1', '$2'],
							[$page['title'], $text],
							$this->config['config']['outputPending']
						);
					}
					$output .= "\r\n";
					$count++;
					if ( $count == $limit ) {
						break;
					}
				}

				$this->mw->log( 'Processing ' . $customer['title'] . '... update page...' );
				
				$newtext = $this->mw->getWikitext( $customer['title'] );
				$newtext = preg_replace(
					'/<!--\s*HGZ_FCC_START\s*-->(.+)<!--\s*HGZ_FCC_END\s*-->/s', '<!-- HGZ_FCC_START -->' . $output . '<!-- HGZ_FCC_END -->',
					$newtext,
					1
				);
				$newtext = preg_replace(
					'/HGZ_FCC_LASTVISIT\s*\=\s*([^\n\|\}])+/', 'HGZ_FCC_LASTVISIT = ' . date('YmdHi', time()),
					$newtext,
					1
				);
				
				$this->mw->editPage(
					$customer['title'],
					$newtext,
					str_replace(
						['$1', '$2'],
						[$count, count( $pages )],
						$this->config['config']['summary']
					)
				);
				
				unset( $pages );
				
			}
		}
		
		protected function run() {
			$this->getConfig();
			
			if ( $this->config['enabled'] === true ) {
				$this->db->connectWMCSReplica(
					$this->config['connect']['cluster'],
					$this->config['connect']['database']
				);
				
				$this->getCustomers();
				$this->processCustomers();
			}
			
			$this->mw->logoutUser();
		}
	
	}
	
	$instance = new HgzFlaggedCatCheck();
	
?>