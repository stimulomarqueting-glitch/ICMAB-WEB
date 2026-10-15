<?php
/**
 * Setup step 2 — languages (Polylang) and WordPress basics.
 *
 *  - Polylang languages: English (en, en_GB, flag gb, order 0, default) and
 *    Español (es, es_ES, flag es, order 1)
 *  - Polylang options: language from the directory (/es/), no /en/ for the
 *    default language, no browser detection, no media translation
 *  - existing content without a language → English
 *  - WordPress: permalinks /%postname%/, Europe/Madrid, "j F Y", Monday
 *    first, comments and pings closed, tagline = brand claim, site title
 *    'MatSurfer' only while it is still a WordPress default
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

/** The two languages, as Polylang expects them. */
function mats_installer_language_specs(): array {
	return array(
		'en' => array(
			'name'       => 'English',
			'slug'       => 'en',
			'locale'     => 'en_GB',
			'rtl'        => false,
			'term_group' => 0,
			'flag'       => 'gb',
		),
		'es' => array(
			'name'       => 'Español',
			'slug'       => 'es',
			'locale'     => 'es_ES',
			'rtl'        => false,
			'term_group' => 1,
			'flag'       => 'es',
		),
	);
}

/**
 * Polylang options, in the order they must be set (some depend on others).
 *
 * 'redirect_lang' is Polylang's "Remove the page name or page id from the
 * URL of the front page": it MUST be true for the Spanish front page to live
 * at /es/ (like the original site). With false, Polylang publishes it at
 * /es/inicio/ and /es/ answers with a 301 redirect there.
 */
function mats_installer_polylang_options(): array {
	return array(
		'force_lang'    => 1,
		'rewrite'       => true,
		'hide_default'  => true,
		'default_lang'  => 'en',
		'browser'       => false,
		'redirect_lang' => true,
		'media_support' => false,
	);
}

/** WordPress options the site needs (permalinks are handled apart). */
function mats_installer_wp_options(): array {
	return array(
		'timezone_string'        => 'Europe/Madrid',
		'date_format'            => 'j F Y',
		'start_of_week'          => 1,
		'default_comment_status' => 'closed',
		'default_ping_status'    => 'closed',
	);
}

/** Site titles that are WordPress/host defaults (safe to replace). */
function mats_installer_default_titles(): array {
	return array( '', 'wordpress', 'my wordpress', 'my wordpress website', 'my wordpress site', 'my wordpress blog', 'my blog', 'my site', 'my website', 'mi wordpress', 'mi sitio', 'mi sitio web', 'mi blog', 'mi web', 'just another wordpress site' );
}

function mats_installer_default_taglines(): array {
	return array( '', 'just another wordpress site', 'otro sitio realizado con wordpress', 'otro sitio de wordpress' );
}

/** Current Polylang option value (array access works on every 3.x version). */
function mats_installer_pll_option( string $key ) {
	if ( mats_installer_pll_ready() && isset( PLL()->options ) ) {
		$options = PLL()->options;
		if ( is_object( $options ) && method_exists( $options, 'get' ) ) {
			return $options->get( $key );
		}
		if ( is_array( $options ) || $options instanceof ArrayAccess ) {
			return $options[ $key ] ?? null;
		}
	}
	$raw = get_option( 'polylang', array() );
	return is_array( $raw ) ? ( $raw[ $key ] ?? null ) : null;
}

