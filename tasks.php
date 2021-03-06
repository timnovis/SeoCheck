<?php
if( empty( $_POST ) ) {
	return;
} else {
	$get = $_GET;
	$post = $_POST;
	$cron = $post['cron'];
}

if (is_null($cron)) {
	$cron = $get['cron'];
}

if( $cron == 'auto' ) {
	require 'config/config.php';
	require 'class/controller.php';
	require 'class/robots.php';
	require 'class/mailManager.php';
	require 'class/dbController.php';

	$database = new DbController();

	if( !file_exists( __DIR__ . '/config/.dbConfig' ) ) {
		require 'config/setup.php';
		createTables( $database );
	} else {

		$controller = new Controller();
		$mm = new MailManager();
		$rows = $database->get_field();
		$prefix = constant( 'PREFIX' );
		if( $rows ) {
			foreach ( $rows as $result ) {
				if( $controller->is_404($result->url) ) {
					return false;
				} else {
					//Checks to take place on each row of the database
					$robot = new Robots( $result->url );
					$crawlable = $robot->isOkToCrawl( '/' );
					$ga = $controller->getAnalytics( $result->url );
					$indexError = $controller->indexError(  $controller->strtoboo( $result->indexNeeded ), $crawlable );
					$gaCheck = $controller->checkGA( $result, $ga );
					$gaPrev = $controller->strtoboo( $result->gaPreviousState );
					$indexPrev = $controller->strtoboo( $result->indexPreviousState );

					if( !$gaCheck !== $gaPrev) {
						if( !$gaCheck ){
							$database->runQuery( "UPDATE {$prefix}siteslist SET gaPreviousState = 1 WHERE id = $result->id" );
						} else {
							$database->runQuery( "UPDATE {$prefix}siteslist SET gaPreviousState = 0 WHERE id = $result->id" );
						}
					}

					if( $indexError !== $indexPrev) {
						if( $indexError ){
							$database->runQuery( "UPDATE {$prefix}siteslist SET indexPreviousState = 1 WHERE id = $result->id" );
						} else {
							$database->runQuery( "UPDATE {$prefix}siteslist SET indexPreviousState = 0 WHERE id = $result->id" );
						}
					}

					// If either of the critera are throwing an error
					// send an email with errors in
					if( !$gaCheck || $indexError) {
						if( HTML_EMAIL ) {
							$mm->send_email_html( $result, $ga, $gaCheck, $indexError );
						} else {
							$mm->send_email_plain( $result, $ga, $gaCheck, $indexError );
						}
						if( NOTIFY_SLACK ) {
							$mm->send_slack_msg( $result, $ga, $gaCheck, $indexError );
						}
					}
				}

			}
		}
	}
} elseif( $cron == 'addSite' ) {
	require 'config/config.php';
	require 'class/dbController.php';

	$prefix = constant( 'PREFIX' );
	$conn = new DbController();
	$siteName = $post['siteName'];
	$siteUrl = urldecode($post['siteUrl']);
	$siteGa = $post['siteGa'];
	$siteIndexable = $post['siteIndexable'];
	if( $siteIndexable == 'on' ) {
		$indexStatus = 1;
	} else {
		$indexStatus = 0;
	}
	$conn->runQuery( "INSERT INTO `{$prefix}siteslist` (
		`title`,
		`url`,
		`ga_code`,
		`indexNeeded`
		) VALUES ('$siteName', '$siteUrl', '$siteGa', '$indexStatus');" );
	die();
}
