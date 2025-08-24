/**
 * External dependencies
 */
import clsx from 'clsx';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useChatbotConfig } from '../../config';
import ChatbotHeader from './chatbot-header';
import ChatbotMessage from './chatbot-message';
import SendIcon from './send-icon';
import './style.scss';
import {
	ChatbotMessage as ChatbotMessageType,
	MessageRole,
	MessagePartChannel,
	MessagePartType,
} from '../../types';
import logError from '../../utils/log-error';

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
 * Renders the chatbot.
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

	const messagesContainerRef = useRef< HTMLDivElement | null >( null );
	const inputRef = useRef< HTMLInputElement | null >( null );

	const scrollIntoView = () => {
		setTimeout( () => {
			if ( messagesContainerRef.current ) {
				messagesContainerRef.current.scrollTop =
					messagesContainerRef?.current?.scrollHeight;
			}
		}, 50 );
	};

	// Scroll to the latest message when the component mounts.
	useEffect( () => {
		scrollIntoView();
	} );

	// Focus on the input when the component mounts.
	useEffect( () => {
		if ( inputRef.current ) {
			inputRef.current.focus();
		}
	}, [ inputRef ] );

	const [ input, setInputValue ] = useState( '' );
	const [ loading, setLoading ] = useState( false );

	const sendPrompt = async ( message: string ) => {
		const currentMessages = messages || [];

		const promptMessage: ChatbotMessageType = {
			role: MessageRole.User,
			parts: [
				{
					channel: MessagePartChannel.Content,
					type: MessagePartType.Text,
					text: message,
				},
			],
			type: 'regular',
		};

		if ( onUpdateMessages ) {
			onUpdateMessages( [ ...currentMessages, promptMessage ] );
		}

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

		if ( newMessage ) {
			setInputValue( '' );
		}

		if ( onUpdateMessages ) {
			if ( newMessage ) {
				onUpdateMessages( [
					...currentMessages,
					promptMessage,
					newMessage,
				] );
			} else {
				onUpdateMessages( [ ...currentMessages ] );
			}
		}
	};

	const handleSubmit = ( event: React.FormEvent< HTMLFormElement > ) => {
		event.preventDefault();

		if ( ! input || loading ) {
			return;
		}

		sendPrompt( input );
		scrollIntoView();
	};

	if ( ! messages || ! labels ) {
		return null;
	}

	return (
		<div
			className={ clsx( 'wp-ai-sdk-chatbot-demo__container', className ) }
		>
			<div className="wp-ai-sdk-chatbot-demo__inner-container">
				<ChatbotHeader onClose={ onClose } />
				<div
					className="wp-ai-sdk-chatbot-demo__messages-container"
					ref={ messagesContainerRef }
				>
					{ !! initialBotMessage && (
						<ChatbotMessage
							content={ {
								role: MessageRole.Model,
								parts: [
									{
										channel: MessagePartChannel.Content,
										type: MessagePartType.Text,
										text: initialBotMessage,
									},
								],
								type: 'regular',
							} }
						/>
					) }
					{ messages.map( ( content, index: number ) => (
						<ChatbotMessage key={ index } content={ content } />
					) ) }
					{ loading && (
						<ChatbotMessage
							content={ {
								role: MessageRole.Model,
								parts: [
									{
										channel: MessagePartChannel.Content,
										type: MessagePartType.Text,
										text: '',
									},
								],
								type: 'regular',
							} }
							loading
						/>
					) }
				</div>
				<div className="wp-ai-sdk-chatbot-demo__input-container">
					<form
						className="wp-ai-sdk-chatbot-demo__input-form"
						onSubmit={ handleSubmit }
					>
						<label
							htmlFor="wp-ai-sdk-chatbot-demo-input"
							className="screen-reader-text"
						>
							{ labels.inputLabel }
						</label>
						<input
							id="wp-ai-sdk-chatbot-demo-input"
							className="wp-ai-sdk-chatbot-demo__input"
							placeholder={ labels.inputPlaceholder }
							value={ input }
							onChange={ ( event ) =>
								setInputValue( event.target.value )
							}
							ref={ inputRef }
						/>
						<button className="wp-ai-sdk-chatbot-demo__btn-send">
							<SendIcon className="wp-ai-sdk-chatbot-demo__btn-send-icon" />
							<span className="screen-reader-text">
								{ labels.sendButton }
							</span>
						</button>
					</form>
				</div>
			</div>
		</div>
	);
}
