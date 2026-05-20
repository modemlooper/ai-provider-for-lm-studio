<?php

declare( strict_types=1 );

namespace modemlooper\AiProviderForLMStudio\Models;

use modemlooper\AiProviderForLMStudio\Models\Traits\LmStudioRequestOptionsTrait;
use modemlooper\AiProviderForLMStudio\Provider\LmStudioProvider;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * Class for an LM Studio text generation model using the OpenAI-compatible chat completions API.
 *
 * @since 1.0.0
 */
class LmStudioTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {
	use LmStudioRequestOptionsTrait;

	/**
	 * Prepares the response format parameter for LM Studio's OpenAI-compatible API.
	 *
	 * LM Studio's OpenAI-compatible API uses the same response_format key as OpenAI,
	 * but schema mode expects the schema to be nested at json_schema.schema.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed>|null $output_schema The output schema.
	 * @return array<string, mixed> The prepared response format parameter.
	 */
	protected function prepareResponseFormatParam( ?array $output_schema ): array {
		if ( is_array( $output_schema ) ) {
			return array(
				'type'        => 'json_schema',
				'json_schema' => array(
					'name'   => 'response_schema',
					'schema' => $output_schema,
				),
			);
		}

		return array(
			'type' => 'json_object',
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected function createRequest(
		HttpMethodEnum $method,
		string $path,
		array $headers = array(),
		$data = null
	): Request {
		$request_options = $this->prepareRequestOptionsForTextGeneration();

		// Keep transport-only timeout options out of the OpenAI-compatible payload.
		if ( is_array( $data ) ) {
			unset( $data['lmstudio.request_timeout'], $data['lmstudio.connect_timeout'] );
		}

		// LM Studio uses OpenAI-compatible endpoints at /v1/.
		$path = ltrim( (string) preg_replace( '#^v1/?#', '', ltrim( $path, '/' ) ), '/' );
		$path = '/v1/' . $path;

		return new Request(
			$method,
			LmStudioProvider::url( $path ),
			$headers,
			$data,
			$request_options
		);
	}

	/**
	 * Prepares request options for text generation with a longer default timeout.
	 *
	 * Supported custom options:
	 *  - lmstudio.request_timeout (seconds)
	 *  - lmstudio.connect_timeout (seconds)
	 *
	 * @since 1.1.0
	 *
	 * @return \WordPress\AiClient\Providers\Http\DTO\RequestOptions Prepared request options.
	 */
	private function prepareRequestOptionsForTextGeneration(): RequestOptions {
		return $this->prepareRequestOptions( 60.0, 10.0 );
	}
}
