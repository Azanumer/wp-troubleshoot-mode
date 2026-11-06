<?php
/**
 * Uninstall WP Troubleshoot Mode.
 *
 * The plugin stores no options and no tables — safe mode lives only in a
 * browser cookie, which expires on its own. Nothing to clean up.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
