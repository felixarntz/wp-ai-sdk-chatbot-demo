/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import {
	useState,
	useEffect,
	useCallback,
	useMemo,
	useRef,
} from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { ESCAPE } from '@wordpress/keycodes';

/**
 * Internal dependencies
 */
import Chatbot from '../Chatbot';
import { ChatbotConfigProvider } from '../../config';
import './style.scss';
import type {
	ChatbotConfig,
	ChatbotMessage,
	ServerChatbotConfig,
} from '../../types';

const retrieveVisibility = (): boolean => {
	const chatbotVisibility = window.sessionStorage.getItem(
		'wp-ai-sdk-chatbot-demo-visibility'
	);
	return chatbotVisibility === 'visible';
};

const storeVisibility = ( isVisible: boolean ): void => {
	if ( isVisible ) {
		window.sessionStorage.setItem(
			'wp-ai-sdk-chatbot-demo-visibility',
			'visible'
		);
	} else {
		window.sessionStorage.removeItem( 'wp-ai-sdk-chatbot-demo-visibility' );
	}
};

/**
 * Gets the chatbot configuration.
 *
 * @since 0.1.0
 *
 * @param providerName - The provider name.
 * @param modelName    - The model name.
 * @returns The chatbot configuration.
 */
const getChatbotConfig = (
	providerName?: string,
	modelName?: string
): ChatbotConfig => {
	return {
		labels: {
			title: __( 'WordPress Assistant', 'wp-ai-sdk-chatbot-demo' ),
			subtitle: providerName
				? sprintf(
						/* translators: %s: service name */
						__( 'Powered by %s', 'wp-ai-sdk-chatbot-demo' ),
						modelName
							? `${ providerName } (${ modelName })`
							: providerName
				  )
				: '',
			resetButton: __( 'Reset chat', 'wp-ai-sdk-chatbot-demo' ),
			closeButton: __( 'Close chatbot', 'wp-ai-sdk-chatbot-demo' ),
			sendButton: __( 'Send prompt', 'wp-ai-sdk-chatbot-demo' ),
			inputLabel: __( 'Chatbot input', 'wp-ai-sdk-chatbot-demo' ),
			inputPlaceholder: __(
				'Write your message here',
				'wp-ai-sdk-chatbot-demo'
			),
		},
		initialBotMessage: __(
			'How can I help you?',
			'wp-ai-sdk-chatbot-demo'
		),
	};
};

type ChatbotAppProps = {
	serverChatbotConfig: ServerChatbotConfig;
};

/**
 * Renders the chatbot.
 *
 * @since 0.1.0
 *
 * @param props - Component props.
 * @returns The component to be rendered.
 */
export default function ChatbotApp( props: ChatbotAppProps ) {
	const { serverChatbotConfig } = props;
	const messagesRoute = serverChatbotConfig.messagesRoute;
	const providerMetadata = serverChatbotConfig.currentProviderMetadata;
	const modelMetadata = serverChatbotConfig.currentModelMetadata;

	const chatbotRef = useRef< HTMLDivElement | null >( null );
	const toggleButtonRef = useRef< HTMLButtonElement | null >( null );

	const [ isVisible, setIsVisible ] = useState< boolean >( false );
	const [ messages, setMessages ] = useState< ChatbotMessage[] | undefined >(
		undefined
	);

	useEffect( () => {
		const initialVisibility = retrieveVisibility();
		if ( initialVisibility ) {
			setIsVisible( true );
		}
	}, [ setIsVisible ] );

	const toggleVisibility = useCallback( () => {
		setIsVisible( ! isVisible );
		storeVisibility( ! isVisible );

		// Focus on the toggle when the chatbot is closed.
		if ( isVisible && toggleButtonRef.current ) {
			toggleButtonRef.current.focus();
		}
	}, [ isVisible, toggleButtonRef ] );

	useEffect( () => {
		if ( ! messages && messagesRoute ) {
			async function fetchMessages() {
				const messagesHistory: ChatbotMessage[] = await apiFetch( {
					path: messagesRoute,
					method: 'GET',
				} );
				setMessages( messagesHistory );
			}
			fetchMessages();
		}
	}, [ messages, messagesRoute ] );

	useEffect( () => {
		const chatbotReference = chatbotRef.current;
		if ( ! chatbotReference ) {
			return;
		}

		// If focus is within the chatbot, close the chatbot when pressing ESC.
		const handleKeyDown = ( event: KeyboardEvent ) => {
			if ( event.keyCode === ESCAPE ) {
				toggleVisibility();
			}
		};

		chatbotReference.addEventListener( 'keydown', handleKeyDown );
		return () => {
			chatbotReference.removeEventListener( 'keydown', handleKeyDown );
		};
	}, [ chatbotRef, toggleVisibility ] );

	const config: ChatbotConfig = useMemo(
		() => getChatbotConfig( providerMetadata.name, modelMetadata.name ),
		[ providerMetadata.name, modelMetadata.name ]
	);

	return (
		<>
			<div
				id="wp-ai-sdk-chatbot-demo-container"
				className="wp-ai-sdk-chatbot-demo-container"
				hidden={ ! isVisible }
				ref={ chatbotRef }
			>
				{ isVisible && (
					<ChatbotConfigProvider config={ config }>
						<Chatbot
							messagesRoute={ messagesRoute }
							messages={ messages }
							onUpdateMessages={ setMessages }
							onClose={ toggleVisibility }
						/>
					</ChatbotConfigProvider>
				) }
			</div>
			<Button
				variant="primary"
				onClick={ toggleVisibility }
				className="wp-ai-sdk-chatbot-demo-button button button-primary" // Used so that we don't need to load the heavy 'wp-components' stylesheet everywhere.
				aria-controls="wp-ai-sdk-chatbot-demo-container"
				aria-expanded={ isVisible }
				ref={ toggleButtonRef }
			>
				{ __( 'Need help?', 'wp-ai-sdk-chatbot-demo' ) }
			</Button>
		</>
	);
}
