<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Agents\Agent_Step_Result
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Agents;

use WordPress\AiClient\Messages\DTO\Message;
use InvalidArgumentException;

/**
 * Class representing the result of a step in an agent's execution.
 *
 * @since 0.1.0
 */
class Agent_Step_Result {

	/**
	 * The index of the step in the agent's execution.
	 *
	 * @since 0.1.0
	 * @var int
	 */
	private int $step_index;

	/**
	 * Whether the agent has finished its execution.
	 *
	 * @since 0.1.0
	 * @var bool
	 */
	private bool $finished;

	/**
	 * The new messages appended to the agent's trajectory during the step.
	 *
	 * @since 0.1.0
	 * @var array<Message>
	 */
	private array $new_messages;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param int            $step_index   The index of the step in the agent's execution.
	 * @param bool           $finished     Whether the agent has finished its execution.
	 * @param array<Message> $new_messages The new messages appended to the agent's trajectory during the step.
	 *
	 * @throws InvalidArgumentException If no new messages are provided.
	 */
	public function __construct( int $step_index, bool $finished, array $new_messages ) {
		$this->step_index   = $step_index;
		$this->finished     = $finished;
		$this->new_messages = $new_messages;

		if ( count( $this->new_messages ) === 0 ) {
			throw new InvalidArgumentException( 'At least one message must be provided.' );
		}
	}

	/**
	 * Gets the step index.
	 *
	 * @since 0.1.0
	 *
	 * @return int The step index.
	 */
	public function step_index(): int {
		return $this->step_index;
	}

	/**
	 * Checks whether the agent has finished its execution.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True if the agent has finished, false otherwise.
	 */
	public function finished(): bool {
		return $this->finished;
	}

	/**
	 * Gets the new messages appended to the agent's trajectory during the step.
	 *
	 * @since 0.1.0
	 *
	 * @return array<Message> The new messages.
	 */
	public function new_messages(): array {
		return $this->new_messages;
	}

	/**
	 * Gets the last message from the step.
	 *
	 * @since 0.1.0
	 *
	 * @return Message The last message appended to the trajectory in the step.
	 */
	public function last_message(): Message {
		return end( $this->new_messages );
	}
}
