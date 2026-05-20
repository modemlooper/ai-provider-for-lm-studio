<?php

declare( strict_types=1 );

namespace modemlooper\AiProviderForLMStudio\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Error;
use WordPress\AiClient\AiClient;

/**
 * Class for the LM Studio settings in the WordPress admin.
 *
 * Provides a settings page under Settings > LM Studio for configuring the LM Studio
 * host URL.
 *
 * @since 1.0.0
 */
class LmStudioSettings {

	private const OPTION_GROUP = 'ai-provider-for-lmstudio-settings';
	private const OPTION_NAME  = 'ai_provider_for_lmstudio_settings';
	private const PAGE_SLUG    = 'ai-provider-for-lmstudio';
	private const SECTION_ID   = 'ai_provider_for_lmstudio_main';
	private const AJAX_ACTION  = 'ai_provider_for_lmstudio_list_models';
	private const NONCE_ACTION = 'ai_provider_for_lmstudio_nonce';

	/**
	 * Initializes the settings.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_settings_screen' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_script' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_list_models' ) );
		add_filter( 'wpai_has_ai_credentials', array( $this, 'is_connected' ) );
		add_filter( 'wpai_is_lmstudio_connector_configured', array( $this, 'is_connected' ) );
	}

	/**
	 * Registers the setting and settings fields.
	 *
	 * @since 1.0.0
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			self::SECTION_ID,
			'',
			'__return_empty_string',
			self::PAGE_SLUG
		);

		add_settings_field(
			self::OPTION_NAME . '_host',
			__( 'Host URL', 'ai-provider-for-lm-studio' ),
			array( $this, 'render_host_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID,
			array( 'label_for' => self::OPTION_NAME . '-host' )
		);

		add_settings_field(
			self::OPTION_NAME . '_model',
			__( 'Available Models', 'ai-provider-for-lm-studio' ),
			array( $this, 'render_available_models_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID,
			array( 'label_for' => self::OPTION_NAME . '-model' )
		);
	}

	/**
	 * Registers the settings screen.
	 *
	 * @since 1.0.0
	 */
	public function register_settings_screen(): void {
		add_options_page(
			__( 'LM Studio Settings', 'ai-provider-for-lm-studio' ),
			__( 'LM Studio', 'ai-provider-for-lm-studio' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_screen' )
		);
	}

	/**
	 * Sanitizes the settings array.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The input value.
	 * @return array<string, string> The sanitized settings.
	 */
	public function sanitize_settings( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$host = isset( $value['host'] ) ? trim( (string) $value['host'] ) : '';
		if ( '' !== $host ) {
			$host = rtrim( esc_url_raw( $host ), '/' );
		}

		return array(
			'host' => $host,
		);
	}

	/**
	 * Renders the settings screen.
	 *
	 * @since 1.0.0
	 */
	public function render_screen(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>

		<div class="wrap ai-provider-for-lmstudio-settings-screen">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %s: link to the AI Credentials screen */
					esc_html__( 'Configure the connection to your LM Studio instance. If you want to use an API key, enter it on the %sSettings > Connectors%s screen.', 'ai-provider-for-lm-studio' ),
					'<a href="' . esc_url( admin_url( 'options-connectors.php' ) ) . '">',
					'</a>'
				);
				?>
			</p>
			<p>
				<?php
				printf(
					/* translators: 1: code tag, 2: closing code tag */
					esc_html__( 'Leave the host URL empty to use the default (%1$shttp://localhost:1234%2$s). You can also set the %1$sLM_STUDIO_HOST%2$s environment variable to override this setting.', 'ai-provider-for-lm-studio' ),
					'<code>',
					'</code>'
				);
				?>
			</p>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>

		<?php
	}

	/**
	 * Renders the host URL field.
	 *
	 * @since 1.0.0
	 */
	public function render_host_field(): void {
		$settings = self::get_settings();
		$value    = isset( $settings['host'] ) ? $settings['host'] : '';
		?>

		<input
			type="url"
			id="<?php echo esc_attr( self::OPTION_NAME . '-host' ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME . '[host]' ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="http://localhost:1234"
		/>
		<p class="description">
			<?php
			printf(
				/* translators: 1: code tag, 2: closing code tag */
				esc_html__( 'The base URL of your LM Studio instance (without /v1). Example: %1$shttp://localhost:1234%2$s', 'ai-provider-for-lm-studio' ),
				'<code>',
				'</code>'
			);
			?>
		</p>

		<?php
	}