/** Adds or adjusts the two languages. */
function mats_installer_setup_pll_languages( array &$result ): bool {
	$model = PLL()->model;
	$ok    = true;
	foreach ( mats_installer_language_specs() as $slug => $spec ) {
		$lang = mats_installer_pll_language( $slug );
		if ( ! $lang ) {
			if ( isset( $model->languages ) && method_exists( $model->languages, 'add' ) ) {
				$added = $model->languages->add( $spec );
			} elseif ( is_callable( array( $model, 'add_language' ) ) ) {
				$added = $model->add_language( $spec ); // Polylang < 3.7
			} else {
				$added = new WP_Error( 'mats_pll_api', 'esta versión de Polylang no permite añadir idiomas desde el tema' );
			}
			if ( is_wp_error( $added ) || false === $added ) {
				$ok = false;
				mats_installer_add( $result, 'error', sprintf( 'No se ha podido añadir el idioma %1$s: %2$s', $spec['name'], is_wp_error( $added ) ? wp_strip_all_tags( $added->get_error_message() ) : 'error desconocido' ), admin_url( 'admin.php?page=mlang' ), 'Abrir Idiomas' );
				continue;
			}
			if ( method_exists( $model, 'clean_languages_cache' ) ) {
				$model->clean_languages_cache();
			}
			mats_installer_add( $result, 'ok', sprintf( 'Idioma %1$s añadido (%2$s).', $spec['name'], 'en' === $slug ? 'idioma principal, en la dirección raíz de la web' : 'en las direcciones que empiezan por /es/' ) );
			/* Polylang finishes setting up new languages on the next page load */
			$result['reload'] = true;
			mats_installer_flag_activation( 'language:' . $slug );
			continue;
		}

		$diff = array();
		if ( $lang->locale !== $spec['locale'] ) {
			$diff['locale'] = $spec['locale'];
		}
		if ( (int) $lang->term_group !== (int) $spec['term_group'] ) {
			$diff['term_group'] = $spec['term_group'];
		}
		if ( ( $lang->flag_code ?? '' ) !== $spec['flag'] ) {
			$diff['flag'] = $spec['flag'];
		}
		if ( ! $diff ) {
			mats_installer_add( $result, 'ok', sprintf( 'El idioma %s ya estaba configurado.', $spec['name'] ) );
			continue;
		}
		$args = array_merge( array( 'lang_id' => (int) $lang->term_id ), $diff );
		if ( isset( $model->languages ) && method_exists( $model->languages, 'update' ) ) {
			$updated = $model->languages->update( $args );
		} elseif ( is_callable( array( $model, 'update_language' ) ) ) {
			$updated = $model->update_language( $args );
		} else {
			$updated = new WP_Error( 'mats_pll_api', 'esta versión de Polylang no permite cambiar idiomas desde el tema' );
		}
		if ( is_wp_error( $updated ) || false === $updated ) {
			mats_installer_add( $result, 'warn', sprintf( 'El idioma %s ya existía pero no se ha podido ajustar (código regional, bandera u orden). Revísalo en Idiomas.', $spec['name'] ), admin_url( 'admin.php?page=mlang' ), 'Abrir Idiomas' );
		} else {
			mats_installer_add( $result, 'ok', sprintf( 'El idioma %s ya existía: ajustado a la configuración de la web.', $spec['name'] ) );
		}
		if ( method_exists( $model, 'clean_languages_cache' ) ) {
			$model->clean_languages_cache();
		}
	}

	/* languages the theme does not use */
	foreach ( (array) $model->get_languages_list() as $lang ) {
		if ( ! in_array( $lang->slug, mats_langs(), true ) ) {
			mats_installer_add( $result, 'warn', sprintf( 'Polylang tiene además el idioma «%s». La web solo está preparada para inglés y español: no lo uses o bórralo en Idiomas.', $lang->name ), admin_url( 'admin.php?page=mlang' ), 'Abrir Idiomas' );
		}
	}
	return $ok;
}

