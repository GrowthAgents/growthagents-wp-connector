<?php
/**
 * Uninstall cleanup.
 *
 * Removes this plugin's own options only. Deliberately does NOT delete the
 * 'growthagents' WordPress user or anything it published — that user is an
 * ordinary Editor and its posts belong to the site regardless of whether
 * this plugin is installed.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'growthagents_connector_token' );
delete_option( 'growthagents_connector_user_id' );
