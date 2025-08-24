<?php
/**
 * Interface Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Agents\Contracts\Agent
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Agents\Contracts;

use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Agents\Agent_Step_Result;

/**
 * Interface for an agent.
 *
 * @since 0.1.0
 */
interface Agent {

	/**
	 * Executes a single step of the agent's execution.
	 *
	 * @since 0.1.0
	 *
	 * @return Agent_Step_Result The result of the step execution.
	 */
	public function step(): Agent_Step_Result;
}