	/**
	 * Renders the available models list.
	 *
	 * @since 1.0.0
	 */
	public function render_available_models_field(): void {
		?>

		<div id="lmstudio-models-container">
			<span id="lmstudio-model-status"></span>
		</div>
		<p class="description">
			<?php
			echo esc_html__( 'Available models are fetched from your LM Studio instance. If a model is not listed that you want, ensure that model is loaded within LM Studio.', 'ai-provider-for-lm-studio' );
			?>
		</p>

		<?php
	}

	/**
	 * Enqueues the settings page script.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_settings_script( string $hook_suffix ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$plugin_dir = AI_PROVIDER_FOR_LM_STUDIO_PLUGIN_DIR;
		$asset_file = $plugin_dir . 'build/admin/settings.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(); // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Asset file path is built from a known constant.

		$dependencies = isset( $asset['dependencies'] ) ? $asset['dependencies'] : array();
		$version      = isset( $asset['version'] ) ? $asset['version'] : false;

		wp_enqueue_script(
			'ai-provider-for-lmstudio-settings',
			plugins_url( 'build/admin/settings.js', $plugin_dir . 'plugin.php' ),
			$dependencies,
			$version,
			true
		);

		wp_enqueue_style(
			'ai-provider-for-lmstudio-settings',
			plugins_url( 'build/admin/style-settings.css', $plugin_dir . 'plugin.php' ),
			array(),
			$version
		);
		wp_style_add_data( 'ai-provider-for-lmstudio-settings', 'rtl', 'replace' );

		wp_localize_script(
			'ai-provider-for-lmstudio-settings',
			'aiProviderForLmStudioSettings',
			array(
				'ajaxUrl' => esc_url( admin_url( 'admin-ajax.php' ) . '?action=' . self::AJAX_ACTION . '&_wpnonce=' . wp_create_nonce( self::NONCE_ACTION ) ),
			)
		);
	}

	/**
	 * Handles the AJAX request to list available LM Studio models.
	 *
	 * @since 1.0.0
	 */
	public function ajax_list_models(): void {
		check_ajax_referer( self::NONCE_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'ai-provider-for-lm-studio' ), 403 );
		}

		$models = $this->get_models();

		if ( is_wp_error( $models ) ) {
			wp_send_json_error( $models->get_error_message(), $models->get_error_code() );
		}

		wp_send_json_success( $models );
	}

	/**
	 * Checks if the LM Studio provider is connected.
	 *
	 * @since 1.1.0
	 *
	 * @return bool True if the LM Studio provider is connected, false otherwise.
	 */
	public function is_connected(): bool {
		return ! is_wp_error( $this->get_models() );
	}

	/**
	 * Gets the models from the LM Studio provider.
	 *
	 * @since 1.1.0
	 *
	 * @return \WP_Error|array<string, \modemlooper\AiProviderForLMStudio\Settings\ModelMetadata> The models.
	 */
	public function get_models() {
		$provider_id = 'lmstudio';
		$registry    = AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( $provider_id ) ) {
			return new WP_Error( 'ai_provider_not_found', __( 'AI provider not found.', 'ai-provider-for-lm-studio' ), 404 );
		}

		$provider_classname = $registry->getProviderClassName( $provider_id );

		try {
			// phpcs:ignore Generic.Commenting.DocComment.MissingShort
			$provider_availability = $provider_classname::availability();
			if ( ! $provider_availability->isConfigured() ) {
				return new WP_Error( 'ai_provider_not_configured', __( 'AI provider not configured - ensure LM Studio is running or you have valid API credentials.', 'ai-provider-for-lm-studio' ), 400 );
			}

			// phpcs:ignore Generic.Commenting.DocComment.MissingShort
			$model_metadata_directory = $provider_classname::modelMetadataDirectory();
			return $model_metadata_directory->listModelMetadata();
		} catch ( \Throwable $e ) {
			/* translators: %s: Error message. */
			return new WP_Error( 'could_not_list_models', sprintf( __( 'Could not list models for provider - is LM Studio running or are the API credentials invalid? Error: %s', 'ai-provider-for-lm-studio' ), $e->getMessage() ), 500 );
		}
	}

	/**
	 * Gets the settings from the WordPress option.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> The settings.
	 */
	public static function get_settings(): array {
		return (array) get_option( self::OPTION_NAME, array() );
	}
}
