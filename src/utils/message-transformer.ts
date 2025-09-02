/**
 * Utility functions for transforming messages between WordPress AI SDK format and Agenttic UI format.
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

import { 
	ChatbotMessage as ChatbotMessageType, 
	MessageRole, 
	MessagePartChannel,
	MessagePartType 
} from '../types';

/**
 * Agenttic UI message format (based on actual package types)
 */
export interface AgentticMessage {
	id: string;
	role: 'user' | 'agent';
	content: Array<{
		type: 'text' | 'image_url' | 'component';
		text?: string;
		image_url?: string;
		component?: React.ComponentType;
		componentProps?: any;
	}>;
	timestamp: number;
	archived: boolean;
	showIcon: boolean;
	icon?: string;
}

/**
 * Transforms WordPress AI SDK message parts to Agenttic UI content format.
 * 
 * @param parts - Array of message parts from WordPress AI SDK
 * @returns Content array suitable for Agenttic UI
 */
function transformMessagePartsToContent( parts: ChatbotMessageType['parts'] ): AgentticMessage['content'] {
	const contentParts = parts.filter( part => 
		part.channel !== MessagePartChannel.Thought 
	);

	const content: AgentticMessage['content'] = [];

	contentParts.forEach( part => {
		if ( part.type === MessagePartType.Text && 'text' in part ) {
			content.push({
				type: 'text',
				text: part.text,
			});
		}
		
		if ( part.type === MessagePartType.File && 'file' in part ) {
			const { mimeType } = part.file;
			let url: string = '';
			
			if ( 'base64Data' in part.file ) {
				url = `data:${mimeType};base64,${part.file.base64Data}`;
			} else {
				url = part.file.url;
			}
			
			if ( mimeType.startsWith('image/') ) {
				content.push({
					type: 'image_url',
					image_url: url,
				});
			} else {
				// For non-image files, add as text
				content.push({
					type: 'text',
					text: `[File: ${mimeType} - ${url}]`,
				});
			}
		}
		
		if ( part.type === MessagePartType.FunctionCall && 'functionCall' in part ) {
			content.push({
				type: 'text',
				text: `[Function Call: ${JSON.stringify(part.functionCall, null, 2)}]`,
			});
		}
		
		if ( part.type === MessagePartType.FunctionResponse && 'functionResponse' in part ) {
			content.push({
				type: 'text',
				text: `[Function Response: ${JSON.stringify(part.functionResponse, null, 2)}]`,
			});
		}
	});

	// Fallback to empty text if no content
	if ( content.length === 0 ) {
		content.push({ type: 'text', text: '' });
	}

	return content;
}

/**
 * Transforms a WordPress AI SDK message to Agenttic UI format.
 *
 * @param message - WordPress AI SDK ChatbotMessage
 * @param index - Message index for ID generation
 * @returns AgentticMessage compatible with Agenttic UI
 */
export function transformToAgentticMessage( 
	message: ChatbotMessageType, 
	index: number 
): AgentticMessage {
	return {
		id: `msg-${index}-${Date.now()}`,
		content: transformMessagePartsToContent( message.parts ),
		role: message.role === MessageRole.User ? 'user' : 'agent',
		timestamp: Date.now(),
		archived: false,
		showIcon: true,
	};
}

/**
 * Transforms an array of WordPress AI SDK messages to Agenttic UI format.
 *
 * @param messages - Array of WordPress AI SDK ChatbotMessages
 * @returns Array of AgentticMessages
 */
export function transformMessagesToAgenttic( 
	messages: ChatbotMessageType[] 
): AgentticMessage[] {
	return messages.map( ( message, index ) => 
		transformToAgentticMessage( message, index ) 
	);
}

/**
 * Creates a WordPress AI SDK message from user input (for sending to backend).
 *
 * @param content - User input text
 * @returns ChatbotMessage in WordPress AI SDK format
 */
export function createUserMessage( content: string ): ChatbotMessageType {
	return {
		role: MessageRole.User,
		parts: [
			{
				channel: MessagePartChannel.Content,
				type: MessagePartType.Text,
				text: content,
			},
		],
		type: 'regular',
	};
}