/** Applies the Polylang options. */
function mats_installer_setup_pll_options( array &$result ): bool {
	$wanted  = mats_installer_polylang_options();
	$options = PLL()->options ?? null;
	$changed = false;
	$ok      = true;

	if ( is_object( $options ) && method_exists( $options, 'set' ) && method_exists( $options, 'save' ) ) {
		$languages = PLL()->model->languages ?? null;
		foreach ( $wanted as $key => $value ) {
			if ( $options->get( $key ) === $value ) {
				continue;
			}
			if ( 'default_lang' === $key && is_object( $languages ) && method_exists( $languages, 'update_default' ) ) {
				/* also moves the default category and menus to the new default language */
				$errors = $languages->update_default( $value );
			} else {
				$errors = $options->set( $key, $value );
			}
			if ( is_wp_error( $errors ) && $errors->has_errors() && $options->get( $key ) !== $value ) {
				$ok = false;
				mats_installer_add( $result, 'warn', sprintf( 'No se ha podido aplicar el ajuste «%1$s» de Polylang: %2$s', $key, wp_strip_all_tags( $errors->get_error_message() ) ) );
				continue;
			}
			$changed = true;
		}
		if ( $changed ) {
			$options->save();
		}
	} else {
		/* Polylang < 3.7: plain option array */
		$raw = get_option( 'polylang', array() );
		$raw = is_array( $raw ) ? $raw : array();
		foreach ( $wanted as $key => $value ) {
			if ( ( $raw[ $key ] ?? null ) !== $value ) {
				$raw[ $key ] = $value;
				$changed     = true;
			}
		}
		if ( $changed ) {
			update_option( 'polylang', $raw );
		}
	}

	mats_installer_add(
		$result,
		'ok',
		$changed
			? 'Ajustes de Polylang aplicados: inglés en la raíz (sin /en/), español en /es/ (también su portada), sin detección automática del idioma del navegador.'
			: 'Los ajustes de Polylang ya eran los correctos.'
	);
	return $ok;
}

/** Content created before Polylang existed → English. */
function mats_installer_assign_default_language( array &$result ): void {
	$model = PLL()->model;
	$en    = mats_installer_pll_language( 'en' );
	if ( ! $en || ! method_exists( $model, 'set_language_in_mass' ) ) {
		return;
	}
	$before = method_exists( $model, 'get_objects_with_no_lang' ) ? $model->get_objects_with_no_lang( 1000 ) : array();
	$count  = 0;
	foreach ( (array) $before as $ids ) {
		$count += is_array( $ids ) ? count( $ids ) : 0;
	}
	$model->set_language_in_mass( $en );
	if ( $count ) {
		mats_installer_add( $result, 'ok', sprintf( 'El contenido que ya existía (%d elementos entre páginas, entradas y categorías) queda marcado como inglés.', $count ) );
	}
}

/** Spanish translation of the tagline in Polylang's string translations. */
function mats_installer_translate_tagline( string $en, string $es ): void {
	if ( ! class_exists( 'PLL_MO' ) || '' === $en || '' === $es ) {
		return;
	}
	$lang = mats_installer_pll_language( 'es' );
	if ( ! $lang ) {
		return;
	}
	$mo = new PLL_MO();
	$mo->import_from_db( $lang );
	$current = method_exists( $mo, 'translate_if_any' ) ? (string) $mo->translate_if_any( $en ) : '';
	if ( '' !== $current && $current !== $en ) {
		return; // somebody already translated it: keep theirs (Polylang itself stores untranslated strings as source = translation)
	}
	$mo->add_entry( $mo->make_entry( $en, $es ) );
	$mo->export_to_db( $lang );
}

/** Permalinks, time zone, formats, comments, title and tagline. */
function mats_installer_setup_wp_options( array &$result ): void {
	global $wp_rewrite;

	if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		mats_installer_add( $result, 'ok', 'Direcciones de las páginas: ahora son limpias, como /product/ o /es/producto/.' );
	}
	$changed = array();
	foreach ( mats_installer_wp_options() as $name => $value ) {
		if ( (string) get_option( $name ) !== (string) $value ) {
			update_option( $name, $value );
			$changed[] = $name;
		}
	}
	if ( in_array( 'timezone_string', $changed, true ) ) {
		update_option( 'gmt_offset', '' );
	}
	if ( $changed ) {
		mats_installer_add( $result, 'ok', 'Ajustes de WordPress aplicados: zona horaria de Madrid, fechas como «15 octubre 2026», la semana empieza en lunes y los comentarios están cerrados.' );
	}

	$brand = mats_content_data()['brand'] ?? array();
	$name  = (string) ( $brand['projectName'] ?? 'MatSurfer' );
	if ( in_array( mb_strtolower( trim( (string) get_option( 'blogname' ) ) ), mats_installer_default_titles(), true ) ) {
		update_option( 'blogname', $name );
		mats_installer_add( $result, 'ok', sprintf( 'Título de la web: «%s»', $name ) );
	}
	$claim = (string) ( $brand['claim'] ?? '' );
	if ( $claim && in_array( mb_strtolower( trim( (string) get_option( 'blogdescription' ) ) ), mats_installer_default_taglines(), true ) ) {
		update_option( 'blogdescription', $claim );
		mats_installer_add( $result, 'ok', sprintf( 'Descripción corta (lema) de la web: «%s»', $claim ) );
	}
	if ( mats_installer_pll_ready() && get_option( 'blogdescription' ) === $claim ) {
		mats_installer_translate_tagline( $claim, (string) ( $brand['claimEs'] ?? '' ) );
	}
}

