#!/usr/bin/php
<?php
	require_once('phpWBF.php');
	
	class hgzPurger {
		
		protected $mw;
				
		protected $config = [];
		
		public function __construct() {
			$this->mw = new phpWBF( 'de.wikipedia.org' );
			
			$this->mw->loginProfile( 'Hgzbot' );
			
			$this->run();
		}
		
		public function __destruct() {
			unset( $this->mw );
		}
		
		protected function getConfig() {
			
			$json = $this->mw->mwRawGet(
				'User:Hgzbot/Service/Purger/config.json',
				'application/json'
			);
			$this->config = json_decode( $json, true );
		}
		
		protected function purgeGroup( $frequency, $type ) {
			if ( !isset( $this->config['config']['pages'][$frequency][$type] ) || count( $this->config['config']['pages'][$frequency][$type] ) == 0 ) {
				return;
			}
			
			$titles = $this->config['config']['pages'][$frequency][$type];
			$forcelink = false;
			$forcerecursivelink = false;
						
			if ( $type === 'forcelink' ) {
				$forcelink = true;
			}
			if ( $type === 'forcerecursivelink' ) {
				$forcerecursivelink = true;
			}
			
			$this->mw->purge($titles, $forcelink, $forcerecursivelink);
		}
		
		protected function purgePages() {
			$frequency = $_SERVER['argv'][1];
			
			if ( !isset( $this->config['config']['pages'][$frequency] ) ) {
				return;
			}
			
			$this->purgeGroup( $frequency, 'standard' );           // standard
			$this->purgeGroup( $frequency, 'forcelink' );          // forcelink
			$this->purgeGroup( $frequency, 'forcerecursivelink' ); // forcerecursivelink
			
		}
		
		protected function run() {
			$this->getConfig();
						
			if ( $this->config['enabled'] === true ) {
				$this->purgePages();
			}
			
			$this->mw->logoutUser();
		}
	
	}
		
	$instance = new hgzPurger();
	
?>