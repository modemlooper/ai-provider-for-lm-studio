<?php

declare( strict_types=1 );

namespace modemlooper\AiProviderForLMStudio\Provider;

use modemlooper\AiProviderForLMStudio\Metadata\LmStudioModelMetadataDirectory;
use modemlooper\AiProviderForLMStudio\Models\LmStudioImageGenerationModel;
use modemlooper\AiProviderForLMStudio\Models\LmStudioTextGenerationModel;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Class for the LM Studio provider.
 *
 * @since 1.0.0
 */
class LmStudioProvider extends AbstractApiProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function baseUrl(): string {
		$host = getenv( 'LM_STUDIO_HOST' );
		if ( false !== $host && '' !== $host ) {
			return rtrim( $host, '/' );
		}

		return 'http://localhost:1234';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createModel(
		ModelMetadata $model_metadata,
		ProviderMetadata $provider_metadata
	): ModelInterface {

		$capabilities_string_list = $model_metadata->toArray()[ ModelMetadata::KEY_SUPPORTED_CAPABILITIES ];

		if ( in_array( 'image_generation', $capabilities_string_list, true ) ) {
			return new LmStudioImageGenerationModel( $model_metadata, $provider_metadata );
		}

		$capabilities = $model_metadata->getSupportedCapabilities();
		foreach ( $capabilities as $capability ) {
			if ( $capability->isTextGeneration() ) {
				return new LmStudioTextGenerationModel( $model_metadata, $provider_metadata );
			}
		}

		throw new RuntimeException(
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
			'Unsupported model capabilities: ' . implode( ', ', $capabilities )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		$provider_meta = array(
			'lmstudio',
			'LM Studio',
			ProviderTypeEnum::server(),
			'',
			RequestAuthenticationMethod::apiKey(),
		);

		// Provider description support was added in 1.2.0.
		if ( version_compare( AiClient::VERSION, '1.2.0', '>=' ) ) {
			if ( function_exists( '__' ) ) {
				$provider_meta[] = __( 'Text generation with LM Studio, running locally on your machine.', 'ai-provider-for-lm-studio' );
			} else {
				$provider_meta[] = 'Text generation with LM Studio, running locally on your machine.';
			}
		}

		// Provider logo path support was added in 1.3.0.
		if ( version_compare( AiClient::VERSION, '1.3.0', '>=' ) ) {
			$provider_meta[] = defined( 'AI_PROVIDER_FOR_LM_STUDIO_PLUGIN_DIR' )
				? AI_PROVIDER_FOR_LM_STUDIO_PLUGIN_DIR . 'includes/Provider/logo.png'
				: dirname( __DIR__, 2 ) . '/includes/Provider/logo.png';
		}

		return new ProviderMetadata( ...$provider_meta );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		// Check valid API access by attempting to list models.
		return new ListModelsApiBasedProviderAvailability(
			static::modelMetadataDirectory()
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new LmStudioModelMetadataDirectory();
	}
}
