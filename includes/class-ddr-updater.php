<?php
/**
 * Auto-aggiornamento del plugin dalle release GitHub, SENZA plugin esterni.
 *
 * Si aggancia all'API aggiornamenti di WordPress: controlla l'ultima release
 * pubblicata sul repo GitHub, e se la versione e' superiore a quella installata
 * mostra "aggiornamento disponibile" nel backend, con update con un clic.
 *
 * Repo pubblico: nessun token necessario. La risposta dell'API e' messa in
 * cache (transient) per non superare il rate limit di GitHub.
 *
 * Quando il plugin sara' su WordPress.org, gli aggiornamenti li servira' WP.org
 * per slug: basta rimuovere/azzerare DDR_GITHUB_REPO o l'header Update URI.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DDR_Updater {

	/** @var string es. "artwork/diritto-di-recesso" */
	protected $repo;

	/** @var string path file principale */
	protected $file;

	/** @var string es. "diritto-di-recesso/diritto-di-recesso.php" */
	protected $basename;

	/** @var string es. "diritto-di-recesso" */
	protected $slug;

	/** @var string versione installata */
	protected $version;

	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	public static function init() {
		// Senza repo configurato l'updater resta inerte.
		if ( ! defined( 'DDR_GITHUB_REPO' ) || ! DDR_GITHUB_REPO ) {
			return;
		}
		new self( DDR_FILE, DDR_GITHUB_REPO, DDR_VERSION );
	}

	public function __construct( $file, $repo, $version ) {
		$this->file     = $file;
		$this->repo     = trim( $repo, '/' );
		$this->basename = plugin_basename( $file );
		$this->slug     = dirname( $this->basename );
		$this->version  = $version;

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
		// Pulisce la cache dopo un aggiornamento andato a buon fine.
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ), 10, 0 );
	}

	/* ------------------------------------------------------------------ */

	/**
	 * Recupera (con cache) l'ultima release dal repo GitHub.
	 *
	 * @return object|null { version, zip_url, html_url, body, published_at }
	 */
	protected function get_release() {
		return self::fetch_release();
	}

	/**
	 * Recupera (con cache) l'ultima release dal repo GitHub. Statico, cosi' e'
	 * richiamabile anche dal pannello admin per mostrare lo stato.
	 *
	 * @return object|null { version, zip_url, html_url, body, published_at }
	 */
	public static function fetch_release() {
		if ( ! defined( 'DDR_GITHUB_REPO' ) || ! DDR_GITHUB_REPO ) {
			return null;
		}
		$repo      = trim( DDR_GITHUB_REPO, '/' );
		$cache_key = 'ddr_gh_release';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached ? $cached : null;
		}

		$url      = 'https://api.github.com/repos/' . $repo . '/releases/latest';
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'DirittoDiRecesso/' . DDR_VERSION . '; ' . home_url(),
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			// Cache "vuota" breve per non martellare l'API in caso di errore.
			set_transient( $cache_key, '', 30 * MINUTE_IN_SECONDS );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );
		if ( empty( $data->tag_name ) ) {
			set_transient( $cache_key, '', 30 * MINUTE_IN_SECONDS );
			return null;
		}

		// Preferisce un asset .zip allegato alla release; altrimenti lo zipball.
		$zip_url = isset( $data->zipball_url ) ? $data->zipball_url : '';
		if ( ! empty( $data->assets ) && is_array( $data->assets ) ) {
			foreach ( $data->assets as $asset ) {
				if ( isset( $asset->browser_download_url ) && preg_match( '/\.zip$/i', $asset->browser_download_url ) ) {
					$zip_url = $asset->browser_download_url;
					break;
				}
			}
		}

		$release = (object) array(
			'version'      => ltrim( $data->tag_name, 'vV' ),
			'zip_url'      => $zip_url,
			'html_url'     => isset( $data->html_url ) ? $data->html_url : ( 'https://github.com/' . $repo ),
			'body'         => isset( $data->body ) ? (string) $data->body : '',
			'published_at' => isset( $data->published_at ) ? $data->published_at : '',
		);

		set_transient( $cache_key, $release, self::CACHE_TTL );
		return $release;
	}

	/**
	 * Inietta l'aggiornamento nel transient di WordPress.
	 */
	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_release();
		if ( ! $release || ! $release->zip_url ) {
			return $transient;
		}

		if ( version_compare( $release->version, $this->version, '>' ) ) {
			$transient->response[ $this->basename ] = (object) array(
				'slug'        => $this->slug,
				'plugin'      => $this->basename,
				'new_version' => $release->version,
				'url'         => $release->html_url,
				'package'     => $release->zip_url,
			);
		} else {
			// Segnala "nessun aggiornamento" (utile per la UI di WP).
			$transient->no_update[ $this->basename ] = (object) array(
				'slug'        => $this->slug,
				'plugin'      => $this->basename,
				'new_version' => $this->version,
				'url'         => $release->html_url,
				'package'     => '',
			);
		}

		return $transient;
	}

	/**
	 * Popola la schermata "Visualizza dettagli / changelog".
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}

		$release = $this->get_release();
		$data    = get_file_data( $this->file, array( 'Name' => 'Plugin Name', 'Author' => 'Author', 'AuthorURI' => 'Author URI' ) );

		$info               = new stdClass();
		$info->name         = $data['Name'];
		$info->slug         = $this->slug;
		$info->version      = $release ? $release->version : $this->version;
		$info->author       = '<a href="' . esc_url( $data['AuthorURI'] ) . '">' . esc_html( $data['Author'] ) . '</a>';
		$info->homepage     = $release ? $release->html_url : ( 'https://github.com/' . $this->repo );
		$info->download_link = $release ? $release->zip_url : '';
		$info->last_updated = $release ? $release->published_at : '';
		$info->sections     = array(
			'description' => esc_html__( 'Recesso digitale conforme all’art. 54-bis del Codice del Consumo per WooCommerce.', 'diritto-di-recesso' ),
			'changelog'   => $release && $release->body ? nl2br( esc_html( $release->body ) ) : esc_html__( 'Vedi le release su GitHub.', 'diritto-di-recesso' ),
		);

		return $info;
	}

	/**
	 * Lo zipball GitHub si estrae in una cartella "owner-repo-hash/": va
	 * rinominata nello slug del plugin perche' l'update sovrascriva la cartella
	 * giusta. Per gli asset .zip nominati correttamente questo e' un no-op.
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = null ) {
		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
			return $source;
		}

		global $wp_filesystem;
		$desired = trailingslashit( $remote_source ) . $this->slug;

		if ( untrailingslashit( $source ) === $desired ) {
			return $source;
		}

		if ( $wp_filesystem && $wp_filesystem->move( untrailingslashit( $source ), $desired ) ) {
			return trailingslashit( $desired );
		}

		return $source;
	}

	public function clear_cache() {
		delete_transient( 'ddr_gh_release' );
	}

	/**
	 * Forza un nuovo controllo: svuota la cache GitHub e il transient update di WP.
	 */
	public static function force_recheck() {
		delete_transient( 'ddr_gh_release' );
		delete_site_transient( 'update_plugins' );
		if ( function_exists( 'wp_update_plugins' ) ) {
			wp_update_plugins();
		}
	}
}
