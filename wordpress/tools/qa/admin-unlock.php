<?php
/**
 * admin module QA: releases every post edit lock (so the next user does
 * not get the "already being edited" dialog).
 *
 * @package matsurfer-qa
 */
delete_post_meta_by_key( '_edit_lock' );
echo "locks released\n";
