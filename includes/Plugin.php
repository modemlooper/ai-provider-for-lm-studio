<?php

/**
 * Plugin initializer class.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace modemlooper\AiProviderForLMStudio;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use modemlooper\AiProviderForLMStudio\Provider\LmStudioProvider;
use modemlooper\AiProviderForLMStudio\Settings\LmStudioSettings;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

/**
 * Plugin class.
 *
 * @since 1.0.0
 */
class Plugin {

	/**
	 * Initializes the plugin.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_provider' ), 5 );
		add_action( 'init', array( $this, 'register_fallback_auth' ), 15 );
		add_action( 'init', array( $this, 'initialize_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AI_PROVIDER_FOR_LM_STUDIO_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );
		add_filter( 'http_request_host_is_external', array( $this, 'allow_localhost_requests' ), 10, 3 );
		add_filter( 'http_allowed_safe_ports', array( $this, 'allow_lmstudio_ports' ) );
	}

	/**
	 * Gets the LM Studio host.
	 *
	 * @since 1.0.0
	 *
	 * @return string The LM Studio host.
	 */
	private function get_lmstudio_host(): string {
		// Get the LM_STUDIO_HOST environment variable if set.
		$host = getenv( 'LM_STUDIO_HOST' );
		if ( false !== $host && '' !== $host ) {
			return $host;
		}

		// Get the LM Studio host from the WordPress option if set.
		$settings = LmStudioSettings::get_settings();
		if ( isset( $settings['host'] ) && '' !== $settings['host'] ) {
			return $settings['host'];
		}

		return 'http://localhost:1234';
	}

	/**
	 * Sets the LM_STUDIO_HOST environment variable.
	 *
	 * @since 1.0.0
	 */
	private function set_lmstudio_host(): void {
		$host = $this->get_lmstudio_host();

		if ( '' === $host ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Required to set LM_STUDIO_HOST for the provider SDK.
		putenv( 'LM_STUDIO_HOST=' . $host );
	}

	/**
	 * Registers the LM Studio provider with the AI Client.
	 *
	 * @since 1.0.0
	 */
	public function register_provider(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$this->set_lmstudio_host();

		$registry = AiClient::defaultRegistry();

		if ( $registry->hasProvider( LmStudioProvider::class ) ) {
			return;
		}

		$registry->registerProvider( LmStudioProvider::class );
	}

	/**
	 * Registers fallback authentication for the LM Studio provider.
	 *
	 * If no API key was provided via wp-ai-client (which passes credentials at priority 10),
	 * this registers an empty API key so that local LM Studio instances work without configuration.
	 *
	 * @since 1.0.0
	 */
	public function register_fallback_auth(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( 'lmstudio' ) ) {
			return;
		}

		// Only set fallback if no authentication has been configured yet.
		$auth = $registry->getProviderRequestAuthentication( 'lmstudio' );
		if ( null !== $auth ) {
			return;
		}

		$registry->setProviderRequestAuthentication(
			'lmstudio',
			new ApiKeyRequestAuthentication( '' )
		);
	}

	/**
	 * Initializes the LM Studio settings.
	 *
	 * @since 1.0.0
	 */
	public function initialize_settings(): void {
		$settings = new LmStudioSettings();
		$settings->init();
	}

	/**
	 * Adds action links to the plugin list table.
	 *
	 * This adds "Settings" link to the plugin's action links
	 * on the Plugins page.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string> $links Existing action links.
	 * @return array<string> Modified action links.
	 */
	public function plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			admin_url( 'options-general.php?page=ai-provider-for-lmstudio' ),
			esc_html__( 'Settings', 'ai-provider-for-lm-studio' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Allows localhost requests to the LM Studio host.
	 *
	 * @since 1.0.0
	 *
	 * @param bool   $external Whether the request is external.
	 * @param string $host The host of the request.
	 * @param string $url The URL of the request.
	 * @return bool Whether the request is allowed.
	 */
	public function allow_localhost_requests( $external, $host, $url ): bool {
		if ( strpos( $url, $this->get_lmstudio_host() ) !== false ) {
			return true;
		}

		return $external;
	}

	/**
	 * Allows LM Studio ports.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int> $ports The ports.
	 * @return array<int> The allowed ports.
	 */
	public function allow_lmstudio_ports( $ports ): array {
		$lmstudio_host = $this->get_lmstudio_host();
		$lmstudio_port = wp_parse_url( $lmstudio_host, PHP_URL_PORT );

		if ( ! $lmstudio_port ) {
			return $ports;
		}

		return array_merge( $ports, array( $lmstudio_port ) );
	}
}
