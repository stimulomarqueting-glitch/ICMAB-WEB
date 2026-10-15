<?php
/**
 * admin module QA: forgets "No volver a mostrar" of the Spanish-pack notice
 * for the administrator (user 1) and says whether es_ES is installed.
 *
 * @package matsurfer-qa
 */

delete_user_meta( 1, 'mats_hide_es_notice' );
echo wp_json_encode( array( 'es' => mats_admin_spanish_available() ) );