/** New URLs take effect: rules are rebuilt (and .htaccess updated where it is used). */
function mats_installer_flush_rewrites(): void {
	flush_rewrite_rules( true );
	/* rebuilt again on the next request, with Polylang's final settings loaded */
	delete_option( 'rewrite_rules' );
}

/** Does this .htaccess file contain WordPress's rewrite rules? */
function mats_installer_htaccess_has_rules( string $file ): bool {
	if ( ! is_readable( $file ) || ! function_exists( 'extract_from_markers' ) ) {
		return false;
	}
	foreach ( (array) extract_from_markers( $file, 'WordPress' ) as $line ) {
		if ( false !== stripos( (string) $line, 'RewriteRule' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * On Apache / LiteSpeed (Hostinger) clean addresses need WordPress's rules
 * in the .htaccess file; when WordPress cannot write it, every page but the
 * home page answers "not found". Returns the file path when the rules are
 * missing, '' when all is well (or the server does not use .htaccess).
 */
function mats_installer_htaccess_problem(): string {
	global $is_apache;
	if ( empty( $is_apache ) || is_multisite() || '' === (string) get_option( 'permalink_structure' ) ) {
		return '';
	}
	if ( ! function_exists( 'get_home_path' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	if ( ! function_exists( 'extract_from_markers' ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}
	$file = get_home_path() . '.htaccess';
	return mats_installer_htaccess_has_rules( $file ) ? '' : $file;
}

/* ── WordPress admin in Spanish ──────────────────────────────────────── */

/** Locale of the WordPress admin the staff will use. */
function mats_installer_admin_locale(): string {
	return 'es_ES';
}

/** wordpress.org plugins whose Spanish translations are installed (by key of mats_installer_plugin_specs()). */
function mats_installer_translated_plugins(): array {
	return array( 'polylang', 'wpforms', 'smtp' );
}

/**
 * Is the site language a WordPress default (English, nobody chose it)? Only
 * then is it changed to Spanish: a language somebody picked is respected.
 */
function mats_installer_site_language_is_default(): bool {
	return in_array( (string) get_option( 'WPLANG' ), array( '', 'en_US' ), true );
}

/** Why the Spanish pack could not be downloaded, as a sentence for the message. */
function mats_installer_langpack_reason( string $why ): string {
	$reasons = array(
		'config'  => 'esta instalación de WordPress no permite descargar archivos desde el escritorio (está bloqueado en su configuración)',
		'folder'  => 'el servidor no deja que WordPress escriba en la carpeta de idiomas (wp-content/languages)',
		'blocked' => 'este servidor tiene bloqueadas las conexiones con wordpress.org',
		'network' => 'no se ha podido conectar con wordpress.org (puede ser un corte momentáneo o que el servidor no tenga salida a internet)',
	);
	return $reasons[ $why ] ?? $reasons['network'];
}

/**
 * Spanish translations of the plugins the site uses (Polylang, WPForms
 * Lite, WP Mail SMTP when installed), straight from wordpress.org.
 * Returns the names of the plugins whose translation was installed now.
 */
function mats_installer_install_plugin_translations( array &$result, string $locale ): array {
	require_once ABSPATH . 'wp-admin/includes/translation-install.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	$installed    = wp_get_installed_translations( 'plugins' );
	$plugins      = get_plugins();
	$updates      = array();
	$names        = array();
	$already      = array();
	$failed       = array();
	foreach ( mats_installer_translated_plugins() as $key ) {
		$spec = mats_installer_plugin_specs()[ $key ] ?? null;
		$file = $spec ? mats_installer_plugin_state( $key )['file'] : '';
		/* only the wordpress.org version (folder = its slug); Pro versions bring their own translations */
		if ( ! $file || dirname( $file ) !== $spec['slug'] ) {
			continue;
		}
		if ( isset( $installed[ $spec['slug'] ][ $locale ] ) ) {
			$already[] = $spec['name'];
			continue;
		}
		$version = (string) ( $plugins[ $file ]['Version'] ?? '' );
		$api     = mats_installer_quietly(
			static fn() => translations_api(
				'plugins',
				array(
					'slug'    => $spec['slug'],
					'version' => $version,
				)
			)
		);
		$found = null;
		if ( ! is_wp_error( $api ) ) {
			foreach ( (array) ( $api['translations'] ?? array() ) as $translation ) {
				if ( $locale === ( $translation['language'] ?? '' ) && ! empty( $translation['package'] ) ) {
					$found = $translation;
					break;
				}
			}
		}
		if ( ! $found ) {
			$failed[] = $spec['name'];
			continue;
		}
		$updates[]               = (object) array(
			'type'       => 'plugin',
			'slug'       => $spec['slug'],
			'language'   => $locale,
			'version'    => (string) ( $found['version'] ?? $version ),
			'updated'    => (string) ( $found['updated'] ?? '' ),
			'package'    => (string) $found['package'],
			'autoupdate' => true,
		);
		$names[ $spec['slug'] ] = $spec['name'];
	}

	$done = array();
	if ( $updates ) {
		$upgrader = new Language_Pack_Upgrader( new Automatic_Upgrader_Skin() );
		ob_start();
		$results = mats_installer_quietly( static fn() => $upgrader->bulk_upgrade( $updates, array( 'clear_update_cache' => false ) ) );
		ob_end_clean();
		foreach ( $updates as $i => $update ) {
			$one = is_array( $results ) ? ( $results[ $i ] ?? false ) : false;
			if ( $one && ! is_wp_error( $one ) ) {
				$done[] = $names[ $update->slug ];
			} else {
				$failed[] = $names[ $update->slug ];
			}
		}
	}

	if ( $done ) {
		mats_installer_add( $result, 'ok', sprintf( 'Traducción al español de %s instalada: sus pantallas también salen en español.', implode( ', ', $done ) ) );
	}
	if ( $already ) {
		mats_installer_add( $result, 'ok', sprintf( '%s ya tenía su traducción al español.', implode( ', ', $already ) ) );
	}
	if ( $failed ) {
		mats_installer_add( $result, 'info', sprintf( 'No se ha podido descargar ahora la traducción al español de %s. No pasa nada: WordPress la ofrecerá en Escritorio → Actualizaciones («Actualizar traducciones»).', implode( ', ', $failed ) ), admin_url( 'update-core.php' ), 'Ir a Actualizaciones' );
	}
	return $done;
}

/**
 * The WordPress admin in Spanish for the ICMAB staff: downloads WordPress's
 * Spanish language pack and the plugins' Spanish translations from
 * wordpress.org, then makes Spanish the site language (WPLANG). The public
 * site is not affected: Polylang shows each page in its own language
 * (en_GB at the root, es_ES under /es/). Never an error: without internet
 * it only explains how to do it by hand.
 */
function mats_installer_setup_admin_language( array &$result ): void {
	require_once ABSPATH . 'wp-admin/includes/translation-install.php';
	$locale = mats_installer_admin_locale();
	$have   = in_array( $locale, get_available_languages(), true );
	$why    = '';

	if ( ! $have ) {
		if ( ! wp_is_file_mod_allowed( 'download_language_pack' ) ) {
			$why = 'config';
		} elseif ( ! wp_can_install_language_pack() ) {
			$why = 'folder';
		} else {
			ob_start();
			$got = mats_installer_quietly( static fn() => wp_download_language_pack( $locale ) );
			ob_end_clean();
			$have = $locale === $got && in_array( $locale, get_available_languages(), true );
			if ( $have ) {
				mats_installer_add( $result, 'ok', 'Traducción al español de WordPress descargada de wordpress.org.' );
			} else {
				$http = function_exists( '_wp_http_get_object' ) ? _wp_http_get_object() : null;
				$why  = $http && $http->block_request( 'https://api.wordpress.org/translations/core/1.0/' ) ? 'blocked' : 'network';
			}
		}
	}

	if ( ! $have ) {
		mats_installer_add(
			$result,
			'info',
			sprintf(
				'El escritorio de WordPress sigue en inglés: no se ha podido descargar su traducción al español porque %s. No afecta a la web pública, que ya sale en inglés y en español. Para tener los menús en español, ve a Ajustes → Generales, elige «Español» en «Idioma del sitio» y pulsa «Guardar cambios» (WordPress descarga la traducción en ese momento); después repite este paso para traducir también los plugins. Si «Español» no aparece en esa lista, el servidor no puede conectar con wordpress.org: pide a Stimulo que lo revise.',
				mats_installer_langpack_reason( $why )
			),
			admin_url( 'options-general.php' ),
			'Ir a Ajustes → Generales'
		);
		return;
	}

	if ( (string) get_option( 'WPLANG' ) === $locale ) {
		mats_installer_add( $result, 'ok', 'El escritorio de WordPress ya estaba en español.' );
	} elseif ( mats_installer_site_language_is_default() ) {
		update_option( 'WPLANG', $locale );
		mats_installer_add( $result, 'ok', 'El escritorio de WordPress queda en español (menús, botones y fechas). La web pública no cambia: sigue en inglés en la raíz y en español en /es/. Cada persona puede elegir otro idioma en su perfil.' );
	} else {
		mats_installer_add( $result, 'info', sprintf( 'El idioma del escritorio de WordPress es «%s» porque alguien lo eligió así: no se cambia. Se puede cambiar en Ajustes → Generales → Idioma del sitio, y cada persona puede elegir el suyo en su perfil.', (string) get_option( 'WPLANG' ) ), admin_url( 'options-general.php' ), 'Ir a Ajustes → Generales' );
	}

	mats_installer_install_plugin_translations( $result, $locale );
}

/** ✓ when the admin is in Spanish; optional (•) otherwise: the site works the same. */
function mats_installer_admin_language_check(): array {
	$locale = mats_installer_admin_locale();
	$have   = in_array( $locale, get_available_languages(), true );
	$site   = (string) get_option( 'WPLANG' );
	if ( $have && $site === $locale ) {
		return mats_installer_check( true, 'El escritorio de WordPress está en español.' );
	}
	return mats_installer_check(
		null,
		$have
			? 'El escritorio de WordPress no está en español (opcional): se cambia en Ajustes → Generales → Idioma del sitio.'
			: 'El escritorio de WordPress está en inglés (opcional): para ponerlo en español, elige «Español» en Ajustes → Generales → Idioma del sitio y repite este paso.',
		admin_url( 'options-general.php' ),
		'Ajustes generales'
	);
}

function mats_installer_step_languages( array $opts ): array {
	$result = mats_installer_result( 'languages' );

	if ( mats_installer_needs_reload() ) {
		$result['reload']  = true;
		$result['skipped'] = true;
		mats_installer_add( $result, 'info', 'Este paso se hará en cuanto se recargue la página (Polylang se acaba de activar).' );
		return $result;
	}

	$pll = mats_installer_pll_ready();
	if ( ! $pll ) {
		mats_installer_add( $result, 'error', 'Polylang no está activo, así que la web todavía no puede tener dos idiomas. Haz primero el paso 1 (Plugins necesarios).' );
	} else {
		mats_installer_setup_pll_languages( $result );
		if ( mats_installer_pll_language( 'en' ) ) {
			mats_installer_setup_pll_options( $result );
			mats_installer_assign_default_language( $result );
		}
		if ( class_exists( 'PLL_Admin_Notices' ) && method_exists( 'PLL_Admin_Notices', 'dismiss' ) ) {
			PLL_Admin_Notices::dismiss( 'wizard' ); // its own setup assistant is not needed
		}
		delete_transient( 'pll_activation_redirect' );
	}

	mats_installer_setup_wp_options( $result );
	if ( function_exists( 'mats_installer_refresh_polylang' ) ) {
		mats_installer_refresh_polylang(); // home URLs of each language are cached by Polylang
	}
	mats_installer_flush_rewrites();
	$htaccess = mats_installer_htaccess_problem();
	if ( $htaccess ) {
		mats_installer_add(
			$result,
			'warn',
			'WordPress no ha podido escribir el archivo .htaccess del servidor, así que las direcciones limpias (/product/, /es/producto/) pueden dar «página no encontrada». Abre Ajustes → Enlaces permanentes y pulsa «Guardar cambios»: si sigue sin poder, WordPress te mostrará abajo las líneas que hay que pegar en ese archivo. También puedes pedir al proveedor del hosting que permita a WordPress escribir el archivo .htaccess.',
			admin_url( 'options-permalink.php' ),
			'Abrir Enlaces permanentes'
		);
	}

	mats_installer_setup_admin_language( $result );

	if ( $result['ok'] ) {
		mats_installer_add( $result, 'ok', 'Idiomas listos: inglés en la raíz de la web y español en /es/.' );
	}
	return $result;
}

function mats_installer_status_languages(): array {
	$checks = array();
	$pll    = mats_installer_pll_ready();
	$checks[] = mats_installer_check( $pll, $pll ? 'Polylang está activo.' : 'Polylang no está activo (paso 1).' );

	if ( $pll ) {
		foreach ( mats_installer_language_specs() as $slug => $spec ) {
			$lang = mats_installer_pll_language( $slug );
			$ok   = $lang && $lang->locale === $spec['locale'];
			$checks[] = mats_installer_check( (bool) $ok, $ok ? sprintf( 'Idioma %s configurado.', $spec['name'] ) : sprintf( 'Falta el idioma %s.', $spec['name'] ), admin_url( 'admin.php?page=mlang' ), 'Idiomas' );
		}
		$opts_ok = true;
		foreach ( mats_installer_polylang_options() as $key => $value ) {
			if ( mats_installer_pll_option( $key ) !== $value ) {
				$opts_ok = false;
			}
		}
		$checks[] = mats_installer_check( $opts_ok, $opts_ok ? 'Inglés en la raíz de la web y español en /es/.' : 'Los ajustes de Polylang (direcciones de cada idioma) no son los de la web.' );
	}

	$permalinks = '/%postname%/' === get_option( 'permalink_structure' );
	$htaccess   = $permalinks ? mats_installer_htaccess_problem() : '';
	if ( $htaccess ) {
		$checks[] = mats_installer_check( false, 'Direcciones limpias elegidas, pero falta escribirlas en el archivo .htaccess del servidor: las páginas pueden dar «página no encontrada». Abre Enlaces permanentes y pulsa «Guardar cambios».', admin_url( 'options-permalink.php' ), 'Enlaces permanentes' );
	} else {
		$checks[] = mats_installer_check( $permalinks, $permalinks ? 'Direcciones limpias (/product/, /es/producto/).' : 'Las direcciones de las páginas todavía no son las limpias.', admin_url( 'options-permalink.php' ), 'Enlaces permanentes' );
	}
	$basics     = true;
	foreach ( mats_installer_wp_options() as $name => $value ) {
		if ( (string) get_option( $name ) !== (string) $value ) {
			$basics = false;
		}
	}
	$checks[] = mats_installer_check( $basics, $basics ? 'Zona horaria de Madrid, formato de fecha y comentarios cerrados.' : 'Faltan ajustes básicos (zona horaria, fecha o comentarios).', admin_url( 'options-general.php' ), 'Ajustes generales' );
	$checks[] = mats_installer_admin_language_check();
	return mats_installer_status_from( $checks );
}
