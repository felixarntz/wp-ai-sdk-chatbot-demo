<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools\Abstract_Tool
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools;

use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools\Contracts\Tool;
use WP_Error;

/**
 * Base class for a function tool.
 *
 * @since 0.1.0
 */
abstract class Abstract_Tool implements Tool {

	/**
	 * The name of the tool.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected string $name;

	/**
	 * The description of the tool.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected string $description;

	/**
	 * The parameters of the tool.
	 *
	 * @since 0.1.0
	 * @var array<string, mixed>
	 */
	protected array $parameters;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->name        = $this->name();
		$this->description = $this->description();
		$this->parameters  = $this->parameters();
	}

	/**
	 * Gets the name of the tool.
	 *
	 * @since 0.1.0
	 *
	 * @return string The name of the tool.
	 */
	final public function get_name(): string {
		return $this->name;
	}

	/**
	 * Gets the description of the tool.
	 *
	 * @since 0.1.0
	 *
	 * @return string The description of the tool.
	 */
	final public function get_description(): string {
		return $this->description;
	}

	/**
	 * Gets the parameters of the tool.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed> The parameters of the tool.
	 */
	final public function get_parameters(): array {
		return $this->parameters;
	}

	/**
	 * Executes the tool with the given input arguments.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $args The input arguments to the tool.
	 * @return mixed|WP_Error The result of the tool execution, or a WP_Error on failure.
	 */
	abstract public function execute( $args );

	/**
	 * Returns the name of the tool.
	 *
	 * @since 0.1.0
	 *
	 * @return string The name of the tool.
	 */
	abstract protected function name(): string;

	/**
	 * Returns the description of the tool.
	 *
	 * @since 0.1.0
	 *
	 * @return string The description of the tool.
	 */
	abstract protected function description(): string;

	/**
	 * Returns the parameters of the tool.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed> The parameters of the tool.
	 */
	abstract protected function parameters(): array;
}
