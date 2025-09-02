/**
 * External dependencies
 */
import clsx from 'clsx';
import { AgentUI } from '@automattic/agenttic-ui';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { useState, useMemo } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useChatbotConfig } from '../../config';
import './style.scss';
import {
	ChatbotMessage as ChatbotMessageType,
} from '../../types';
import logError from '../../utils/log-error';
import { 
	transformMessagesToAgenttic, 
	createUserMessage,
	type AgentticMessage 
} from '../../utils/message-transformer';

type ChatbotProps = {
	/**
	 * The route to use for fetching and sending messages.
	 */
	messagesRoute: string;

	/**
	 * The messages to display in the chatbot.
	 */
	messages?: ChatbotMessageType[];

	/**
	 * Function to call when the history of messages is updated.
	 */
	onUpdateMessages?: ( messages: ChatbotMessageType[] ) => void;
	/**
	 * Function to call when the close button is clicked.
	 */
	onClose: () => void;
	/**
	 * Class name to use on the chatbot container.
	 */
	className?: string;
};

/**
 * Renders the chatbot using Agenttic UI.
 *
 * @since 0.1.0
 *
 * @param props - Component props.
 * @returns The component to be rendered.
 */
export default function Chatbot( props: ChatbotProps ) {
	const { messagesRoute, messages, onUpdateMessages, onClose, className } =
		props;

	const labels = useChatbotConfig( 'labels' );
	const initialBotMessage = useChatbotConfig( 'initialBotMessage' );

	const [ loading, setLoading ] = useState( false );

	// Transform WordPress AI SDK messages to Agenttic format
	const agentticMessages = useMemo( (): AgentticMessage[] => {
		if ( ! messages ) {
			return [];
		}

		const transformedMessages = transformMessagesToAgenttic( messages );
		
		// Add initial bot message if it exists and no messages yet
		if ( initialBotMessage && transformedMessages.length === 0 ) {
			return [
				{
					id: 'initial-message',
					content: [{ type: 'text', text: initialBotMessage }],
					role: 'agent' as const,
					timestamp: Date.now(),
					archived: false,
					showIcon: true,
				},
			];
		}

		return transformedMessages;
	}, [ messages, initialBotMessage ] );

	const sendPrompt = async ( message: string ) => {
		const currentMessages = messages || [];

		// Create user message in WordPress AI SDK format
		const promptMessage = createUserMessage( message );

		// Update messages with user input immediately
		if ( onUpdateMessages ) {
			onUpdateMessages( [ ...currentMessages, promptMessage ] );
		}

		// Send to backend
		let newMessage: ChatbotMessageType | undefined;
		setLoading( true );
		try {
			newMessage = await apiFetch( {
				path: messagesRoute,
				method: 'POST',
				data: promptMessage,
			} );
		} catch ( error ) {
			logError( error );
		}
		setLoading( false );

		// Update messages with response
		if ( onUpdateMessages ) {
			if ( newMessage ) {
				onUpdateMessages( [
					...currentMessages,
					promptMessage,
					newMessage,
				] );
			} else {
				// If API call failed, revert to previous messages
				onUpdateMessages( [ ...currentMessages ] );
			}
		}
	};

	// Note: Reset functionality could be added back as a custom action if needed
	// const handleReset = async () => {
	// 	try {
	// 		await apiFetch( {
	// 			path: messagesRoute,
	// 			method: 'DELETE',
	// 		} );
	// 		if ( onUpdateMessages ) {
	// 			onUpdateMessages( [] );
	// 		}
	// 	} catch ( error ) {
	// 		logError( error );
	// 	}
	// };

	const handleClose = () => {
		onClose();
	};

	if ( ! messages || ! labels ) {
		return null;
	}

	return (
		<div
			className={ clsx( 'wp-ai-sdk-chatbot-demo__container', className ) }
		>
			<div className="agenttic">
				{/* Custom header with reset and close functionality */}
				<div className="wp-ai-sdk-chatbot-demo__header">
					<div className="wp-ai-sdk-chatbot-demo__header-title">
						{ labels?.title }
						{ labels?.subtitle && (
							<div className="wp-ai-sdk-chatbot-demo__header-subtitle">
								{ labels.subtitle }
							</div>
						) }
					</div>
					<div className="wp-ai-sdk-chatbot-demo__header-actions">
						<button
							className="wp-ai-sdk-chatbot-demo__header-reset-button"
							aria-label={ labels?.resetButton || 'Reset chat' }
							onClick={ async () => {
								try {
									await apiFetch( {
										path: messagesRoute,
										method: 'DELETE',
									} );
									if ( onUpdateMessages ) {
										onUpdateMessages( [] );
									}
								} catch ( error ) {
									logError( error );
								}
							} }
						>
							<span className="wp-ai-sdk-chatbot-demo__header-reset-icon" />
							<span className="screen-reader-text">
								{ labels?.resetButton || 'Reset chat' }
							</span>
						</button>
						<button
							className="wp-ai-sdk-chatbot-demo__header-close-button"
							aria-label={ labels?.closeButton || 'Close chatbot' }
							onClick={ handleClose }
						>
							<span className="wp-ai-sdk-chatbot-demo__header-close-icon" />
							<span className="screen-reader-text">
								{ labels?.closeButton || 'Close chatbot' }
							</span>
						</button>
					</div>
				</div>

				{/* AgentUI without header */}
				<div className="wp-ai-sdk-chatbot-demo__agenttic-content">
					<AgentUI
						messages={ agentticMessages }
						onSubmit={ sendPrompt }
						isProcessing={ loading }
						variant="embedded"
						placeholder={ labels.inputPlaceholder || 'Type your message...' }
					/>
				</div>
			</div>
		</div>
	);
}
