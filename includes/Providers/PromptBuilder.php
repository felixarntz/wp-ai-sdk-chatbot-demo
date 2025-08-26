<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Providers\PromptBuilder
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Providers;

use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Files\Enums\FileTypeEnum;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Messages\DTO\Message;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Providers\Models\DTO\ModelRequirements;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Providers\Models\DTO\RequiredOption;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Providers\Models\ImageGeneration\Contracts\ImageGenerationModelInterface;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Providers\ProviderRegistry;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Results\DTO\GenerativeAiResult;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use RuntimeException;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase, WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid, WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

/**
 * Temporary implementation of the PHP AI Client SDK's PromptBuilder class (until it's available).
 *
 * @since 0.1.0
 */
class PromptBuilder {

	/**
	 * The prompt messages.
	 *
	 * @since 0.1.0
	 * @var array<Message>
	 */
	private array $messages;

	/**
	 * The provider registry.
	 *
	 * @since 0.1.0
	 * @var ProviderRegistry
	 */
	private ProviderRegistry $registry;

	/**
	 * The model to use for the prompt.
	 *
	 * @since 0.1.0
	 * @var ModelInterface|null
	 */
	private ?ModelInterface $model;

	/**
	 * The model configuration.
	 *
	 * @since 0.1.0
	 * @var ModelConfig
	 */
	private ModelConfig $modelConfig;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param ProviderRegistry $registry The provider registry.
	 * @param array<Message>   $messages The prompt messages.
	 */
	public function __construct( ProviderRegistry $registry, array $messages ) {
		$this->registry    = $registry;
		$this->messages    = $messages;
		$this->model       = null;
		$this->modelConfig = new ModelConfig();
	}

	/**
	 * Sets function declarations for the prompt.
	 *
	 * @since 0.1.0
	 *
	 * @param array<FunctionDeclaration> $functionDeclarations The function declarations.
	 * @return $this
	 */
	public function usingFunctionDeclarations( array $functionDeclarations ): self {
		$this->modelConfig->setFunctionDeclarations( $functionDeclarations );
		return $this;
	}

	/**
	 * Sets a system instruction for the prompt.
	 *
	 * @since 0.1.0
	 *
	 * @param string $instruction The system instruction.
	 * @return $this
	 */
	public function usingSystemInstruction( string $instruction ): self {
		$this->modelConfig->setSystemInstruction( $instruction );
		return $this;
	}

	/**
	 * Sets the output file type for the prompt (relevant for e.g. image generation).
	 *
	 * @since 0.1.0
	 *
	 * @param FileTypeEnum $type The file type to use.
	 * @return $this
	 */
	public function usingOutputFileType( FileTypeEnum $type ): self {
		$this->modelConfig->setOutputFileType( $type );
		return $this;
	}

	/**
	 * Sets the model to use for the prompt.
	 *
	 * @since 0.1.0
	 *
	 * @param ModelInterface $model The model instance.
	 * @return $this
	 */
	public function usingModel( ModelInterface $model ): self {
		$this->model = $model;
		return $this;
	}

	/**
	 * Generates a text result from the prompt.
	 *
	 * @since 0.1.0
	 *
	 * @return GenerativeAiResult The text result.
	 *
	 * @throws RuntimeException If no suitable model is found or if the model does not support text generation.
	 */
	public function generateTextResult(): GenerativeAiResult {
		if ( null === $this->model ) {
			$modelInstance = $this->findModelInstance( array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ) );
		} else {
			$modelInstance = $this->model;
		}

		if ( ! ( $modelInstance instanceof TextGenerationModelInterface ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new RuntimeException( 'The model class ' . get_class( $modelInstance ) . ' does not support text generation.' );
		}

		$modelInstance->setConfig( $this->modelConfig );
		return $modelInstance->generateTextResult( $this->messages );
	}

	/**
	 * Generates an image result from the prompt.
	 *
	 * @since 0.1.0
	 *
	 * @return GenerativeAiResult The image result.
	 *
	 * @throws RuntimeException If no suitable model is found or if the model does not support image generation.
	 */
	public function generateImageResult(): GenerativeAiResult {
		if ( null === $this->model ) {
			$modelInstance = $this->findModelInstance( array( CapabilityEnum::imageGeneration() ) );
		} else {
			$modelInstance = $this->model;
		}

		if ( ! ( $modelInstance instanceof ImageGenerationModelInterface ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new RuntimeException( 'The model class ' . get_class( $modelInstance ) . ' does not support image generation.' );
		}

		$modelInstance->setConfig( $this->modelConfig );
		return $modelInstance->generateImageResult( $this->messages );
	}

	/**
	 * Finds a suitable model instance for the necessary capabilities and options.
	 *
	 * @since 0.1.0
	 *
	 * @param array<CapabilityEnum> $requiredCapabilities List of required capabilities.
	 * @return ModelInterface The model interface to use.
	 *
	 * @throws RuntimeException If no suitable model is found.
	 */
	private function findModelInstance( array $requiredCapabilities ): ModelInterface {
		$requiredOptions = array();
		foreach ( $this->modelConfig->toArray() as $option => $value ) {
			$requiredOptions[] = new RequiredOption( $option, $value );
		}
		$modelRequirements = new ModelRequirements(
			$requiredCapabilities,
			$requiredOptions
		);

		$providerModelsMetadata = $this->registry->findModelsMetadataForSupport( $modelRequirements );
		if ( ! isset( $providerModelsMetadata[0] ) ) {
			throw new RuntimeException( 'No provider model supports the necessary model requirements.' );
		}

		return $this->registry->getProviderModel(
			$providerModelsMetadata[0]->getProvider()->getId(),
			$providerModelsMetadata[0]->getModels()[0]->getId()
		);
	}
}
