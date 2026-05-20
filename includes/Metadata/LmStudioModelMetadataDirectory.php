<?php

declare( strict_types=1 );

namespace modemlooper\AiProviderForLMStudio\Metadata;

use modemlooper\AiProviderForLMStudio\Provider\LmStudioProvider;
use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModelMetadataDirectory;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

/**
 * Class for the LM Studio model metadata directory.
 *
 * Uses LM Studio's OpenAI-compatible /v1/models endpoint.
 *
 * @since 1.0.0
 *
 * @phpstan-type ModelsResponseData array{
 *     object?: string,
 *     data: list<array{id: string, object?: string, created?: int, owned_by?: string}>
 * }
 */
class LmStudioModelMetadataDirectory extends AbstractApiBasedModelMetadataDirectory {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected function sendListModelsRequest(): array {
		$request  = $this->createRequest( HttpMethodEnum::GET(), 'v1/models' );
		$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
		$response = $this->getHttpTransporter()->send( $request );

		ResponseUtil::throwIfNotSuccessful( $response );

		/** @var ModelsResponseData $models_data */
		$models_data = $response->getData();
		if ( ! isset( $models_data['data'] ) || ! is_array( $models_data['data'] ) ) {
			throw ResponseException::fromMissingData( 'LM Studio', 'data' );
		}

		$models_map = array();
		foreach ( $models_data['data'] as $model_entry ) {
			$model_name = $model_entry['id'] ?? '';
			if ( '' === $model_name ) {
				continue;
			}

			$metadata = $this->buildModelMetadata( $model_name );
			if ( null !== $metadata ) {
				$models_map[ $model_name ] = $metadata;
			}
		}

		ksort( $models_map );

		return $models_map;
	}

	/**
	 * Builds a ModelMetadata object for a single model.
	 *
	 * LM Studio models are treated as text-generation models by default.
	 * Image generation models are detected from their name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_name The model name.
	 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata|null The model metadata, or null if the model should be excluded.
	 */
	private function buildModelMetadata( string $model_name ): ?ModelMetadata {
		$is_image_generation_model = $this->isImageGenerationModel( $model_name );

		// All models exposed via LM Studio's /v1/models are chat/text models.
		$input_modalities_option = new SupportedOption(
			OptionEnum::inputModalities(),
			array( array( ModalityEnum::text() ) )
		);

		if ( $is_image_generation_model ) {
			return new ModelMetadata(
				$model_name,
				$model_name,
				array(
					CapabilityEnum::imageGeneration(),
				),
				array(
					new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
					new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::image() ) ) ),
					new SupportedOption( OptionEnum::candidateCount() ),
					new SupportedOption( OptionEnum::outputMimeType(), array( 'image/png' ) ),
					new SupportedOption( OptionEnum::outputFileType(), array( FileTypeEnum::inline() ) ),
					new SupportedOption( OptionEnum::customOptions() ),
				)
			);
		}

		$options = array(
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::candidateCount() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::topK() ),
			new SupportedOption( OptionEnum::stopSequences() ),
			new SupportedOption( OptionEnum::frequencyPenalty() ),
			new SupportedOption( OptionEnum::presencePenalty() ),
			new SupportedOption( OptionEnum::outputMimeType(), array( 'text/plain', 'application/json' ) ),
			new SupportedOption( OptionEnum::outputSchema() ),
			new SupportedOption( OptionEnum::functionDeclarations() ),
			new SupportedOption( OptionEnum::customOptions() ),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) ),
			$input_modalities_option,
		);

		return new ModelMetadata(
			$model_name,
			$model_name,
			array(
				CapabilityEnum::textGeneration(),
				CapabilityEnum::chatHistory(),
			),
			$options
		);
	}

	/**
	 * Determines whether a model is likely an image-generation model.
	 *
	 * @since 1.1.0
	 *
	 * @param string $model_name The model name.
	 * @return bool True if the model appears to support image generation.
	 */
	private function isImageGenerationModel( string $model_name ): bool {
		if ( '' === $model_name ) {
			return false;
		}

		// Detect common image generation model names.
		$image_gen_patterns = array(
			'stable-diffusion',
			'sdxl',
			'flux',
			'dall-e',
			'midjourney',
			'image-gen',
			'imagegen',
		);

		$lower_name = strtolower( $model_name );
		foreach ( $image_gen_patterns as $pattern ) {
			if ( str_contains( $lower_name, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Creates a request object for the LM Studio API.
	 *
	 * @since 1.0.0
	 *
	 * @param \WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum                     $method  The HTTP method.
	 * @param string                             $path    The API endpoint path, relative to the base URI.
	 * @param array<string, string|list<string>> $headers The request headers.
	 * @param string|array<string, mixed>|null   $data    The request data.
	 * @return \WordPress\AiClient\Providers\Http\DTO\Request The request object.
	 */
	private function createRequest( HttpMethodEnum $method, string $path, array $headers = array(), $data = null ): Request {
		return new Request(
			$method,
			LmStudioProvider::url( $path ),
			$headers,
			$data
		);
	}
}
